<?php
/**
 * Sends the notification mail of a submission.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Submissions;

use Formwerk\Forms\Placeholders;
use WP_Error;

/**
 * Builds the mail from the form's template and reports whether it went out.
 */
final class Notifier {

	/**
	 * Sends the mail to the form's recipients.
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
		$context = array(
			'form'  => (string) $form['title'],
			'site'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'page'  => $page,
			'date'  => (string) wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $time ),
			'yes'   => __( 'Yes', 'formwerk' ),
			'no'    => __( 'No', 'formwerk' ),
			'empty' => '–',
		);

		$recipients = array();
		// Fixed addresses only: nothing a visitor typed decides where this mail goes.
		foreach ( explode( ',', (string) $form['mail']['to'] ) as $recipient ) {
			$recipient = trim( $recipient );
			if ( is_email( $recipient ) ) {
				$recipients[] = $recipient;
			}
		}
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

		$error   = '';
		$capture = static function ( $wp_error ) use ( &$error ): void {
			if ( $wp_error instanceof WP_Error ) {
				$error = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $capture );
		try {
			$sent = (bool) wp_mail(
				$recipients,
				Placeholders::render( (string) $form['mail']['subject'], $fields, $values, $context, true ),
				Placeholders::render( (string) $form['mail']['body'], $fields, $values, $context ),
				$headers
			);
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
