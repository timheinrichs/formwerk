<?php
/**
 * Sends the emails of a submission.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Submissions;

use Formwerk\Forms\Placeholders;
use WP_Error;

/**
 * Builds the emails from the form's templates and reports whether they went out:
 * the notification to the website and the confirmation to the person who sent the form.
 */
final class Notifier {

	public const CONFIRM_OFF     = 'off';
	public const CONFIRM_SKIPPED = 'skipped';

	/**
	 * Sends the notification to the form's recipients.
	 *
	 * @param array<string, mixed>             $form   Form with its mail settings.
	 * @param array<int, array<string, mixed>> $fields Fields the values belong to.
	 * @param array<string, mixed>             $values Validated values.
	 * @param string                           $page   Address of the page the form was sent from.
	 * @param int|null                         $time   When the submission arrived; now when null.
	 *
	 * @return array{sent: bool, error: string}
	 */
	public function send( array $form, array $fields, array $values, string $page, ?int $time = null ): array {
		// Fixed addresses only: nothing a visitor typed decides where this mail goes.
		$recipients = self::recipients( $form );
		if ( array() === $recipients ) {
			return array(
				'sent'  => false,
				'error' => __( 'The form has no valid recipient address.', 'formwerk' ),
			);
		}

		$headers = array();
		foreach ( $fields as $field ) {
			$address = 'email' === $field['type'] ? ( $values[ $field['name'] ] ?? '' ) : '';
			// Same check as the validator, so an address that passed it always becomes the Reply-To.
			if ( is_string( $address ) && false !== filter_var( $address, FILTER_VALIDATE_EMAIL ) ) {
				$headers[] = 'Reply-To: ' . $address;
				break;
			}
		}

		$context = self::context( $form, $page, $time );

		return self::mail(
			$recipients,
			Placeholders::render( (string) $form['mail']['subject'], $fields, $values, $context, true ),
			Placeholders::render( (string) $form['mail']['body'], $fields, $values, $context ),
			$headers
		);
	}

	/**
	 * Sends the confirmation to the address the person entered, when the form asks for one.
	 *
	 * @param array<string, mixed>             $form   Form with its confirmation settings.
	 * @param array<int, array<string, mixed>> $fields Fields the values belong to.
	 * @param array<string, mixed>             $values Validated values.
	 * @param string                           $page   Address of the page the form was sent from.
	 * @param int|null                         $time   When the submission arrived; now when null.
	 *
	 * @return array{status: string, error: string} Status is one of Store::MAIL_SENT, Store::MAIL_FAILED, CONFIRM_OFF, CONFIRM_SKIPPED.
	 */
	public function confirm( array $form, array $fields, array $values, string $page, ?int $time = null ): array {
		$confirm = $form['confirm'] ?? array();
		if ( empty( $confirm['enabled'] ) ) {
			return array(
				'status' => self::CONFIRM_OFF,
				'error'  => '',
			);
		}

		$address = self::confirm_address( $form, $values );
		if ( '' === $address ) {
			// An optional email field left empty: nothing to send, and nothing went wrong.
			return array(
				'status' => self::CONFIRM_SKIPPED,
				'error'  => __( 'No email address was entered.', 'formwerk' ),
			);
		}

		$headers = array();
		$reply   = self::recipients( $form );
		if ( array() !== $reply ) {
			// Answers to the confirmation reach the people who receive the submissions.
			$headers[] = 'Reply-To: ' . $reply[0];
		}

		$context = self::context( $form, $page, $time );
		$result  = self::mail(
			array( $address ),
			Placeholders::render( (string) $confirm['subject'], $fields, $values, $context, true ),
			Placeholders::render( (string) $confirm['body'], $fields, $values, $context ),
			$headers
		);

		return array(
			'status' => $result['sent'] ? Store::MAIL_SENT : Store::MAIL_FAILED,
			'error'  => $result['error'],
		);
	}

	/**
	 * The address the confirmation goes to: the value of the chosen email field, if it is a valid address.
	 *
	 * @param array<string, mixed> $form   Form.
	 * @param array<string, mixed> $values Values.
	 */
	public static function confirm_address( array $form, array $values ): string {
		$address = $values[ (string) ( $form['confirm']['to_field'] ?? '' ) ] ?? '';

		return is_string( $address ) && false !== filter_var( $address, FILTER_VALIDATE_EMAIL ) ? $address : '';
	}

	/**
	 * Values for the general placeholders.
	 *
	 * @param array<string, mixed> $form Form.
	 * @param string               $page Address of the page the form was sent from.
	 * @param int|null             $time When the submission arrived; now when null.
	 *
	 * @return array<string, string>
	 */
	private static function context( array $form, string $page, ?int $time ): array {
		return array(
			'form'  => (string) $form['title'],
			'site'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'page'  => $page,
			'date'  => (string) wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $time ),
			'yes'   => __( 'Yes', 'formwerk' ),
			'no'    => __( 'No', 'formwerk' ),
			'empty' => '–',
		);
	}

	/**
	 * The fixed recipient addresses of the notification.
	 *
	 * @param array<string, mixed> $form Form.
	 *
	 * @return string[]
	 */
	private static function recipients( array $form ): array {
		$recipients = array();
		foreach ( explode( ',', (string) ( $form['mail']['to'] ?? '' ) ) as $recipient ) {
			$recipient = trim( $recipient );
			if ( is_email( $recipient ) ) {
				$recipients[] = $recipient;
			}
		}

		return $recipients;
	}

	/**
	 * Sends one email and catches everything that can go wrong on the way.
	 *
	 * @param string[] $to      Recipients.
	 * @param string   $subject Subject.
	 * @param string   $body    Plain text.
	 * @param string[] $headers Extra headers.
	 *
	 * @return array{sent: bool, error: string}
	 */
	private static function mail( array $to, string $subject, string $body, array $headers ): array {
		// Placeholders put raw entries into the text; as plain text no mail program reads them as HTML.
		$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		$error     = '';
		$capture   = static function ( $wp_error ) use ( &$error ): void {
			if ( $wp_error instanceof WP_Error ) {
				$error = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $capture );
		try {
			$sent = (bool) wp_mail( $to, $subject, $body, $headers );
		} catch ( \Throwable $exception ) {
			// Another plugin's mail hook failed. The submission is stored; record the failure instead of breaking the request.
			$sent  = false;
			$error = sprintf(
				/* translators: %s: error message. */
				__( 'Sending stopped with an error: %s', 'formwerk' ),
				$exception->getMessage()
			);
		} finally {
			remove_action( 'wp_mail_failed', $capture );
		}
		if ( ! $sent && '' === $error ) {
			$error = __( 'The mail system gave no reason.', 'formwerk' );
		}

		return array(
			'sent'  => $sent,
			'error' => $sent ? '' : $error,
		);
	}
}
