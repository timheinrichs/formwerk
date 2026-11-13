<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Forms;

use Formwerk\Forms\SpamGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpamGuardTest extends TestCase {

	private const NOW = 1_800_000_000;

	public function test_a_token_is_valid_after_a_few_seconds(): void {
		$guard = new SpamGuard( 'key' );
		$token = $guard->issue( 7, self::NOW );

		$this->assertSame( SpamGuard::OK, $guard->verify( $token, 7, self::NOW + SpamGuard::MIN_SECONDS ) );
		$this->assertSame( SpamGuard::OK, $guard->verify( $token, 7, self::NOW + SpamGuard::MAX_SECONDS ) );
	}

	public function test_submitting_at_once_is_too_fast(): void {
		$guard = new SpamGuard( 'key' );

		$this->assertSame( SpamGuard::TOO_FAST, $guard->verify( $guard->issue( 7, self::NOW ), 7, self::NOW + 1 ) );
	}

	public function test_a_renewed_token_can_be_used_at_once(): void {
		$guard = new SpamGuard( 'key' );

		$this->assertSame( SpamGuard::OK, $guard->verify( $guard->issue( 7, self::NOW, true ), 7, self::NOW ) );
	}

	public function test_old_tokens_expire(): void {
		$guard = new SpamGuard( 'key' );

		$this->assertSame( SpamGuard::EXPIRED, $guard->verify( $guard->issue( 7, self::NOW ), 7, self::NOW + SpamGuard::MAX_SECONDS + 1 ) );
	}

	public function test_tokens_are_bound_to_form_key_and_time(): void {
		$guard = new SpamGuard( 'key' );
		$token = $guard->issue( 7, self::NOW );
		$later = self::NOW + 10;

		$this->assertSame( SpamGuard::INVALID, $guard->verify( $token, 8, $later ) );
		$this->assertSame( SpamGuard::INVALID, ( new SpamGuard( 'other key' ) )->verify( $token, 7, $later ) );
		$this->assertSame( SpamGuard::INVALID, $guard->verify( ( self::NOW - 100 ) . substr( $token, 10 ), 7, $later ) );
		$this->assertSame( SpamGuard::INVALID, $guard->verify( $guard->issue( 7, $later + 3600 ), 7, $later ) );
	}

	public function test_malformed_tokens_are_invalid(): void {
		$guard = new SpamGuard( 'key' );

		foreach ( array( '', 'abc', null, array( 'x' ), '123.zzz', self::NOW . '.' . str_repeat( 'a', 31 ) ) as $token ) {
			$this->assertSame( SpamGuard::INVALID, $guard->verify( $token, 7, self::NOW + 10 ) );
		}
	}

	public function test_a_filled_honeypot_is_spam(): void {
		$this->assertSame( SpamGuard::HONEYPOT, SpamGuard::inspect( 'http://spam.example', array() ) );
		$this->assertSame( SpamGuard::OK, SpamGuard::inspect( '', array() ) );
		$this->assertSame( SpamGuard::OK, SpamGuard::inspect( null, array() ) );
	}

	public function test_link_limit_counts_links_across_all_values(): void {
		$values = array(
			'nachricht' => 'Siehe https://a.example und http://b.example',
			'tage'      => array( 'www.c.example' ),
			'ok'        => true,
		);

		$this->assertSame( SpamGuard::LINKS, SpamGuard::inspect( '', $values, 2 ) );
		$this->assertSame( SpamGuard::OK, SpamGuard::inspect( '', $values, 3 ) );
		$this->assertSame( SpamGuard::OK, SpamGuard::inspect( '', $values, 0 ) );
	}

	public function test_protocol_relative_links_count_too(): void {
		$this->assertSame( SpamGuard::LINKS, SpamGuard::inspect( '', array( 'm' => 'see //evil.example/x and //b.example' ), 1 ) );
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function link_texts(): array {
		return array(
			'plain text'        => array( 'Ich komme mit zwei Personen.', false ),
			'email address'     => array( 'Schreibt mir an anna@example.de', false ),
			'sentence end'      => array( 'Bis bald.Danke', false ),
			'https'             => array( 'https://evil.example', true ),
			'protocol relative' => array( 'go //evil.example/x', true ),
			'www'               => array( 'www.evil.example', true ),
			'bare domain'       => array( 'verify at evil-bank.com/login', true ),
			'bare domain de'    => array( 'mehr auf beispiel.de', true ),
		);
	}

	#[DataProvider( 'link_texts' )]
	public function test_links_are_found_for_the_confirmation( string $text, bool $expected ): void {
		$this->assertSame( $expected, SpamGuard::contains_link( array( 'nachricht' => $text ) ) );
	}
}
