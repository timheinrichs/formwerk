<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Submissions;

use Formwerk\Forms\Schema;
use Formwerk\Submissions\CsvExport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvExportTest extends TestCase {

	private const WORDS = array(
		'yes' => 'Ja',
		'no'  => 'Nein',
	);

	/**
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function cells(): array {
		return array(
			'plain'             => array( 'Anna', 'Anna' ),
			'umlauts'           => array( 'Größe', 'Größe' ),
			'comma'             => array( 'Köln, Ehrenfeld', '"Köln, Ehrenfeld"' ),
			'quotes'            => array( 'Sie sagte "ja"', '"Sie sagte ""ja"""' ),
			'line break'        => array( "Zeile 1\nZeile 2", "\"Zeile 1\nZeile 2\"" ),
			'leading space'     => array( ' x', '" x"' ),
			'formula'           => array( '=SUM(A1:A9)', "'=SUM(A1:A9)" ),
			'plus'              => array( '+49 221 1234', "'+49 221 1234" ),
			'minus'             => array( '-5', "'-5" ),
			'at'                => array( '@cmd', "'@cmd" ),
			'formula and comma' => array( '=1,2', "\"'=1,2\"" ),
			'number'            => array( 42, '42' ),
			'not scalar'        => array( array( 'x' ), '' ),
		);
	}

	#[DataProvider( 'cells' )]
	public function test_cells_are_quoted_and_protected( $value, string $expected ): void {
		$this->assertSame( $expected, CsvExport::cell( $value ) );
	}

	public function test_lines_end_with_crlf(): void {
		$this->assertSame( "a,\"b,c\",\r\n", CsvExport::line( array( 'a', 'b,c', '' ) ) );
	}

	public function test_columns_keep_fields_that_only_older_submissions_have(): void {
		$form        = Schema::normalize(
			array(
				array(
					'type'  => 'heading',
					'label' => 'Kopf',
				),
				array(
					'type'  => 'text',
					'label' => 'Name',
				),
				array(
					'type'  => 'email',
					'label' => 'E-Mail',
				),
			)
		)['fields'];
		$submissions = array(
			array(
				'fields' => array(
					array(
						'name'  => 'name',
						'label' => 'Name',
						'type'  => 'text',
					),
					array(
						'name'  => 'telefon',
						'label' => 'Telefon',
						'type'  => 'tel',
					),
				),
			),
		);

		$columns = CsvExport::field_columns( $form, $submissions );

		$this->assertSame( array( 'name', 'e_mail', 'telefon' ), array_column( $columns, 'name' ) );
		$this->assertSame( array( 'Name', 'E-Mail', 'Telefon' ), array_column( $columns, 'label' ) );
	}

	public function test_answers_follow_the_columns(): void {
		$columns    = array(
			array(
				'name'  => 'name',
				'label' => 'Name',
				'type'  => 'text',
			),
			array(
				'name'  => 'tage',
				'label' => 'Tage',
				'type'  => 'checkboxes',
			),
			array(
				'name'  => 'ok',
				'label' => 'OK',
				'type'  => 'consent',
			),
			array(
				'name'  => 'fehlt',
				'label' => 'Fehlt',
				'type'  => 'text',
			),
		);
		$submission = array(
			'values' => array(
				'name' => 'Anna',
				'tage' => array( 'Mo', 'Di' ),
				'ok'   => true,
			),
		);

		$this->assertSame( array( 'Anna', 'Mo, Di', 'Ja', '' ), CsvExport::answers( $submission, $columns, self::WORDS ) );
	}

	public function test_cells_with_semicolons_or_tabs_are_quoted(): void {
		$this->assertSame( '"a;b"', CsvExport::cell( 'a;b' ) );
		$this->assertSame( "\"a\tb\"", CsvExport::cell( "a\tb" ) );
	}
}
