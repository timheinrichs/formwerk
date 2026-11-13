<?php
/**
 * How long submissions are kept.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Submissions;

/**
 * Deletes submissions older than the retention period once a day.
 */
final class Retention {

	public const OPTION  = 'formwerk_retention_days';
	public const DEFAULT = 180;
	public const MAX     = 3650;
	public const HOOK    = 'formwerk_cleanup';

	public const NOTICE_OPTION = 'formwerk_retention_notice';

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
	 * Registers the daily clean-up.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'clean_up' ) );
		add_action( 'admin_notices', array( $this, 'explain_after_update' ) );
		add_action(
			'init',
			static function (): void {
				if ( ! wp_next_scheduled( self::HOOK ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
				}
			}
		);
	}

	/**
	 * After an update: say once that automatic deletion exists and is off, until a period is chosen.
	 */
	public function explain_after_update(): void {
		$screen = get_current_screen();
		if ( ! get_option( self::NOTICE_OPTION ) || ! $screen || ! str_contains( (string) $screen->id, 'formwerk' ) || ! current_user_can( \Formwerk\Plugin::capability() ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Formwerk can now delete old submissions automatically. On this website it is switched off, so all submissions are kept.', 'formwerk' ),
			esc_url( admin_url( 'admin.php?page=formwerk-submissions#formwerk-retention' ) ),
			esc_html__( 'Choose how long to keep submissions', 'formwerk' )
		);
	}

	/**
	 * Days submissions are kept; 0 keeps them until they are deleted by hand.
	 */
	public static function days(): int {
		$days = get_option( self::OPTION, self::DEFAULT );

		return is_numeric( $days ) ? max( 0, min( self::MAX, (int) $days ) ) : self::DEFAULT;
	}

	/**
	 * Saves the retention period.
	 *
	 * @param mixed $days Submitted value.
	 *
	 * @return bool Whether the value was valid.
	 */
	public static function save( $days ): bool {
		$text = is_scalar( $days ) ? trim( (string) $days ) : '';
		if ( 1 !== preg_match( '/^[0-9]{1,4}$/', $text ) || (int) $text > self::MAX ) {
			return false;
		}
		update_option( self::OPTION, (int) $text, false );
		delete_option( self::NOTICE_OPTION );

		return true;
	}

	/**
	 * Deletes what is older than the retention period.
	 */
	public function clean_up(): int {
		return $this->store->delete_older_than( self::days() );
	}
}
