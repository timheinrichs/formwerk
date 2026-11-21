<?php
/**
 * Field definitions of a form.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Forms;

/**
 * Brings the fields that come from the builder into a safe, canonical shape.
 *
 * The central guarantee: every field that collects a value has a name, and no two fields
 * share one. Mail templates and stored submissions refer to fields by that name.
 *
 * Pure logic: no WordPress functions.
 */
final class Schema {

	public const TYPES = array( 'text', 'email', 'tel', 'number', 'date', 'textarea', 'select', 'radio', 'checkboxes', 'consent', 'heading' );

	/**
	 * Widths a field can have: 100 %, 50 %, 33 % and 25 % of the form. Fields follow each other
	 * in rows; a field that no longer fits starts the next row.
	 */
	public const WIDTHS = array( 'full', 'half', 'third', 'quarter' );

	public const MAX_FIELDS = 100;
	public const MAX_STEPS  = 20;

	private const CHOICE_TYPES = array( 'select', 'radio', 'checkboxes' );
	private const NAME_LENGTH  = 40;

	private const TRANSLITERATION = array(
		'ä' => 'ae',
		'ö' => 'oe',
		'ü' => 'ue',
		'ß' => 'ss',
		'é' => 'e',
		'è' => 'e',
		'ê' => 'e',
		'á' => 'a',
		'à' => 'a',
		'â' => 'a',
		'ç' => 'c',
		'ñ' => 'n',
		'ó' => 'o',
		'ô' => 'o',
		'ú' => 'u',
		'í' => 'i',
	);

	/**
	 * Whether fields of this type collect a value.
	 *
	 * @param string $type Field type.
	 */
	public static function has_value( string $type ): bool {
		return 'heading' !== $type && in_array( $type, self::TYPES, true );
	}

	/**
	 * Whether fields of this type offer options to choose from.
	 *
	 * @param string $type Field type.
	 */
	public static function has_options( string $type ): bool {
		return in_array( $type, self::CHOICE_TYPES, true );
	}

	/**
	 * Normalises the steps of a form: each has a stable ID and an optional title.
	 *
	 * @param mixed $raw Steps as sent by the builder.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	public static function steps( $raw ): array {
		$steps = array();
		$ids   = array();
		foreach ( is_array( $raw ) ? array_values( $raw ) : array() as $index => $item ) {
			if ( ! is_array( $item ) || count( $steps ) >= self::MAX_STEPS ) {
				continue;
			}
			$id = (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( self::text( $item['id'] ?? '' ) ) );
			if ( '' === $id || isset( $ids[ $id ] ) ) {
				$id = 's' . ( $index + 1 ) . '_' . substr( md5( 'step' . $index . count( $ids ) ), 0, 6 );
			}
			$ids[ $id ] = true;
			$steps[]    = array(
				'id'    => $id,
				'title' => self::text( $item['title'] ?? '', 200 ),
			);
		}

		return $steps;
	}

	/**
	 * Normalises a list of fields.
	 *
	 * With steps, every field belongs to one of them (unknown or missing: the first). In a form
	 * shown in steps the fields are ordered step by step and a row never spans two steps; in a
	 * simple form the steps are only remembered, for when it is switched back.
	 *
	 * @param mixed                                        $raw      Fields as sent by the builder.
	 * @param array<int, array{id: string, title: string}> $steps    Normalised steps of the form.
	 * @param bool                                         $in_steps Whether the form is shown in steps.
	 *
	 * @return array{fields: array<int, array<string, mixed>>, errors: array<int, array{field: string, code: string}>}
	 */
	public static function normalize( $raw, array $steps = array(), bool $in_steps = true ): array {
		$in_steps   = $in_steps && array() !== $steps;
		$step_order = array_flip( array_column( $steps, 'id' ) );
		$first_step = (string) ( $steps[0]['id'] ?? '' );
		$fields     = array();
		$errors     = array();
		$names      = array();
		$ids        = array();
		$wanted     = array();

		foreach ( is_array( $raw ) ? array_values( $raw ) : array() as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( count( $fields ) >= self::MAX_FIELDS ) {
				$errors[] = array(
					'field' => '',
					'code'  => 'too_many_fields',
				);
				break;
			}
			$type = self::text( $item['type'] ?? '' );
			$id   = (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( self::text( $item['id'] ?? '' ) ) );
			if ( '' === $id || isset( $ids[ $id ] ) ) {
				$id = 'f' . ( $index + 1 ) . '_' . substr( md5( $type . $index . count( $ids ) ), 0, 6 );
			}
			$ids[ $id ] = true;

			if ( ! in_array( $type, self::TYPES, true ) ) {
				$errors[] = array(
					'field' => $id,
					'code'  => 'type_invalid',
				);
				continue;
			}

			$field = array(
				'id'           => $id,
				'type'         => $type,
				'label'        => self::text( $item['label'] ?? '', 200 ),
				'name'         => '',
				'required'     => self::has_value( $type ) && ! empty( $item['required'] ),
				'placeholder'  => self::text( $item['placeholder'] ?? '', 200 ),
				'help'         => self::text( $item['help'] ?? '', 500 ),
				'content'      => self::multiline( $item['content'] ?? '', 2000 ),
				'options'      => array(),
				'width'        => 'full',
				'step'         => is_string( $item['step'] ?? null ) && isset( $step_order[ $item['step'] ] ) ? $item['step'] : $first_step,
				'autocomplete' => (string) preg_replace( '/[^a-z0-9 -]/', '', strtolower( self::text( $item['autocomplete'] ?? '', 60 ) ) ),
			);

			if ( '' === $field['label'] && ( 'heading' !== $type || '' === $field['content'] ) ) {
				$errors[] = array(
					'field' => $id,
					'code'  => 'label_required',
				);
			}

			if ( self::has_options( $type ) ) {
				$field['options'] = self::options( $item['options'] ?? array() );
				if ( array() === $field['options'] ) {
					$errors[] = array(
						'field' => $id,
						'code'  => 'options_required',
					);
				}
			}

			// Forms from 0.3 to 0.5 stored "half" for both fields of a row; it stays half.
			$width          = $item['width'] ?? '';
			$field['width'] = is_string( $width ) && in_array( $width, self::WIDTHS, true ) ? $width : 'full';

			$fields[] = $field;
			$wanted[] = self::has_value( $type ) ? self::slug( self::text( $item['name'] ?? '' ) ) : '';
		}//end foreach

		if ( $in_steps ) {
			// Step by step, keeping the order within each step.
			$order = array_keys( $fields );
			usort(
				$order,
				static fn( int $a, int $b ): int => array( $step_order[ $fields[ $a ]['step'] ], $a ) <=> array( $step_order[ $fields[ $b ]['step'] ], $b )
			);
			$fields = array_map( static fn( int $i ): array => $fields[ $i ], $order );
			$wanted = array_map( static fn( int $i ): string => $wanted[ $i ], $order );
		}

		// Names the builder sends back belong to existing fields, and mail templates use them.
		// They are reserved first, so a new field can never take over the name of an old one.
		foreach ( $fields as $index => $field ) {
			if ( '' !== $wanted[ $index ] && ! isset( $names[ $wanted[ $index ] ] ) ) {
				$names[ $wanted[ $index ] ] = true;
				$fields[ $index ]['name']   = $wanted[ $index ];
			}
		}
		foreach ( $fields as $index => $field ) {
			if ( ! self::has_value( $field['type'] ) || '' !== $field['name'] ) {
				continue;
			}
			$base = '' !== $wanted[ $index ] ? $wanted[ $index ] : self::slug( $field['label'] );
			$base = '' !== $base ? $base : 'field';
			$name = $base;
			for ( $suffix = 2; isset( $names[ $name ] ); $suffix++ ) {
				$name = substr( $base, 0, self::NAME_LENGTH - strlen( '_' . $suffix ) ) . '_' . $suffix;
			}
			$names[ $name ]           = true;
			$fields[ $index ]['name'] = $name;
		}

		return array(
			'fields' => $fields,
			'errors' => $errors,
		);
	}

