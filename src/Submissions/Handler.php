<?php
/**
 * Processes a submitted form.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Submissions;

use Formwerk\Forms\FormRepository;
use Formwerk\Forms\Schema;
use Formwerk\Forms\SpamGuard;
use Formwerk\Forms\Validator;
use Formwerk\I18n\Wpml;

/**
 * The one path every submission takes, with or without JavaScript:
 * check the token, validate, check for spam, store, then send the mail.
 *
 * Nothing is rejected silently. A visitor always learns whether the message was taken,
 * and a stored submission never depends on the mail going out.
 */
final class Handler {

	public const OK      = 'ok';
	public const INVALID = 'invalid';
	public const BLOCKED = 'blocked';
	public const FAILED  = 'failed';

	private const LIMIT_ADDRESS = 10;
	private const LIMIT_FORM    = 60;
	private const LIMIT_TOKEN   = 3;
	private const RATE_WINDOW   = 600;

	/**
	 * Form storage.
	 *
	 * @var FormRepository
	 */
	private FormRepository $forms;

	/**
	 * Submission storage.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Mail sender.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Translations of the form texts.
	 *
	 * @var Wpml
	 */
	private Wpml $wpml;

	/**
	 * Counter for the limits.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $limiter;

	/**
	 * Constructor.
	 *
	 * @param FormRepository $forms    Form storage.
	 * @param Store          $store    Submission storage.
	 * @param Notifier       $notifier Mail sender.
	 * @param RateLimiter    $limiter  Counter for the limits.
	 * @param Wpml           $wpml     Translations of the form texts.
	 */
	public function __construct( FormRepository $forms, Store $store, Notifier $notifier, ?RateLimiter $limiter = null, ?Wpml $wpml = null ) {
		$this->forms    = $forms;
		$this->store    = $store;
		$this->notifier = $notifier;
		$this->limiter  = $limiter ?? new RateLimiter();
		$this->wpml     = $wpml ?? new Wpml();
	}

	/**
	 * Creates the token a form needs to be submitted.
	 *
	 * @param int  $form_id Form ID.
	 * @param bool $renewed Whether it replaces a rejected token of a form that was already filled in.
	 */
	public function token( int $form_id, bool $renewed = false ): string {
		return $this->guard()->issue( $form_id, time(), $renewed );
	}

	/**
	 * Handles one submission.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $request Unslashed request data: 'fw' (values), 'formwerk_token', 'formwerk_hp' (honeypot),
	 *                                      'formwerk_page', 'formwerk_sid' (random ID of this attempt, set by the script).
	 *
	 * @return array{status: string, http: int, message: string, errors: array<string, string>, values: array<string, mixed>, renew: bool}
	 */
	public function handle( int $form_id, array $request ): array {
		// Answers to the visitor (errors, success) and the confirmation use the language of the page.
		$language = Wpml::valid_language( $request['formwerk_lang'] ?? '' );

		return Wpml::in_language(
			$language,
			fn(): array => $this->process( $form_id, $request, $language )
		);
	}

