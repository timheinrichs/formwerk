<?php
/**
 * Guards against version drift between the places a release number lives.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit;

use Formwerk\Plugin;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase {

	public function test_plugin_header_matches_class_constant(): void {
		$header = (string) file_get_contents( Plugin::path( 'formwerk.php' ) );

		$this->assertSame( 1, preg_match( '/^\s*\*\s*Version:\s*(\S+)\s*$/m', $header, $match ) );
		$this->assertSame( Plugin::VERSION, $match[1] );
	}

	public function test_package_json_matches_class_constant(): void {
		$package = json_decode( (string) file_get_contents( Plugin::path( 'package.json' ) ), true, 8, JSON_THROW_ON_ERROR );

		$this->assertSame( Plugin::VERSION, $package['version'] );
	}

	public function test_minimum_php_matches_composer_requirement(): void {
		$header   = (string) file_get_contents( Plugin::path( 'formwerk.php' ) );
		$composer = json_decode( (string) file_get_contents( Plugin::path( 'composer.json' ) ), true, 8, JSON_THROW_ON_ERROR );

		$this->assertSame( 1, preg_match( '/^\s*\*\s*Requires PHP:\s*(\S+)\s*$/m', $header, $match ) );
		$this->assertSame( '>=' . $match[1], $composer['require']['php'] );
		$this->assertStringContainsString( "define( 'FORMWERK_MIN_PHP', '" . $match[1] . "' );", $header );
	}
}
