<?php
/**
 * SMTP configuration value object.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Smtp;

/**
 * Normalised SMTP settings plus the rules that decide whether they may be saved.
 *
 * Pure logic: no WordPress functions, so it is covered by unit tests.
 */
final class Config {

	public const ENCRYPTIONS = array( 'none', 'ssl', 'tls' );

	public const STATUS_OFF    = 'off';
	public const STATUS_ACTIVE = 'active';
	public const STATUS_BROKEN = 'broken';

	public const DEFAULTS = array(
		'host'       => '',
		'port'       => 587,
		'encryption' => 'tls',
		'auth'       => true,
		'username'   => '',
		'password'   => '',
		'from_email' => '',
		'from_name'  => '',
		'force_from' => false,
	);

	/**
	 * Normalised values, keyed like DEFAULTS.
	 *
	 * @var array<string, mixed>
	 */
	private array $values;

	/**
	 * Builds a config from loosely typed input (database option, constants, request data).
	 *
	 * @param array<string, mixed> $values Raw values; unknown keys are dropped, missing keys get defaults.
	 */
	public function __construct( array $values = array() ) {
		$this->values = self::normalize( $values );
	}

	/**
	 * Brings raw values into their canonical types.
	 *
	 * @param array<string, mixed> $values Raw values.
	 *
	 * @return array<string, mixed>
	 */
	public static function normalize( array $values ): array {
		$values = array_merge( self::DEFAULTS, array_intersect_key( $values, self::DEFAULTS ) );

		foreach ( array( 'host', 'username', 'from_email', 'from_name' ) as $key ) {
			$values[ $key ] = trim( self::to_string( $values[ $key ] ) );
		}
		// PHPMailer needs IPv6 addresses in brackets; a bare one would be read as host:port.
		if ( false !== filter_var( $values['host'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$values['host'] = '[' . $values['host'] . ']';
		}
		// Line breaks in a sender name would allow header injection.
		$values['from_name']  = trim( (string) preg_replace( '/[\r\n\t]+/', ' ', $values['from_name'] ) );
		$values['password']   = self::to_string( $values['password'] );
		$values['port']       = is_numeric( $values['port'] ) ? (int) $values['port'] : 0;
		$values['auth']       = self::to_bool( $values['auth'] );
		$values['force_from'] = self::to_bool( $values['force_from'] );

		$encryption           = strtolower( trim( self::to_string( $values['encryption'] ) ) );
		$aliases              = array(
			''         => 'none',
			'off'      => 'none',
			'starttls' => 'tls',
			'smtps'    => 'ssl',
		);
		$values['encryption'] = $aliases[ $encryption ] ?? $encryption;

		return $values;
	}

	/**
	 * Checks the values and returns one error code per invalid field.
	 *
	 * An empty host is valid: it means "do not use SMTP", so the other fields are not checked.
	 *
	 * @return array<string, string> Field name => error code. Empty when the config can be saved.
	 */
	public function errors(): array {
		$errors = array();

		if ( '' !== $this->values['from_email'] && false === filter_var( $this->values['from_email'], FILTER_VALIDATE_EMAIL ) ) {
			$errors['from_email'] = 'from_email_invalid';
		}
		if ( '' === $this->values['host'] ) {
			return $errors;
		}
		if ( ! self::is_valid_host( $this->values['host'] ) ) {
			$errors['host'] = 'host_invalid';
		}
		if ( $this->values['port'] < 1 || $this->values['port'] > 65535 ) {
			$errors['port'] = 'port_invalid';
		}
		if ( ! in_array( $this->values['encryption'], self::ENCRYPTIONS, true ) ) {
			$errors['encryption'] = 'encryption_invalid';
		}
		if ( $this->values['auth'] ) {
			if ( '' === $this->values['username'] ) {
				$errors['username'] = 'username_required';
			}
			if ( '' === $this->values['password'] ) {
				$errors['password'] = 'password_required';
			}
		}

		return $errors;
	}

	/**
	 * Whether WordPress mail should be sent through SMTP.
	 */
	public function is_active(): bool {
		return self::STATUS_ACTIVE === $this->status();
	}

	/**
	 * Tells "not configured" apart from "configured, but unusable".
	 *
	 * @return string One of the STATUS_* constants.
	 */
	public function status(): string {
		if ( '' === $this->values['host'] ) {
			return self::STATUS_OFF;
		}

		return array() === $this->errors() ? self::STATUS_ACTIVE : self::STATUS_BROKEN;
	}

	/**
	 * Accepts exactly what PHPMailer accepts: a host name, an IPv4 address, or an IPv6 address in brackets.
	 *
	 * @param string $host Normalised host.
	 */
	private static function is_valid_host( string $host ): bool {
		if ( 1 === preg_match( '/^\[(.+)\]$/', $host, $match ) ) {
			return false !== filter_var( $match[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
		}
		// Digits and dots only: must be a real IPv4 address, not a host name.
		if ( 1 === preg_match( '/^[0-9.]+$/', $host ) ) {
			return false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		}

		return strlen( $host ) <= 253
			&& 1 === preg_match( '/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)*[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/', $host );
	}

	/**
	 * Returns one value.
	 *
	 * @param string $key Field name from DEFAULTS.
	 *
	 * @return mixed
	 */
	public function get( string $key ) {
		return $this->values[ $key ] ?? null;
	}

	/**
	 * Returns all values.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->values;
	}

	/**
	 * Casts scalars to string and everything else to an empty string.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function to_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Reads booleans the way they arrive from constants, forms and JSON.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function to_bool( $value ): bool {
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return (bool) $value;
	}
}
