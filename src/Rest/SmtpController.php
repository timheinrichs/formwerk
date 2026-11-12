<?php
/**
 * REST endpoints for the email settings screen.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Rest;

use Formwerk\Plugin;
use Formwerk\Smtp\FailureReason;
use Formwerk\Smtp\Log;
use Formwerk\Smtp\Settings;
use Formwerk\Smtp\TestMail;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Settings, test mail and log. Only for people who may manage the site's mail.
 */
final class SmtpController {

	public const NAMESPACE = 'formwerk/v1';

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Mail log.
	 *
	 * @var Log
	 */
	private Log $log;

	/**
	 * Test mail sender.
	 *
	 * @var TestMail
	 */
	private TestMail $test_mail;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings  Settings store.
	 * @param Log      $log       Mail log.
	 * @param TestMail $test_mail Test mail sender.
	 */
	public function __construct( Settings $settings, Log $log, TestMail $test_mail ) {
		$this->settings  = $settings;
		$this->log       = $log;
		$this->test_mail = $test_mail;
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		$permission = array( $this, 'can_manage' );

		register_rest_route(
			self::NAMESPACE,
			'/smtp',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => $permission,
					'args'                => array(
						'host'       => array( 'type' => 'string' ),
						'port'       => array( 'type' => 'integer' ),
						'encryption' => array( 'type' => 'string' ),
						'auth'       => array( 'type' => 'boolean' ),
						'username'   => array( 'type' => 'string' ),
						'password'   => array( 'type' => 'string' ),
						'from_email' => array( 'type' => 'string' ),
						'from_name'  => array( 'type' => 'string' ),
						'force_from' => array( 'type' => 'boolean' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/smtp/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_test' ),
				'permission_callback' => $permission,
				'args'                => array(
					'to' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/smtp/log',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_log' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_log' ),
					'permission_callback' => $permission,
				),
			)
		);
	}

	/**
	 * Mail settings decide where password reset mails go, so they need the highest role.
	 */
	public function can_manage(): bool {
		return current_user_can( Plugin::capability() );
	}

	/**
	 * Returns the settings without the password.
	 */
	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response( $this->display() );
	}

	/**
	 * Saves the settings, or reports which fields block saving.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error(
				'formwerk_invalid_request',
				__( 'The settings were not saved, because the request contained no data.', 'formwerk' ),
				array( 'status' => 400 )
			);
		}
		$errors = $this->settings->save( $params );

		if ( isset( $errors['general'] ) ) {
			return new WP_Error(
				'formwerk_not_saved',
				__( 'The settings were not saved, because the database did not accept them. Please try again.', 'formwerk' ),
				array( 'status' => 500 )
			);
		}
		if ( array() !== $errors ) {
			return new WP_Error(
				'formwerk_invalid_settings',
				__( 'The settings were not saved. Please correct the marked fields.', 'formwerk' ),
				array(
					'status' => 400,
					'fields' => array_map( array( $this, 'error_message' ), $errors ),
				)
			);
		}

		return new WP_REST_Response( $this->display() );
	}

	/**
	 * Sends a test mail with the saved settings.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_test( WP_REST_Request $request ) {
		$to = sanitize_email( (string) $request->get_param( 'to' ) );
		if ( ! is_email( $to ) ) {
			return new WP_Error(
				'formwerk_invalid_recipient',
				__( 'Please enter a valid email address for the test.', 'formwerk' ),
				array( 'status' => 400 )
			);
		}
		$result = $this->test_mail->send( $to );

		return new WP_REST_Response(
			array(
				'sent'    => $result['sent'],
				'message' => $result['sent']
					? $this->success_message( $to, $result['via_smtp'] )
					: $this->failure_message( $result['reason'] ),
				'details' => $result['error'],
				'debug'   => $result['debug'],
			)
		);
	}

	/**
	 * Returns the log entries.
	 */
	public function get_log(): WP_REST_Response {
		return new WP_REST_Response( array( 'entries' => $this->log->all() ) );
	}

	/**
	 * Empties the log.
	 */
	public function clear_log(): WP_REST_Response {
		$this->log->clear();

		return new WP_REST_Response( array( 'entries' => array() ) );
	}

	/**
	 * Settings with readable error messages.
	 *
	 * @return array<string, mixed>
	 */
	private function display(): array {
		$display           = $this->settings->for_display();
		$display['errors'] = array_map( array( $this, 'error_message' ), $display['errors'] );

		return $display;
	}

	/**
	 * Says what a successful test does and does not prove.
	 *
	 * @param string $to       Recipient.
	 * @param bool   $via_smtp Whether the mail server was used.
	 */
	private function success_message( string $to, bool $via_smtp ): string {
		if ( $via_smtp ) {
			return sprintf(
				/* translators: %s: email address. */
				__( 'The mail server accepted the test email for %s. Check that inbox, including the spam folder.', 'formwerk' ),
				$to
			);
		}

		return sprintf(
			/* translators: %s: email address. */
			__( 'The web server took the test email for %s. No mail server is in use, so it may arrive late or land in the spam folder. Check that inbox.', 'formwerk' ),
			$to
		);
	}

	/**
	 * Turns a failure reason into a sentence that says what to do next.
	 *
	 * @param string $reason One of the FailureReason constants.
	 */
	private function failure_message( string $reason ): string {
		switch ( $reason ) {
			case FailureReason::CONNECT:
				return __( 'The website could not reach the mail server. Check the server name and the port. If both are correct, ask your web host whether outgoing connections on this port are allowed.', 'formwerk' );
			case FailureReason::LOGIN:
				return __( 'The mail server did not accept the username or password. Enter both again. Some providers require a separate app password.', 'formwerk' );
			case FailureReason::SECURE:
				return __( 'The secure connection failed. Try the other encryption type: STARTTLS normally uses port 587, SSL/TLS port 465.', 'formwerk' );
			case FailureReason::SENDER:
				return __( 'The mail server did not accept the sender address. Use the address of the mailbox you log in with.', 'formwerk' );
			default:
				return __( 'The test email was not sent.', 'formwerk' );
		}
	}

	/**
	 * Turns a validation code into a sentence that says what to do.
	 *
	 * @param string $code Error code from Settings::errors().
	 */
	private function error_message( string $code ): string {
		switch ( $code ) {
			case 'host_invalid':
				return __( 'Enter the server name without "https://", port or spaces, for example smtp.example.com.', 'formwerk' );
			case 'port_invalid':
				return __( 'Enter a port between 1 and 65535. Common ports are 587 and 465.', 'formwerk' );
			case 'encryption_invalid':
				return __( 'Choose one of the listed encryption types.', 'formwerk' );
			case 'username_required':
				return __( 'Enter the username of the mailbox, or switch off "Server requires login".', 'formwerk' );
			case 'password_required':
				return __( 'Enter the password of the mailbox, or switch off "Server requires login".', 'formwerk' );
			case 'from_email_invalid':
				return __( 'Enter a complete email address, for example info@example.com.', 'formwerk' );
			case Settings::ERROR_CONSTANT:
				return __( 'The value fixed in wp-config.php is not valid. Ask the person who maintains this website to correct it there.', 'formwerk' );
			default:
				return __( 'This value is not valid.', 'formwerk' );
		}
	}
}
