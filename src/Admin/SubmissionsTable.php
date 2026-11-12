<?php
/**
 * List of submissions.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Admin;

use Formwerk\Forms\Placeholders;
use Formwerk\Submissions\Store;
use WP_List_Table;

/**
 * The standard WordPress list table, so it behaves like posts and comments do.
 */
final class SubmissionsTable extends WP_List_Table {

	/**
	 * Submission storage.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Form titles by ID, for forms that still exist.
	 *
	 * @var array<int, string>
	 */
	private array $forms;

	/**
	 * Constructor.
	 *
	 * @param Store              $store Submission storage.
	 * @param array<int, string> $forms Form titles by ID.
	 */
	public function __construct( Store $store, array $forms ) {
		parent::__construct(
			array(
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			)
		);
		$this->store = $store;
		$this->forms = $forms;
	}

	/**
	 * Columns of the table.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox">',
			'created' => __( 'Received', 'formwerk' ),
			'form'    => __( 'Form', 'formwerk' ),
			'summary' => __( 'Content', 'formwerk' ),
			'mail'    => __( 'Email', 'formwerk' ),
		);
	}

	/**
	 * Loads the rows of the current page.
	 */
	public function prepare_items() {
		$per_page = 20;
		$result   = $this->store->query(
			array(
				'form_id'     => self::filter_form(),
				'mail_status' => self::filter_status(),
				'per_page'    => $per_page,
				'page'        => $this->get_pagenum(),
			)
		);

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'created' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Form chosen in the filter.
	 */
	public static function filter_form(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		return isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
	}

	/**
	 * Mail status chosen in the views.
	 */
	public static function filter_status(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$status = isset( $_GET['mail_status'] ) ? sanitize_key( wp_unslash( $_GET['mail_status'] ) ) : '';

		return Store::MAIL_UNSENT === $status ? $status : '';
	}

	/**
	 * Message for an empty list.
	 */
	public function no_items() {
		if ( 0 !== self::filter_form() || '' !== self::filter_status() ) {
			esc_html_e( 'No submissions match this filter.', 'formwerk' );
			return;
		}
		esc_html_e( 'No submissions yet. As soon as someone sends one of your forms, the message appears here, even if its email could not be sent.', 'formwerk' );
	}

	/**
	 * Links above the table.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$base   = admin_url( 'admin.php?page=' . SubmissionsPage::SLUG );
		$failed = $this->store->query(
			array(
				'mail_status' => Store::MAIL_UNSENT,
				'per_page'    => 1,
			)
		)['total'];

		$views = array(
			'all' => sprintf( '<a href="%s"%s>%s</a>', esc_url( $base ), '' === self::filter_status() ? ' class="current" aria-current="page"' : '', esc_html__( 'All', 'formwerk' ) ),
		);
		if ( $failed > 0 ) {
			$views['failed'] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'mail_status', Store::MAIL_UNSENT, $base ) ),
				'' !== self::filter_status() ? ' class="current" aria-current="page"' : '',
				esc_html__( 'Email not sent', 'formwerk' ),
				esc_html( number_format_i18n( $failed ) )
			);
		}

		return $views;
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete permanently', 'formwerk' ) );
	}

	/**
	 * Form filter above the table.
	 *
	 * @param string $which Position: top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which || array() === $this->forms ) {
			return;
		}
		echo '<div class="alignleft actions">';
		printf( '<label class="screen-reader-text" for="formwerk-filter-form">%s</label>', esc_html__( 'Filter by form', 'formwerk' ) );
		echo '<select name="form_id" id="formwerk-filter-form">';
		printf( '<option value="0">%s</option>', esc_html__( 'All forms', 'formwerk' ) );
		foreach ( $this->forms as $id => $title ) {
			printf( '<option value="%d"%s>%s</option>', (int) $id, selected( self::filter_form(), $id, false ), esc_html( $title ) );
		}
		echo '</select>';
		submit_button( __( 'Filter', 'formwerk' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<label class="screen-reader-text" for="formwerk-cb-%1$d">%2$s</label><input type="checkbox" id="formwerk-cb-%1$d" name="submission[]" value="%1$d">',
			(int) $item['id'],
			esc_html(
				sprintf(
					/* translators: %s: date and time. */
					__( 'Select submission from %s', 'formwerk' ),
					self::date( $item )
				)
			)
		);
	}

	/**
	 * Date column with the row actions.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	protected function column_created( $item ) {
		$view    = SubmissionsPage::url( array( 'submission' => $item['id'] ) );
		$actions = array(
			'view' => sprintf( '<a href="%s">%s</a>', esc_url( $view ), esc_html__( 'View', 'formwerk' ) ),
		);
		if ( isset( $this->forms[ $item['form_id'] ] ) ) {
			$actions['resend'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( SubmissionsPage::resend_url( (int) $item['id'] ) ),
				esc_html( Store::MAIL_SENT === $item['mail_status'] ? __( 'Send email again', 'formwerk' ) : __( 'Send email', 'formwerk' ) )
			);
		}
		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete">%s</a>',
			esc_url( SubmissionsPage::confirm_url( array( (int) $item['id'] ) ) ),
			esc_html__( 'Delete', 'formwerk' )
		);

		// On narrow screens only this column is visible, so it also says when the email did not go out.
		$warning = Store::is_unsent( $item )
			? ' <span class="formwerk-result is-failed">· ' . esc_html__( 'Email not sent', 'formwerk' ) . '</span>'
			: '';

		return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>%s%s', esc_url( $view ), esc_html( self::date( $item ) ), $warning, $this->row_actions( $actions ) );
	}

	/**
	 * Form column.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	protected function column_form( $item ) {
		if ( isset( $this->forms[ $item['form_id'] ] ) ) {
			return esc_html( $this->forms[ $item['form_id'] ] );
		}

		return esc_html(
			sprintf(
				/* translators: %s: form name. */
				__( '%s (form deleted)', 'formwerk' ),
				'' !== $item['form_title'] ? $item['form_title'] : '#' . $item['form_id']
			)
		);
	}

	/**
	 * First answers of the submission.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	protected function column_summary( $item ) {
		return esc_html( self::summary( $item ) );
	}

	/**
	 * The first few answers in one line, to tell submissions apart.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	public static function summary( array $item ): string {
		$parts = array();
		foreach ( $item['fields'] as $field ) {
			$text = Placeholders::format( $field, $item['values'][ $field['name'] ] ?? '' );
			if ( 'consent' === $field['type'] || '' === $text ) {
				continue;
			}
			$parts[] = wp_html_excerpt( (string) preg_replace( '/\s+/', ' ', $text ), 60, '…' );
			if ( count( $parts ) >= 3 ) {
				break;
			}
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Mail status column.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	protected function column_mail( $item ) {
		return self::mail_status( $item );
	}

	/**
	 * Mail status in words, with the reason when it failed.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	public static function mail_status( array $item ): string {
		if ( Store::MAIL_SENT === $item['mail_status'] ) {
			return esc_html__( 'Sent', 'formwerk' );
		}
		if ( Store::is_unsent( $item ) ) {
			$reason = '' !== $item['mail_error'] ? $item['mail_error'] : __( 'Sending was interrupted.', 'formwerk' );

			return sprintf(
				'<strong class="formwerk-result is-failed">%s</strong><br><span class="description">%s</span>',
				esc_html__( 'Not sent', 'formwerk' ),
				esc_html( $reason )
			);
		}

		return esc_html__( 'Being sent', 'formwerk' );
	}

	/**
	 * Date of a submission in the site's time zone and format.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	public static function date( array $item ): string {
		$time = strtotime( $item['created_at'] . ' UTC' );

		return false === $time ? (string) $item['created_at'] : wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $time );
	}
}
