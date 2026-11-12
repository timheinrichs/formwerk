<?php
/**
 * Plugin Name:       Formwerk
 * Plugin URI:        https://github.com/timheinrichs/formwerk
 * Description:       Forms and SMTP for WordPress: a form builder that cannot lose submission data, plus SMTP delivery for all site mail.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Tim Heinrichs
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       formwerk
 * Domain Path:       /languages
 *
 * @package Formwerk
 */

// This file must stay parseable by old PHP versions so the requirement notice below can be shown.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FORMWERK_FILE', __FILE__ );
define( 'FORMWERK_MIN_PHP', '8.1' );

/**
 * Shows an admin notice when the plugin cannot start.
 *
 * The text is built when the notice is printed: translations must not be loaded this early.
 *
 * @param string $problem Either 'php' or 'vendor'.
 */
function formwerk_boot_notice( $problem ) {
	add_action(
		'admin_notices',
		static function () use ( $problem ) {
			if ( 'php' === $problem ) {
				$message = sprintf(
					/* translators: 1: required PHP version, 2: running PHP version. */
					__( 'PHP %1$s or newer is required. This site runs PHP %2$s, so the plugin is inactive.', 'formwerk' ),
					FORMWERK_MIN_PHP,
					PHP_VERSION
				);
			} else {
				$message = __( 'The vendor folder is missing, so the plugin is inactive. Install the release ZIP from GitHub, or run "composer install" in the plugin folder.', 'formwerk' );
			}
			printf( '<div class="notice notice-error"><p><strong>Formwerk:</strong> %s</p></div>', esc_html( $message ) );
		}
	);
}

if ( version_compare( PHP_VERSION, FORMWERK_MIN_PHP, '<' ) ) {
	formwerk_boot_notice( 'php' );
	return;
}

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	formwerk_boot_notice( 'vendor' );
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

Formwerk\Plugin::instance()->boot();
