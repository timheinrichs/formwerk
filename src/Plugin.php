<?php
/**
 * Plugin bootstrap.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk;

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
