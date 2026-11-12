<?php
/**
 * Removes the plugin's data when it is deleted in the backend.
 *
 * @package Formwerk
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes the data of the current site.
 */
function formwerk_uninstall_site() {
	delete_option( 'formwerk_smtp' );
	delete_option( 'formwerk_mail_log' );
	delete_transient( 'formwerk_smtp_broken_logged' );
}

if ( is_multisite() ) {
	// Every site of the network has its own settings (with the mail password) and its own log.
	$formwerk_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $formwerk_site_ids as $formwerk_site_id ) {
		switch_to_blog( (int) $formwerk_site_id );
		formwerk_uninstall_site();
		restore_current_blog();
	}
} else {
	formwerk_uninstall_site();
}
