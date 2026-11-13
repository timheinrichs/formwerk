<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Submissions;

use Formwerk\Submissions\Retention;
use Formwerk\Tests\WpState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RetentionTest extends TestCase {

	protected function setUp(): void {
		WpState::reset();
	}

	public function test_the_default_is_180_days(): void {
		$this->assertSame( 180, Retention::days() );
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool, 2: int}>
	 */
	public static function inputs(): array {
		return array(
			'normal'       => array( '30', true, 30 ),
			'keep forever' => array( '0', true, 0 ),
			'with spaces'  => array( ' 90 ', true, 90 ),
			'largest'      => array( '3650', true, 3650 ),
			'too large'    => array( '3651', false, 180 ),
			'negative'     => array( '-1', false, 180 ),
			'decimal'      => array( '7.5', false, 180 ),
			'text'         => array( 'eine Woche', false, 180 ),
			'empty'        => array( '', false, 180 ),
			'not a scalar' => array( array( 3 ), false, 180 ),
		);
	}

	#[DataProvider( 'inputs' )]
	public function test_only_whole_days_in_range_are_saved( $input, bool $saved, int $days ): void {
		$this->assertSame( $saved, Retention::save( $input ) );
		$this->assertSame( $days, Retention::days() );
	}

	public function test_broken_stored_values_fall_back_or_are_bounded(): void {
		WpState::$options[ '1:' . Retention::OPTION ] = 'kaputt';
		$this->assertSame( 180, Retention::days() );

		WpState::$options[ '1:' . Retention::OPTION ] = 99999;
		$this->assertSame( Retention::MAX, Retention::days() );
	}
}