	/**
	 * Turns a label into a field name: lower case, a-z, 0-9 and underscores, starting with a letter.
	 *
	 * @param string $text Label or wanted name.
	 */
	public static function slug( string $text ): string {
		$text = strtr( mb_strtolower( $text ), self::TRANSLITERATION );
		$text = trim( (string) preg_replace( '/[^a-z0-9]+/', '_', $text ), '_' );
		if ( '' === $text ) {
			return '';
		}
		if ( 1 === preg_match( '/^[0-9]/', $text ) ) {
			$text = 'field_' . $text;
		}

		return rtrim( substr( $text, 0, self::NAME_LENGTH ), '_' );
	}

	/**
	 * Names of all fields that collect a value.
	 *
	 * @param array<int, array<string, mixed>> $fields Normalised fields.
	 *
	 * @return string[]
	 */
	public static function names( array $fields ): array {
		return array_values( array_filter( array_column( $fields, 'name' ), 'strlen' ) );
	}

	/**
	 * Cleans the options of a choice field: single-line, non-empty, unique.
	 *
	 * @param mixed $raw Options from the builder.
	 *
	 * @return string[]
	 */
	private static function options( $raw ): array {
		$options = array();
		foreach ( is_array( $raw ) ? $raw : array() as $option ) {
			$option = self::text( $option, 200 );
			if ( '' !== $option && ! in_array( $option, $options, true ) ) {
				$options[] = $option;
			}
			if ( count( $options ) >= 200 ) {
				break;
			}
		}

		return $options;
	}

	/**
	 * Single-line text of bounded length.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Maximum number of characters.
	 */
	private static function text( $value, int $length = 100 ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		return mb_substr( trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value ) ), 0, $length );
	}

	/**
	 * Multi-line text of bounded length.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Maximum number of characters.
	 */
	private static function multiline( $value, int $length ): string {
		$value = is_scalar( $value ) ? str_replace( array( "\r\n", "\r" ), "\n", (string) $value ) : '';

		return mb_substr( trim( (string) preg_replace( '/[\x00-\x09\x0B-\x1F\x7F]+/', ' ', $value ) ), 0, $length );
	}
}
