<?php
/**
 * Turns submissions into CSV.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Submissions;

use Formwerk\Forms\Placeholders;
use Formwerk\Forms\Schema;

/**
 * Standard CSV (RFC 4180): comma, double quotes, CRLF, UTF-8 without BOM.
 *
 * Pure logic: no WordPress functions, so it is covered by unit tests.
 */
final class CsvExport {

	/**
	 * Field columns for a set of submissions.
	 *
	 * The form's current fields come first, in their order. Fields that only older submissions
	 * have (removed or renamed since) follow, so no answer is left out of the file.
	 *
	 * @param array<int, array<string, mixed>> $form_fields Current fields of the form.
	 * @param array<int, array<string, mixed>> $submissions Submissions with their field snapshots.
	 *
	 * @return array<int, array{name: string, label: string, type: string}>
	 */
	public static function field_columns( array $form_fields, array $submissions ): array {
		$columns = array();
		foreach ( $form_fields as $field ) {
			if ( Schema::has_value( (string) $field['type'] ) && '' !== $field['name'] ) {
				$columns[ $field['name'] ] = array(
					'name'  => (string) $field['name'],
					'label' => (string) $field['label'],
					'type'  => (string) $field['type'],
				);
			}
		}
		foreach ( $submissions as $submission ) {
			foreach ( $submission['fields'] as $field ) {
				if ( isset( $field['name'] ) && ! isset( $columns[ $field['name'] ] ) ) {
					$columns[ $field['name'] ] = array(
						'name'  => (string) $field['name'],
						'label' => (string) ( $field['label'] ?? $field['name'] ),
						'type'  => (string) ( $field['type'] ?? 'text' ),
					);
				}
			}
		}

		return array_values( $columns );
	}

	/**
	 * The answers of one submission, in the order of the columns.
	 *
	 * @param array<string, mixed>                                         $submission Submission.
	 * @param array<int, array{name: string, label: string, type: string}> $columns    Field columns.
	 * @param array<string, string>                                        $words      'yes' and 'no'.
	 *
	 * @return string[]
	 */
	public static function answers( array $submission, array $columns, array $words ): array {
		$cells = array();
		foreach ( $columns as $column ) {
			// A field the submission does not have (added later) stays empty, also for consent fields.
			if ( ! array_key_exists( $column['name'], $submission['values'] ) ) {
				$cells[] = '';
				continue;
			}
			$value   = $submission['values'][ $column['name'] ];
			$cells[] = '' === $value || array() === $value ? '' : Placeholders::format( $column, $value, $words );
		}

		return $cells;
	}

	/**
	 * One CSV line.
	 *
	 * @param array<int, mixed> $cells Cell values.
	 */
	public static function line( array $cells ): string {
		return implode( ',', array_map( array( self::class, 'cell' ), $cells ) ) . "\r\n";
	}

	/**
	 * One cell: protected against formulas, quoted when needed.
	 *
	 * Spreadsheet programs run cells that start with = + - @ as formulas, so such values get a
	 * leading apostrophe. Visitors type these values; a submission must not run code on the
	 * computer of the person who opens the export.
	 *
	 * @param mixed $value Cell value.
	 */
	public static function cell( $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( '' !== $value && 1 === preg_match( '/^[=+\-@\t\r]/', $value ) ) {
			$value = "'" . $value;
		}
		// Semicolons and tabs too: spreadsheet programs that guess the separator then still read one cell.
		if ( 1 === preg_match( '/[",;\t\r\n]|^\s|\s$/', $value ) ) {
			$value = '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}
}
