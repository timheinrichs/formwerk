<?php
/**
 * A published release on GitHub.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Update;

/**
 * Reads and checks the answer of the GitHub API for the latest release.
 *
 * Only what is expected is accepted: a published release (no draft, no pre-release), a tag
 * "vX.Y.Z", and a ZIP named "formwerk-X.Y.Z.zip" from this repository's download address with
 * a SHA-256 checksum. Anything else means "no update", so a renamed or foreign file is never
 * installed. No WordPress functions: testable on its own.
 */
final class Release {

	public const REPOSITORY = 'timheinrichs/formwerk';

	/**
	 * Version, e.g. "0.4.0".
	 *
	 * @var string
	 */
	public string $version;

	/**
	 * Download address of the ZIP.
	 *
	 * @var string
	 */
	public string $package;

	/**
	 * SHA-256 of the ZIP, 64 hex characters.
	 *
	 * @var string
	 */
	public string $checksum;

	/**
	 * Page of the release on GitHub.
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * Release notes (Markdown).
	 *
	 * @var string
	 */
	public string $notes;

	/**
	 * Publication date, ISO 8601, or ''.
	 *
	 * @var string
	 */
	public string $published;

	/**
	 * Download address of the file with the requirements, or '' when the release has none.
	 *
	 * @var string
	 */
	public string $manifest;

	/**
	 * Constructor.
	 *
	 * @param string $version   Version.
	 * @param string $package   ZIP address.
	 * @param string $checksum  SHA-256 of the ZIP.
	 * @param string $url       Release page.
	 * @param string $notes     Release notes.
	 * @param string $published Publication date.
	 * @param string $manifest  Requirements file address.
	 */
	private function __construct( string $version, string $package, string $checksum, string $url, string $notes, string $published, string $manifest ) {
		$this->version   = $version;
		$this->package   = $package;
		$this->checksum  = $checksum;
		$this->url       = $url;
		$this->notes     = $notes;
		$this->published = $published;
		$this->manifest  = $manifest;
	}

