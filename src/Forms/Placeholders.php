<?php
/**
 * Placeholders in mail templates.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Forms;

/**
 * Finds and fills placeholders such as {all_fields} and {field:name}.
 *
 * A template with a placeholder that does not exist cannot be saved. That is what keeps
 * raw placeholders out of real emails.
 *
 * Pure logic: no WordPress functions.
 */
final class Placeholders {

	public const ALL_FIELDS = 'all_fields';
	public const FIELD      = 'field';

	/**
	 * Placeholders that do not depend on the fields. The values come with the render context.
	 */
	public const GENERAL = array( 'form', 'site', 'page', 'date' );

	private const PATTERN = '/\{([^{}\s]*)\}/';

	/**
	 * Lists the placeholders of a template that cannot be filled.
	 *
	 * @param string   $template Mail text, subject or address.
	 * @param string[] $names    Names of the form's fields.
	 *
	 * @return string[] The unknown placeholders as written, without duplicates.
	 */
	public static function unknown( string $template, array $names ): array {
		$unknown = array();
		if ( false === preg_match_all( self::PATTERN, $template, $matches, PREG_SET_ORDER ) ) {
			return $unknown;
		}
		foreach ( $matches as $match ) {
			if ( ! self::is_known( $match[1], $names ) && ! in_array( $match[0], $unknown, true ) ) {
				$unknown[] = $match[0];
			}
		}

		return $unknown;
	}

	/**
	 * Fills a template.
	 *
	 * @param string                           $template Mail text, subject or address.
	 * @param array<int, array<string, mixed>> $fields   Normalised fields of the form.
	 * @param array<string, mixed>             $values   Validated values, keyed by field name.
	 * @param array<string, string>            $context  Values for the general placeholders, plus 'yes', 'no' and 'empty'.
	 * @param bool                             $one_line True for subjects and addresses: the result has no line breaks.
	 */
	public static function render( string $template, array $fields, array $values, array $context = array(), bool $one_line = false ): string {
		$by_name = array();
		foreach ( $fields as $field ) {
			if ( '' !== $field['name'] ) {
				$by_name[ $field['name'] ] = $field;
			}
		}

		$result = (string) preg_replace_callback(
			self::PATTERN,
			static function ( array $found ) use ( $fields, $by_name, $values, $context, $one_line ): string {
				$token = $found[1];
				if ( self::ALL_FIELDS === $token ) {
					return self::all_fields( $fields, $values, $context, $one_line );
				}
				if ( in_array( $token, self::GENERAL, true ) ) {
					return (string) ( $context[ $token ] ?? '' );
				}
				$name = str_starts_with( $token, self::FIELD . ':' ) ? substr( $token, strlen( self::FIELD ) + 1 ) : '';
				if ( isset( $by_name[ $name ] ) ) {
					return self::format( $by_name[ $name ], $values[ $name ] ?? '', $context );
				}
				if ( '' !== $name ) {
					// A field the submission does not know: it was added to the form after the submission
					// arrived, and the email is sent again. There is no answer for it.
					return (string) ( $context['empty'] ?? '' );
				}

				// Unknown placeholders are refused when a form is saved. Should one get here anyway, it stays visible.
				return $found[0];
			},
			$template
		);

		return $one_line ? trim( (string) preg_replace( '/\s*[\r\n]+\s*/', ' ', $result ) ) : $result;
	}

	/**
	 * Turns a stored value into text.
	 *
	 * @param array<string, mixed>  $field   Field definition.
	 * @param mixed                 $value   Validated value.
	 * @param array<string, string> $context Words for yes, no and empty.
	 */
	public static function format( array $field, $value, array $context = array() ): string {
		if ( 'consent' === $field['type'] ) {
			return $value ? ( $context['yes'] ?? 'Yes' ) : ( $context['no'] ?? 'No' );
		}
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( $value, 'is_scalar' ) );
		}
		$value = is_scalar( $value ) ? (string) $value : '';

		return '' === $value ? ( $context['empty'] ?? '' ) : $value;
	}

	/**
	 * One "Label: value" block per field, in the order of the form.
	 *
	 * @param array<int, array<string, mixed>> $fields   Normalised fields.
	 * @param array<string, mixed>             $values   Validated values.
	 * @param array<string, string>            $context  Words for yes, no and empty.
	 * @param bool                             $one_line Whether the result must stay on one line.
	 */
	private static function all_fields( array $fields, array $values, array $context, bool $one_line ): string {
		$lines = array();
		foreach ( $fields as $field ) {
			if ( '' === $field['name'] ) {
				continue;
			}
			$text = self::format( $field, $values[ $field['name'] ] ?? '', $context );
			// A label that ends a sentence ("I agree.") would otherwise read "I agree.: Yes".
			$lines[] = rtrim( (string) $field['label'], " \t.:!?" ) . ':' . ( ! $one_line && str_contains( $text, "\n" ) ? "\n" : ' ' ) . $text;
		}

		return implode( $one_line ? '; ' : "\n\n", $lines );
	}

	/**
	 * Whether a placeholder can be filled.
	 *
	 * @param string   $token Text between the braces.
	 * @param string[] $names Names of the form's fields.
	 */
	private static function is_known( string $token, array $names ): bool {
		if ( self::ALL_FIELDS === $token || in_array( $token, self::GENERAL, true ) ) {
			return true;
		}

		return str_starts_with( $token, self::FIELD . ':' ) && in_array( substr( $token, strlen( self::FIELD ) + 1 ), $names, true );
	}
}
