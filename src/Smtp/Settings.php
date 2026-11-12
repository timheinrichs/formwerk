<?php
/**
 * Reads and writes the SMTP settings.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Smtp;

use Formwerk\Support\Secret;

/**
 * Combines the stored option with the FORMWERK_SMTP_* constants from wp-config.php.
 *
 * Constants win over the database and lock the matching field in the backend.
 */
final class Settings {

	public const OPTION = 'formwerk_smtp';

	public const ERROR_CONSTANT  = 'constant_invalid';
	public const ERROR_NOT_SAVED = 'not_saved';

	private const CONSTANTS = array(
		'host'       => 'FORMWERK_SMTP_HOST',
		'port'       => 'FORMWERK_SMTP_PORT',
		'encryption' => 'FORMWERK_SMTP_ENCRYPTION',
		'auth'       => 'FORMWERK_SMTP_AUTH',
		'username'   => 'FORMWERK_SMTP_USER',
		'password'   => 'FORMWERK_SMTP_PASSWORD',
		'from_email' => 'FORMWERK_SMTP_FROM_EMAIL',
		'from_name'  => 'FORMWERK_SMTP_FROM_NAME',
		'force_from' => 'FORMWERK_SMTP_FORCE_FROM',
	);

	/**
	 * Encrypts the stored password. Built on first use, because the site salt is not needed on most requests.
	 *
	 * @var Secret|null
	 */
	private ?Secret $secret;

	/**
	 * Effective config per site, for this request.
	 *
	 * @var array<int, Config>
	 */
	private array $configs = array();

	/**
	 * Sites whose stored password could not be decrypted.
	 *
	 * @var array<int, bool>
	 */
	private array $unreadable = array();

	/**
	 * Constructor.
	 *
	 * @param Secret|null $secret Encryption helper; created from the site salt when omitted.
	 */
	public function __construct( ?Secret $secret = null ) {
		$this->secret = $secret;
	}

	/**
	 * Effective settings: database values with the password decrypted, overridden by constants.
	 */
	public function config(): Config {
		$site = get_current_blog_id();
		if ( ! isset( $this->configs[ $site ] ) ) {
			$this->configs[ $site ] = new Config( array_merge( $this->stored_plain(), $this->overrides() ) );
		}

		return $this->configs[ $site ];
	}

	/**
	 * Fields that are set by a constant and cannot be changed in the backend.
	 *
	 * @return string[]
	 */
	public function locked(): array {
		return array_keys( $this->overrides() );
	}

	/**
	 * Errors of the effective settings. Problems in constants get their own code,
	 * because they cannot be fixed in the backend.
	 *
	 * @return array<string, string> Field name => error code.
	 */
	public function errors(): array {
		return $this->mark_constant_errors( $this->config()->errors() );
	}

	/**
	 * Saves the editable fields.
	 *
	 * An empty password keeps the stored one. Nothing is written when an editable field is invalid.
	 * Invalid constants do not block saving: they cannot be corrected here.
	 *
	 * @param array<string, mixed> $input Submitted values.
	 *
	 * @return array<string, string> Field name => error code; empty on success.
	 */
	public function save( array $input ): array {
		$stored = $this->stored_plain();
		$input  = array_diff_key( array_intersect_key( $input, Config::DEFAULTS ), $this->overrides() );

		if ( ! isset( $input['password'] ) || ! is_string( $input['password'] ) || '' === $input['password'] ) {
			unset( $input['password'] );
		}
		$candidate = array_merge( $stored, $input );
		$errors    = ( new Config( array_merge( $candidate, $this->overrides() ) ) )->errors();
		$blocking  = array_diff_key( $errors, $this->overrides() );
		if ( array() !== $blocking ) {
			return $blocking;
		}

		$to_store             = Config::normalize( $candidate );
		$to_store['password'] = $this->secret()->encrypt( $to_store['password'] );

		// update_option() also returns false when nothing changed, so compare before reporting a failure.
		if ( ! update_option( self::OPTION, $to_store, false ) && get_option( self::OPTION ) !== $to_store ) {
			return array( 'general' => self::ERROR_NOT_SAVED );
		}

		$site = get_current_blog_id();
		unset( $this->configs[ $site ], $this->unreadable[ $site ] );

		return array();
	}

	/**
	 * Settings for the backend. The password itself never leaves the server.
	 *
	 * @return array<string, mixed>
	 */
	public function for_display(): array {
		$config = $this->config();
		$values = $config->all();
		$locked = $this->locked();

		$values['has_password'] = '' !== $values['password'];
		unset( $values['password'] );

		return array(
			'settings'            => $values,
			'locked'              => $locked,
			'status'              => $config->status(),
			'errors'              => $this->errors(),
			// Only worth a warning when the stored password is actually used.
			'password_unreadable' => ! empty( $this->unreadable[ get_current_blog_id() ] )
				&& $values['auth'] && ! in_array( 'password', $locked, true ),
			'key_in_database'     => $values['has_password'] && ! in_array( 'password', $locked, true ) && ! self::has_config_salts(),
		);
	}

	/**
	 * Whether the encryption key comes from wp-config.php. Without salts there, WordPress keeps
	 * them in the database, right next to the encrypted password.
	 */
	private static function has_config_salts(): bool {
		foreach ( array( 'AUTH_KEY', 'AUTH_SALT' ) as $constant ) {
			$value = defined( $constant ) ? constant( $constant ) : '';
			if ( ! is_string( $value ) || '' === $value || 'put your unique phrase here' === $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Replaces the codes of locked fields.
	 *
	 * @param array<string, string> $errors Field name => error code.
	 *
	 * @return array<string, string>
	 */
	private function mark_constant_errors( array $errors ): array {
		foreach ( $this->locked() as $field ) {
			if ( isset( $errors[ $field ] ) ) {
				$errors[ $field ] = self::ERROR_CONSTANT;
			}
		}

		return $errors;
	}

	/**
	 * Returns the encryption helper.
	 */
	private function secret(): Secret {
		if ( null === $this->secret ) {
			$this->secret = new Secret( wp_salt( 'auth' ) );
		}

		return $this->secret;
	}

	/**
	 * Database values with the password decrypted.
	 *
	 * @return array<string, mixed>
	 */
	private function stored_plain(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = Config::normalize( is_array( $stored ) ? $stored : array() );

		if ( '' !== $stored['password'] ) {
			$plain = $this->secret()->decrypt( $stored['password'] );
			if ( null === $plain ) {
				$this->unreadable[ get_current_blog_id() ] = true;
				$plain                                     = '';
			}
			$stored['password'] = $plain;
		}

		return $stored;
	}

	/**
	 * Values defined as constants in wp-config.php.
	 *
	 * @return array<string, mixed>
	 */
	private function overrides(): array {
		$overrides = array();
		foreach ( self::CONSTANTS as $key => $constant ) {
			if ( defined( $constant ) ) {
				$overrides[ $key ] = constant( $constant );
			}
		}

		return $overrides;
	}
}
