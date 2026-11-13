<?php
/**
 * Download of submissions as CSV.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Admin;

use Formwerk\Forms\FormRepository;
use Formwerk\Plugin;
use Formwerk\Submissions\CsvExport;
use Formwerk\Submissions\Store;

/**
 * Streams the submissions of one form as a CSV file.
 */
final class Export {

	public const ACTION = 'formwerk_export';

	private const BATCH = 500;

	/**
	 * Submission storage.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Form storage.
	 *
	 * @var FormRepository
	 */
	private FormRepository $forms;

	/**
	 * Constructor.
	 *
	 * @param Store          $store Submission storage.
	 * @param FormRepository $forms Form storage.
	 */
	public function __construct( Store $store, FormRepository $forms ) {
		$this->store = $store;
		$this->forms = $forms;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download' ) );
	}

	/**
	 * Address of the download for a form and an optional mail filter.
	 *
	 * @param int    $form_id     Form ID.
	 * @param string $mail_status Filter of the list, or ''.
	 */
	public static function url( int $form_id, string $mail_status = '' ): string {
		$args = array(
			'action'  => self::ACTION,
			'form_id' => $form_id,
		);
		if ( '' !== $mail_status ) {
			$args['mail_status'] = $mail_status;
		}

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION . '_' . $form_id );
	}

	/**
	 * Sends the file.
	 */
	public function download(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Checked right below.
		$form_id     = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
		$mail_status = isset( $_GET['mail_status'] ) && Store::MAIL_UNSENT === $_GET['mail_status'] ? Store::MAIL_UNSENT : '';
		// phpcs:enable
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to export submissions.', 'formwerk' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION . '_' . $form_id );

		$form = $this->forms->find( $form_id );
		if ( null === $form ) {
			wp_die( esc_html__( 'This form no longer exists, so its submissions cannot be exported by form.', 'formwerk' ), '', array( 'response' => 404 ) );
		}

		// All snapshots first: the columns must be known before the first line is written.
		$submissions = array();
		for ( $page = 1; ; $page++ ) {
			$batch       = $this->store->query(
				array(
					'form_id'     => $form_id,
					'mail_status' => $mail_status,
					'per_page'    => self::BATCH,
					'page'        => $page,
				)
			)['items'];
			$submissions = array_merge( $submissions, $batch );
			if ( count( $batch ) < self::BATCH ) {
				break;
			}
		}
		$columns = CsvExport::field_columns( $form['fields'], $submissions );
		$words   = array(
			'yes' => __( 'Yes', 'formwerk' ),
			'no'  => __( 'No', 'formwerk' ),
		);

		$filename = sanitize_file_name( sanitize_title( $form['title'] ) . '-' . wp_date( 'Y-m-d' ) . '.csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$header = array_merge(
			array(
				__( 'Received', 'formwerk' ),
				__( 'Sent from', 'formwerk' ),
				__( 'Language', 'formwerk' ),
				__( 'Notification email', 'formwerk' ),
				__( 'Confirmation email', 'formwerk' ),
			),
			array_column( $columns, 'label' )
		);
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV, not HTML; every cell is encoded by CsvExport.
		echo CsvExport::line( $header );
		foreach ( $submissions as $submission ) {
			$time = strtotime( $submission['created_at'] . ' UTC' );
			echo CsvExport::line(
				array_merge(
					array(
						false === $time ? $submission['created_at'] : wp_date( 'Y-m-d H:i', $time ),
						$submission['page'],
						$submission['lang'],
						self::status( $submission['mail_status'] ),
						'off' === $submission['confirm_status'] ? '' : self::status( $submission['confirm_status'] ),
					),
					CsvExport::answers( $submission, $columns, $words )
				)
			);
		}
		// phpcs:enable
		exit;
	}

	/**
	 * Mail status in words.
	 *
	 * @param string $status Stored status.
	 */
	private static function status( string $status ): string {
		switch ( $status ) {
			case Store::MAIL_SENT:
				return __( 'Sent', 'formwerk' );
			case Store::MAIL_PENDING:
				return __( 'Being sent', 'formwerk' );
			case 'skipped':
				return __( 'No address entered', 'formwerk' );
			default:
				return __( 'Not sent', 'formwerk' );
		}
	}
}
