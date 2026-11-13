<?php
/**
 * Stores forms.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Forms;

use Formwerk\Plugin;

/**
 * Forms live in a non-public post type; fields and mail settings are JSON in post meta.
 *
 * A form is only written when it is complete and consistent. That is where "cannot lose
 * data" is enforced: unique field names, a recipient, and no placeholder that cannot be filled.
 */
final class FormRepository {

	public const POST_TYPE = 'formwerk_form';

	private const META_FIELDS   = '_formwerk_fields';
	private const META_MAIL     = '_formwerk_mail';
	private const META_MESSAGES = '_formwerk_messages';
	private const META_CONFIRM  = '_formwerk_confirmation';
	private const META_SETTINGS = '_formwerk_settings';
	private const META_SPAM     = '_formwerk_spam_count';

	/**
	 * Registers the post type.
	 */
	public function register(): void {
		add_action(
			'init',
			static function (): void {
				register_post_type(
					self::POST_TYPE,
					array(
						'label'           => __( 'Forms', 'formwerk' ),
						'public'          => false,
						'show_ui'         => false,
						'show_in_rest'    => false,
						'rewrite'         => false,
						'query_var'       => false,
						'can_export'      => true,
						'supports'        => array( 'title' ),
						// Own capabilities, all mapped to the plugin's capability. Inherited page rights would let
						// editors list, change and delete forms through XML-RPC.
						'capability_type' => array( 'formwerk_form', 'formwerk_forms' ),
						'map_meta_cap'    => true,
						'capabilities'    => array_fill_keys(
							array( 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts', 'create_posts' ),
							Plugin::capability()
						),
					)
				);
			}
		);
	}

	/**
	 * Loads a form.
	 *
	 * @param int $id Form ID.
	 *
	 * @return array<string, mixed>|null Null when there is no such form.
	 */
	public function find( int $id ): ?array {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$fields = Schema::normalize( $this->meta( $id, self::META_FIELDS ) )['fields'];

		return array(
			'id'       => $id,
			'title'    => $post->post_title,
			'modified' => $post->post_modified_gmt,
			'fields'   => $fields,
			'mail'     => $this->mail( $this->meta( $id, self::META_MAIL ) ),
			'confirm'  => $this->confirmation( $this->meta( $id, self::META_CONFIRM ) ),
			'messages' => $this->messages( $this->meta( $id, self::META_MESSAGES ) ),
			'settings' => $this->settings( $this->meta( $id, self::META_SETTINGS ) ),
			'spam'     => (int) get_post_meta( $id, self::META_SPAM, true ),
		);
	}

	/**
	 * Lists all forms, newest first.
	 *
	 * @return array<int, array{id:int, title:string, modified:string, changed:string, fields:int, spam:int}>
	 */
	public function all(): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- A site has a handful of forms; the list is not paged.
				'posts_per_page' => 500,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$forms = array();
		foreach ( $posts as $post ) {
			$forms[] = array(
				'id'       => (int) $post->ID,
				'title'    => $post->post_title,
				'modified' => $post->post_modified_gmt,
				// Formatted here, so lists in the backend all show dates the same way.
				'changed'  => (string) wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), (int) strtotime( $post->post_modified_gmt . ' UTC' ) ),
				'fields'   => count( Schema::names( Schema::normalize( $this->meta( (int) $post->ID, self::META_FIELDS ) )['fields'] ) ),
				'spam'     => (int) get_post_meta( $post->ID, self::META_SPAM, true ),
			);
		}

		return $forms;
	}

	/**
	 * A new, unsaved form with safe defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function blank(): array {
		return array(
			'id'       => 0,
			'title'    => '',
			'modified' => '',
			'fields'   => array(),
			'mail'     => $this->mail( array() ),
			'confirm'  => $this->confirmation( array() ),
			'messages' => $this->messages( array() ),
			'settings' => $this->settings( array() ),
			'spam'     => 0,
		);
	}

	/**
	 * Creates or updates a form.
	 *
	 * @param int                  $id    Form ID, 0 to create.
	 * @param array<string, mixed> $input Form as sent by the builder.
	 *
	 * @return array{id: int, errors: array<int, array<string, mixed>>} ID 0 and the errors when nothing was saved.
	 */
	public function save( int $id, array $input ): array {
		$errors = array();
		$title  = isset( $input['title'] ) && is_string( $input['title'] ) ? self::line( $input['title'], 200 ) : '';
		if ( '' === $title ) {
			$errors[] = array(
				'path' => 'title',
				'code' => 'title_required',
			);
		}

		$schema = Schema::normalize( $input['fields'] ?? array() );
		foreach ( $schema['errors'] as $error ) {
			$errors[] = array(
				'path'  => 'fields',
				'field' => $error['field'],
				'code'  => $error['code'],
			);
		}
		$names = Schema::names( $schema['fields'] );
		if ( array() === $names ) {
			$errors[] = array(
				'path' => 'fields',
				'code' => 'fields_required',
			);
		}

		$mail     = $this->mail( $input['mail'] ?? array() );
		$messages = $this->messages( $input['messages'] ?? array() );
		$settings = $this->settings( $input['settings'] ?? array() );

		foreach ( array( 'to', 'subject', 'body' ) as $part ) {
			$check_placeholders = 'to' !== $part;
			if ( '' === trim( $mail[ $part ] ) ) {
				$errors[] = array(
					'path' => 'mail.' . $part,
					'code' => 'mail_' . $part . '_required',
				);
				continue;
			}
			$unknown = $check_placeholders ? Placeholders::unknown( $mail[ $part ], $names ) : array();
			if ( array() !== $unknown ) {
				$errors[] = array(
					'path'         => 'mail.' . $part,
					'code'         => 'placeholder_unknown',
					'placeholders' => $unknown,
				);
			}
		}
		$confirm = $this->confirmation( $input['confirm'] ?? array() );
		// The builder may point at a new email field by its ID, because new fields get their name only now.
		foreach ( $schema['fields'] as $field ) {
			if ( 'email' === $field['type'] && $field['id'] === $confirm['to_field'] ) {
				$confirm['to_field'] = $field['name'];
			}
		}
		$errors = array_merge( $errors, $this->confirmation_errors( $confirm, $schema['fields'] ) );

		if ( ! $this->recipients_are_valid( $mail['to'] ) ) {
			$errors[] = array(
				'path' => 'mail.to',
				'code' => str_contains( $mail['to'], '{' ) ? 'mail_to_placeholder' : 'mail_to_invalid',
			);
		}

		if ( array() !== $errors ) {
			return array(
				'id'     => 0,
				'errors' => $errors,
			);
		}

		$post = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $title,
		);
		if ( $id > 0 ) {
			if ( null === $this->find( $id ) ) {
				return array(
					'id'     => 0,
					'errors' => array(
						array(
							'path' => 'form',
							'code' => 'form_missing',
						),
					),
				);
			}
			$post['ID'] = $id;
			$saved      = wp_update_post( wp_slash( $post ), true );
		} else {
			$saved = wp_insert_post( wp_slash( $post ), true );
		}
		if ( is_wp_error( $saved ) || 0 === (int) $saved ) {
			return array(
				'id'     => 0,
				'errors' => array(
					array(
						'path' => 'form',
						'code' => 'not_saved',
					),
				),
			);
		}
		$saved = (int) $saved;

		$written = $this->store( $saved, self::META_FIELDS, $schema['fields'] )
			&& $this->store( $saved, self::META_MAIL, $mail )
			&& $this->store( $saved, self::META_CONFIRM, $confirm )
			&& $this->store( $saved, self::META_MESSAGES, $messages )
			&& $this->store( $saved, self::META_SETTINGS, $settings );
		if ( ! $written ) {
			if ( $id <= 0 ) {
				wp_delete_post( $saved, true );
			}

			return array(
				'id'     => 0,
				'errors' => array(
					array(
						'path' => 'form',
						'code' => 'not_saved',
					),
				),
			);
		}

		return array(
			'id'     => $saved,
			'errors' => array(),
		);
	}

	/**
	 * Deletes a form. Its submissions stay.
	 *
	 * @param int $id Form ID.
	 */
	public function delete( int $id ): bool {
		return null !== $this->find( $id ) && (bool) wp_delete_post( $id, true );
	}

	/**
	 * Counts a rejected spam attempt.
	 *
	 * @param int $id Form ID.
	 */
	public function count_spam( int $id ): void {
		update_post_meta( $id, self::META_SPAM, (int) get_post_meta( $id, self::META_SPAM, true ) + 1 );
	}

	/**
	 * Mail settings with defaults.
	 *
	 * @param mixed $raw Stored or submitted value.
	 *
	 * @return array{to:string, subject:string, body:string}
	 */
	private function mail( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();

		return array(
			// The mail is plain text: only control characters go, so text in angle brackets
			// such as "<{field:email}>" and percent signs survive saving.
			'to'      => isset( $raw['to'] ) && is_string( $raw['to'] ) ? self::line( $raw['to'], 1000 ) : (string) get_option( 'admin_email' ),
			/* translators: {form} is a placeholder and must stay as it is. */
			'subject' => isset( $raw['subject'] ) && is_string( $raw['subject'] ) ? self::line( $raw['subject'], 300 ) : __( 'New submission: {form}', 'formwerk' ),
			'body'    => isset( $raw['body'] ) && is_string( $raw['body'] )
				? self::lines( $raw['body'], 20000 )
				/* translators: The words in braces are placeholders and must stay as they are. */
				: __( "{all_fields}\n\n--\nSent with the form \"{form}\" on {page} ({date}).", 'formwerk' ),
		);
	}

	/**
	 * Confirmation to the person who sent the form, with defaults. Off until switched on.
	 *
	 * @param mixed $raw Stored or submitted value.
	 *
	 * @return array{enabled:bool, to_field:string, subject:string, body:string}
	 */
	private function confirmation( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();

		return array(
			'enabled'  => ! empty( $raw['enabled'] ),
			'to_field' => isset( $raw['to_field'] ) && is_string( $raw['to_field'] ) ? (string) preg_replace( '/[^a-z0-9_-]/', '', $raw['to_field'] ) : '',
			/* translators: {form} is a placeholder and must stay as it is. */
			'subject'  => isset( $raw['subject'] ) && is_string( $raw['subject'] ) ? self::line( $raw['subject'], 300 ) : __( 'Your message: {form}', 'formwerk' ),
			'body'     => isset( $raw['body'] ) && is_string( $raw['body'] )
				? self::lines( $raw['body'], 20000 )
				/* translators: The words in braces are placeholders and must stay as they are. */
				: __( "Thank you for your message. We have received it and will get back to you.\n\nYour entries:\n\n{all_fields}\n\n--\n{site}", 'formwerk' ),
		);
	}

	/**
	 * What blocks saving a confirmation that is switched on.
	 *
	 * @param array{enabled:bool, to_field:string, subject:string, body:string} $confirm Confirmation settings.
	 * @param array<int, array<string, mixed>>                                  $fields  Normalised fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function confirmation_errors( array $confirm, array $fields ): array {
		if ( ! $confirm['enabled'] ) {
			return array();
		}
		$errors       = array();
		$email_fields = array_column( array_filter( $fields, static fn( array $field ): bool => 'email' === $field['type'] ), 'name' );

		if ( array() === $email_fields ) {
			$errors[] = array(
				'path' => 'confirm.to_field',
				'code' => 'confirm_no_email_field',
			);
		} elseif ( ! in_array( $confirm['to_field'], $email_fields, true ) ) {
			$errors[] = array(
				'path' => 'confirm.to_field',
				'code' => 'confirm_to_required',
			);
		}
		foreach ( array( 'subject', 'body' ) as $part ) {
			if ( '' === trim( $confirm[ $part ] ) ) {
				$errors[] = array(
					'path' => 'confirm.' . $part,
					'code' => 'confirm_' . $part . '_required',
				);
				continue;
			}
			$unknown = Placeholders::unknown( $confirm[ $part ], Schema::names( $fields ) );
			if ( array() !== $unknown ) {
				$errors[] = array(
					'path'         => 'confirm.' . $part,
					'code'         => 'placeholder_unknown',
					'placeholders' => $unknown,
				);
			}
		}

		return $errors;
	}

	/**
	 * Texts shown to visitors, with defaults.
	 *
	 * @param mixed $raw Stored or submitted value.
	 *
	 * @return array{submit:string, success:string}
	 */
	private function messages( $raw ): array {
		$raw     = is_array( $raw ) ? $raw : array();
		$submit  = isset( $raw['submit'] ) && is_string( $raw['submit'] ) ? self::line( $raw['submit'], 100 ) : '';
		$success = isset( $raw['success'] ) && is_string( $raw['success'] ) ? self::lines( $raw['success'], 2000 ) : '';

		return array(
			'submit'  => '' !== $submit ? $submit : __( 'Send', 'formwerk' ),
			'success' => '' !== $success ? $success : __( 'Thank you. Your message has been sent.', 'formwerk' ),
		);
	}

	/**
	 * Further settings, with defaults.
	 *
	 * @param mixed $raw Stored or submitted value.
	 *
	 * @return array{max_links:int}
	 */
	private function settings( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();

		return array(
			'max_links' => max( 0, min( 50, (int) ( $raw['max_links'] ?? 0 ) ) ),
		);
	}

	/**
	 * Each recipient is a fixed email address.
	 *
	 * Placeholders are not allowed here: a notification that goes to an address typed into
	 * the form would let strangers send mail through the site.
	 *
	 * @param string $to Comma-separated recipients.
	 */
	private function recipients_are_valid( string $to ): bool {
		foreach ( explode( ',', $to ) as $recipient ) {
			if ( '' !== trim( $to ) && ! is_email( trim( $recipient ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * One line of plain text: control characters become spaces, length is bounded.
	 *
	 * @param string $text   Raw text.
	 * @param int    $length Maximum number of characters.
	 */
	private static function line( string $text, int $length ): string {
		return mb_substr( trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $text ) ), 0, $length );
	}

	/**
	 * Several lines of plain text: line breaks stay, other control characters become spaces.
	 *
	 * @param string $text   Raw text.
	 * @param int    $length Maximum number of characters.
	 */
	private static function lines( string $text, int $length ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

		return mb_substr( trim( (string) preg_replace( '/[\x00-\x09\x0B-\x1F\x7F]+/', ' ', $text ) ), 0, $length );
	}

	/**
	 * Reads JSON meta.
	 *
	 * @param int    $id  Form ID.
	 * @param string $key Meta key.
	 *
	 * @return mixed Decoded value, or an empty array.
	 */
	private function meta( int $id, string $key ) {
		$value = get_post_meta( $id, $key, true );
		$value = is_string( $value ) && '' !== $value ? json_decode( $value, true ) : array();

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Writes JSON meta.
	 *
	 * @param int    $id    Form ID.
	 * @param string $key   Meta key.
	 * @param mixed  $value Value to encode.
	 *
	 * @return bool Whether the value is stored as given.
	 */
	private function store( int $id, string $key, $value ): bool {
		$json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}
		update_post_meta( $id, $key, wp_slash( $json ) );

		// update_post_meta() also returns false when nothing changed, so read the value back instead.
		return get_post_meta( $id, $key, true ) === $json;
	}
}
