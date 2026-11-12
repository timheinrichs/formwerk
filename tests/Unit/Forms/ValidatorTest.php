<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Forms;

use Formwerk\Forms\Schema;
use Formwerk\Forms\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function fields(): array {
		return Schema::normalize(
			array(
				array(
					'type'  => 'heading',
					'label' => 'Anmeldung',
				),
				array(
					'type'     => 'text',
					'label'    => 'Name',
					'required' => true,
				),
				array(
					'type'     => 'email',
					'label'    => 'E-Mail',
					'required' => true,
				),
				array(
					'type'  => 'tel',
					'label' => 'Telefon',
				),
				array(
					'type'  => 'number',
					'label' => 'Personen',
				),
				array(
					'type'  => 'date',
					'label' => 'Termin',
				),
				array(
					'type'  => 'textarea',
					'label' => 'Nachricht',
				),
				array(
					'type'    => 'select',
					'label'   => 'Kurs',
					'options' => array( 'Grundkurs', 'Aufbaukurs' ),
				),
				array(
					'type'    => 'checkboxes',
					'label'   => 'Tage',
					'options' => array( 'Mo', 'Di', 'Mi' ),
				),
				array(
					'type'     => 'consent',
					'label'    => 'Datenschutz gelesen',
					'required' => true,
				),
			)
		)['fields'];
	}

	public function test_a_complete_submission_is_cleaned_and_accepted(): void {
		$result = Validator::validate(
			$this->fields(),
			array(
				'name'                => "  Anna\tBeispiel ",
				'e_mail'              => 'anna@example.com',
				'telefon'             => '+49 (0) 221 / 123-45',
				'personen'            => '2,5',
				'termin'              => '2027-02-28',
				'nachricht'           => "Zeile 1\r\nZeile 2",
				'kurs'                => 'Aufbaukurs',
				'tage'                => array( 'Mo', 'Mi', 'Mo' ),
				'datenschutz_gelesen' => '1',
				'not_a_field'         => 'ignored',
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame(
			array(
				'name'                => 'Anna Beispiel',
				'e_mail'              => 'anna@example.com',
				'telefon'             => '+49 (0) 221 / 123-45',
				'personen'            => '2.5',
				'termin'              => '2027-02-28',
				'nachricht'           => "Zeile 1\nZeile 2",
				'kurs'                => 'Aufbaukurs',
				'tage'                => array( 'Mo', 'Mi' ),
				'datenschutz_gelesen' => true,
			),
			$result['values']
		);
	}

	public function test_an_empty_submission_lists_every_field_and_the_required_ones_as_errors(): void {
		$result = Validator::validate( $this->fields(), array() );

		$this->assertSame(
			array(
				'name'                => 'required',
				'e_mail'              => 'required',
				'datenschutz_gelesen' => 'required',
			),
			$result['errors']
		);
		$this->assertCount( 9, $result['values'] );
		$this->assertSame( array(), $result['values']['tage'] );
		$this->assertFalse( $result['values']['datenschutz_gelesen'] );
	}

	/**
	 * @return array<string, array{0: string, 1: mixed, 2: string}>
	 */
	public static function invalid_values(): array {
		return array(
			'email without domain'   => array( 'e_mail', 'anna@', 'email_invalid' ),
			'email with line break'  => array( 'e_mail', "anna@example.com\nBcc: x@example.com", 'email_invalid' ),
			'phone with letters'     => array( 'telefon', 'call me', 'tel_invalid' ),
			'phone too short'        => array( 'telefon', '12', 'tel_invalid' ),
			'number with text'       => array( 'personen', '2 Personen', 'number_invalid' ),
			'impossible date'        => array( 'termin', '2027-02-30', 'date_invalid' ),
			'date in another format' => array( 'termin', '28.02.2027', 'date_invalid' ),
			'option not offered'     => array( 'kurs', 'Geheimkurs', 'option_invalid' ),
			'checkbox not offered'   => array( 'tage', array( 'Mo', 'So' ), 'option_invalid' ),
			'text too long'          => array( 'name', 'x', 'placeholder' ),
			'message too long'       => array( 'nachricht', 'x', 'placeholder' ),
			'array for a text field' => array( 'name', array( 'a' ), 'required' ),
		);
	}

	#[DataProvider( 'invalid_values' )]
	public function test_invalid_values_are_named( string $name, $value, string $code ): void {
		if ( 'placeholder' === $code ) {
			$value = str_repeat( 'ä', ( 'name' === $name ? Validator::MAX_TEXT : Validator::MAX_TEXTAREA ) + 1 );
			$code  = 'too_long';
		}
		$result = Validator::validate( $this->fields(), array( $name => $value ) );

		$this->assertSame( $code, $result['errors'][ $name ] ?? null );
	}

	public function test_optional_fields_may_stay_empty(): void {
		$result = Validator::validate(
			$this->fields(),
			array(
				'telefon' => '  ',
				'termin'  => '',
				'kurs'    => '',
			)
		);

		$this->assertArrayNotHasKey( 'telefon', $result['errors'] );
		$this->assertArrayNotHasKey( 'termin', $result['errors'] );
		$this->assertArrayNotHasKey( 'kurs', $result['errors'] );
		$this->assertSame( '', $result['values']['telefon'] );
	}
}