	/**
	 * Handles one submission in the visitor's language.
	 *
	 * @param int                  $form_id  Form ID.
	 * @param array<string, mixed> $request  Unslashed request data.
	 * @param string               $language WPML language code of the page, or ''.
	 *
	 * @return array{status: string, http: int, message: string, errors: array<string, string>, values: array<string, mixed>, renew: bool}
	 */
	private function process( int $form_id, array $request, string $language ): array {
		$form = $this->forms->find( $form_id );
		if ( null === $form ) {
			return $this->result( self::FAILED, 404, __( 'This form no longer exists. Please reload the page.', 'formwerk' ) );
		}
		// What the visitor sees; $form itself stays in the default language for storing and the notification.
		$shown  = $this->wpml->translate( $form );
		$input  = isset( $request['fw'] ) && is_array( $request['fw'] ) ? $request['fw'] : array();
		$result = Validator::validate( $form['fields'], $input );
		$values = $result['values'];
		$token  = $request['formwerk_token'] ?? '';

		$check = $this->guard()->verify( $token, $form_id, time() );
		if ( SpamGuard::INVALID === $check || SpamGuard::EXPIRED === $check ) {
			return $this->result( self::BLOCKED, 400, __( 'The form was open for too long. Please send it again; your entries are still here.', 'formwerk' ), array(), $values, true );
		}

		// Mistakes are shown before anything else, so nobody has to send the form twice to see them.
		if ( array() !== $result['errors'] ) {
			$errors = array();
			foreach ( $form['fields'] as $field ) {
				if ( isset( $result['errors'][ $field['name'] ] ) ) {
					$errors[ $field['name'] ] = Messages::for_error( $result['errors'][ $field['name'] ], $field );
				}
			}

			return $this->result( self::INVALID, 422, __( 'The form was not sent. Please check the marked fields.', 'formwerk' ), $errors, $values );
		}

		if ( SpamGuard::TOO_FAST === $check ) {
			return $this->result( self::BLOCKED, 429, __( 'That was very quick. Please wait a few seconds and send the form again.', 'formwerk' ), array(), $values );
		}

		$spam = SpamGuard::inspect( $request['formwerk_hp'] ?? '', $values, (int) $form['settings']['max_links'] );
		if ( SpamGuard::LINKS === $spam ) {
			return $this->result( self::BLOCKED, 400, __( 'The form was not sent, because it contains too many links. Please remove some and send it again.', 'formwerk' ), array(), $values );
		}
		if ( SpamGuard::OK !== $spam ) {
			$this->forms->count_spam( $form_id );

			return $this->result( self::BLOCKED, 400, __( 'The form was not sent, because it looked like an automated message. Please reload the page and try again.', 'formwerk' ), array(), $values );
		}

		// The same attempt again (the script fell back to a normal page request, or a double click):
		// it is stored already, so confirm it instead of storing it twice.
		$attempt = $this->attempt_key( $form_id, $request['formwerk_sid'] ?? '' );
		if ( '' !== $attempt && false !== get_transient( $attempt ) ) {
			return $this->result( self::OK, 200, (string) $shown['messages']['success'] );
		}

		$buckets = $this->take_rate_limits( $form_id, (string) $token );
		if ( null === $buckets ) {
			return $this->result( self::BLOCKED, 429, __( 'Several messages were just sent from your network. Please wait about 10 minutes and send the form again; your entries stay here.', 'formwerk' ), array(), $values );
		}

		$page = isset( $request['formwerk_page'] ) && is_string( $request['formwerk_page'] ) ? esc_url_raw( $request['formwerk_page'] ) : '';
		$page = self::is_own_url( $page ) ? $page : '';

		$snapshot = array();
		foreach ( $form['fields'] as $field ) {
			if ( Schema::has_value( $field['type'] ) ) {
				$snapshot[] = array(
					'name'  => $field['name'],
					'label' => $field['label'],
					'type'  => $field['type'],
				);
			}
		}

		// Store first. From here on the submission cannot get lost.
		$id = $this->store->insert(
			$form_id,
			array(
				'form_title' => $form['title'],
				'fields'     => $snapshot,
				'values'     => $values,
				'page'       => $page,
			),
			(string) apply_filters( 'formwerk_submission_language', '' !== $language ? Wpml::locale_of( $language ) : determine_locale(), $form_id )
		);
		if ( 0 === $id ) {
			foreach ( $buckets as $bucket ) {
				$this->limiter->release( $bucket[0], $bucket[1] );
			}

			return $this->result( self::FAILED, 500, __( 'Your message could not be saved. Please try again later or contact us another way.', 'formwerk' ), array(), $values );
		}
		if ( '' !== $attempt ) {
			set_transient( $attempt, $id, HOUR_IN_SECONDS );
		}

		// The notification goes to the website, in its default language.
		$mail = Wpml::in_language(
			Wpml::default_language(),
			fn(): array => $this->notifier->send( $form, $snapshot, $values, $page )
		);
		$this->store->set_mail_status( $id, $mail['sent'] ? Store::MAIL_SENT : Store::MAIL_FAILED, $mail['error'] );

		$confirm = $this->notifier->confirm( $shown, self::relabel( $snapshot, $shown ), self::reword( $values, $shown ), $page );
		if ( Notifier::CONFIRM_OFF !== $confirm['status'] ) {
			$this->store->set_confirm_status( $id, $confirm['status'], $confirm['error'] );
		}

		/**
		 * Fires after a submission was stored and its mail was attempted.
		 *
		 * @param int                  $id      Submission ID.
		 * @param array<string, mixed> $form    Form.
		 * @param array<string, mixed> $values  Validated values.
		 * @param bool                 $sent    Whether the notification mail went out.
		 */
		do_action( 'formwerk_submission_stored', $id, $form, $values, $mail['sent'] );

		return $this->result( self::OK, 200, (string) $shown['messages']['success'] );
	}

