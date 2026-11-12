<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Smtp;

use Formwerk\Smtp\Log;
use Formwerk\Smtp\Mailer;
use Formwerk\Smtp\Settings;
use Formwerk\Tests\WpState;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase {

	private const SMTP = array(
		'host'       => 'smtp.example.com',
		'port'       => 465,
		'encryption' => 'ssl',
		'auth'       => true,
		'username'   => 'mailbox@example.com',
		'password'   => 'secret',
		'from_email' => 'info@example.com',
		'from_name'  => 'Example',
	);

	protected function setUp(): void {
		WpState::reset();
	}

	/**
	 * @param array<string, mixed> $values Settings to save.
	 */
	private function mailer( array $values ): Mailer {
		$settings = new Settings();
		$settings->save( $values );

		return new Mailer( $settings, new Log() );
	}

	/**
	 * Stand-in with the PHPMailer properties the plugin touches.
	 */
	private function phpmailer( string $from, string $sender = '' ): object {
		return new class( $from, $sender ) {
			public string $From;
			public string $Sender;
			public string $Mailer = 'mail';
			public $Host;
			public $Port;
			public $SMTPSecure;
			public $SMTPAutoTLS;
			public $SMTPAuth;
			public $Username;
			public $Password;
			public $Timeout;

			public function __construct( string $from, string $sender ) {
				$this->From   = $from;
				$this->Sender = $sender;
			}

			public function isSMTP(): void {
				$this->Mailer = 'smtp';
			}
		};
	}

	public function test_default_wordpress_sender_is_replaced(): void {
		$mailer = $this->mailer( self::SMTP );

		$this->assertSame( 'info@example.com', $mailer->filter_from_email( 'wordpress@example.org' ) );
		$this->assertSame( 'Example', $mailer->filter_from_name( 'WordPress' ) );
	}

	public function test_senders_set_by_other_plugins_stay_unless_forced(): void {
		$mailer = $this->mailer( self::SMTP );

		$this->assertSame( 'shop@other.example', $mailer->filter_from_email( 'shop@other.example' ) );
		$this->assertSame( 'Shop', $mailer->filter_from_name( 'Shop' ) );

		$forced = $this->mailer( array_merge( self::SMTP, array( 'force_from' => true ) ) );

		$this->assertSame( 'info@example.com', $forced->filter_from_email( 'shop@other.example' ) );
		$this->assertSame( 'Example', $forced->filter_from_name( 'Shop' ) );
	}

	public function test_without_a_sender_setting_nothing_is_replaced(): void {
		$mailer = $this->mailer( array() );

		$this->assertSame( 'wordpress@example.org', $mailer->filter_from_email( 'wordpress@example.org' ) );
		$this->assertSame( 'WordPress', $mailer->filter_from_name( 'WordPress' ) );
	}

	public function test_active_settings_switch_the_mailer_to_smtp(): void {
		$phpmailer = $this->phpmailer( 'info@example.com' );

		$this->mailer( self::SMTP )->configure( $phpmailer );

		$this->assertSame( 'smtp', $phpmailer->Mailer );
		$this->assertSame( 'smtp.example.com', $phpmailer->Host );
		$this->assertSame( 465, $phpmailer->Port );
		$this->assertSame( 'ssl', $phpmailer->SMTPSecure );
		$this->assertTrue( $phpmailer->SMTPAuth );
		$this->assertSame( 'secret', $phpmailer->Password );
		$this->assertSame( 'info@example.com', $phpmailer->Sender );
	}

	public function test_no_encryption_also_disables_opportunistic_tls(): void {
		$phpmailer = $this->phpmailer( 'info@example.com' );

		$this->mailer(
			array_merge(
				self::SMTP,
				array(
					'encryption' => 'none',
					'auth'       => false,
				)
			)
		)->configure( $phpmailer );

		$this->assertSame( '', $phpmailer->SMTPSecure );
		$this->assertFalse( $phpmailer->SMTPAutoTLS );
		$this->assertSame( '', $phpmailer->Password );
	}

	public function test_envelope_sender_does_not_leak_into_the_next_mail(): void {
		$mailer    = $this->mailer( self::SMTP );
		$phpmailer = $this->phpmailer( 'info@example.com' );
		$mailer->configure( $phpmailer );

		// WordPress reuses the instance: the next mail comes from another plugin's sender.
		$phpmailer->From = 'shop@other.example';
		$mailer->configure( $phpmailer );

		$this->assertSame( '', $phpmailer->Sender );
	}

	public function test_envelope_sender_of_other_plugins_is_left_alone(): void {
		$phpmailer = $this->phpmailer( 'shop@other.example', 'bounces@other.example' );

		$this->mailer( self::SMTP )->configure( $phpmailer );

		$this->assertSame( 'bounces@other.example', $phpmailer->Sender );
	}

	public function test_unusable_settings_fall_back_and_leave_one_log_entry(): void {
		$settings = new Settings();
		$settings->save( self::SMTP );
		WpState::$options[ '1:' . Settings::OPTION ]['password'] = 'fw1:broken';

		$log       = new Log();
		$mailer    = new Mailer( new Settings(), $log );
		$phpmailer = $this->phpmailer( 'info@example.com' );

		$mailer->configure( $phpmailer );
		$mailer->configure( $phpmailer );

		$this->assertSame( 'mail', $phpmailer->Mailer );
		$this->assertCount( 1, $log->all() );
		$this->assertStringContainsString( 'incomplete or invalid', $log->all()[0]['message'] );
	}

	public function test_switched_off_smtp_is_silent(): void {
		$log       = new Log();
		$phpmailer = $this->phpmailer( 'wordpress@example.org' );

		( new Mailer( new Settings(), $log ) )->configure( $phpmailer );

		$this->assertSame( 'mail', $phpmailer->Mailer );
		$this->assertSame( array(), $log->all() );
	}
}
