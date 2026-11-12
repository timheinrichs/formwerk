<?php
/**
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Forms;

use Formwerk\Forms\Placeholders;
use Formwerk\Forms\Schema;
use PHPUnit\Framework\TestCase;

final class PlaceholdersTest extends TestCase {

	private const CONTEXT = array(
		'form'  => 'Anmeldung',
		'site'  => 'Beispiel',
		'page'  => 'https://example.org/anmeldung/',
		'date'  => '12.11.2026',
		'yes'   => 'Ja',
		'no'    => 'Nein',
		'empty' => '–',
	);

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
					'type'  => 'text',
					'label' => 'Name',
				),
				array(
					'type'  => 'email',
					'label' => 'E-Mail',
				),
				array(
					'type'  => 'textarea',
					'label' => 'Nachricht',
				),
				array(
					'type'    => 'checkboxes',
					'label'   => 'Tage',
					'options' => array( 'Mo', 'Di' ),
				),
				array(
					'type'  => 'consent',
					'label' => 'Datenschutz',
				),
			)
		)['fields'];
	}

	private const VALUES = array(
		'name'        => 'Anna Beispiel',
		'e_mail'      => 'anna@example.com',
		'nachricht'   => "Hallo,\nich melde mich an.",
		'tage'        => array( 'Mo', 'Di' ),
		'datenschutz' => true,
	);

	public function test_unknown_placeholders_are_found(): void {
		$names    = Schema::names( $this->fields() );
		$template = 'Von {field:name} <{field:e_mail}> am {date}: {your-message} {field:telefon} {field} {all_fields} {your-message}';

		$this->assertSame( array( '{your-message}', '{field:telefon}', '{field}' ), Placeholders::unknown( $template, $names ) );
	}

	public function test_a_valid_template_has_no_unknown_placeholders(): void {
		$names = Schema::names( $this->fields() );

		$this->assertSame( array(), Placeholders::unknown( "{all_fields}\n\n{form} {site} {page} {date} {field:tage}", $names ) );
		$this->assertSame( array(), Placeholders::unknown( 'Text with { spaced braces } and none at all', $names ) );
	}

	public function test_all_fields_prints_every_field_with_its_label(): void {
		$text = Placeholders::render( '{all_fields}', $this->fields(), self::VALUES, self::CONTEXT );

		$this->assertSame(
			"Name: Anna Beispiel\n\nE-Mail: anna@example.com\n\nNachricht:\nHallo,\nich melde mich an.\n\nTage: Mo, Di\n\nDatenschutz: Ja",
			$text
		);
	}

	public function test_labels_that_end_a_sentence_read_naturally(): void {
		$fields = Schema::normalize(
			array(
				array(
					'type'  => 'consent',
					'label' => 'Ich habe die Hinweise gelesen.',
				),
			)
		)['fields'];
		$name   = $fields[0]['name'];

		$this->assertSame( 'Ich habe die Hinweise gelesen: Ja', Placeholders::render( '{all_fields}', $fields, array( $name => true ), self::CONTEXT ) );
	}

	public function test_empty_values_stay_visible(): void {
		$text = Placeholders::render( '{all_fields}', $this->fields(), array( 'name' => 'Anna' ), self::CONTEXT );

		$this->assertStringContainsString( 'E-Mail: –', $text );
		$this->assertStringContainsString( 'Tage: –', $text );
		$this->assertStringContainsString( 'Datenschutz: Nein', $text );
	}

	public function test_single_placeholders_and_general_ones_are_filled(): void {
		$text = Placeholders::render( 'Neue Einsendung über {form} von {field:name} ({page}, {date})', $this->fields(), self::VALUES, self::CONTEXT );

		$this->assertSame( 'Neue Einsendung über Anmeldung von Anna Beispiel (https://example.org/anmeldung/, 12.11.2026)', $text );
	}

	public function test_subjects_and_addresses_never_contain_line_breaks(): void {
		$subject = Placeholders::render( 'Nachricht: {field:nachricht}', $this->fields(), self::VALUES, self::CONTEXT, true );

		$this->assertSame( 'Nachricht: Hallo, ich melde mich an.', $subject );
		$this->assertStringNotContainsString( "\n", Placeholders::render( '{all_fields}', $this->fields(), self::VALUES, self::CONTEXT, true ) );
	}

	public function test_the_old_german_names_are_unknown(): void {
		$this->assertSame( array( '{alle_felder}', '{feld:name}' ), Placeholders::unknown( '{alle_felder} {feld:name}', Schema::names( $this->fields() ) ) );
	}

	public function test_a_field_missing_from_an_old_submission_is_shown_as_empty(): void {
		$text = Placeholders::render( 'Firma: {field:firma}', $this->fields(), self::VALUES, self::CONTEXT );

		$this->assertSame( 'Firma: –', $text );
	}

	public function test_values_are_not_expanded_a_second_time(): void {
		$values = array_merge( self::VALUES, array( 'name' => '{field:e_mail} {all_fields}' ) );

		$this->assertSame( 'Name: {field:e_mail} {all_fields}', Placeholders::render( 'Name: {field:name}', $this->fields(), $values, self::CONTEXT ) );
	}
}
