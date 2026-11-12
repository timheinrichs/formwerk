<?php
/**
 * HTML of a form.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Frontend;

/**
 * Prints a form that works without JavaScript and is usable with keyboard and screen reader:
 * real labels, errors tied to their field, and a summary that links to each problem.
 */
final class Renderer {

	/**
	 * Renders a form.
	 *
	 * @param array<string, mixed> $form   Form from the repository.
	 * @param string               $dom_id ID of this copy of the form on the page.
	 * @param string               $token  Submission token.
	 * @param array<string, mixed> $state  Result of an earlier attempt without JavaScript: 'status', 'message', 'errors', 'values'.
	 * @param bool                 $styled Whether Formwerk's own look is switched on.
	 */
	public function render( array $form, string $dom_id, string $token, array $state = array(), bool $styled = false ): string {
		$id      = (int) $form['id'];
		$errors  = isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : array();
		$values  = isset( $state['values'] ) && is_array( $state['values'] ) ? $state['values'] : array();
		$classes = 'formwerk-form' . ( $styled ? ' formwerk-form--styled' : '' );

		if ( 'ok' === ( $state['status'] ?? '' ) ) {
			return sprintf(
				'<div class="%1$s formwerk-form--sent" id="%2$s"><p class="formwerk-form__success" role="status" tabindex="-1">%3$s</p></div>',
				esc_attr( $classes ),
				esc_attr( $dom_id ),
				self::e( (string) $state['message'] )
			);
		}

		$html = sprintf(
			'<form class="%1$s" id="%2$s" method="post" action="%3$s" novalidate data-formwerk-form="%4$d" data-formwerk-api="%5$s" data-formwerk-sending="%6$s">',
			esc_attr( $classes ),
			esc_attr( $dom_id ),
			esc_url( admin_url( 'admin-post.php' ) ),
			$id,
			esc_url( rest_url( 'formwerk/v1/forms/' . $id ) ),
			esc_attr__( 'Sending…', 'formwerk' )
		);

		$html .= $this->summary( $dom_id, (string) ( $state['message'] ?? '' ), $errors, $form['fields'] );

		$html .= '<input type="hidden" name="action" value="formwerk_submit">';
		$html .= sprintf( '<input type="hidden" name="formwerk_form" value="%d">', $id );
		$html .= sprintf( '<input type="hidden" name="formwerk_instance" value="%s">', esc_attr( $dom_id ) );
		$html .= sprintf( '<input type="hidden" name="formwerk_token" value="%s">', esc_attr( $token ) );
		$html .= sprintf( '<input type="hidden" name="formwerk_page" value="%s">', esc_url( $this->current_url() ) );
		// Filled by the script with a random ID, so a repeated attempt is not stored twice. Empty in the
		// HTML on purpose: a cached page would otherwise give every visitor the same ID.
		$html .= '<input type="hidden" name="formwerk_sid" value="">';
		// Honeypot: invisible to people, tempting for bots. A neutral name keeps browsers from filling it in.
		$html .= sprintf(
			'<div class="formwerk-form__hp" aria-hidden="true"><label for="%1$s-hp">%2$s</label><input type="text" id="%1$s-hp" name="formwerk_hp" value="" tabindex="-1" autocomplete="off"></div>',
			esc_attr( $dom_id ),
			esc_html__( 'Please leave this field empty', 'formwerk' )
		);

		$html .= '<div class="formwerk-form__fields">';
		foreach ( $form['fields'] as $field ) {
			$html .= $this->field( $dom_id, $field, $values[ $field['name'] ] ?? null, (string) ( $errors[ $field['name'] ] ?? '' ) );
		}
		$html .= '</div>';

		$html .= sprintf(
			'<div class="formwerk-form__actions"><button type="submit" class="formwerk-form__submit wp-element-button">%s</button></div>',
			self::e( (string) $form['messages']['submit'] )
		);

		return $html . '</form>';
	}