	/**
	 * The release from the API answer, or null when it is not one Formwerk may install.
	 *
	 * @param array<string, mixed> $json Decoded answer of GET /repos/{repository}/releases/latest.
	 */
	public static function from_api( array $json ): ?self {
		if ( ! empty( $json['draft'] ) || ! empty( $json['prerelease'] ) ) {
			return null;
		}
		$tag = $json['tag_name'] ?? '';
		if ( ! is_string( $tag ) || 1 !== preg_match( '/^v(\d{1,4}\.\d{1,4}\.\d{1,4})$/', $tag, $match ) ) {
			return null;
		}
		$version  = $match[1];
		$download = 'https://github.com/' . self::REPOSITORY . '/releases/download/' . $tag . '/';
		$zip      = null;
		$manifest = '';
		foreach ( is_array( $json['assets'] ?? null ) ? $json['assets'] : array() as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$name = $asset['name'] ?? '';
			$from = $asset['browser_download_url'] ?? '';
			if ( 'formwerk-' . $version . '.zip' === $name && $download . $name === $from ) {
				$zip = $asset;
			} elseif ( 'formwerk-' . $version . '.json' === $name && $download . $name === $from ) {
				$manifest = $from;
			}
		}
		if ( null === $zip ) {
			return null;
		}
		$digest = $zip['digest'] ?? '';
		if ( ! is_string( $digest ) || 1 !== preg_match( '/^sha256:([0-9a-f]{64})$/', $digest, $hash ) ) {
			// Without a checksum the download cannot be checked, so it is not offered.
			return null;
		}
		$page = $json['html_url'] ?? '';
		if ( ! is_string( $page ) || 'https://github.com/' . self::REPOSITORY . '/releases/tag/' . $tag !== $page ) {
			$page = 'https://github.com/' . self::REPOSITORY . '/releases';
		}
		$published = $json['published_at'] ?? '';

		return new self(
			$version,
			(string) $zip['browser_download_url'],
			$hash[1],
			$page,
			is_string( $json['body'] ?? null ) ? $json['body'] : '',
			is_string( $published ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T[\d:]+Z$/', $published ) ? $published : '',
			$manifest
		);
	}

	/**
	 * Whether an address is a download of this repository's releases. Such downloads are always
	 * checked against the published checksum, never handed to WordPress unchecked.
	 *
	 * @param string $url Download address.
	 */
	public static function is_own_package( string $url ): bool {
		return str_starts_with( $url, 'https://github.com/' . self::REPOSITORY . '/releases/download/' );
	}

	/**
	 * The release and its requirements from what was fetched (or cached), or null when it is
	 * missing or incomplete. A release whose requirements file exists but could not be read is
	 * incomplete: without it, WordPress would take it for compatible with any PHP version.
	 *
	 * @param mixed $fetched Array with "api" (API answer) and "requirements" (requirements file).
	 *
	 * @return array{release: self, requires: string, requires_php: string, tested: string}|null
	 */
	public static function from_fetched( $fetched ): ?array {
		$release = is_array( $fetched ) && is_array( $fetched['api'] ?? null ) ? self::from_api( $fetched['api'] ) : null;
		if ( null === $release ) {
			return null;
		}
		$file = $fetched['requirements'] ?? null;
		if ( '' !== $release->manifest && ! is_array( $file ) ) {
			return null;
		}

		return array( 'release' => $release ) + self::requirements( $file );
	}

	/**
	 * Minimum WordPress and PHP versions, and the WordPress version it was tested with, from the
	 * release's requirements file.
	 *
	 * @param mixed $json Decoded requirements file.
	 *
	 * @return array{requires: string, requires_php: string, tested: string} Empty strings for unknown values.
	 */
	public static function requirements( $json ): array {
		$read = static function ( $value ): string {
			return is_string( $value ) && 1 === preg_match( '/^\d{1,2}(\.\d{1,2}){0,2}$/', $value ) ? $value : '';
		};

		return array(
			'requires'     => $read( is_array( $json ) ? ( $json['requires'] ?? '' ) : '' ),
			'requires_php' => $read( is_array( $json ) ? ( $json['requires_php'] ?? '' ) : '' ),
			'tested'       => $read( is_array( $json ) ? ( $json['tested'] ?? '' ) : '' ),
		);
	}

	/**
	 * The tested WordPress version as WordPress compares it: "7.1" means the whole 7.1 series,
	 * as on WordPress.org, so 7.1.4 counts as tested. Full versions stay as they are.
	 *
	 * @param string $tested  Tested version from the requirements file.
	 * @param string $running WordPress version of the site: shown instead when it belongs to the series.
	 */
	public static function tested_through( string $tested, string $running = '' ): string {
		if ( 1 !== preg_match( '/^\d{1,2}\.\d{1,2}$/', $tested ) ) {
			return $tested;
		}
		if ( $running === $tested || str_starts_with( $running, $tested . '.' ) ) {
			return $running;
		}

		return $tested . '.99';
	}

	/**
	 * The release notes as simple HTML for the "Changelog" tab of the details dialog.
	 *
	 * Everything is escaped first; only headings, lists, bold text and https links are formatted.
	 * Lines meant for developers (compare links, pull request lists GitHub generates) are left out:
	 * the people who read this dialog run the website.
	 */
	public function changelog_html(): string {
		$html  = '';
		$list  = false;
		$lines = preg_split( '/\R/', $this->notes );
		foreach ( false === $lines ? array() : $lines as $line ) {
			$line = trim( $line );
			if ( self::is_for_developers( $line ) ) {
				continue;
			}
			if ( preg_match( '/^[-*]\s+(.+)$/', $line, $item ) ) {
				$html .= ( $list ? '' : '<ul>' ) . '<li>' . self::inline( $item[1] ) . '</li>';
				$list  = true;
				continue;
			}
			if ( $list ) {
				$html .= '</ul>';
				$list  = false;
			}
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^#{1,6}\s+(.+)$/', $line, $heading ) ) {
				$html .= '<h4>' . self::inline( $heading[1] ) . '</h4>';
			} else {
				$html .= '<p>' . self::inline( $line ) . '</p>';
			}
		}//end foreach

		return $html . ( $list ? '</ul>' : '' );
	}

	/**
	 * Whether a line of the release notes only matters to developers.
	 *
	 * @param string $line Trimmed line.
	 */
	private static function is_for_developers( string $line ): bool {
		return 1 === preg_match( '/^\*\*Full Changelog\*\*|^#{1,6}\s+(What\'s Changed|New Contributors)\s*$|^[-*]\s+.*\bby @[\w-]+ in https:\/\/\S+$/i', $line );
	}

	/**
	 * Escaped text with bold parts and https links.
	 *
	 * @param string $text Markdown text of one line.
	 */
	private static function inline( string $text ): string {
		$text = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		$text = (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = (string) preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

		return (string) preg_replace_callback(
			'/\[([^\]]+)\]\((https:\/\/[^\s)"\'<>]+)\)|(?<![="])(https:\/\/[^\s<>"\']*[^\s<>"\'.,:;!?)\]])/',
			static function ( array $link ): string {
				$href  = '' !== ( $link[2] ?? '' ) ? $link[2] : $link[3];
				$label = '' !== ( $link[1] ?? '' ) ? $link[1] : $link[3];

				return '<a href="' . $href . '">' . $label . '</a>';
			},
			$text
		);
	}
}
