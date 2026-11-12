<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Smtp;

use Formwerk\Smtp\FailureReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FailureReasonTest extends TestCase {

	#[DataProvider( 'failures' )]
	public function test_failures_are_sorted_into_actionable_groups( string $expected, string $error, string $transcript ): void {
		$this->assertSame( $expected, FailureReason::classify( $error, $transcript ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function failures(): array {
		return array(
			'closed port, translated error' => array(
				FailureReason::CONNECT,
				'SMTP-Fehler: Es konnte keine Verbindung zum SMTP-Host hergestellt werden. Failed to connect to server',
				'SMTP ERROR: Failed to connect to server: Connection refused (111)',
			),
			'unknown host'                  => array(
				FailureReason::CONNECT,
				'SMTP Error: Could not connect to SMTP host.',
				'Connection failed. Error #2: stream_socket_client(): php_network_getaddresses: getaddrinfo for smtp.invalid failed',
			),
			'wrong password'                => array(
				FailureReason::LOGIN,
				'SMTP Error: Could not authenticate.',
				"SERVER -> CLIENT: 535 5.7.8 Error: authentication failed\nSMTP ERROR: Password command failed",
			),
			'wrong password, translated'    => array(
				FailureReason::LOGIN,
				'SMTP-Fehler: Authentifizierung fehlgeschlagen.',
				'SERVER -> CLIENT: 535 Authentication credentials invalid',
			),
			'sender not owned'              => array(
				FailureReason::SENDER,
				'SMTP Error: data not accepted.',
				"SERVER -> CLIENT: 550 5.7.1 Sender address is not allowed\nSMTP ERROR: MAIL FROM command failed",
			),
			'tls handshake'                 => array(
				FailureReason::SECURE,
				'SMTP Error: Could not connect to SMTP host.',
				'Connection failed. Error #2: stream_socket_enable_crypto(): SSL operation failed with code 1. certificate verify failed',
			),
			'starttls refused'              => array(
				FailureReason::SECURE,
				'SMTP connect() failed.',
				'SMTP ERROR: STARTTLS command failed: 454 TLS not available',
			),
			'something else'                => array(
				FailureReason::UNKNOWN,
				'Message body empty',
				'',
			),
		);
	}

	public function test_a_successful_starttls_exchange_is_not_mistaken_for_a_tls_problem(): void {
		$transcript = "CLIENT -> SERVER: STARTTLS\nSERVER -> CLIENT: 220 2.0.0 Ready to start TLS\nSMTP ERROR: Failed to connect to server: Connection timed out";

		$this->assertSame( FailureReason::CONNECT, FailureReason::classify( 'SMTP connect() failed.', $transcript ) );
	}
}
