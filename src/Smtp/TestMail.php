<?php
/**
 * Sends a test mail and records the SMTP conversation.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Smtp;

use PHPMailer\PHPMailer\PHPMailer;
use WP_Error;

/**
 * Lets people check the mail setup from the backend.
 */
final class TestMail {

	/**
	 * Settings source.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Result log.
	 *
	 * @var Log
	 */
	private Log $log;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings source.
	 * @param Log      $log      Result log.
	 */
	public function __construct( Settings $settings, Log $log ) {
		$this->settings = $settings;
		$this->log      = $log;
	}

	/**
	 * Sends the test mail with the saved settings.
	 *
	 * @param string $to Recipient address.
	 *
	 * @return array{sent:bool, via_smtp:bool, reason:string, error:string, debug:string}
	 */
	public function send( string $to ): array {
		$transcript = new DebugTranscript();
		$error      = '';
		$mailer     = null;
		$previous   = array();

		$enable_debug  = static function ( $phpmailer ) use ( $transcript, &$mailer, &$previous ): void {
			if ( ! $phpmailer instanceof PHPMailer ) {
				return;
			}
			$mailer   = $phpmailer;
			$previous = array( $phpmailer->SMTPDebug, $phpmailer->Debugoutput );

			$phpmailer->SMTPDebug   = 2;
			$phpmailer->Debugoutput = static function ( $output ) use ( $transcript ): void {
				$transcript->add( (string) $output );
			};
		};
		$capture_error = static function ( $wp_error ) use ( &$error ): void {
			if ( $wp_error instanceof WP_Error ) {
				$error = $wp_error->get_error_message();
			}
		};

		$subject = sprintf(
			/* translators: %s: site name. */
			__( 'Formwerk test email from %s', 'formwerk' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = __( 'This is a test email from Formwerk.', 'formwerk' ) . "\n\n"
			. __( 'If you can read this, your website can send email with the current settings.', 'formwerk' ) . "\n\n"
			. home_url( '/' ) . "\n";

		add_action( 'phpmailer_init', $enable_debug, 2000 );
		add_action( 'wp_mail_failed', $capture_error );
		try {
			$sent = (bool) wp_mail( $to, $subject, $body );
		} finally {
			remove_action( 'phpmailer_init', $enable_debug, 2000 );
			remove_action( 'wp_mail_failed', $capture_error );
			// WordPress reuses the mailer: later mails of this request must not be recorded.
			if ( null !== $mailer ) {
				list( $mailer->SMTPDebug, $mailer->Debugoutput ) = $previous;
			}
		}

		$via_smtp = $this->settings->config()->is_active();
		if ( $sent ) {
			$this->log->add(
				Log::STATUS_SENT,
				array( $to ),
				$subject,
				$via_smtp
					? __( 'Test email accepted by the mail server.', 'formwerk' )
					: __( 'Test email handed to the web server (no mail server in use).', 'formwerk' )
			);
		}

		return array(
			'sent'     => $sent,
			'via_smtp' => $via_smtp,
			'reason'   => $sent ? '' : FailureReason::classify( $error, $transcript->text() ),
			'error'    => $error,
			'debug'    => $transcript->text(),
		);
	}
}