	/**
	 * Escapes text for HTML, including entities typed into the builder: "Tom &amp; Jerry" stays
	 * exactly that, so the option a visitor picks matches the stored option.
	 *
	 * @param string $text Text from the form definition or a submission.
	 */
	private static function e( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true );
	}

	/**
	 * Message box above the fields. Always present, so scripts and screen readers have one place to look.
	 *
	 * @param string                           $dom_id  ID of the form element.
	 * @param string                           $message Message of the last attempt.
	 * @param array<string, string>            $errors  Field name => message.
	 * @param array<int, array<string, mixed>> $fields  Fields of the form.
	 */
	private function summary( string $dom_id, string $message, array $errors, array $fields ): string {
		$html = sprintf(
			'<div class="formwerk-form__status" role="alert" tabindex="-1"%s>',
			'' === $message ? ' hidden' : ''
		);
		if ( '' !== $message ) {
			$html .= '<p>' . esc_html( $message ) . '</p>';
			if ( array() !== $errors ) {
				$html .= '<ul>';
				foreach ( $fields as $field ) {
					if ( isset( $errors[ $field['name'] ] ) ) {
						$html .= sprintf(
							'<li><a href="#%1$s">%2$s</a>: %3$s</li>',
							esc_attr( $this->control_id( $dom_id, $field ) ),
							esc_html( rtrim( (string) $field['label'], " \t.:!?" ) ),
							esc_html( $errors[ $field['name'] ] )
						);
					}
				}
				$html .= '</ul>';
			}
		}

		return $html . '</div>';
	}

	/**
	 * One field with label, help, control and error.
	 *
	 * @param string               $dom_id ID of the form element.
	 * @param array<string, mixed> $field  Field definition.
	 * @param mixed                $value  Value to show.
	 * @param string               $error  Error message, '' when valid.
	 */
	private function field( string $dom_id, array $field, $value, string $error ): string {
		$type    = (string) $field['type'];
		$classes = 'formwerk-field formwerk-field--' . $type . ' formwerk-field--' . $field['width'] . ( '' !== $error ? ' formwerk-field--error' : '' );

		if ( 'heading' === $type ) {
			return sprintf(
				'<div class="%1$s">%2$s%3$s</div>',
				esc_attr( $classes ),
				'' !== $field['label'] ? '<h3 class="formwerk-field__heading">' . self::e( (string) $field['label'] ) . '</h3>' : '',
				'' !== $field['content'] ? '<p class="formwerk-field__text">' . nl2br( self::e( (string) $field['content'] ) ) . '</p>' : ''
			);
		}

		$name     = 'fw[' . $field['name'] . ']';
		$control  = $this->control_id( $dom_id, $field );
		$base     = $dom_id . '-' . $field['name'];
		$help_id  = '' !== $field['help'] ? $base . '-help' : '';
		$error_id = $base . '-error';
		$required = $field['required'] ? ' <span class="formwerk-field__required">' . esc_html__( '(required)', 'formwerk' ) . '</span>' : '';
		$help     = '' !== $help_id ? sprintf( '<p class="formwerk-field__help" id="%s">%s</p>', esc_attr( $help_id ), self::e( (string) $field['help'] ) ) : '';
		$error_el = sprintf( '<p class="formwerk-field__error" id="%s"%s>%s</p>', esc_attr( $error_id ), '' === $error ? ' hidden' : '', esc_html( $error ) );
		$wrapper  = sprintf( ' data-formwerk-name="%s"', esc_attr( (string) $field['name'] ) );

		$describedby = trim( $help_id . ' ' . ( '' !== $error ? $error_id : '' ) );
		$described   = ( '' !== $describedby ? ' aria-describedby="' . esc_attr( $describedby ) . '"' : '' )
			. ( '' !== $help_id ? ' data-formwerk-help="' . esc_attr( $help_id ) . '"' : '' );
		$common      = ( $field['required'] ? ' required' : '' ) . ( '' !== $error ? ' aria-invalid="true"' : '' ) . $described;

		if ( in_array( $type, array( 'radio', 'checkboxes' ), true ) ) {
			$chosen  = is_array( $value ) ? $value : array( (string) $value );
			$options = '';
			foreach ( $field['options'] as $index => $option ) {
				// The first option takes the field's ID: links in the error summary land on it.
				$option_id = 0 === $index ? $control : $control . '-' . $index;
				$options  .= sprintf(
					'<div class="formwerk-field__option"><input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s> <label for="%2$s">%7$s</label></div>',
					'radio' === $type ? 'radio' : 'checkbox',
					esc_attr( $option_id ),
					esc_attr( 'radio' === $type ? $name : $name . '[]' ),
					self::e( $option ),
					checked( in_array( $option, $chosen, true ), true, false ),
					( 'radio' === $type && $field['required'] ? ' required' : '' )
						. ( 'checkboxes' === $type && '' !== $error ? ' aria-invalid="true"' : '' )
						. ( 0 === $index ? $described : '' ),
					self::e( $option )
				);
			}

			return sprintf(
				'<fieldset class="%1$s"%2$s%3$s%4$s><legend class="formwerk-field__label">%5$s%6$s</legend>%7$s%8$s%9$s</fieldset>',
				esc_attr( $classes ),
				$wrapper,
				'radio' === $type ? ' role="radiogroup"' . ( '' !== $error ? ' aria-invalid="true"' : '' ) : '',
				$described,
				self::e( (string) $field['label'] ),
				$required,
				$help,
				$options,
				$error_el
			);
		}//end if

		if ( 'consent' === $type ) {
			return sprintf(
				'<div class="%1$s"%2$s><div class="formwerk-field__option"><input type="checkbox" id="%3$s" name="%4$s" value="1"%5$s%6$s> <label for="%3$s">%7$s%8$s</label></div>%9$s%10$s</div>',
				esc_attr( $classes ),
				$wrapper,
				esc_attr( $control ),
				esc_attr( $name ),
				checked( true === $value || '1' === $value, true, false ),
				$common,
				self::e( (string) $field['label'] ),
				$required,
				$help,
				$error_el
			);
		}

		$attributes = sprintf( ' id="%s" name="%s"%s', esc_attr( $control ), esc_attr( $name ), $common );
		if ( '' !== $field['placeholder'] ) {
			$attributes .= ' placeholder="' . self::e( (string) $field['placeholder'] ) . '"';
		}
		if ( '' !== $field['autocomplete'] ) {
			$attributes .= ' autocomplete="' . esc_attr( (string) $field['autocomplete'] ) . '"';
		}
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( 'textarea' === $type ) {
			$input = sprintf( '<textarea%s rows="6">%s</textarea>', $attributes, self::e( $value ) );
		} elseif ( 'select' === $type ) {
			$input = sprintf( '<select%s><option value="">%s</option>', $attributes, esc_html__( 'Please choose', 'formwerk' ) );
			foreach ( $field['options'] as $option ) {
				$input .= sprintf( '<option value="%1$s"%2$s>%1$s</option>', self::e( $option ), selected( $value, $option, false ) );
			}
			$input .= '</select>';
		} else {
			$input_type = in_array( $type, array( 'email', 'tel', 'date' ), true ) ? $type : 'text';
			$input      = sprintf(
				'<input type="%s"%s value="%s"%s>',
				$input_type,
				$attributes,
				self::e( $value ),
				'number' === $type ? ' inputmode="decimal"' : ''
			);
		}

		return sprintf(
			'<div class="%1$s"%2$s><label class="formwerk-field__label" for="%3$s">%4$s%5$s</label>%6$s%7$s%8$s</div>',
			esc_attr( $classes ),
			$wrapper,
			esc_attr( $control ),
			self::e( (string) $field['label'] ),
			$required,
			$help,
			$input,
			$error_el
		);
	}

	/**
	 * ID of the element that receives focus for a field.
	 *
	 * @param string               $dom_id ID of the form element.
	 * @param array<string, mixed> $field  Field definition.
	 */
	private function control_id( string $dom_id, array $field ): string {
		return $dom_id . '-' . $field['name'] . '-input';
	}

	/**
	 * Address of the page being shown, without the marker of an earlier attempt.
	 */
	private function current_url(): string {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$home = wp_parse_url( home_url() );
		// REQUEST_URI already contains the folder of a WordPress in a subdirectory, so only the origin comes from home_url().
		$origin = ( $home['scheme'] ?? 'https' ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );

		return remove_query_arg( Controller::FLASH_PARAM, $origin . '/' . ltrim( $path, '/' ) );
	}
}
