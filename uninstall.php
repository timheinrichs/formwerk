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
	delete_option( 'formwerk_db_version' );
	delete_option( 'formwerk_frontend_style' );

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dropping the plugin's own table; the name is built from the table prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}formwerk_submissions" );

	$formwerk_forms = get_posts(
		array(
			'post_type'      => 'formwerk_form',
			// 'any' leaves out the trash and drafts that were never saved.
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $formwerk_forms as $formwerk_form ) {
		wp_delete_post( (int) $formwerk_form, true );
	}
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
