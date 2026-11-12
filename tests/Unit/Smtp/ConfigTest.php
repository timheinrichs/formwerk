<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Smtp;

use Formwerk\Smtp\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase {

	private const VALID = array(
		'host'       => 'smtp.example.com',
		'port'       => 587,
		'encryption' => 'tls',
		'auth'       => true,
		'username'   => 'mailbox@example.com',
		'password'   => 'secret',
		'from_email' => 'info@example.com',
		'from_name'  => 'Example',
		'force_from' => false,
	);

	public function test_defaults_mean_smtp_is_off_and_valid(): void {
		$config = new Config();

		$this->assertSame( array(), $config->errors() );
		$this->assertFalse( $config->is_active() );
		$this->assertSame( 587, $config->get( 'port' ) );
		$this->assertSame( 'tls', $config->get( 'encryption' ) );
	}

	public function test_complete_settings_are_active(): void {
		$config = new Config( self::VALID );

		$this->assertSame( array(), $config->errors() );
		$this->assertTrue( $config->is_active() );
	}

	public function test_values_from_constants_and_forms_are_normalised(): void {
		$config = new Config(
			array(
				'host'       => '  smtp.example.com ',
				'port'       => '465',
				'encryption' => 'STARTTLS',
				'auth'       => 'false',
				'force_from' => 'yes',
				'from_name'  => "Example\r\nBcc: someone@example.com",
				'unknown'    => 'dropped',
			)
		);

		$this->assertSame( 'smtp.example.com', $config->get( 'host' ) );
		$this->assertSame( 465, $config->get( 'port' ) );
		$this->assertSame( 'tls', $config->get( 'encryption' ) );
		$this->assertFalse( $config->get( 'auth' ) );
		$this->assertTrue( $config->get( 'force_from' ) );
		$this->assertSame( 'Example Bcc: someone@example.com', $config->get( 'from_name' ) );
		$this->assertArrayNotHasKey( 'unknown', $config->all() );
	}

	public function test_password_keeps_surrounding_spaces(): void {
		$this->assertSame( ' pass ', ( new Config( array( 'password' => ' pass ' ) ) )->get( 'password' ) );
	}

	/**
	 * @param array<string, mixed>  $change   Values that differ from the valid set.
	 * @param array<string, string> $expected Expected errors.
	 */
	#[DataProvider( 'invalid_settings' )]
	public function test_invalid_settings_are_reported_per_field( array $change, array $expected ): void {
		$config = new Config( array_merge( self::VALID, $change ) );

		$this->assertSame( $expected, $config->errors() );
		$this->assertFalse( $config->is_active() );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>, 1: array<string, string>}>
	 */
	public static function invalid_settings(): array {
		return array(
			'host with scheme'      => array( array( 'host' => 'https://smtp.example.com' ), array( 'host' => 'host_invalid' ) ),
			'host with space'       => array( array( 'host' => 'smtp example.com' ), array( 'host' => 'host_invalid' ) ),
			'host with port'        => array( array( 'host' => 'smtp.example.com:587' ), array( 'host' => 'host_invalid' ) ),
			'port zero'             => array( array( 'port' => 0 ), array( 'port' => 'port_invalid' ) ),
			'port text'             => array( array( 'port' => 'abc' ), array( 'port' => 'port_invalid' ) ),
			'port too high'         => array( array( 'port' => 70000 ), array( 'port' => 'port_invalid' ) ),
			'unknown encryption'    => array( array( 'encryption' => 'rot13' ), array( 'encryption' => 'encryption_invalid' ) ),
			'login without user'    => array( array( 'username' => '' ), array( 'username' => 'username_required' ) ),
			'login without pass'    => array( array( 'password' => '' ), array( 'password' => 'password_required' ) ),
			'broken sender address' => array( array( 'from_email' => 'info@' ), array( 'from_email' => 'from_email_invalid' ) ),
		);
	}

	public function test_ip_addresses_are_valid_hosts(): void {
		$this->assertSame( array(), ( new Config( array_merge( self::VALID, array( 'host' => '192.0.2.10' ) ) ) )->errors() );
		$this->assertSame( array(), ( new Config( array_merge( self::VALID, array( 'host' => '[2001:db8::1]' ) ) ) )->errors() );
	}

	public function test_bare_ipv6_address_gets_the_brackets_the_mailer_needs(): void {
		$config = new Config( array_merge( self::VALID, array( 'host' => '2001:db8::1' ) ) );

		$this->assertSame( '[2001:db8::1]', $config->get( 'host' ) );
		$this->assertSame( array(), $config->errors() );
	}

	#[DataProvider( 'hosts_the_mailer_rejects' )]
	public function test_hosts_the_mailer_rejects_are_invalid( string $host ): void {
		$config = new Config( array_merge( self::VALID, array( 'host' => $host ) ) );

		$this->assertSame( array( 'host' => 'host_invalid' ), $config->errors() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function hosts_the_mailer_rejects(): array {
		return array(
			'bracketed ipv4'     => array( '[192.0.2.10]' ),
			'opening bracket'    => array( '[192.0.2.10' ),
			'closing bracket'    => array( '192.0.2.10]' ),
			'numeric, not ipv4'  => array( '999.0.0.1' ),
			'digits only'        => array( '12345' ),
			'bracketed hostname' => array( '[smtp.example.com]' ),
			'leading hyphen'     => array( '-smtp.example.com' ),
			'underscore'         => array( 'smtp_server.example.com' ),
		);
	}

	public function test_status_separates_off_active_and_broken(): void {
		$this->assertSame( Config::STATUS_OFF, ( new Config() )->status() );
		$this->assertSame( Config::STATUS_ACTIVE, ( new Config( self::VALID ) )->status() );
		$this->assertSame( Config::STATUS_BROKEN, ( new Config( array_merge( self::VALID, array( 'password' => '' ) ) ) )->status() );
	}

	public function test_login_fields_are_optional_when_login_is_off(): void {
		$config = new Config(
			array_merge(
				self::VALID,
				array(
					'auth'     => false,
					'username' => '',
					'password' => '',
				)
			)
		);

		$this->assertSame( array(), $config->errors() );
		$this->assertTrue( $config->is_active() );
	}

	public function test_sender_address_is_checked_even_without_a_host(): void {
		$config = new Config(
			array(
				'host'       => '',
				'from_email' => 'not-an-address',
			)
		);

		$this->assertSame( array( 'from_email' => 'from_email_invalid' ), $config->errors() );
	}
}
