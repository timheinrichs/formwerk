<?php
/**
 * Validates a submission against the fields of its form.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Forms;

/**
 * Server-side validation. The browser's own checks are a convenience; this is the authority.
 *
 * Pure logic: no WordPress functions.
 */
final class Validator {

	public const MAX_TEXT     = 500;
	public const MAX_TEXTAREA = 10000;

	/**
	 * Cleans and checks the submitted values.
	 *
	 * Every value field appears in the result, also when it was left empty, so a stored
	 * submission always has the complete set of fields.
	 *
	 * @param array<int, array<string, mixed>> $fields Normalised fields of the form.
	 * @param array<string, mixed>             $input  Submitted values, keyed by field name.
	 *
	 * @return array{values: array<string, mixed>, errors: array<string, string>}
	 */
	public static function validate( array $fields, array $input ): array {
		$values = array();
		$errors = array();

		foreach ( $fields as $field ) {
			if ( ! Schema::has_value( (string) $field['type'] ) ) {
				continue;
			}
			$name = (string) $field['name'];
			$raw  = $input[ $name ] ?? null;

			list( $value, $error ) = self::check( $field, $raw );

			$empty = '' === $value || array() === $value || false === $value;
			if ( '' === $error && $empty && $field['required'] ) {
				$error = 'required';
			}
			$values[ $name ] = $value;
			if ( '' !== $error ) {
				$errors[ $name ] = $error;
			}
		}

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Cleans one value and names the first problem found.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $raw   Submitted value.
	 *
	 * @return array{0: mixed, 1: string} Clean value and error code ('' when valid).
	 */
	private static function check( array $field, $raw ): array {
		switch ( $field['type'] ) {
			case 'checkboxes':
				$chosen = array();
				foreach ( is_array( $raw ) ? $raw : array() as $option ) {
					$option = self::line( $option );
					if ( ! in_array( $option, $field['options'], true ) ) {
						return array( array(), 'option_invalid' );
					}
					if ( ! in_array( $option, $chosen, true ) ) {
						$chosen[] = $option;
					}
				}
				return array( $chosen, '' );

			case 'consent':
				return array( in_array( $raw, array( '1', 1, true, 'on' ), true ), '' );

			case 'select':
			case 'radio':
				$value = self::line( $raw );
				return array( $value, '' === $value || in_array( $value, $field['options'], true ) ? '' : 'option_invalid' );

			case 'textarea':
				$value = is_scalar( $raw ) ? str_replace( array( "\r\n", "\r" ), "\n", (string) $raw ) : '';
				$value = trim( (string) preg_replace( '/[\x00-\x09\x0B-\x1F\x7F]+/', ' ', $value ) );
				return array( mb_substr( $value, 0, self::MAX_TEXTAREA ), mb_strlen( $value ) > self::MAX_TEXTAREA ? 'too_long' : '' );
		}//end switch

		$value = self::line( $raw );
		if ( mb_strlen( $value ) > self::MAX_TEXT ) {
			return array( mb_substr( $value, 0, self::MAX_TEXT ), 'too_long' );
		}
		if ( '' === $value ) {
			return array( '', '' );
		}

		$error = '';
		switch ( $field['type'] ) {
			case 'email':
				if ( false === filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
					$error = 'email_invalid';
				}
				break;

			case 'tel':
				$digits = (string) preg_replace( '/[^0-9]/', '', $value );
				if ( 1 !== preg_match( '/^\+?[0-9()\/\s.-]{3,40}$/', $value ) || strlen( $digits ) < 3 ) {
					$error = 'tel_invalid';
				}
				break;

			case 'number':
				$value = str_replace( ',', '.', $value );
				if ( 1 !== preg_match( '/^-?[0-9]+(\.[0-9]+)?$/', $value ) ) {
					$error = 'number_invalid';
				}
				break;

			case 'date':
				if ( 1 !== preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $value, $part )
					|| ! checkdate( (int) $part[2], (int) $part[3], (int) $part[1] )
				) {
					$error = 'date_invalid';
				}
				break;
		}//end switch

		return array( $value, $error );
	}

	/**
	 * Single-line text without control characters.
	 *
	 * @param mixed $raw Submitted value.
	 */
	private static function line( $raw ): string {
		return is_scalar( $raw ) ? trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string) $raw ) ) : '';
	}
}
