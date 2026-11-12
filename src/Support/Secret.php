<?php
/**
 * Symmetric encryption for values stored in the database.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Support;

use InvalidArgumentException;
use SodiumException;

/**
 * Encrypts short secrets (the SMTP password) with a key derived from the site salt.
 *
 * A database dump alone does not reveal the secret; the salt from wp-config.php is needed too.
 */
final class Secret {

	private const PREFIX = 'fw1:';

	/**
	 * Derived encryption key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Builds the key from a site-specific salt.
	 *
	 * @param string $salt Site salt, for example wp_salt( 'auth' ).
	 *
	 * @throws InvalidArgumentException When the salt is empty.
	 */
	public function __construct( string $salt ) {
		if ( '' === $salt ) {
			throw new InvalidArgumentException( 'Salt must not be empty.' );
		}
		$this->key = sodium_crypto_generichash( 'formwerk-secret|' . $salt, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Encrypts a value. An empty string stays empty.
	 *
	 * @param string $plain Value to protect.
	 */
	public function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport encoding of ciphertext.
		return self::PREFIX . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $this->key ) );
	}

	/**
	 * Decrypts a stored value.
	 *
	 * @param string $stored Value produced by encrypt().
	 *
	 * @return string|null The plain value, '' for an empty input, or null when it cannot be read
	 *                     (wrong format, tampered data, or the site salt has changed).
	 */
	public function decrypt( string $stored ): ?string {
		if ( '' === $stored ) {
			return '';
		}
		if ( ! str_starts_with( $stored, self::PREFIX ) ) {
			return null;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport encoding of ciphertext.
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		try {
			$plain = sodium_crypto_secretbox_open(
				substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				$this->key
			);
		} catch ( SodiumException $e ) {
			return null;
		}

		return false === $plain ? null : $plain;
	}
}
