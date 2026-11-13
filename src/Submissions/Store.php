<?php
/**
 * Stores submissions.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Submissions;

/**
 * Submissions live in their own table. A row is written before any email is sent,
 * so a submission exists in the backend even when the mail fails.
 */
final class Store {

	public const MAIL_PENDING = 'pending';
	public const MAIL_SENT    = 'sent';
	public const MAIL_FAILED  = 'failed';
	public const MAIL_UNSENT  = 'unsent';

	private const DB_VERSION        = '2';
	private const DB_VERSION_OPTION = 'formwerk_db_version';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'formwerk_submissions';
	}

	/**
	 * Creates or updates the table when needed. Cheap to call on every request.
	 */
	public function maybe_install(): void {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				form_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				lang varchar(20) NOT NULL DEFAULT '',
				data longtext NOT NULL,
				mail_status varchar(20) NOT NULL DEFAULT 'pending',
				mail_error text NOT NULL,
				confirm_status varchar(20) NOT NULL DEFAULT 'off',
				confirm_error text NOT NULL,
				PRIMARY KEY  (id),
				KEY form_created (form_id, created_at),
				KEY created_at (created_at)
			) {$charset};"
		);
		// Only remember the version once the newest column exists: an existing table counts as
		// "there" even when adding columns failed, and then every new submission would fail.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check of the plugin's own table.
		if ( null === $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'confirm_status'" ) ) {
			return;
		}
		$previous = get_option( self::DB_VERSION_OPTION );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );

		// Sites updating from 0.2.0 already have submissions. Automatic deletion is new: it stays
		// off until someone chooses a period, so an update never deletes anything on its own.
		if ( false !== $previous && false === get_option( Retention::OPTION ) ) {
			add_option( Retention::OPTION, 0, '', false );
			add_option( Retention::NOTICE_OPTION, 1, '', false );
		}
	}

	/**
	 * Whether submissions can be stored.
	 */
	public function is_ready(): bool {
		return self::DB_VERSION === get_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		// A deleted site of a network takes its submissions with it.
		add_filter(
			'wpmu_drop_tables',
			static function ( $tables ) {
				$tables   = is_array( $tables ) ? $tables : array();
				$tables[] = self::table();

				return $tables;
			}
		);
		add_action( 'admin_notices', array( $this, 'warn_when_missing' ) );
	}

	/**
	 * Tells administrators when the table could not be created.
	 */
	public function warn_when_missing(): void {
		if ( $this->is_ready() || ! current_user_can( \Formwerk\Plugin::capability() ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>Formwerk:</strong> %s</p></div>',
			esc_html__( 'The database table for submissions could not be created or updated, so forms cannot be sent. Please ask your web host whether the database user may create and change tables.', 'formwerk' )
		);
	}

	/**
	 * Saves a submission.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Field snapshot, values and page.
	 * @param string               $lang    Language of the page the form was sent from.
	 *
	 * @return int ID of the new row, 0 when it could not be written.
	 */
	public function insert( int $form_id, array $data, string $lang ): int {
		global $wpdb;

		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table.
		$written = $wpdb->insert(
			self::table(),
			array(
				'form_id'        => $form_id,
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
				'lang'           => substr( $lang, 0, 20 ),
				'data'           => $json,
				'mail_status'    => self::MAIL_PENDING,
				'mail_error'     => '',
				'confirm_status' => 'off',
				'confirm_error'  => '',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return 1 === $written ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Records the result of the notification mail.
	 *
	 * @param int    $id     Submission ID.
	 * @param string $status One of the MAIL_* constants.
	 * @param string $error  Error message when the mail failed.
	 */
	public function set_mail_status( int $id, string $status, string $error = '' ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$wpdb->update(
			self::table(),
			array(
				'mail_status' => $status,
				'mail_error'  => mb_substr( $error, 0, 1000 ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Records the result of the confirmation to the person who sent the form.
	 *
	 * @param int    $id     Submission ID.
	 * @param string $status MAIL_SENT, MAIL_FAILED, or one of the Notifier::CONFIRM_* values.
	 * @param string $error  Error message or reason.
	 */
	public function set_confirm_status( int $id, string $status, string $error = '' ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$wpdb->update(
			self::table(),
			array(
				'confirm_status' => substr( $status, 0, 20 ),
				'confirm_error'  => mb_substr( $error, 0, 1000 ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Loads one submission.
	 *
	 * @param int $id Submission ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own table; the table name is not user input.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Lists submissions, newest first.
	 *
	 * @param array{form_id?: int, mail_status?: string, per_page?: int, page?: int} $args Filter and paging.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$where  = array( '1 = 1' );
		$values = array();
		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$values[] = (int) $args['form_id'];
		}
		if ( self::MAIL_UNSENT === ( $args['mail_status'] ?? '' ) ) {
			$where[]  = '(mail_status = %s OR (mail_status = %s AND created_at < %s) OR confirm_status = %s)';
			$values[] = self::MAIL_FAILED;
			$values[] = self::MAIL_PENDING;
			$values[] = self::stale_before();
			$values[] = self::MAIL_FAILED;
		} elseif ( ! empty( $args['mail_status'] ) ) {
			$where[]  = 'mail_status = %s';
			$values[] = (string) $args['mail_status'];
		}
		$per_page = max( 1, min( 500, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( max( 1, (int) ( $args['page'] ?? 1 ) ) - 1 ) * $per_page;
		$sql      = ' FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );

		// Reading everything in order (export): continue after the last ID, which stays fast and
		// misses nothing when submissions arrive or are deleted in between. No total needed.
		if ( isset( $args['after_id'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own table; fixed fragments and placeholders only.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT *' . $sql . ' AND id > %d ORDER BY id ASC LIMIT %d', array_merge( $values, array( (int) $args['after_id'], $per_page ) ) ), ARRAY_A );

			return array(
				'items' => array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() ),
				'total' => -1,
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own table; the SQL consists of fixed fragments and placeholders only.
		$total = (int) $wpdb->get_var( array() === $values ? 'SELECT COUNT(*)' . $sql : $wpdb->prepare( 'SELECT COUNT(*)' . $sql, $values ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT *' . $sql . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', array_merge( $values, array( $per_page, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() ),
			'total' => $total,
		);
	}

	/**
	 * Number of submissions whose notification did not go out.
	 */
	public function count_unsent(): int {
		if ( ! $this->is_ready() ) {
			return 0;
		}

		return $this->query(
			array(
				'mail_status' => self::MAIL_UNSENT,
				'per_page'    => 1,
			)
		)['total'];
	}

	/**
	 * Whether the notification of a submission did not go out.
	 *
	 * A submission still marked "pending" after a few minutes was interrupted while sending.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	public static function is_unsent( array $item ): bool {
		return self::notification_unsent( $item ) || self::MAIL_FAILED === ( $item['confirm_status'] ?? '' );
	}

	/**
	 * Whether the notification to the website did not go out.
	 *
	 * @param array<string, mixed> $item Submission.
	 */
	public static function notification_unsent( array $item ): bool {
		return self::MAIL_FAILED === $item['mail_status']
			|| ( self::MAIL_PENDING === $item['mail_status'] && $item['created_at'] < self::stale_before() );
	}

	/**
	 * Time (UTC) before which a pending mail counts as interrupted.
	 */
	private static function stale_before(): string {
		return gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Deletes submissions that arrived more than a number of days ago.
	 *
	 * @param int $days Age in days; 0 or less deletes nothing.
	 *
	 * @return int Number of deleted rows.
	 */
	public function delete_older_than( int $days ): int {
		if ( $days <= 0 || ! $this->is_ready() ) {
			return 0;
		}
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own table; the table name is not user input.
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE created_at < %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );

		return (int) $deleted;
	}

	/**
	 * Submissions in which a person entered an email address, for the privacy tools.
	 *
	 * @param string $email    Email address.
	 * @param int    $page     Page, starting at 1.
	 * @param int    $per_page Rows per page.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_by_email( string $email, int $page = 1, int $per_page = 50 ): array {
		$email = strtolower( trim( $email ) );
		if ( '' === $email || ! $this->is_ready() ) {
			return array();
		}
		global $wpdb;

		// The JSON holds the address as "address"; the database narrows down, PHP checks exactly.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own table; the table name is not user input.
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE data LIKE %s ORDER BY id ASC', '%' . $wpdb->esc_like( '"' . $email . '"' ) . '%' ), ARRAY_A );
		$found = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$item = $this->hydrate( $row );
			foreach ( $item['fields'] as $field ) {
				$value = $item['values'][ $field['name'] ] ?? '';
				if ( 'email' === ( $field['type'] ?? '' ) && is_string( $value ) && strtolower( trim( $value ) ) === $email ) {
					$found[] = $item;
					break;
				}
			}
		}

		return array_slice( $found, ( max( 1, $page ) - 1 ) * $per_page, $per_page );
	}

	/**
	 * Deletes submissions.
	 *
	 * @param int[] $ids Submission IDs.
	 *
	 * @return int Number of deleted rows.
	 */
	public function delete( array $ids ): int {
		global $wpdb;

		$deleted = 0;
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
			$deleted += (int) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		}

		return $deleted;
	}

	/**
	 * Turns a database row into a submission.
	 *
	 * @param array<string, mixed> $row Database row.
	 *
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$data = json_decode( (string) $row['data'], true );
		$data = is_array( $data ) ? $data : array();

		return array(
			'id'             => (int) $row['id'],
			'form_id'        => (int) $row['form_id'],
			'created_at'     => (string) $row['created_at'],
			'lang'           => (string) $row['lang'],
			'fields'         => isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : array(),
			'values'         => isset( $data['values'] ) && is_array( $data['values'] ) ? $data['values'] : array(),
			'page'           => isset( $data['page'] ) && is_string( $data['page'] ) ? $data['page'] : '',
			'form_title'     => isset( $data['form_title'] ) && is_string( $data['form_title'] ) ? $data['form_title'] : '',
			'confirm_to'     => isset( $data['confirm_to'] ) && is_string( $data['confirm_to'] ) ? $data['confirm_to'] : '',
			'mail_status'    => (string) $row['mail_status'],
			'mail_error'     => (string) $row['mail_error'],
			// Rows from before the confirmation existed have no such columns until the table is updated.
			'confirm_status' => (string) ( $row['confirm_status'] ?? 'off' ),
			'confirm_error'  => (string) ( $row['confirm_error'] ?? '' ),
		);
	}
}
