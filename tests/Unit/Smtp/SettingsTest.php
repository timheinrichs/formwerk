<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Smtp;

use Formwerk\Smtp\Config;
use Formwerk\Smtp\Settings;
use Formwerk\Support\Secret;
use Formwerk\Tests\WpState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	private const INPUT = array(
		'host'       => 'smtp.example.com',
		'port'       => 587,
		'encryption' => 'tls',
		'auth'       => true,
		'username'   => 'mailbox@example.com',
		'password'   => 'secret',
		'from_email' => 'info@example.com',
	);

	protected function setUp(): void {
		WpState::reset();
	}

	private function stored(): array {
		return WpState::$options[ '1:' . Settings::OPTION ];
	}

	public function test_saved_password_is_encrypted_and_never_displayed(): void {
		$settings = new Settings();

		$this->assertSame( array(), $settings->save( self::INPUT ) );

		$this->assertStringStartsWith( 'fw1:', $this->stored()['password'] );
		$this->assertSame( 'secret', $settings->config()->get( 'password' ) );

		$display = $settings->for_display();
		$this->assertStringNotContainsString( 'secret', (string) json_encode( $display ) );
		$this->assertTrue( $display['settings']['has_password'] );
		$this->assertArrayNotHasKey( 'password', $display['settings'] );
		$this->assertSame( Config::STATUS_ACTIVE, $display['status'] );
	}

	public function test_empty_or_missing_password_keeps_the_stored_one(): void {
		$settings = new Settings();
		$settings->save( self::INPUT );

		$this->assertSame(
			array(),
			$settings->save(
				array(
					'username' => 'other@example.com',
					'password' => '',
				)
			)
		);
		$this->assertSame( array(), $settings->save( array( 'from_name' => 'Example' ) ) );

		$this->assertSame( 'secret', $settings->config()->get( 'password' ) );
		$this->assertSame( 'other@example.com', $settings->config()->get( 'username' ) );
		$this->assertSame( 'Example', $settings->config()->get( 'from_name' ) );
	}

	public function test_invalid_input_is_reported_and_nothing_is_written(): void {
		$settings = new Settings();
		$settings->save( self::INPUT );
		$before = $this->stored();

		$errors = $settings->save(
			array(
				'host' => 'https://smtp.example.com',
				'port' => 0,
			)
		);

		$this->assertSame(
			array(
				'host' => 'host_invalid',
				'port' => 'port_invalid',
			),
			$errors
		);
		$this->assertSame( $before, $this->stored() );
		$this->assertSame( 'smtp.example.com', $settings->config()->get( 'host' ) );
	}

	public function test_unreadable_password_turns_the_config_broken_and_is_flagged(): void {
		( new Settings( new Secret( 'old-salt' ) ) )->save( self::INPUT );

		$settings = new Settings( new Secret( 'new-salt' ) );
		$display  = $settings->for_display();

		$this->assertSame( Config::STATUS_BROKEN, $display['status'] );
		$this->assertTrue( $display['password_unreadable'] );
		$this->assertSame( array( 'password' => 'password_required' ), $display['errors'] );
		$this->assertFalse( $display['settings']['has_password'] );
	}

	public function test_unreadable_password_is_not_flagged_when_login_is_off(): void {
		( new Settings( new Secret( 'old-salt' ) ) )->save( self::INPUT );
		WpState::$options[ '1:' . Settings::OPTION ]['auth'] = false;

		$display = ( new Settings( new Secret( 'new-salt' ) ) )->for_display();

		$this->assertFalse( $display['password_unreadable'] );
		$this->assertSame( Config::STATUS_ACTIVE, $display['status'] );
	}

	public function test_database_failure_is_reported(): void {
		$settings              = new Settings();
		WpState::$update_fails = true;

		$this->assertSame( array( 'general' => Settings::ERROR_NOT_SAVED ), $settings->save( self::INPUT ) );
	}

	public function test_saving_unchanged_values_is_not_a_failure(): void {
		$settings = new Settings();
		$settings->save( array( 'from_name' => 'Example' ) );

		$this->assertSame( array(), $settings->save( array( 'from_name' => 'Example' ) ) );
	}

	public function test_each_site_of_a_network_gets_its_own_config(): void {
		$settings = new Settings();
		$settings->save( self::INPUT );
		$this->assertSame( 'smtp.example.com', $settings->config()->get( 'host' ) );

		WpState::$blog_id = 2;
		$this->assertSame( '', $settings->config()->get( 'host' ) );

		WpState::$blog_id = 1;
		$this->assertSame( 'smtp.example.com', $settings->config()->get( 'host' ) );
	}

	#[RunInSeparateProcess]
	public function test_constants_win_lock_their_fields_and_are_not_stored(): void {
		define( 'FORMWERK_SMTP_HOST', 'relay.example.net' );
		define( 'FORMWERK_SMTP_PASSWORD', 'from-config' );

		$settings = new Settings();
		$errors   = $settings->save(
			array_merge(
				self::INPUT,
				array(
					'host'     => 'ignored.example.com',
					'password' => 'ignored',
				)
			)
		);

		$this->assertSame( array(), $errors );
		$this->assertSame( 'relay.example.net', $settings->config()->get( 'host' ) );
		$this->assertSame( 'from-config', $settings->config()->get( 'password' ) );
		$this->assertSame( '', $this->stored()['host'] );
		$this->assertSame( '', $this->stored()['password'] );

		$display = $settings->for_display();
		$this->assertSame( array( 'host', 'password' ), $display['locked'] );
		$this->assertStringNotContainsString( 'from-config', (string) json_encode( $display ) );
	}

	#[RunInSeparateProcess]
	public function test_invalid_constant_is_marked_but_does_not_block_other_fields(): void {
		define( 'FORMWERK_SMTP_ENCRYPTION', 'TLSv1.2' );

		$settings = new Settings();

		$this->assertSame( array(), $settings->save( array_merge( self::INPUT, array( 'from_name' => 'Example' ) ) ) );
		$this->assertSame( 'Example', $settings->config()->get( 'from_name' ) );
		$this->assertSame( array( 'encryption' => Settings::ERROR_CONSTANT ), $settings->errors() );
		$this->assertSame( Config::STATUS_BROKEN, $settings->for_display()['status'] );
	}
}
