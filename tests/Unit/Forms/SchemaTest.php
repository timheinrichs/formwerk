<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Forms;

use Formwerk\Forms\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase {

	public function test_names_come_from_labels(): void {
		$result = Schema::normalize(
			array(
				array(
					'id'    => 'a',
					'type'  => 'text',
					'label' => 'Vor- und Nachname',
				),
				array(
					'id'    => 'b',
					'type'  => 'email',
					'label' => 'E-Mail-Adresse',
				),
				array(
					'id'    => 'c',
					'type'  => 'textarea',
					'label' => 'Größe & Maße',
				),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array( 'vor_und_nachname', 'e_mail_adresse', 'groesse_masse' ), Schema::names( $result['fields'] ) );
	}

	public function test_duplicate_names_are_impossible(): void {
		$result = Schema::normalize(
			array(
				array(
					'type'  => 'text',
					'label' => 'Name',
				),
				array(
					'type'  => 'text',
					'label' => 'Name',
				),
				array(
					'type'  => 'text',
					'label' => 'Anderes',
					'name'  => 'name',
				),
				array(
					'type'  => 'text',
					'label' => 'Name 2',
				),
			)
		);
		$names  = Schema::names( $result['fields'] );

		// The name a field already has wins; the others get a free one.
		$this->assertSame( array( 'name_2', 'name_3', 'name', 'name_2_2' ), $names );
		$this->assertSame( $names, array_unique( $names ) );
	}

	public function test_a_new_field_never_takes_the_name_of_an_existing_one(): void {
		// An existing field "name" (used as {field:name} in the email), and a new field with the same
		// label that was dragged above it. The email must keep showing the answer of the old field.
		$result = Schema::normalize(
			array(
				array(
					'id'    => 'new1',
					'type'  => 'text',
					'label' => 'Name',
					'name'  => '',
				),
				array(
					'id'    => 'old1',
					'type'  => 'text',
					'label' => 'Name',
					'name'  => 'name',
				),
			)
		);

		$this->assertSame( array( 'new1', 'old1' ), array_column( $result['fields'], 'id' ) );
		$this->assertSame( array( 'name_2', 'name' ), Schema::names( $result['fields'] ) );
	}

	public function test_a_chosen_name_is_kept_and_cleaned(): void {
		$result = Schema::normalize(
			array(
				array(
					'type'  => 'text',
					'label' => 'Ihr Anliegen',
					'name'  => 'Betreff!',
				),
			)
		);

		$this->assertSame( 'betreff', $result['fields'][0]['name'] );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function slugs(): array {
		return array(
			'umlauts'           => array( 'Straße und Hausnummer', 'strasse_und_hausnummer' ),
			'starts with digit' => array( '2. Teilnehmer*in', 'field_2_teilnehmer_in' ),
			'only symbols'      => array( '???', '' ),
			'very long'         => array( str_repeat( 'abc ', 30 ), 'abc_abc_abc_abc_abc_abc_abc_abc_abc_abc' ),
		);
	}

	#[DataProvider( 'slugs' )]
	public function test_slugs_are_safe_field_names( string $label, string $expected ): void {
		$this->assertSame( $expected, Schema::slug( $label ) );
	}

	public function test_a_label_without_usable_characters_still_gets_a_name(): void {
		$result = Schema::normalize(
			array(
				array(
					'type'  => 'text',
					'label' => '???',
				),
				array(
					'type'  => 'text',
					'label' => '!!!',
				),
			)
		);

		$this->assertSame( array( 'field', 'field_2' ), Schema::names( $result['fields'] ) );
	}

	public function test_headings_have_no_name_and_are_never_required(): void {
		$result = Schema::normalize(
			array(
				array(
					'type'     => 'heading',
					'label'    => 'Angaben zur Person',
					'required' => true,
				),
			)
		);

		$this->assertSame( '', $result['fields'][0]['name'] );
		$this->assertFalse( $result['fields'][0]['required'] );
		$this->assertSame( array(), Schema::names( $result['fields'] ) );
	}

	public function test_problems_are_reported_per_field(): void {
		$result = Schema::normalize(
			array(
				array(
					'id'    => 'a',
					'type'  => 'text',
					'label' => '',
				),
				array(
					'id'      => 'b',
					'type'    => 'select',
					'label'   => 'Kurs',
					'options' => array( ' ', '' ),
				),
				array(
					'id'    => 'c',
					'type'  => 'file',
					'label' => 'Upload',
				),
				array(
					'id'      => 'd',
					'type'    => 'heading',
					'label'   => '',
					'content' => 'Nur Text ist erlaubt.',
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'field' => 'a',
					'code'  => 'label_required',
				),
				array(
					'field' => 'b',
					'code'  => 'options_required',
				),
				array(
					'field' => 'c',
					'code'  => 'type_invalid',
				),
			),
			$result['errors']
		);
	}

	public function test_options_are_trimmed_and_unique(): void {
		$result = Schema::normalize(
			array(
				array(
					'type'    => 'radio',
					'label'   => 'Kurs',
					'options' => array( ' A ', 'B', 'A', '', array( 'x' ) ),
				),
			)
		);

		$this->assertSame( array( 'A', 'B' ), $result['fields'][0]['options'] );
	}

	public function test_missing_or_repeated_ids_are_replaced(): void {
		$result = Schema::normalize(
			array(
				array(
					'id'    => 'same',
					'type'  => 'text',
					'label' => 'A',
				),
				array(
					'id'    => 'same',
					'type'  => 'text',
					'label' => 'B',
				),
				array(
					'type'  => 'text',
					'label' => 'C',
				),
			)
		);
		$ids    = array_column( $result['fields'], 'id' );

		$this->assertCount( 3, array_unique( $ids ) );
		$this->assertSame( 'same', $ids[0] );
	}

	public function test_garbage_input_gives_an_empty_form(): void {
		$this->assertSame(
			array(
				'fields' => array(),
				'errors' => array(),
			),
			Schema::normalize( 'nonsense' )
		);
		$this->assertSame( array(), Schema::normalize( array( 'text', 5, null ) )['fields'] );
	}

	public function test_the_number_of_fields_is_limited(): void {
		$result = Schema::normalize(
			array_fill(
				0,
				Schema::MAX_FIELDS + 3,
				array(
					'type'  => 'text',
					'label' => 'Feld',
				)
			)
		);

		$this->assertCount( Schema::MAX_FIELDS, $result['fields'] );
		$this->assertSame( 'too_many_fields', $result['errors'][0]['code'] );
	}
}
