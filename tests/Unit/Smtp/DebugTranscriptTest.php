<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Smtp;

use Formwerk\Smtp\DebugTranscript;
use PHPUnit\Framework\TestCase;

final class DebugTranscriptTest extends TestCase {

	public function test_login_credentials_never_appear(): void {
		$transcript = new DebugTranscript();
		$transcript->add( 'SERVER -> CLIENT: 220 smtp.example.com ESMTP' );
		$transcript->add( 'CLIENT -> SERVER: EHLO localhost' );
		$transcript->add( 'CLIENT -> SERVER: AUTH LOGIN' );
		$transcript->add( 'SERVER -> CLIENT: 334 VXNlcm5hbWU6' );
		$transcript->add( 'CLIENT -> SERVER: bWFpbGJveEBleGFtcGxlLmNvbQ==' );
		$transcript->add( 'SERVER -> CLIENT: 334 UGFzc3dvcmQ6' );
		$transcript->add( 'CLIENT -> SERVER: c2VjcmV0' );
		$transcript->add( 'SERVER -> CLIENT: 235 2.7.0 Authentication successful' );

		$text = $transcript->text();

		$this->assertStringNotContainsString( 'bWFpbGJveEBleGFtcGxlLmNvbQ==', $text );
		$this->assertStringNotContainsString( 'c2VjcmV0', $text );
		$this->assertStringContainsString( 'CLIENT -> SERVER: AUTH LOGIN [credentials hidden]', $text );
		$this->assertStringContainsString( '235 2.7.0 Authentication successful', $text );
		$this->assertStringContainsString( 'CLIENT -> SERVER: EHLO localhost', $text );
	}

	public function test_auth_plain_with_inline_credentials_is_hidden(): void {
		$line = DebugTranscript::redact( 'CLIENT -> SERVER: AUTH PLAIN AG1haWxib3gAc2VjcmV0' );

		$this->assertSame( 'CLIENT -> SERVER: AUTH PLAIN [credentials hidden]', $line );
	}

	public function test_message_body_is_collapsed_into_one_marker(): void {
		$transcript = new DebugTranscript();
		$transcript->add( 'CLIENT -> SERVER: MAIL FROM:<info@example.com>' );
		$transcript->add( 'CLIENT -> SERVER: RCPT TO:<someone@example.com>' );
		$transcript->add( 'CLIENT -> SERVER: DATA' );
		$transcript->add( "CLIENT -> SERVER: Subject: Test\nCLIENT -> SERVER: \nCLIENT -> SERVER: Body line\nCLIENT -> SERVER: ." );
		$transcript->add( 'SERVER -> CLIENT: 250 2.0.0 Ok: queued' );
		$transcript->add( 'CLIENT -> SERVER: QUIT' );

		$this->assertSame(
			implode(
				"\n",
				array(
					'CLIENT -> SERVER: MAIL FROM:<info@example.com>',
					'CLIENT -> SERVER: RCPT TO:<someone@example.com>',
					'CLIENT -> SERVER: DATA',
					'CLIENT -> SERVER: [hidden]',
					'SERVER -> CLIENT: 250 2.0.0 Ok: queued',
					'CLIENT -> SERVER: QUIT',
				)
			),
			$transcript->text()
		);
	}

	public function test_connection_errors_are_kept(): void {
		$transcript = new DebugTranscript();
		$transcript->add( "SMTP ERROR: Failed to connect to server: Connection refused (111)\n\n" );

		$this->assertSame( 'SMTP ERROR: Failed to connect to server: Connection refused (111)', $transcript->text() );
	}

	public function test_output_is_bounded(): void {
		$transcript = new DebugTranscript();
		for ( $i = 0; $i < 500; $i++ ) {
			$transcript->add( 'SERVER -> CLIENT: 250 line ' . $i . ' ' . str_repeat( 'x', 2000 ) );
		}
		$lines = explode( "\n", $transcript->text() );

		$this->assertCount( 200, $lines );
		$this->assertSame( 500, strlen( $lines[0] ) );
	}
}
