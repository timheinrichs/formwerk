<?php
/**
 * Tests for reading GitHub releases.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Update;

use Formwerk\Update\Release;
use PHPUnit\Framework\TestCase;

/**
 * Release.
 */
final class ReleaseTest extends TestCase {

	private const HASH = 'f3b1c2d4e5f60718293a4b5c6d7e8f90112233445566778899aabbccddeeff00';

	/**
	 * A valid API answer, changed by $changes.
	 *
	 * @param array<string, mixed> $changes Top-level changes.
	 *
	 * @return array<string, mixed>
	 */
	private function api( array $changes = array() ): array {
		$base = 'https://github.com/timheinrichs/formwerk/releases/download/v0.4.0/';

		return array_merge(
			array(
				'tag_name'     => 'v0.4.0',
				'draft'        => false,
				'prerelease'   => false,
				'html_url'     => 'https://github.com/timheinrichs/formwerk/releases/tag/v0.4.0',
				'published_at' => '2026-11-21T15:00:00Z',
				'body'         => "## New\n\n- Updates",
				'assets'       => array(
					array(
						'name'                 => 'formwerk-0.4.0.zip',
						'browser_download_url' => $base . 'formwerk-0.4.0.zip',
						'digest'               => 'sha256:' . self::HASH,
					),
					array(
						'name'                 => 'formwerk-0.4.0.json',
						'browser_download_url' => $base . 'formwerk-0.4.0.json',
					),
				),
			),
			$changes
		);
	}

	public function test_a_published_release_is_read(): void {
		$release = Release::from_api( $this->api() );

		$this->assertNotNull( $release );
		$this->assertSame( '0.4.0', $release->version );
		$this->assertSame( 'https://github.com/timheinrichs/formwerk/releases/download/v0.4.0/formwerk-0.4.0.zip', $release->package );
		$this->assertSame( self::HASH, $release->checksum );
		$this->assertSame( 'https://github.com/timheinrichs/formwerk/releases/download/v0.4.0/formwerk-0.4.0.json', $release->manifest );
		$this->assertSame( 'https://github.com/timheinrichs/formwerk/releases/tag/v0.4.0', $release->url );
		$this->assertSame( '2026-11-21T15:00:00Z', $release->published );
	}

	public function test_drafts_and_prereleases_are_not_offered(): void {
		$this->assertNull( Release::from_api( $this->api( array( 'draft' => true ) ) ) );
		$this->assertNull( Release::from_api( $this->api( array( 'prerelease' => true ) ) ) );
	}

	public function test_the_tag_must_be_a_plain_version(): void {
		$this->assertNull( Release::from_api( $this->api( array( 'tag_name' => 'v0.4.0-rc.1' ) ) ) );
		$this->assertNull( Release::from_api( $this->api( array( 'tag_name' => 'latest' ) ) ) );
	}

	public function test_the_zip_must_have_the_expected_name_and_address(): void {
		$renamed                                        = $this->api();
		$renamed['assets'][0]['name']                   = 'formwerk.zip';
		$foreign                                        = $this->api();
		$foreign['assets'][0]['browser_download_url']   = 'https://evil.example/formwerk-0.4.0.zip';
		$other_tag                                      = $this->api();
		$other_tag['assets'][0]['browser_download_url'] = 'https://github.com/timheinrichs/formwerk/releases/download/v0.3.1/formwerk-0.4.0.zip';

		$this->assertNull( Release::from_api( $renamed ) );
		$this->assertNull( Release::from_api( $foreign ) );
		$this->assertNull( Release::from_api( $other_tag ) );
	}

	public function test_a_zip_without_checksum_is_not_offered(): void {
		$missing = $this->api();
		unset( $missing['assets'][0]['digest'] );
		$weak                        = $this->api();
		$weak['assets'][0]['digest'] = 'md5:0123456789abcdef0123456789abcdef';

		$this->assertNull( Release::from_api( $missing ) );
		$this->assertNull( Release::from_api( $weak ) );
	}

	public function test_a_release_without_requirements_file_still_works(): void {
		$api = $this->api();
		unset( $api['assets'][1] );

		$this->assertSame( '', Release::from_api( $api )?->manifest );
	}

	public function test_an_unexpected_page_address_falls_back_to_the_release_list(): void {
		$release = Release::from_api( $this->api( array( 'html_url' => 'https://evil.example/' ) ) );

		$this->assertSame( 'https://github.com/timheinrichs/formwerk/releases', $release?->url );
	}

