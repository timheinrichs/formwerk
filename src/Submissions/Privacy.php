<?php
/**
 * Connects submissions to the WordPress privacy tools.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Submissions;

use Formwerk\Forms\Placeholders;

/**
 * "Export Personal Data" and "Erase Personal Data" find submissions by the email address
 * a person entered, and a suggested text for the privacy policy.
 */
final class Privacy {

	private const PER_PAGE = 50;

	/**
	 * Submission storage.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Constructor.
	 *
	 * @param Store $store Submission storage.
	 */
	public function __construct( Store $store ) {
		$this->store = $store;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_filter(
			'wp_privacy_personal_data_exporters',
			function ( $exporters ) {
				$exporters['formwerk-submissions'] = array(
					'exporter_friendly_name' => __( 'Formwerk submissions', 'formwerk' ),
					'callback'               => array( $this, 'export' ),
				);

				return $exporters;
			}
		);
		add_filter(
			'wp_privacy_personal_data_erasers',
			function ( $erasers ) {
				$erasers['formwerk-submissions'] = array(
					'eraser_friendly_name' => __( 'Formwerk submissions', 'formwerk' ),
					'callback'             => array( $this, 'erase' ),
				);

				return $erasers;
			}
		);
		add_action( 'admin_init', array( $this, 'suggest_policy_text' ) );
	}

	/**
	 * Exports the submissions of one person.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, starting at 1.
	 *
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( $email, $page = 1 ): array {
		$items = $this->store->find_by_email( (string) $email, (int) $page, self::PER_PAGE );
		$data  = array();
		$words = array(
			'yes' => __( 'Yes', 'formwerk' ),
			'no'  => __( 'No', 'formwerk' ),
		);
		foreach ( $items as $item ) {
			$fields = array(
				array(
					'name'  => __( 'Form', 'formwerk' ),
					'value' => $item['form_title'],
				),
				array(
					'name'  => __( 'Received', 'formwerk' ),
					'value' => $item['created_at'] . ' UTC',
				),
			);
			foreach ( $item['fields'] as $field ) {
				$fields[] = array(
					'name'  => (string) $field['label'],
					'value' => Placeholders::format( $field, $item['values'][ $field['name'] ] ?? '', $words ),
				);
			}
			$data[] = array(
				'group_id'    => 'formwerk-submissions',
				'group_label' => __( 'Form submissions', 'formwerk' ),
				'item_id'     => 'formwerk-submission-' . $item['id'],
				'data'        => $fields,
			);
		}//end foreach

		return array(
			'data' => $data,
			'done' => count( $items ) < self::PER_PAGE,
		);
	}

	/**
	 * Deletes the submissions of one person.
	 *
	 * Always reads the first page: what was deleted in the previous round is gone.
	 *
	 * @param string $email Email address.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase( $email ): array {
		$items   = $this->store->find_by_email( (string) $email, 1, self::PER_PAGE );
		$removed = $this->store->delete( array_map( static fn( array $item ): int => (int) $item['id'], $items ) );

		// Found but not deleted: report it and stop, instead of reading the same page forever.
		$retained = count( $items ) > $removed;

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $retained,
			'messages'       => $retained ? array( __( 'Some form submissions could not be deleted. Please try again or delete them under Formwerk → Submissions.', 'formwerk' ) ) : array(),
			'done'           => $retained || count( $items ) < self::PER_PAGE,
		);
	}

	/**
	 * Suggests a paragraph for the privacy policy under Settings → Privacy.
	 */
	public function suggest_policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$days = Retention::days();
		$text = '<p>' . esc_html__( 'When you send a form on this website, we store your entries, the form, the page and the time, and send them to us by email. If the form offers it, you receive a confirmation by email. We use your entries only to answer your request.', 'formwerk' ) . '</p><p>' . esc_html(
			0 === $days
				? __( 'Submissions are kept until we delete them.', 'formwerk' )
				/* translators: %d: number of days. */
				: sprintf( _n( 'Submissions are deleted automatically after %d day.', 'Submissions are deleted automatically after %d days.', $days, 'formwerk' ), $days )
		) . '</p>';

		wp_add_privacy_policy_content( 'Formwerk', wp_kses_post( $text ) );
	}
}
