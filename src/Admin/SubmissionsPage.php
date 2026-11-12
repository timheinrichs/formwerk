<?php
/**
 * Admin page "Formwerk → Submissions".
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Admin;

use Formwerk\Forms\FormRepository;
use Formwerk\Forms\Placeholders;
use Formwerk\Plugin;
use Formwerk\Submissions\Handler;
use Formwerk\Submissions\Store;

/**
 * List, detail view, delete and "send email".
 */
final class SubmissionsPage {

	public const SLUG = 'formwerk-submissions';

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
	 * Submission handler, for resending mails.
	 *
	 * @var Handler
	 */
	private Handler $handler;

	/**
	 * The list, prepared before the page is printed.
	 *
	 * @var SubmissionsTable|null
	 */
	private ?SubmissionsTable $table = null;

	/**
	 * Number of submissions whose email did not go out, once per request.
	 *
	 * @var int|null
	 */
	private ?int $unsent = null;

	/**
	 * Constructor.
	 *
	 * @param Store          $store   Submission storage.
	 * @param FormRepository $forms   Form storage.
	 * @param Handler        $handler Submission handler.
	 */
	public function __construct( Store $store, FormRepository $forms, Handler $handler ) {
		$this->store   = $store;
		$this->forms   = $forms;
		$this->handler = $handler;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 10 );
		add_action( 'admin_notices', array( $this, 'warn_about_unsent' ) );
	}

	/**
	 * Adds the menu entry, with a count of emails that did not go out.
	 */
	public function add_menu(): void {
		$unsent = $this->unsent();
		$label  = __( 'Submissions', 'formwerk' );
		if ( $unsent > 0 ) {
			$label .= sprintf(
				' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></span>',
				$unsent,
				esc_html( number_format_i18n( $unsent ) ),
				esc_html(
					sprintf(
						/* translators: %s: number of emails. */
						_n( '%s email not sent', '%s emails not sent', $unsent, 'formwerk' ),
						number_format_i18n( $unsent )
					)
				)
			);
		}

		$hook = add_submenu_page( FormsPage::SLUG, __( 'Submissions', 'formwerk' ), $label, Plugin::capability(), self::SLUG, array( $this, 'render' ) );
		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'handle_actions' ) );
			add_action(
				'admin_print_styles-' . $hook,
				static function (): void {
					Screen::enqueue();
				}
			);
		}
	}

	/**
	 * On Formwerk's pages: say when notification emails did not go out.
	 */
	public function warn_about_unsent(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( (string) $screen->id, 'formwerk' ) || ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the current view.
		$viewing = isset( $_GET['page'] ) && self::SLUG === $_GET['page'] && ( isset( $_GET['submission'] ) || isset( $_GET['mail_status'] ) || isset( $_GET['confirm_delete'] ) );
		$unsent  = $this->unsent();
		if ( 0 === $unsent || $viewing ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: number of emails. */
					_n(
						'%s notification email could not be sent. The submission is saved.',
						'%s notification emails could not be sent. The submissions are saved.',
						$unsent,
						'formwerk'
					),
					number_format_i18n( $unsent )
				)
			),
			esc_url( self::url( array( 'mail_status' => Store::MAIL_UNSENT ) ) ),
			esc_html( _n( 'View submission', 'View submissions', $unsent, 'formwerk' ) )
		);
	}

	/**
	 * Address of the page with extra parameters.
	 *
	 * @param array<string, mixed> $args Query parameters.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	/**
	 * Address that sends the notification of one submission. The nonce only fits this action and this submission.
	 *
	 * @param int $id Submission ID.
	 */
	public static function resend_url( int $id ): string {
		return wp_nonce_url(
			self::url(
				array(
					'formwerk_action' => 'resend',
					'submission'      => $id,
				)
			),
			'formwerk_resend_' . $id
		);
	}

	/**
	 * Address of the page that asks before deleting.
	 *
	 * @param int[] $ids Submission IDs.
	 */
	public static function confirm_url( array $ids ): string {
		return self::url( array( 'confirm_delete' => implode( ',', array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Performs actions before the page is printed, then reloads without them in the address.
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- Nonces are checked below, before anything is changed.
		$action = isset( $_REQUEST['formwerk_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['formwerk_action'] ) ) : '';
		$ids    = isset( $_REQUEST['submission'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['submission'] ) ) ) ) : array();
		$bulk   = '';
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( isset( $_REQUEST[ $key ] ) && 'delete' === $_REQUEST[ $key ] ) {
				$bulk = 'delete';
			}
		}
		$filtering = isset( $_REQUEST['filter_action'] );
		// phpcs:enable

		// Bulk delete: never at once, always via the page that asks first.
		if ( '' === $action && 'delete' === $bulk && ! $filtering && array() !== $ids ) {
			check_admin_referer( 'bulk-submissions' );
			wp_safe_redirect( self::confirm_url( $ids ) );
			exit;
		}

		if ( 'resend' === $action && array() !== $ids ) {
			check_admin_referer( 'formwerk_resend_' . $ids[0] );
			$result = $this->handler->resend( $ids[0] );
			set_transient( 'formwerk_resend_' . get_current_user_id(), $result, 60 );
			wp_safe_redirect(
				self::url(
					array(
						'submission' => $ids[0],
						'resent'     => 1,
					)
				)
			);
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked right here.
		if ( 'delete' === $action && array() !== $ids && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( self::delete_nonce( $ids ) );
			$deleted = $this->store->delete( $ids );
			wp_safe_redirect( self::url( array( 'deleted' => $deleted ) ) );
			exit;
		}

		// The list form sends nonce and referrer along with every filter; keep the address short.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only removes parameters.
		if ( ! empty( $_GET['_wp_http_referer'] ) || ! empty( $_GET['_wpnonce'] ) || $filtering ) {
			$keep = array( 'page', 'form_id', 'mail_status', 'paged' );
			$args = array();
			foreach ( $keep as $key ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter values.
				if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] && ! ( $filtering && 'paged' === $key ) ) {
					$args[ $key ] = sanitize_key( wp_unslash( $_GET[ $key ] ) );
				}
			}
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view check.
		if ( ! isset( $_GET['submission'] ) && ! isset( $_GET['confirm_delete'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
			$this->table = new SubmissionsTable( $this->store, $this->form_titles() );
			$this->table->prepare_items();

			// A filter with fewer pages than the page that was open: go to its last page.
			$pages = (int) $this->table->get_pagination_arg( 'total_pages' );
			if ( $this->table->get_pagenum() > max( 1, $pages ) ) {
				wp_safe_redirect( remove_query_arg( 'paged', add_query_arg( array() ) ) );
				exit;
			}
		}
	}

	/**
	 * Prints the page: list, detail view or delete confirmation.
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only views.
		$confirm = isset( $_GET['confirm_delete'] ) ? array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['confirm_delete'] ) ) ) ) ) ) : array();
		$detail  = isset( $_GET['submission'] ) && ! is_array( $_GET['submission'] ) ? absint( wp_unslash( $_GET['submission'] ) ) : 0;
		// phpcs:enable

		echo '<div class="wrap formwerk-submissions">';
		if ( array() !== $confirm ) {
			$this->render_confirm( $confirm );
		} elseif ( $detail > 0 ) {
			$this->render_detail( $this->store->find( $detail ) );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	/**
	 * The list.
	 */
	private function render_list(): void {
		if ( null === $this->table ) {
			return;
		}
		printf( '<h1>%s</h1>', esc_html__( 'Submissions', 'formwerk' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		if ( isset( $_GET['deleted'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
			$deleted = absint( wp_unslash( $_GET['deleted'] ) );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: number of submissions. */
						_n( '%s submission deleted.', '%s submissions deleted.', $deleted, 'formwerk' ),
						number_format_i18n( $deleted )
					)
				)
			);
		}
		$this->table->views();
		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s">', esc_attr( self::SLUG ) );
		if ( '' !== SubmissionsTable::filter_status() ) {
			printf( '<input type="hidden" name="mail_status" value="%s">', esc_attr( SubmissionsTable::filter_status() ) );
		}
		$this->table->display();
		echo '</form>';
	}

	/**
	 * One submission with all its answers.
	 *
	 * @param array<string, mixed>|null $item Submission, null when it does not exist.
	 */
	private function render_detail( ?array $item ): void {
		printf( '<p><a href="%s">← %s</a></p>', esc_url( self::url() ), esc_html__( 'All submissions', 'formwerk' ) );
		if ( null === $item ) {
			printf( '<h1>%s</h1><p>%s</p>', esc_html__( 'Submission not found', 'formwerk' ), esc_html__( 'This submission does not exist any more. It may have been deleted.', 'formwerk' ) );
			return;
		}
		$titles      = $this->form_titles();
		$form_exists = isset( $titles[ $item['form_id'] ] );
		$send_label  = Store::MAIL_SENT === $item['mail_status'] ? __( 'Send email again', 'formwerk' ) : __( 'Send email', 'formwerk' );

		printf(
			'<h1>%s</h1>',
			esc_html(
				sprintf(
					/* translators: %s: date and time. */
					__( 'Submission from %s', 'formwerk' ),
					SubmissionsTable::date( $item )
				)
			)
		);

		$result = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		if ( isset( $_GET['resent'] ) ) {
			$result = get_transient( 'formwerk_resend_' . get_current_user_id() );
			delete_transient( 'formwerk_resend_' . get_current_user_id() );
		}
		if ( is_array( $result ) && $result['sent'] ) {
			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html__( 'The email was sent.', 'formwerk' ) );
		} elseif ( Store::is_unsent( $item ) ) {
			// The most important fact about this submission goes first.
			echo '<div class="notice notice-error formwerk-unsent">';
			printf( '<p><strong>%s</strong></p>', esc_html__( 'The notification email for this submission was not sent. The submission itself is saved.', 'formwerk' ) );
			if ( '' !== $item['mail_error'] ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: error message. */
							__( 'Reason: %s', 'formwerk' ),
							self::sentence( $item['mail_error'] )
						)
					)
				);
			}
			echo '<p>';
			if ( $form_exists ) {
				printf( '<a class="button button-primary" href="%s">%s</a> ', esc_url( self::resend_url( (int) $item['id'] ) ), esc_html( $send_label ) );
			}
			printf( '<a class="button" href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . EmailPage::SLUG ) ), esc_html__( 'Check the email settings', 'formwerk' ) );
			echo '</p></div>';
		}//end if

		$context = array(
			'yes'   => __( 'Yes', 'formwerk' ),
			'no'    => __( 'No', 'formwerk' ),
			'empty' => '–',
		);
		echo '<table class="widefat striped formwerk-detail"><tbody>';
		foreach ( $item['fields'] as $field ) {
			$value = $item['values'][ $field['name'] ] ?? '';
			$text  = Placeholders::format( $field, $value, $context );
			if ( 'email' === $field['type'] && is_string( $value ) && is_email( $value ) ) {
				$cell = sprintf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $value ), esc_html( $value ) );
			} elseif ( 'date' === $field['type'] && is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				$cell = esc_html( (string) wp_date( (string) get_option( 'date_format' ), (int) strtotime( $value . ' 12:00 UTC' ) ) );
			} else {
				$cell = nl2br( esc_html( $text ) );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $cell is escaped above.
			printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( (string) $field['label'] ), $cell );
		}
		echo '</tbody></table>';

		printf( '<h2>%s</h2>', esc_html__( 'Details', 'formwerk' ) );
		echo '<table class="widefat striped formwerk-detail"><tbody>';
		printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html__( 'Form', 'formwerk' ), esc_html( $this->form_label( $item, $titles ) ) );
		if ( '' !== $item['page'] ) {
			printf( '<tr><th scope="row">%s</th><td><a href="%2$s">%2$s</a></td></tr>', esc_html__( 'Sent from', 'formwerk' ), esc_url( $item['page'] ) );
		}
		printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html__( 'Language', 'formwerk' ), esc_html( self::language( $item['lang'] ) ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in SubmissionsTable::mail_status().
		printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html__( 'Notification email', 'formwerk' ), SubmissionsTable::mail_status( $item ) );
		echo '</tbody></table>';

		echo '<p class="formwerk-detail-actions">';
		if ( $form_exists ) {
			printf( '<a class="button" href="%s">%s</a> ', esc_url( self::resend_url( (int) $item['id'] ) ), esc_html( $send_label ) );
		}
		printf( '<a class="button button-link-delete" href="%s">%s</a>', esc_url( self::confirm_url( array( (int) $item['id'] ) ) ), esc_html__( 'Delete submission', 'formwerk' ) );
		echo '</p>';
	}

	/**
	 * Asks before submissions are deleted for good, and names them.
	 *
	 * @param int[] $ids Submission IDs.
	 */
	private function render_confirm( array $ids ): void {
		$items = array_values( array_filter( array_map( array( $this->store, 'find' ), array_slice( $ids, 0, 200 ) ) ) );
		if ( array() === $items ) {
			printf( '<h1>%s</h1><p>%s</p>', esc_html__( 'Nothing to delete', 'formwerk' ), esc_html__( 'These submissions do not exist any more.', 'formwerk' ) );
			printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( self::url() ), esc_html__( 'All submissions', 'formwerk' ) );
			return;
		}
		$count  = count( $items );
		$titles = $this->form_titles();

		printf(
			'<h1>%s</h1>',
			esc_html(
				1 === $count
					? __( 'Delete this submission?', 'formwerk' )
					/* translators: %s: number of submissions. */
					: sprintf( __( 'Delete %s submissions?', 'formwerk' ), number_format_i18n( $count ) )
			)
		);
		printf( '<p>%s</p>', esc_html( _n( 'This submission will be deleted permanently. This cannot be undone.', 'These submissions will be deleted permanently. This cannot be undone.', $count, 'formwerk' ) ) );

		echo '<ul class="formwerk-confirm-list">';
		foreach ( array_slice( $items, 0, 20 ) as $item ) {
			printf(
				'<li><strong>%s</strong> · %s · %s</li>',
				esc_html( SubmissionsTable::date( $item ) ),
				esc_html( $this->form_label( $item, $titles ) ),
				esc_html( SubmissionsTable::summary( $item ) )
			);
		}
		if ( $count > 20 ) {
			/* translators: %s: number of further submissions. */
			printf( '<li>%s</li>', esc_html( sprintf( __( 'and %s more', 'formwerk' ), number_format_i18n( $count - 20 ) ) ) );
		}
		echo '</ul>';

		$keep = array_map( static fn( array $item ): int => (int) $item['id'], $items );
		printf( '<form method="post" action="%s">', esc_url( self::url() ) );
		wp_nonce_field( self::delete_nonce( $keep ) );
		echo '<input type="hidden" name="formwerk_action" value="delete">';
		foreach ( $keep as $id ) {
			printf( '<input type="hidden" name="submission[]" value="%d">', (int) $id );
		}
		printf(
			'<p><button type="submit" class="button button-primary">%s</button> <a class="button" href="%s">%s</a></p>',
			esc_html(
				1 === $count
					? __( 'Delete submission', 'formwerk' )
					/* translators: %s: number of submissions. */
					: sprintf( __( 'Delete %s submissions', 'formwerk' ), number_format_i18n( $count ) )
			),
			esc_url( 1 === $count ? self::url( array( 'submission' => $keep[0] ) ) : self::url() ),
			esc_html__( 'Cancel', 'formwerk' )
		);
		echo '</form>';
	}

	/**
	 * Nonce action for deleting exactly these submissions.
	 *
	 * @param int[] $ids Submission IDs.
	 */
	private static function delete_nonce( array $ids ): string {
		$ids = array_map( 'intval', $ids );
		sort( $ids );

		return 'formwerk_delete_' . md5( implode( ',', $ids ) );
	}

	/**
	 * Name of the form a submission came from, also when the form was deleted.
	 *
	 * @param array<string, mixed> $item   Submission.
	 * @param array<int, string>   $titles Titles of the existing forms.
	 */
	private function form_label( array $item, array $titles ): string {
		return $titles[ $item['form_id'] ] ?? sprintf(
			/* translators: %s: form name. */
			__( '%s (form deleted)', 'formwerk' ),
			'' !== $item['form_title'] ? $item['form_title'] : '#' . $item['form_id']
		);
	}

	/**
	 * Name of a language, e.g. "German (Germany)" for de_DE.
	 *
	 * @param string $locale Locale code.
	 */
	private static function language( string $locale ): string {
		if ( '' === $locale || ! class_exists( '\Locale' ) ) {
			return $locale;
		}
		$name = \Locale::getDisplayName( $locale, get_user_locale() );

		return is_string( $name ) && '' !== $name ? $name : $locale;
	}

	/**
	 * Ends a message with a full stop, so a following sentence does not run into it.
	 *
	 * @param string $text Message.
	 */
	private static function sentence( string $text ): string {
		$text = trim( $text );

		return '' === $text || 1 === preg_match( '/[.!?]$/u', $text ) ? $text : $text . '.';
	}

	/**
	 * Number of submissions whose email did not go out.
	 */
	private function unsent(): int {
		if ( null === $this->unsent ) {
			$this->unsent = $this->store->count_unsent();
		}

		return $this->unsent;
	}

	/**
	 * Titles of the existing forms, by ID.
	 *
	 * @return array<int, string>
	 */
	private function form_titles(): array {
		$titles = array();
		foreach ( $this->forms->all() as $form ) {
			$titles[ $form['id'] ] = $form['title'];
		}

		return $titles;
	}
}