	public function test_only_downloads_of_this_repository_are_own_packages(): void {
		$this->assertTrue( Release::is_own_package( 'https://github.com/timheinrichs/formwerk/releases/download/v0.4.0/formwerk-0.4.0.zip' ) );
		$this->assertFalse( Release::is_own_package( 'https://github.com/someone/formwerk/releases/download/v0.4.0/formwerk-0.4.0.zip' ) );
		$this->assertFalse( Release::is_own_package( 'https://downloads.wordpress.org/plugin/akismet.zip' ) );
	}

	public function test_requirements_are_read_and_checked(): void {
		$this->assertSame(
			array(
				'requires'     => '6.6',
				'requires_php' => '8.1',
				'tested'       => '7.1',
			),
			Release::requirements(
				array(
					'requires'     => '6.6',
					'requires_php' => '8.1',
					'tested'       => '7.1',
				)
			)
		);
		$this->assertSame(
			array(
				'requires'     => '',
				'requires_php' => '',
				'tested'       => '',
			),
			Release::requirements(
				array(
					'requires'     => '<b>6</b>',
					'requires_php' => array(),
				)
			)
		);
		$this->assertSame( '', Release::requirements( 'nonsense' )['requires'] );
	}

	public function test_a_fetch_is_complete_only_with_its_requirements(): void {
		$file = array(
			'requires'     => '6.6',
			'requires_php' => '8.3',
		);
		$ok   = Release::from_fetched(
			array(
				'api'          => $this->api(),
				'requirements' => $file,
			)
		);
		$this->assertSame( '0.4.0', $ok['release']->version ?? '' );
		$this->assertSame( '8.3', $ok['requires_php'] ?? '' );

		// The requirements file exists but could not be read: not offered, or a site on PHP 8.2 would update.
		$this->assertNull(
			Release::from_fetched(
				array(
					'api'          => $this->api(),
					'requirements' => null,
				)
			)
		);
		$this->assertNull(
			Release::from_fetched(
				array(
					'api'          => null,
					'requirements' => null,
				)
			)
		);
		$this->assertNull( Release::from_fetched( 'garbage' ) );

		// Older releases have no requirements file at all; they are still offered.
		$older = $this->api();
		unset( $older['assets'][1] );
		$this->assertNotNull(
			Release::from_fetched(
				array(
					'api'          => $older,
					'requirements' => null,
				)
			)
		);
	}

	public function test_the_changelog_is_simple_and_safe_html(): void {
		$notes   = "## New\n\n- **Updates** from GitHub, see [the docs](https://github.com/timheinrichs/formwerk)\n- `code`\n\n"
			. "<script>alert(1)</script>\n[bad](javascript:alert(1))\nSee https://github.com/timheinrichs/formwerk/issues/12.\n";
		$release = Release::from_api( $this->api( array( 'body' => $notes ) ) );
		$html    = (string) $release?->changelog_html();

		$this->assertStringContainsString( '<h4>New</h4>', $html );
		$this->assertStringContainsString( '<ul><li><strong>Updates</strong> from GitHub, see <a href="https://github.com/timheinrichs/formwerk">the docs</a></li><li><code>code</code></li></ul>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'href="javascript', $html );
		$this->assertStringContainsString( '<a href="https://github.com/timheinrichs/formwerk/issues/12">https://github.com/timheinrichs/formwerk/issues/12</a>.', $html );
	}

	public function test_lines_for_developers_are_left_out_of_the_changelog(): void {
		$notes   = "## New\n\n- Updates from GitHub\n\n## What's Changed\n* feat(update): add updater by @someone in https://github.com/timheinrichs/formwerk/pull/7\n\n"
			. "## New Contributors\n\n**Full Changelog**: https://github.com/timheinrichs/formwerk/compare/v0.3.1...v0.4.0";
		$release = Release::from_api( $this->api( array( 'body' => $notes ) ) );

		$this->assertSame( '<h4>New</h4><ul><li>Updates from GitHub</li></ul>', $release?->changelog_html() );
	}

	public function test_a_tested_series_covers_its_patch_versions(): void {
		$this->assertSame( '7.1.99', Release::tested_through( '7.1' ) );
		$this->assertTrue( version_compare( Release::tested_through( '7.1' ), '7.1.4', '>=' ) );
		$this->assertFalse( version_compare( Release::tested_through( '7.1' ), '7.2', '>=' ) );
		$this->assertSame( '7.1.3', Release::tested_through( '7.1.3' ) );
		$this->assertSame( '', Release::tested_through( '' ) );
		$this->assertSame( '7.1.4', Release::tested_through( '7.1', '7.1.4' ) );
		$this->assertSame( '7.1.99', Release::tested_through( '7.1', '7.2' ) );
	}
}
