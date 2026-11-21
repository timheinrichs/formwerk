<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Forms;

use Formwerk\Forms\FormRepository;
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

	/**
	 * @param array<int, array<string, mixed>> $items Fields as sent.
	 *
	 * @return array<int, string> Width per field: 'full', 'half', 'third' or 'quarter'.
	 */
	private function widths( array $items ): array {
		return array_column( Schema::normalize( $items )['fields'], 'width' );
	}

	/**
	 * Steps used by the step tests.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	private function two_steps(): array {
		return Schema::steps(
			array(
				array(
					'id'    => 'person',
					'title' => 'You',
				),
				array(
					'id'    => 'course',
					'title' => '',
				),
			)
		);
	}

	public function test_steps_get_stable_ids_and_titles(): void {
		$steps = Schema::steps(
			array(
				array(
					'id'    => 'Person!',
					'title' => '  You ',
				),
				array( 'id' => 'person' ),
				'garbage',
			)
		);

		$this->assertSame( 'person', $steps[0]['id'] );
		$this->assertSame( 'You', $steps[0]['title'] );
		$this->assertNotSame( 'person', $steps[1]['id'] );
		$this->assertCount( 2, $steps );
	}

	public function test_fields_are_ordered_step_by_step(): void {
		$fields = Schema::normalize(
			array(
				array(
					'type'  => 'text',
					'label' => 'Course',
					'step'  => 'course',
				),
				array(
					'type'  => 'text',
					'label' => 'Name',
					'step'  => 'person',
				),
				array(
					'type'  => 'text',
					'label' => 'Unknown step',
					'step'  => 'gone',
				),
				array(
					'type'  => 'email',
					'label' => 'Email',
					'step'  => 'person',
				),
			),
			$this->two_steps()
		)['fields'];

		$this->assertSame( array( 'Name', 'Unknown step', 'Email', 'Course' ), array_column( $fields, 'label' ) );
		$this->assertSame( array( 'person', 'person', 'person', 'course' ), array_column( $fields, 'step' ) );
	}

	public function test_without_steps_fields_have_no_step(): void {
		$fields = Schema::normalize(
			array(
				array(
					'type'  => 'text',
					'label' => 'Name',
					'step'  => 'person',
				),
			)
		)['fields'];

		$this->assertSame( '', $fields[0]['step'] );
	}

	public function test_every_step_needs_a_field_to_fill_in(): void {
		$steps  = $this->two_steps();
		$fields = Schema::normalize(
			array(
				array(
					'type'  => 'text',
					'label' => 'Name',
					'step'  => 'person',
				),
				array(
					'type'    => 'heading',
					'content' => 'Only a heading',
					'step'    => 'course',
				),
			),
			$steps
		)['fields'];

		$this->assertSame(
			array(
				array(
					'path' => 'steps',
					'step' => 'course',
					'code' => 'step_empty',
				),
			),
			FormRepository::step_errors( $steps, $fields )
		);
		$this->assertSame( 'steps_required', FormRepository::step_errors( array(), $fields )[0]['code'] );
	}

	public function test_a_simple_form_keeps_the_steps_but_not_their_order(): void {
		$fields = Schema::normalize(
			array(
				array(
					'type'  => 'text',
					'label' => 'Course',
					'step'  => 'course',
					'half'  => true,
				),
				array(
					'type'   => 'text',
					'label'  => 'Name',
					'step'   => 'person',
					'half'   => true,
					'beside' => true,
				),
			),
			$this->two_steps(),
			false
		)['fields'];

		$this->assertSame( array( 'Course', 'Name' ), array_column( $fields, 'label' ) );
		$this->assertSame( array( 'course', 'person' ), array_column( $fields, 'step' ) );
	}

	public function test_at_most_twenty_steps_are_kept(): void {
		$raw = array();
		for ( $i = 1; $i <= Schema::MAX_STEPS + 3; $i++ ) {
			$raw[] = array( 'id' => 'step' . $i );
		}

		$this->assertCount( Schema::MAX_STEPS, Schema::steps( $raw ) );
	}

	public function test_default_visitor_texts_are_not_kept_as_own_texts(): void {
		$this->assertTrue( FormRepository::is_default_message( 'next', 'Next' ) );
		$this->assertTrue( FormRepository::is_default_message( 'submit', '' ) );
		$this->assertFalse( FormRepository::is_default_message( 'next', 'Continue to your course' ) );
	}

	public function test_every_field_has_one_of_four_widths(): void {
		$items = array(
			array(
				'type'  => 'text',
				'label' => 'Voll',
			),
			array(
				'type'  => 'text',
				'label' => 'Halb',
				'width' => 'half',
			),
			array(
				'type'  => 'textarea',
				'label' => 'Drittel',
				'width' => 'third',
			),
			array(
				'type'  => 'consent',
				'label' => 'Viertel',
				'width' => 'quarter',
			),
			array(
				'type'  => 'text',
				'label' => 'Unbekannt',
				'width' => 'huge',
			),
		);

		$this->assertSame( array( 'full', 'half', 'third', 'quarter', 'full' ), $this->widths( $items ) );
	}

	public function test_rows_from_older_versions_keep_half_width(): void {
		$items = array(
			// 0.3 to 0.5: both fields of a row stored the computed width "half".
			array(
				'type'  => 'text',
				'label' => 'Vorname',
				'width' => 'half',
			),
			array(
				'type'   => 'text',
				'label'  => 'Nachname',
				'width'  => 'half',
				'beside' => true,
			),
			// Before 0.3: only "half"; a single one now keeps its width as well.
			array(
				'type'  => 'email',
				'label' => 'E-Mail',
				'width' => 'half',
			),
		);
		$fields = Schema::normalize( $items )['fields'];

		$this->assertSame( array( 'half', 'half', 'half' ), array_column( $fields, 'width' ) );
		$this->assertArrayNotHasKey( 'beside', $fields[1] );
	}
}