	/**
	 * Sends an email of a stored submission again.
	 *
	 * @param int    $id   Submission ID.
	 * @param string $kind 'notification' (to the website) or 'confirmation' (to the person who sent the form).
	 *
	 * @return array{sent: bool, error: string}
	 */
	public function resend( int $id, string $kind = 'notification' ): array {
		$submission = $this->store->find( $id );
		$form       = $submission ? $this->forms->find( $submission['form_id'] ) : null;
		if ( null === $submission || null === $form ) {
			return array(
				'sent'  => false,
				'error' => __( 'The form of this submission no longer exists, so its email cannot be sent again.', 'formwerk' ),
			);
		}
		$received = strtotime( $submission['created_at'] . ' UTC' );
		$received = false === $received ? null : $received;

		if ( 'confirmation' === $kind ) {
			// Sent even if the confirmation was switched off since, because someone asked for it explicitly.
			$form['confirm']['enabled'] = true;
			// In the language the person used when sending the form.
			$result = Wpml::in_language(
				Wpml::code_of( $submission['lang'] ),
				function () use ( $form, $submission, $received ): array {
					$shown = $this->wpml->translate( $form );

					return $this->notifier->confirm( $shown, self::relabel( $submission['fields'], $shown ), self::reword( $submission['values'], $shown ), $submission['page'], $received );
				}
			);
			$this->store->set_confirm_status( $id, $result['status'], $result['error'] );

			return array(
				'sent'  => Store::MAIL_SENT === $result['status'],
				'error' => $result['error'],
			);
		}

		$mail = Wpml::in_language(
			Wpml::default_language(),
			fn(): array => $this->notifier->send( $form, $submission['fields'], $submission['values'], $submission['page'], $received )
		);
		$this->store->set_mail_status( $id, $mail['sent'] ? Store::MAIL_SENT : Store::MAIL_FAILED, $mail['error'] );

		return $mail;
	}

	/**
	 * A field snapshot with the labels of the translated form, for the confirmation.
	 *
	 * @param array<int, array<string, mixed>> $snapshot Stored field snapshot.
	 * @param array<string, mixed>             $shown    Translated form.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function relabel( array $snapshot, array $shown ): array {
		$labels = array_column( $shown['fields'], 'label', 'name' );
		foreach ( $snapshot as $index => $field ) {
			if ( isset( $labels[ $field['name'] ] ) && '' !== $labels[ $field['name'] ] ) {
				$snapshot[ $index ]['label'] = $labels[ $field['name'] ];
			}
		}

		return $snapshot;
	}

	/**
	 * Chosen options in the words of the translated form, for the confirmation.
	 *
	 * Stored values are always the original options; a person who chose "Basic course"
	 * on the English page reads that, not the German original, in their confirmation.
	 *
	 * @param array<string, mixed> $values Stored values.
	 * @param array<string, mixed> $shown  Translated form.
	 *
	 * @return array<string, mixed>
	 */
	private static function reword( array $values, array $shown ): array {
		foreach ( $shown['fields'] as $field ) {
			if ( empty( $field['option_labels'] ) || ! array_key_exists( $field['name'], $values ) ) {
				continue;
			}
			$labels = array_combine( $field['options'], $field['option_labels'] );
			$value  = $values[ $field['name'] ];
			if ( is_array( $value ) ) {
				$values[ $field['name'] ] = array_map( static fn( $option ) => is_string( $option ) ? ( $labels[ $option ] ?? $option ) : $option, $value );
			} elseif ( is_string( $value ) ) {
				$values[ $field['name'] ] = $labels[ $value ] ?? $value;
			}
		}

		return $values;
	}

