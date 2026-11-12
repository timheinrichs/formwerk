<?php
/**
 * Texts for validation errors.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Submissions;

use Formwerk\Forms\Validator;

/**
 * Turns validation codes into sentences that say what to do.
 */
final class Messages {

	/**
	 * Message for a visitor.
	 *
	 * @param string               $code  Error code from the validator.
	 * @param array<string, mixed> $field Field the error belongs to.
	 */
	public static function for_error( string $code, array $field ): string {
		switch ( $code ) {
			case 'required':
				if ( 'consent' === $field['type'] ) {
					return __( 'Please tick this box to continue.', 'formwerk' );
				}
				if ( in_array( $field['type'], array( 'select', 'radio', 'checkboxes' ), true ) ) {
					return __( 'Please choose an option.', 'formwerk' );
				}
				return __( 'Please fill in this field.', 'formwerk' );
			case 'email_invalid':
				return __( 'Please enter a complete email address, for example name@example.com.', 'formwerk' );
			case 'tel_invalid':
				return __( 'Please enter a phone number using digits, for example 0221 123456.', 'formwerk' );
			case 'number_invalid':
				return __( 'Please enter a number using digits only.', 'formwerk' );
			case 'date_invalid':
				return __( 'Please enter a valid date.', 'formwerk' );
			case 'option_invalid':
				return __( 'Please choose one of the listed options.', 'formwerk' );
			case 'too_long':
				return sprintf(
					/* translators: %s: maximum number of characters. */
					__( 'This text is too long. Please shorten it to %s characters.', 'formwerk' ),
					number_format_i18n( 'textarea' === $field['type'] ? Validator::MAX_TEXTAREA : Validator::MAX_TEXT )
				);
			default:
				return __( 'Please check this field.', 'formwerk' );
		}//end switch
	}
}
