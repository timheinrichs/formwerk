<?php
/**
 * Minimal stand-ins for the WordPress functions used by the classes under test.
 *
 * Options and transients live in memory; Formwerk\Tests\WpState resets them.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests {

	final class WpState {

		/** @var array<string, mixed> */
		public static array $options = array();

		/** @var array<string, mixed> */
		public static array $transients = array();

		public static bool $multisite = false;

		public static int $blog_id = 1;

		public static bool $update_fails = false;

		public static function reset(): void {
			self::$options      = array();
			self::$transients   = array();
			self::$multisite    = false;
			self::$blog_id      = 1;
			self::$update_fails = false;
		}
	}
}

namespace {

	use Formwerk\Tests\WpState;

	const DAY_IN_SECONDS = 86400;

	function get_option( string $name, $fallback = false ) {
		return WpState::$options[ WpState::$blog_id . ':' . $name ] ?? $fallback;
	}

	function update_option( string $name, $value, $autoload = null ): bool {
		$key = WpState::$blog_id . ':' . $name;
		if ( WpState::$update_fails || ( array_key_exists( $key, WpState::$options ) && WpState::$options[ $key ] === $value ) ) {
			return false;
		}
		WpState::$options[ $key ] = $value;

		return true;
	}

	function delete_option( string $name ): bool {
		unset( WpState::$options[ WpState::$blog_id . ':' . $name ] );

		return true;
	}

	function get_transient( string $name ) {
		return WpState::$transients[ $name ] ?? false;
	}

	function set_transient( string $name, $value, int $expiration = 0 ): bool {
		WpState::$transients[ $name ] = $value;

		return true;
	}

	function get_current_blog_id(): int {
		return WpState::$blog_id;
	}

	function is_multisite(): bool {
		return WpState::$multisite;
	}

	function wp_salt( string $scheme = 'auth' ): string {
		return 'unit-test-salt-' . $scheme;
	}

	function network_home_url(): string {
		return 'https://www.example.org/';
	}

	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}

	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}

	function add_action( ...$args ): bool {
		return true;
	}

	function add_filter( ...$args ): bool {
		return true;
	}
}
