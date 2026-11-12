<?php
/**
 * Routes WordPress mail through SMTP.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Smtp;

use WP_Error;

/**
 * Applies the SMTP settings to every mail sent with wp_mail(), including mail from other plugins.
 */
final class Mailer {

	private const TIMEOUT          = 15;
	private const BROKEN_TRANSIENT = 'formwerk_smtp_broken_logged';

	/**
	 * Settings source.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Failure log.
	 *
	 * @var Log
	 */
	private Log $log;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings source.
	 * @param Log      $log      Failure log.
	 */
	public function __construct( Settings $settings, Log $log ) {
		$this->settings = $settings;
		$this->log      = $log;
	}

	/**
	 * Registers the hooks. Late priorities so these settings win over other plugins.
	 */
	public function register(): void {
		add_action( 'phpmailer_init', array( $this, 'configure' ), 1000 );
		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ), 1000 );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ), 1000 );
		add_action( 'wp_mail_failed', array( $this, 'record_failure' ) );
	}

	/**
	 * Switches PHPMailer to SMTP when a server is configured.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer instance; WordPress reuses it for every mail of a request.
	 */
	public function configure( $phpmailer ): void {
		$config = $this->settings->config();
		$from   = $config->get( 'from_email' );

		// Envelope sender = visible sender, so SPF and DMARC line up. The instance is shared,
		// so a value from an earlier mail must not leak into a mail with a different sender.
		if ( '' !== $from && $phpmailer->From === $from ) {
			$phpmailer->Sender = $from;
		} elseif ( $phpmailer->Sender === $from ) {
			$phpmailer->Sender = '';
		}

		if ( Config::STATUS_BROKEN === $config->status() ) {
			$this->record_broken_config();
		}
		if ( ! $config->is_active() ) {
			return;
		}
		$encryption = $config->get( 'encryption' );

		$phpmailer->isSMTP();
		$phpmailer->Host        = $config->get( 'host' );
		$phpmailer->Port        = $config->get( 'port' );
		$phpmailer->SMTPSecure  = 'none' === $encryption ? '' : $encryption;
		$phpmailer->SMTPAutoTLS = 'none' !== $encryption;
		$phpmailer->SMTPAuth    = $config->get( 'auth' );
		$phpmailer->Username    = $config->get( 'auth' ) ? $config->get( 'username' ) : '';
		$phpmailer->Password    = $config->get( 'auth' ) ? $config->get( 'password' ) : '';
		$phpmailer->Timeout     = self::TIMEOUT;
	}

	/**
	 * Replaces the sender address.
	 *
	 * Without "force", only the WordPress default (wordpress@domain) is replaced, so senders
	 * that other plugins set on purpose stay untouched.
	 *
	 * @param mixed $email Current sender address.
	 *
	 * @return mixed
	 */
	public function filter_from_email( $email ) {
		$config = $this->settings->config();
		$from   = $config->get( 'from_email' );

		if ( '' === $from || array_key_exists( 'from_email', $config->errors() ) ) {
			return $email;
		}

		return $config->get( 'force_from' ) || $this->default_from_email() === $email ? $from : $email;
	}

	/**
	 * Replaces the sender name, following the same rule as the address.
	 *
	 * @param mixed $name Current sender name.
	 *
	 * @return mixed
	 */
	public function filter_from_name( $name ) {
		$config = $this->settings->config();
		$from   = $config->get( 'from_name' );

		if ( '' === $from ) {
			return $name;
		}

		return $config->get( 'force_from' ) || 'WordPress' === $name ? $from : $name;
	}

	/**
	 * Writes a failed mail to the log.
	 *
	 * @param WP_Error $error Error raised by wp_mail().
	 */
	public function record_failure( $error ): void {
		if ( ! $error instanceof WP_Error ) {
			return;
		}
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : array();
		$to   = $data['to'] ?? array();

		$this->log->add(
			Log::STATUS_FAILED,
			is_array( $to ) ? $to : array( $to ),
			isset( $data['subject'] ) && is_string( $data['subject'] ) ? $data['subject'] : '',
			$error->get_error_message()
		);
	}

	/**
	 * Leaves a trace when a mail server is saved but cannot be used, so the fallback
	 * to the web server's mail function does not go unnoticed. At most once per day.
	 */
	private function record_broken_config(): void {
		if ( false !== get_transient( self::BROKEN_TRANSIENT ) ) {
			return;
		}
		set_transient( self::BROKEN_TRANSIENT, 1, DAY_IN_SECONDS );

		$this->log->add(
			Log::STATUS_FAILED,
			array(),
			'',
			__( 'The saved mail server settings are incomplete or invalid. Emails are sent by the web server instead until this is corrected.', 'formwerk' )
		);
	}

	/**
	 * The sender address WordPress uses when nothing else is set (same logic as wp_mail()).
	 */
	private function default_from_email(): string {
		$host = (string) wp_parse_url( network_home_url(), PHP_URL_HOST );
		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return 'wordpress@' . $host;
	}
}