	/**
	 * Builds the token checker. The key changes when the site salts change; open forms then ask to be sent again.
	 */
	private function guard(): SpamGuard {
		return new SpamGuard( wp_salt( 'nonce' ) );
	}

	/**
	 * Counts this submission against the limits per connection, per form and per token.
	 *
	 * Addresses are only kept as salted hashes, and only for the time window.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $token   Submission token.
	 *
	 * @return array<int, array{0: string, 1: int}>|null The counted buckets, or null when a limit is reached.
	 */
	private function take_rate_limits( int $form_id, string $token ): ?array {
		/**
		 * Filters the limits. Each entry: maximum number of stored submissions per window.
		 *
		 * @param array{address: int, form: int, token: int, window: int} $limits Limits.
		 * @param int                                                      $form_id Form ID.
		 */
		$limits = (array) apply_filters(
			'formwerk_rate_limits',
			array(
				'address' => self::LIMIT_ADDRESS,
				'form'    => self::LIMIT_FORM,
				'token'   => self::LIMIT_TOKEN,
				'window'  => self::RATE_WINDOW,
			),
			$form_id
		);
		$window = max( 60, (int) ( $limits['window'] ?? self::RATE_WINDOW ) );

		/**
		 * Filters the address a submission comes from. Sites behind a proxy can return the visitor's address here.
		 *
		 * @param string $address Address from REMOTE_ADDR.
		 */
		$address = (string) apply_filters( 'formwerk_client_address', isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );

		$checks = array(
			array( 'form|' . $form_id, $window, (int) ( $limits['form'] ?? self::LIMIT_FORM ) ),
			array( 'token|' . $token, SpamGuard::MAX_SECONDS, (int) ( $limits['token'] ?? self::LIMIT_TOKEN ) ),
		);
		if ( '' !== $address ) {
			array_unshift( $checks, array( 'address|' . hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), $window, (int) ( $limits['address'] ?? self::LIMIT_ADDRESS ) ) );
		}

		$taken = array();
		foreach ( $checks as $check ) {
			if ( $check[2] <= 0 ) {
				continue;
			}
			$taken[] = array( $check[0], $check[1] );
			if ( $this->limiter->hit( $check[0], $check[1] ) > $check[2] ) {
				foreach ( $taken as $bucket ) {
					$this->limiter->release( $bucket[0], $bucket[1] );
				}
				return null;
			}
		}

		return $taken;
	}

	/**
	 * Name of the marker that remembers a stored attempt.
	 *
	 * @param int   $form_id Form ID.
	 * @param mixed $sid     Random ID the script gave this attempt.
	 */
	private function attempt_key( int $form_id, $sid ): string {
		if ( ! is_string( $sid ) || 1 !== preg_match( '/^[a-zA-Z0-9-]{16,64}$/', $sid ) ) {
			return '';
		}

		return 'formwerk_sid_' . md5( $form_id . '|' . $sid );
	}

	/**
	 * Whether an address belongs to this site.
	 *
	 * @param string $url Address sent with the form.
	 */
	public static function is_own_url( string $url ): bool {
		return '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/**
	 * Result of handling a submission.
	 *
	 * @param string                $status  One of the class constants.
	 * @param int                   $http    HTTP status for the REST response.
	 * @param string                $message Text for the visitor.
	 * @param array<string, string> $errors  Field name => message.
	 * @param array<string, mixed>  $values  Cleaned values, to show the form again.
	 * @param bool                  $renew   Whether the form needs a fresh token.
	 *
	 * @return array{status: string, http: int, message: string, errors: array<string, string>, values: array<string, mixed>, renew: bool}
	 */
	private function result( string $status, int $http, string $message, array $errors = array(), array $values = array(), bool $renew = false ): array {
		return array(
			'status'  => $status,
			'http'    => $http,
			'message' => $message,
			'errors'  => $errors,
			'values'  => $values,
			'renew'   => $renew,
		);
	}
}
