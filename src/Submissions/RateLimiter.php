<?php
/**
 * Counts events per time window.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Submissions;

/**
 * Fixed-window counters that stay correct when many requests arrive at once.
 *
 * Counts live as transients, so WordPress removes them when they expire. The increment itself
 * is one database statement (or one cache operation), so parallel requests cannot both read
 * "4" and both write "5".
 */
final class RateLimiter {

	private const GROUP = 'formwerk';

	/**
	 * Adds one to a counter and returns the new count.
	 *
	 * @param string $bucket What is counted, e.g. a hashed address or a form.
	 * @param int    $window Length of the window in seconds. A new window starts a new count.
	 */
	public function hit( string $bucket, int $window ): int {
		$name    = $this->name( $bucket, $window );
		$expires = ( intdiv( time(), $window ) + 1 ) * $window;

		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $name, 0, self::GROUP, max( 1, $expires - time() ) );
			$count = wp_cache_incr( $name, 1, self::GROUP );

			return false === $count ? 1 : (int) $count;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic increment; the transient API cannot do this.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_value = option_value",
				'_transient_timeout_' . $name,
				(string) $expires
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'off') ON DUPLICATE KEY UPDATE option_value = option_value + 1",
				'_transient_' . $name
			)
		);
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_transient_' . $name ) );
		// phpcs:enable

		return max( 1, (int) $count );
	}

	/**
	 * Takes back one count, for an attempt that did not go through after all.
	 *
	 * @param string $bucket What is counted.
	 * @param int    $window Length of the window in seconds.
	 */
	public function release( string $bucket, int $window ): void {
		$name = $this->name( $bucket, $window );

		if ( wp_using_ext_object_cache() ) {
			wp_cache_decr( $name, 1, self::GROUP );
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counterpart of the atomic increment.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = GREATEST(CAST(option_value AS SIGNED) - 1, 0) WHERE option_name = %s", '_transient_' . $name ) );
	}

	/**
	 * Transient name of a counter in the current window.
	 *
	 * @param string $bucket What is counted.
	 * @param int    $window Length of the window in seconds.
	 */
	private function name( string $bucket, int $window ): string {
		return 'formwerk_rl_' . substr( md5( $bucket ), 0, 20 ) . '_' . intdiv( time(), $window );
	}
}
