<?php
/**
 * Updates from GitHub releases.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Update;

use Formwerk\Plugin;
use WP_Error;

/**
 * Offers new releases under Dashboard → Updates, like any other plugin.
 *
 * The header "Update URI: https://github.com/timheinrichs/formwerk" makes WordPress ask this
 * class instead of WordPress.org, so a different plugin of the same name there can never be
 * installed over Formwerk. Only the release list of the public repository is read and its ZIP
 * downloaded: no token, and no address of the website is sent (WordPress would send it in its
 * user agent, so every request here sets its own).
 */
final class Updater {

	public const SLUG  = 'formwerk';
	public const CACHE = 'formwerk_update';

	private const API        = 'https://api.github.com/repos/' . Release::REPOSITORY . '/releases/latest';
	private const UPDATE_URI = 'https://github.com/' . Release::REPOSITORY;
	private const CACHE_OK   = 12 * 3600;
	private const CACHE_FAIL = 3600;

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_filter( 'update_plugins_github.com', array( $this, 'offer' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'details' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'download' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'forget_after_update' ), 10, 2 );
	}

	/**
	 * Answers WordPress's update check for Formwerk.
	 *
	 * @param array<string, mixed>|false $update      Update found so far.
	 * @param array<string, string>      $plugin_data Plugin headers.
	 * @param string                     $plugin_file Plugin file, relative to the plugins folder.
	 *
	 * @return array<string, mixed>|false
	 */
	public function offer( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( FORMWERK_FILE ) !== $plugin_file || self::UPDATE_URI !== ( $plugin_data['UpdateURI'] ?? '' ) ) {
			return $update;
		}
		$found = $this->latest();
		if ( null === $found ) {
			// GitHub cannot be reached right now: keep what WordPress knew, so a known update does
			// not disappear until its next check in twelve hours.
			return self::known_update( $plugin_file ) ?? $update;
		}

		// WordPress compares the version itself: older or equal shows as "up to date", which
		// also lets the automatic updates switch appear in the plugin list.
		return array(
			'id'           => self::UPDATE_URI,
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'version'      => $found['release']->version,
			'url'          => $found['release']->url,
			'package'      => $found['release']->package,
			'requires'     => $found['requires'],
			'requires_php' => $found['requires_php'],
			'tested'       => Release::tested_through( $found['tested'] ),
			'icons'        => self::icons(),
			'banners'      => self::banners(),
		);
	}

	/**
	 * Fills the "View details" dialog for Formwerk.
	 *
	 * @param false|object|array<string, mixed> $result Result so far.
	 * @param string                            $action API action.
	 * @param object                            $args   Request arguments.
	 *
	 * @return false|object|array<string, mixed>|WP_Error
	 */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || self::SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$found = $this->latest();
		if ( null === $found ) {
			// Without this, WordPress would show a plugin of the same name from WordPress.org.
			return new WP_Error( 'formwerk_no_release', __( 'The version information could not be loaded from GitHub right now. Formwerk keeps working as before. Please try again later.', 'formwerk' ) );
		}
		$release = $found['release'];

		return (object) array(
			'name'          => 'Formwerk',
			'slug'          => self::SLUG,
			'version'       => $release->version,
			'author'        => '<a href="https://github.com/timheinrichs">Tim Heinrichs</a>',
			'homepage'      => self::UPDATE_URI,
			'requires'      => $found['requires'],
			'requires_php'  => $found['requires_php'],
			'tested'        => Release::tested_through( $found['tested'], (string) get_bloginfo( 'version' ) ),
			'last_updated'  => '' !== $release->published ? gmdate( 'Y-m-d H:i:s', (int) strtotime( $release->published ) ) : '',
			'download_link' => $release->package,
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Forms that never lose a submission, and reliable email for the whole website: Formwerk sends every email of the site through a real mailbox (SMTP), so it arrives in the inbox instead of the spam folder.', 'formwerk' ) . '</p>',
				'changelog'   => $release->changelog_html() . sprintf( '<p><a href="%s">%s</a></p>', esc_url( self::UPDATE_URI . '/releases' ), esc_html__( 'All changes on GitHub', 'formwerk' ) ),
			),
			'icons'         => self::icons(),
			'banners'       => self::banners(),
		);
	}

	/**
	 * Downloads a Formwerk update and checks it against the published SHA-256 checksum.
	 *
	 * Every download of this repository's releases goes through here; if it cannot be checked,
	 * the update stops instead of falling back to an unchecked download by WordPress.
	 *
	 * @param false|string|WP_Error $reply   Result so far; false lets WordPress download.
	 * @param string                $package Download address.
	 *
	 * @return false|string|WP_Error File path, or an error that stops the update.
	 */
	public function download( $reply, $package ) {
		if ( false !== $reply || ! is_string( $package ) || ! Release::is_own_package( $package ) ) {
			return $reply;
		}
		$found = $this->latest();
		if ( null === $found || $package !== $found['release']->package ) {
			// The offered update is older than the cached release information: read it again once.
			$found = $this->latest( true );
		}
		if ( null === $found || $package !== $found['release']->package ) {
			return new WP_Error( 'formwerk_unverified', __( 'The update was not installed, because it could not be checked against the published version right now. Formwerk keeps working as before. Please try again later.', 'formwerk' ) );
		}

		$file = self::fetch_file( $package );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! hash_equals( $found['release']->checksum, (string) hash_file( 'sha256', $file ) ) ) {
			wp_delete_file( $file );
			self::forget();

			return new WP_Error( 'formwerk_checksum', __( 'The update was not installed, because the downloaded file does not match the published version (checksum). Formwerk keeps working as before. Please try again later. If it happens again, contact the person who looks after your website.', 'formwerk' ) );
		}

		return $file;
	}

	/**
	 * After Formwerk was updated, the next check reads the release list again.
	 *
	 * @param object               $upgrader Upgrader.
	 * @param array<string, mixed> $options  What was updated.
	 */
	public function forget_after_update( $upgrader, $options ): void {
		if ( 'plugin' === ( $options['type'] ?? '' ) && in_array( plugin_basename( FORMWERK_FILE ), (array) ( $options['plugins'] ?? array() ), true ) ) {
			self::forget();
		}
	}

	/**
	 * Clears the cached release.
	 */
	public static function forget(): void {
		delete_site_transient( self::CACHE );
	}

	/**
	 * The latest release with its requirements, cached; null when there is none or GitHub cannot be reached.
	 *
	 * @param bool $fresh Read from GitHub even if a cached answer exists.
	 *
	 * @return array{release: Release, requires: string, requires_php: string, tested: string}|null
	 */
	private function latest( bool $fresh = false ): ?array {
		$cached = $fresh || self::check_again() ? false : get_site_transient( self::CACHE );
		if ( ! is_array( $cached ) ) {
			$cached = $this->fetch();
			$usable = is_array( Release::from_fetched( $cached ) );
			// A failed or incomplete answer is asked again after an hour, a good one after twelve.
			set_site_transient( self::CACHE, $cached, $usable ? self::CACHE_OK : self::CACHE_FAIL );
		}

		return Release::from_fetched( $cached );
	}

	/**
	 * Whether someone chose "Check again" under Dashboard → Updates.
	 */
	private static function check_again(): bool {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only skips the cache for people who may update plugins.
		return is_admin() && 'update-core.php' === $pagenow && ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' );
	}

	/**
	 * Reads the latest release and its requirements file from GitHub.
	 *
	 * @return array{api: array<string, mixed>|null, requirements: array<string, mixed>|null}
	 */
	private function fetch(): array {
		$api     = self::get_json( self::API );
		$release = null !== $api ? Release::from_api( $api ) : null;

		return array(
			// Only the fields Release reads are kept, so the cache stays small.
			'api'          => null !== $api ? array_intersect_key( $api, array_flip( array( 'tag_name', 'draft', 'prerelease', 'html_url', 'published_at', 'body', 'assets' ) ) ) : null,
			'requirements' => null !== $release && '' !== $release->manifest ? self::get_json( $release->manifest ) : null,
		);
	}

	/**
	 * Request options for GitHub: a user agent without the website's address.
	 *
	 * @param int $timeout Seconds.
	 *
	 * @return array<string, mixed>
	 */
	private static function request( int $timeout ): array {
		return array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'user-agent'  => 'Formwerk/' . Plugin::VERSION,
		);
	}

	/**
	 * Fetches and decodes a JSON document.
	 *
	 * @param string $url Address.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function get_json( string $url ): ?array {
		$response = wp_safe_remote_get( $url, self::request( 10 ) + array( 'headers' => array( 'Accept' => 'application/vnd.github+json, application/json' ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $json ) ? $json : null;
	}

	/**
	 * Downloads the update into a temporary file.
	 *
	 * @param string $package Download address.
	 *
	 * @return string|WP_Error File path.
	 */
	private static function fetch_file( string $package ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file     = wp_tempnam( 'formwerk.zip' );
		$response = wp_safe_remote_get(
			$package,
			self::request( 300 ) + array(
				'stream'   => true,
				'filename' => $file,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			wp_delete_file( $file );

			return is_wp_error( $response ) ? $response : new WP_Error( 'formwerk_download', __( 'The update could not be downloaded from GitHub. Formwerk keeps working as before. Please try again later.', 'formwerk' ) );
		}

		return $file;
	}

	/**
	 * The update for Formwerk WordPress found at its last check, if any.
	 *
	 * @param string $plugin_file Plugin file.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function known_update( string $plugin_file ): ?array {
		$updates = get_site_transient( 'update_plugins' );
		$known   = is_object( $updates ) ? ( $updates->response[ $plugin_file ] ?? $updates->no_update[ $plugin_file ] ?? null ) : null;

		return is_object( $known ) || is_array( $known ) ? (array) $known : null;
	}

	/**
	 * Icons for the update list and the details dialog.
	 *
	 * @return array<string, string>
	 */
	private static function icons(): array {
		return array(
			'svg' => plugins_url( 'assets/brand/icon.svg', FORMWERK_FILE ),
			'1x'  => plugins_url( 'assets/brand/icon-128x128.png', FORMWERK_FILE ),
			'2x'  => plugins_url( 'assets/brand/icon-256x256.png', FORMWERK_FILE ),
		);
	}

	/**
	 * Banners for the details dialog.
	 *
	 * @return array<string, string>
	 */
	private static function banners(): array {
		return array(
			'low'  => plugins_url( 'assets/brand/banner-772x250.png', FORMWERK_FILE ),
			'high' => plugins_url( 'assets/brand/banner-1544x500.png', FORMWERK_FILE ),
		);
	}
}
