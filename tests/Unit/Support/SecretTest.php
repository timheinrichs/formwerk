<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Support;

use Formwerk\Support\Secret;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SecretTest extends TestCase {

	public function test_round_trip_returns_the_original_value(): void {
		$secret = new Secret( 'site-salt' );
		$stored = $secret->encrypt( 'pässwort with spaces & symbols' );

		$this->assertStringStartsWith( 'fw1:', $stored );
		$this->assertStringNotContainsString( 'pässwort', $stored );
		$this->assertSame( 'pässwort with spaces & symbols', $secret->decrypt( $stored ) );
	}

	public function test_same_value_encrypts_differently_each_time(): void {
		$secret = new Secret( 'site-salt' );

		$this->assertNotSame( $secret->encrypt( 'secret' ), $secret->encrypt( 'secret' ) );
	}

	public function test_empty_value_stays_empty(): void {
		$secret = new Secret( 'site-salt' );

		$this->assertSame( '', $secret->encrypt( '' ) );
		$this->assertSame( '', $secret->decrypt( '' ) );
	}

	public function test_changed_salt_makes_the_value_unreadable(): void {
		$stored = ( new Secret( 'old-salt' ) )->encrypt( 'secret' );

		$this->assertNull( ( new Secret( 'new-salt' ) )->decrypt( $stored ) );
	}

	public function test_tampered_or_foreign_values_are_unreadable(): void {
		$secret = new Secret( 'site-salt' );
		$stored = $secret->encrypt( 'secret' );

		$this->assertNull( $secret->decrypt( 'plain-text-from-an-old-version' ) );
		$this->assertNull( $secret->decrypt( 'fw1:not base64!' ) );
		$this->assertNull( $secret->decrypt( 'fw1:' . base64_encode( 'short' ) ) );
		$this->assertNull( $secret->decrypt( substr( $stored, 0, -4 ) . 'AAAA' ) );
	}

	public function test_empty_salt_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new Secret( '' );
	}
}
