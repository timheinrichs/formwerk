<?php
/**
 * Collects the SMTP conversation of a test mail.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Smtp;

/**
 * Keeps the SMTP conversation readable and free of secrets.
 *
 * PHPMailer's debug output contains the login credentials (base64) and the full message.
 * Only the protocol commands that help with diagnosis are kept; everything else the client
 * sends is replaced by a marker.
 */
final class DebugTranscript {

	private const HIDDEN     = 'CLIENT -> SERVER: [hidden]';
	private const MAX_LINES  = 200;
	private const MAX_LENGTH = 500;

	/**
	 * Collected lines.
	 *
	 * @var string[]
	 */
	private array $lines = array();

	/**
	 * Adds one chunk of PHPMailer debug output.
	 *
	 * @param string $output Debug output, possibly several lines.
	 */
	public function add( string $output ): void {
		foreach ( preg_split( '/\r\n|\r|\n/', $output ) as $line ) {
			$line = self::redact( trim( $line ) );
			if ( '' === $line || count( $this->lines ) >= self::MAX_LINES ) {
				continue;
			}
			// The message body arrives as many client lines: show the marker once.
			if ( self::HIDDEN === $line && end( $this->lines ) === self::HIDDEN ) {
				continue;
			}
			$this->lines[] = $line;
		}
	}

	/**
	 * Returns the transcript as text.
	 */
	public function text(): string {
		return implode( "\n", $this->lines );
	}

	/**
	 * Removes secrets from a single debug line.
	 *
	 * @param string $line One line of debug output.
	 */
	public static function redact( string $line ): string {
		if ( 1 !== preg_match( '/^CLIENT -> SERVER:\s*(.*)$/s', $line, $match ) ) {
			return mb_strcut( $line, 0, self::MAX_LENGTH );
		}
		$command = $match[1];

		if ( 1 === preg_match( '/^AUTH\s+([A-Za-z0-9_-]+)/i', $command, $auth ) ) {
			return 'CLIENT -> SERVER: AUTH ' . strtoupper( $auth[1] ) . ' [credentials hidden]';
		}
		if ( 1 === preg_match( '/^(EHLO|HELO|STARTTLS|MAIL FROM:|RCPT TO:|DATA|RSET|NOOP|QUIT)(\s|$|<)/i', $command ) ) {
			return mb_strcut( $line, 0, self::MAX_LENGTH );
		}

		return self::HIDDEN;
	}
}
