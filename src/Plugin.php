<?php
/**
 * Plugin bootstrap.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk;

use Formwerk\Admin\EmailPage;
use Formwerk\Rest\SmtpController;
use Formwerk\Smtp\Log;
use Formwerk\Smtp\Mailer;
use Formwerk\Smtp\Settings;
use Formwerk\Smtp\TestMail;

/**
 * Wires the plugin's modules into WordPress.
 */
final class Plugin {

	public const VERSION = '0.1.0-dev';

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether hooks are already registered.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Returns the shared instance.
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Registers all hooks. Safe to call more than once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'register_modules' ) );
	}

	/**
	 * Builds the modules and registers their hooks.
	 */
	public function register_modules(): void {
		$settings = new Settings();
		$log      = new Log();

		$log->register();
		( new Mailer( $settings, $log ) )->register();

		$controller = new SmtpController( $settings, $log, new TestMail( $settings, $log ) );
		add_action( 'rest_api_init', array( $controller, 'register_routes' ) );

		if ( is_admin() ) {
			( new EmailPage( $settings ) )->register();
		}
	}

	/**
	 * Capability needed to manage the plugin.
	 *
	 * Mail settings control where password reset mails go. In a network, a site administrator
	 * could use them to take over other accounts, so only network administrators may change them.
	 */
	public static function capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Loads translations shipped in /languages.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'formwerk', false, dirname( plugin_basename( FORMWERK_FILE ) ) . '/languages' );
	}

	/**
	 * Absolute path inside the plugin folder.
	 *
	 * @param string $relative Path relative to the plugin root.
	 */
	public static function path( string $relative = '' ): string {
		return dirname( __DIR__ ) . ( '' === $relative ? '' : '/' . ltrim( $relative, '/' ) );
	}
}
