<?php
/**
 * Classifies why a mail could not be sent.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Smtp;

/**
 * Sorts raw mail errors into a few groups, so the backend can say what to do next.
 *
 * Pure logic: works on the error text and the SMTP conversation.
 */
final class FailureReason {

	public const CONNECT = 'connect';
	public const LOGIN   = 'login';
	public const SECURE  = 'secure';
	public const SENDER  = 'sender';
	public const UNKNOWN = 'unknown';

	/**
	 * Finds the most likely reason.
	 *
	 * @param string $error      Error message from wp_mail().
	 * @param string $transcript SMTP conversation of the attempt, may be empty.
	 *
	 * @return string One of the class constants.
	 */
	public static function classify( string $error, string $transcript = '' ): string {
		$text = $error . "\n" . $transcript;

		if ( 1 === preg_match( '/Could not authenticate|\b(?:535|534)[ -]|authentication (?:failed|unsuccessful|required)|\b530[ -].*auth/i', $text ) ) {
			return self::LOGIN;
		}
		if ( 1 === preg_match( '/MAIL FROM command failed|\b(?:550|553|554|501)[ -].*(?:sender|from address|not owned|not allowed to send|relay)/i', $text ) ) {
			return self::SENDER;
		}
		if ( 1 === preg_match( '/STARTTLS|certificate|\bSSL\b|\bTLS\b|crypto/i', $error )
			|| 1 === preg_match( '/STARTTLS (?:command )?failed|certificate verify failed|SSL operation failed|Unable to (?:start|enable) (?:TLS|crypto)/i', $transcript )
		) {
			return self::SECURE;
		}
		if ( 1 === preg_match( '/Failed to connect|Could not connect|Connection (?:refused|failed|timed out)|timed out|getaddrinfo|Name or service not known|Network is unreachable/i', $text ) ) {
			return self::CONNECT;
		}

		return self::UNKNOWN;
	}
}
