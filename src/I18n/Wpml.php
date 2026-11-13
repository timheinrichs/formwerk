<?php
/**
 * WPML support.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\I18n;

use Formwerk\Admin\FormsPage;
use Formwerk\Forms\FormRepository;

/**
 * Makes the texts of each form translatable with WPML String Translation and uses the
 * translations on the website and in the confirmation email.
 *
 * Every form is one string package, "Formwerk – <form name>". Option values stay in the
 * default language: only their labels are translated, so submissions, validation and the
 * notification to the website always use the original options.
 *
 * Without WPML (or without String Translation) every method leaves things as they are.
 */
final class Wpml {

	public const KIND = 'Formwerk';

	/**
	 * Remembers that the forms that existed before WPML came were registered.
	 */
	public const SYNCED_OPTION = 'formwerk_wpml_synced';

	/**
	 * Whether WPML with String Translation is running, so form texts can be translated.
	 */
	public static function active(): bool {
		return self::languages_active() && has_filter( 'wpml_translate_string' ) && has_action( 'wpml_register_string' );
	}

	/**
	 * Whether WPML itself is running. Languages can be switched (for Formwerk's own texts) even
	 * without String Translation.
	 */
	public static function languages_active(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'formwerk_form_saved', array( $this, 'register_strings' ) );
		add_action( 'formwerk_form_deleted', array( $this, 'delete_strings' ) );
		add_action( 'admin_init', array( $this, 'register_existing' ) );
	}

	/**
	 * Registers the texts of all forms once, when String Translation is running for the first time.
	 * Forms created before WPML was installed would otherwise only show up after their next save.
	 */
	public function register_existing(): void {
		if ( ! self::active() || get_option( self::SYNCED_OPTION ) ) {
			return;
		}
		update_option( self::SYNCED_OPTION, 1, false );
		$forms = new FormRepository();
		foreach ( $forms->all() as $summary ) {
			$form = $forms->find( (int) $summary['id'] );
			if ( null !== $form ) {
				$this->register_strings( $form );
			}
		}
	}

	/**
	 * Registers the texts of a form as one string package, and removes texts it no longer has.
	 *
	 * @param array<string, mixed> $form Saved form.
	 */
	public function register_strings( $form ): void {
		if ( ! self::active() || ! is_array( $form ) ) {
			return;
		}
		$package = self::package( $form );
		do_action( 'wpml_start_string_package_registration', $package );
		foreach ( self::strings( $form ) as $name => $string ) {
			do_action( 'wpml_register_string', $string['value'], $name, $package, $string['title'], $string['type'] );
		}
		do_action( 'wpml_delete_unused_package_strings', $package );
	}

	/**
	 * Removes the string package of a deleted form.
	 *
	 * @param int $form_id Form ID.
	 */
	public function delete_strings( $form_id ): void {
		if ( self::active() ) {
			do_action( 'wpml_delete_package', (string) (int) $form_id, self::KIND );
		}
	}

	/**
	 * The form with its texts in the current language.
	 *
	 * Option labels go into 'option_labels'; the options themselves stay as they are.
	 *
	 * @param array<string, mixed> $form Form from the repository.
	 *
	 * @return array<string, mixed>
	 */
	public function translate( array $form ): array {
		if ( ! self::active() ) {
			return $form;
		}
		$package = self::package( $form );
		$t       = static function ( string $value, string $name ) use ( $package ): string {
			if ( '' === $value ) {
				return $value;
			}
			$translated = apply_filters( 'wpml_translate_string', $value, $name, $package );

			return is_string( $translated ) && '' !== $translated ? $translated : $value;
		};

		foreach ( $form['fields'] as $index => $field ) {
			$key = 'field-' . $field['id'];
			foreach ( array( 'label', 'help', 'placeholder', 'content' ) as $part ) {
				$form['fields'][ $index ][ $part ] = $t( (string) $field[ $part ], $key . '-' . $part );
			}
			$labels = array();
			foreach ( $field['options'] as $option ) {
				$labels[] = $t( (string) $option, self::option_name( $field, (string) $option ) );
			}
			$form['fields'][ $index ]['option_labels'] = $labels;
		}
		$form['title']               = $t( (string) $form['title'], 'form-title' );
		$form['messages']['submit']  = $t( (string) $form['messages']['submit'], 'message-submit' );
		$form['messages']['success'] = $t( (string) $form['messages']['success'], 'message-success' );
		if ( isset( $form['confirm'] ) ) {
			$form['confirm']['subject'] = $t( (string) $form['confirm']['subject'], 'confirm-subject' );
			$form['confirm']['body']    = $t( (string) $form['confirm']['body'], 'confirm-body' );
		}

		return $form;
	}

	/**
	 * Language of the page being shown, as a WPML language code; '' without WPML.
	 */
	public static function current_language(): string {
		if ( ! self::languages_active() ) {
			return '';
		}
		$code = apply_filters( 'wpml_current_language', null );

		return is_string( $code ) ? $code : '';
	}

	/**
	 * A language code sent with a form, if it is one of the site's languages.
	 *
	 * @param mixed $code Submitted code.
	 */
	public static function valid_language( $code ): string {
		if ( ! self::languages_active() || ! is_string( $code ) || '' === $code ) {
			return '';
		}
		$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

		return is_array( $languages ) && isset( $languages[ $code ] ) ? $code : '';
	}

	/**
	 * WordPress locale of a WPML language code, e.g. "en_US" for "en".
	 *
	 * @param string $code Language code.
	 */
	public static function locale_of( string $code ): string {
		$languages = self::languages_active() ? apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) : null;
		$locale    = is_array( $languages ) ? ( $languages[ $code ]['default_locale'] ?? '' ) : '';

		return is_string( $locale ) ? $locale : '';
	}

	/**
	 * WPML language code of a WordPress locale; '' when no active language uses it.
	 *
	 * @param string $locale Locale, e.g. "de_DE".
	 */
	public static function code_of( string $locale ): string {
		$languages = self::languages_active() ? apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) : null;
		foreach ( is_array( $languages ) ? $languages : array() as $code => $language ) {
			if ( ( $language['default_locale'] ?? '' ) === $locale ) {
				return (string) $code;
			}
		}

		return '';
	}

	/**
	 * The site's default language code; '' without WPML.
	 */
	public static function default_language(): string {
		$code = self::languages_active() ? apply_filters( 'wpml_default_language', null ) : null;

		return is_string( $code ) ? $code : '';
	}

	/**
	 * Runs code in a language: WPML strings and Formwerk's own texts both follow it.
	 *
	 * @param string   $code     Language code; '' runs the code unchanged.
	 * @param callable $callback Code to run.
	 *
	 * @return mixed What the callback returns.
	 */
	public static function in_language( string $code, callable $callback ) {
		if ( '' === $code || ! self::languages_active() ) {
			return $callback();
		}
		$previous = self::current_language();
		$locale   = self::locale_of( $code );
		do_action( 'wpml_switch_language', $code );
		$switched = '' !== $locale && switch_to_locale( $locale );
		try {
			return $callback();
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
			do_action( 'wpml_switch_language', '' !== $previous ? $previous : null );
		}
	}

	/**
	 * The translatable texts of a form, by string name.
	 *
	 * Names use the field's ID, so a text keeps its translation when fields are moved or renamed.
	 *
	 * @param array<string, mixed> $form Form.
	 *
	 * @return array<string, array{value: string, title: string, type: string}>
	 */
	public static function strings( array $form ): array {
		$strings = array();
		$add     = static function ( string $name, $value, string $title, string $type = 'LINE' ) use ( &$strings ): void {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$strings[ $name ] = array(
					'value' => $value,
					'title' => $title,
					'type'  => $type,
				);
			}
		};

		foreach ( $form['fields'] as $field ) {
			$name = (string) $field['label'];
			$key  = 'field-' . $field['id'];
			/* translators: %s: field label. */
			$add( $key . '-label', $field['label'], sprintf( __( 'Field “%s”: label', 'formwerk' ), $name ) );
			/* translators: %s: field label. */
			$add( $key . '-help', $field['help'], sprintf( __( 'Field “%s”: help text', 'formwerk' ), $name ) );
			/* translators: %s: field label. */
			$add( $key . '-placeholder', $field['placeholder'], sprintf( __( 'Field “%s”: example', 'formwerk' ), $name ) );
			/* translators: %s: field label. */
			$add( $key . '-content', $field['content'], sprintf( __( 'Field “%s”: text', 'formwerk' ), $name ), 'AREA' );
			foreach ( $field['options'] as $option ) {
				/* translators: %s: field label. */
				$add( self::option_name( $field, (string) $option ), $option, sprintf( __( 'Field “%s”: option', 'formwerk' ), $name ) );
			}
		}
		$add( 'form-title', $form['title'] ?? '', __( 'Form name (in emails as {form})', 'formwerk' ) );
		$add( 'message-submit', $form['messages']['submit'] ?? '', __( 'Text on the send button', 'formwerk' ) );
		$add( 'message-success', $form['messages']['success'] ?? '', __( 'Message after sending', 'formwerk' ), 'AREA' );
		if ( ! empty( $form['confirm']['enabled'] ) ) {
			$add( 'confirm-subject', $form['confirm']['subject'], __( 'Confirmation: subject', 'formwerk' ) );
			$add( 'confirm-body', $form['confirm']['body'], __( 'Confirmation: text', 'formwerk' ), 'AREA' );
		}

		return $strings;
	}

	/**
	 * The package that holds the texts of a form.
	 *
	 * @param array<string, mixed> $form Form.
	 *
	 * @return array{kind: string, name: string, title: string, edit_link: string}
	 */
	private static function package( array $form ): array {
		return array(
			'kind'      => self::KIND,
			'name'      => (string) (int) $form['id'],
			/* translators: %s: form name. */
			'title'     => sprintf( __( 'Formwerk – %s', 'formwerk' ), (string) $form['title'] ),
			'edit_link' => admin_url( 'admin.php?page=' . FormsPage::SLUG . '&form=' . (int) $form['id'] ),
		);
	}

	/**
	 * String name of an option: based on its text, so the translation follows the option when the order changes.
	 *
	 * @param array<string, mixed> $field  Field.
	 * @param string               $option Option text.
	 */
	private static function option_name( array $field, string $option ): string {
		return 'field-' . $field['id'] . '-option-' . substr( md5( $option ), 0, 10 );
	}
}
