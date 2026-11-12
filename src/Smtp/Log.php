<?php
/**
 * Mail log.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Smtp;

/**
 * Keeps the most recent mail failures and test results so problems are visible in the backend.
 *
 * Entries hold recipient addresses, so they are limited in number and age and can be erased per person.
 */
final class Log {

	public const OPTION      = 'formwerk_mail_log';
	public const MAX_ENTRIES = 50;
	public const MAX_AGE     = 30 * 86400;

	public const STATUS_FAILED = 'failed';
	public const STATUS_SENT   = 'sent';

	private const MAX_RECIPIENTS = 10;

	/**
	 * Adds an entry and drops the ones beyond the limits.
	 *
	 * @param string   $status     One of the STATUS_* constants.
	 * @param string[] $recipients Recipients as passed to wp_mail(), with or without a display name.
	 * @param string   $subject    Mail subject.
	 * @param string   $message    Error message or note.
	 */
	public function add( string $status, array $recipients, string $subject, string $message ): void {
		$entries = $this->all();

		array_unshift(
			$entries,
			array(
				'time'    => time(),
				'status'  => self::STATUS_SENT === $status ? self::STATUS_SENT : self::STATUS_FAILED,
				'to'      => self::addresses( $recipients ),
				'subject' => self::clean_text( $subject, 200 ),
				'message' => self::clean_text( $message, 500 ),
			)
		);

		update_option( self::OPTION, array_slice( $entries, 0, self::MAX_ENTRIES ), false );
	}

	/**
	 * Returns the entries that are not expired, newest first.
	 *
	 * @return array<int, array{time:int, status:string, to:string[], subject:string, message:string}>
	 */
	public function all(): array {
		$entries = get_option( self::OPTION, array() );
		if ( ! is_array( $entries ) ) {
			return array();
		}
		$oldest = time() - self::MAX_AGE;

		return array_values(
			array_filter(
				$entries,
				static function ( $entry ) use ( $oldest ): bool {
					return is_array( $entry ) && isset( $entry['time'], $entry['to'] ) && $entry['time'] >= $oldest;
				}
			)
		);
	}

	/**
	 * Removes all entries.
	 */
	public function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Removes the entries that were addressed to one person.
	 *
	 * @param string $email Address of the person.
	 *
	 * @return int Number of removed entries.
	 */
	public function erase_recipient( string $email ): int {
		$email   = strtolower( trim( $email ) );
		$entries = $this->all();
		$kept    = array_values(
			array_filter(
				$entries,
				static function ( array $entry ) use ( $email ): bool {
					return ! in_array( $email, array_map( 'strtolower', (array) $entry['to'] ), true );
				}
			)
		);
		$removed = count( $entries ) - count( $kept );
		if ( $removed > 0 ) {
			update_option( self::OPTION, $kept, false );
		}

		return $removed;
	}

	/**
	 * Hooks the log into the WordPress tool "Erase Personal Data".
	 */
	public function register(): void {
		add_filter(
			'wp_privacy_personal_data_erasers',
			function ( $erasers ) {
				$erasers['formwerk-mail-log'] = array(
					'eraser_friendly_name' => __( 'Formwerk email log', 'formwerk' ),
					'callback'             => function ( $email ) {
						return array(
							'items_removed'  => $this->erase_recipient( (string) $email ) > 0,
							'items_retained' => false,
							'messages'       => array(),
							'done'           => true,
						);
					},
				);

				return $erasers;
			}
		);
	}

	/**
	 * Pulls the plain addresses out of "Name <address>" recipients.
	 *
	 * @param array<int, mixed> $recipients Raw recipients.
	 *
	 * @return string[]
	 */
	public static function addresses( array $recipients ): array {
		$addresses = array();
		foreach ( array_slice( $recipients, 0, self::MAX_RECIPIENTS ) as $recipient ) {
			if ( ! is_string( $recipient ) ) {
				continue;
			}
			if ( 1 === preg_match( '/<([^<>]+)>\s*$/', $recipient, $match ) ) {
				$recipient = $match[1];
			}
			$recipient = trim( $recipient );
			if ( false !== filter_var( $recipient, FILTER_VALIDATE_EMAIL ) ) {
				$addresses[] = $recipient;
			}
		}

		return $addresses;
	}

	/**
	 * Makes a text safe to store and show: one line, bounded length, content untouched.
	 *
	 * Angle brackets stay, because mail servers put the rejected address in them.
	 *
	 * @param string $text   Raw text.
	 * @param int    $length Maximum number of characters.
	 */
	public static function clean_text( string $text, int $length ): string {
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'ISO-8859-1' );
		}
		$text = (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $text );

		return mb_substr( trim( (string) preg_replace( '/ {2,}/', ' ', $text ) ), 0, $length );
	}
}
