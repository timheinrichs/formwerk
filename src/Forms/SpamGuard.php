<?php
/**
 * Spam protection without a captcha.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Forms;

/**
 * Signed time token, honeypot and link limit.
 *
 * The token replaces a nonce: it is not bound to a user, so pages with forms can be cached.
 * It proves that the form was requested from this site a plausible time ago.
 *
 * Pure logic: no WordPress functions.
 */
final class SpamGuard {

	public const OK       = 'ok';
	public const INVALID  = 'invalid';
	public const TOO_FAST = 'too_fast';
	public const EXPIRED  = 'expired';
	public const HONEYPOT = 'honeypot';
	public const LINKS    = 'links';

	public const MIN_SECONDS = 3;
	public const MAX_SECONDS = 86400;

	/**
	 * Signing key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Constructor.
	 *
	 * @param string $key Site-specific secret.
	 */
	public function __construct( string $key ) {
		$this->key = $key;
	}

	/**
	 * Creates a token for a form.
	 *
	 * @param int  $form_id Form ID.
	 * @param int  $now     Current Unix time.
	 * @param bool $renewed True when the token replaces one that was rejected after the form was filled in.
	 *                      The person has spent time on the form already, so the minimum time does not apply.
	 */
	public function issue( int $form_id, int $now, bool $renewed = false ): string {
		$issued = $renewed ? $now - self::MIN_SECONDS : $now;

		return $issued . '.' . $this->sign( $form_id, $issued );
	}

	/**
	 * Checks a token.
	 *
	 * @param mixed $token   Submitted token.
	 * @param int   $form_id Form the submission belongs to.
	 * @param int   $now     Current Unix time.
	 *
	 * @return string One of OK, INVALID, TOO_FAST, EXPIRED.
	 */
	public function verify( $token, int $form_id, int $now ): string {
		if ( ! is_string( $token ) || 1 !== preg_match( '/^([0-9]{1,12})\.([a-f0-9]{32})$/', $token, $part ) ) {
			return self::INVALID;
		}
		$issued = (int) $part[1];
		if ( ! hash_equals( $this->sign( $form_id, $issued ), $part[2] ) || $issued > $now + 60 ) {
			return self::INVALID;
		}
		if ( $now - $issued < self::MIN_SECONDS ) {
			return self::TOO_FAST;
		}

		return $now - $issued > self::MAX_SECONDS ? self::EXPIRED : self::OK;
	}

	/**
	 * Checks the content of a submission.
	 *
	 * @param mixed                $honeypot  Value of the hidden field that people leave empty.
	 * @param array<string, mixed> $values    Validated values.
	 * @param int                  $max_links Links allowed across all text values; 0 switches the check off.
	 *
	 * @return string One of OK, HONEYPOT, LINKS.
	 */
	public static function inspect( $honeypot, array $values, int $max_links = 0 ): string {
		if ( null !== $honeypot && '' !== $honeypot ) {
			return self::HONEYPOT;
		}
		if ( $max_links > 0 && preg_match_all( '~https?://|(?<![:/\w])//[a-z0-9]|\bwww\.~i', self::text( $values ) ) > $max_links ) {
			return self::LINKS;
		}

		return self::OK;
	}

	/**
	 * Whether any value looks like a web address, including the forms mail programs turn into
	 * links on their own ("www.example.com", "//example.com", "example.com/login").
	 *
	 * Used for the confirmation: an email to an address a stranger typed in must not carry
	 * their links in the name of the website.
	 *
	 * @param array<string, mixed> $values Validated values.
	 */
	public static function contains_link( array $values ): bool {
		$tlds = 'com|net|org|info|biz|io|co|de|at|ch|eu|uk|nl|fr|it|es|pl|ru|cn|xyz|top|link|click|online|site|shop|app|me|ly|gl|cc|tk|ml|ga|cf|gq|live|icu|vip|club|work';

		return 1 === preg_match(
			'~https?://|(?<![:/\w])//[a-z0-9]|\bwww\.|\b[a-z0-9][a-z0-9-]*(?:\.[a-z0-9-]+)*\.(?:' . $tlds . ')\b(?!@)~i',
			// Email addresses are not links; they are taken out before looking.
			(string) preg_replace( '/[^\s@<>]+@[^\s@<>]+/', ' ', self::text( $values ) )
		);
	}

	/**
	 * All text values joined, for searching.
	 *
	 * @param array<string, mixed> $values Validated values.
	 */
	private static function text( array $values ): string {
		$text = '';
		foreach ( $values as $value ) {
			$text .= ' ' . ( is_array( $value ) ? implode( ' ', array_filter( $value, 'is_scalar' ) ) : ( is_scalar( $value ) ? (string) $value : '' ) );
		}

		return $text;
	}

	/**
	 * Signature over form and time.
	 *
	 * @param int $form_id Form ID.
	 * @param int $issued  Unix time of issue.
	 */
	private function sign( int $form_id, int $issued ): string {
		return substr( hash_hmac( 'sha256', 'formwerk-form|' . $form_id . '|' . $issued, $this->key ), 0, 32 );
	}
}
