<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Smtp;

use Formwerk\Smtp\Log;
use Formwerk\Tests\WpState;
use PHPUnit\Framework\TestCase;

final class LogTest extends TestCase {

	protected function setUp(): void {
		WpState::reset();
	}

	public function test_recipients_with_display_names_keep_their_real_address(): void {
		$this->assertSame(
			array( 'john@example.com', 'plain@example.com' ),
			Log::addresses( array( 'John Doe <john@example.com>', ' plain@example.com ', 'not an address', array( 'nested' ) ) )
		);
	}

	public function test_error_text_keeps_the_rejected_address(): void {
		$log = new Log();
		$log->add( Log::STATUS_FAILED, array( 'user@example.com' ), "Order\r\nconfirmation", "550 5.1.1 <user@example.com>:\tRecipient address rejected" );

		$entry = $log->all()[0];

		$this->assertSame( '550 5.1.1 <user@example.com>: Recipient address rejected', $entry['message'] );
		$this->assertSame( 'Order confirmation', $entry['subject'] );
		$this->assertSame( Log::STATUS_FAILED, $entry['status'] );
	}

	public function test_text_is_bounded_without_breaking_characters(): void {
		$this->assertSame( str_repeat( 'ä', 10 ), Log::clean_text( str_repeat( 'ä', 50 ), 10 ) );
		$this->assertSame( 'Grüße', Log::clean_text( mb_convert_encoding( 'Grüße', 'ISO-8859-1', 'UTF-8' ), 20 ) );
	}

	public function test_only_the_newest_entries_are_kept(): void {
		$log = new Log();
		for ( $i = 1; $i <= Log::MAX_ENTRIES + 5; $i++ ) {
			$log->add( Log::STATUS_FAILED, array(), 'Mail ' . $i, 'Error' );
		}
		$entries = $log->all();

		$this->assertCount( Log::MAX_ENTRIES, $entries );
		$this->assertSame( 'Mail ' . ( Log::MAX_ENTRIES + 5 ), $entries[0]['subject'] );
	}

	public function test_expired_entries_disappear(): void {
		$log = new Log();
		$log->add( Log::STATUS_FAILED, array(), 'Old', 'Error' );
		WpState::$options[ '1:' . Log::OPTION ][0]['time'] = time() - Log::MAX_AGE - 10;
		$log->add( Log::STATUS_FAILED, array(), 'New', 'Error' );

		$this->assertSame( array( 'New' ), array_column( $log->all(), 'subject' ) );
		$this->assertCount( 1, WpState::$options[ '1:' . Log::OPTION ] );
	}

	public function test_entries_of_one_person_can_be_erased(): void {
		$log = new Log();
		$log->add( Log::STATUS_FAILED, array( 'Anna <anna@example.com>' ), 'A', 'Error' );
		$log->add( Log::STATUS_FAILED, array( 'bert@example.com', 'ANNA@example.com' ), 'B', 'Error' );
		$log->add( Log::STATUS_FAILED, array( 'bert@example.com' ), 'C', 'Error' );

		$this->assertSame( 2, $log->erase_recipient( 'anna@example.com' ) );
		$this->assertSame( array( 'C' ), array_column( $log->all(), 'subject' ) );
		$this->assertSame( 0, $log->erase_recipient( 'nobody@example.com' ) );
	}
}
