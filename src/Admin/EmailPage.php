<?php
/**
 * Admin page "Formwerk → Email".
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Admin;

use Formwerk\Plugin;
use Formwerk\Smtp\Config;
use Formwerk\Smtp\Settings;

/**
 * Adds the menu entry and loads the React screen.
 */
final class EmailPage {

	public const SLUG = 'formwerk-email';

	/**
	 * Settings source.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Hook suffix of the page, set once the menu is registered.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings source.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'warn_when_broken' ) );
	}

	/**
	 * Adds the menu entry.
	 */
	public function add_menu(): void {
		$this->hook = (string) add_submenu_page(
			FormsPage::SLUG,
			__( 'Email delivery', 'formwerk' ),
			__( 'Email', 'formwerk' ),
			Plugin::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Tells the people in charge, on every admin page, when the saved mail server is not being used.
	 */
	public function warn_when_broken(): void {
		$screen = get_current_screen();
		if ( ! current_user_can( Plugin::capability() )
			|| ( $screen && $screen->id === $this->hook )
			|| Config::STATUS_BROKEN !== $this->settings->config()->status()
		) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>Formwerk:</strong> %1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Emails from this website are currently not sent through your mail server, because its settings are incomplete or invalid. They are sent by the web server instead and may land in spam folders.', 'formwerk' ),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
			esc_html__( 'Check the email settings', 'formwerk' )
		);
	}

	/**
	 * Prints the page.
	 */
	public function render(): void {
		Screen::render( __( 'Email delivery', 'formwerk' ), 'smtp' );
	}

	/**
	 * Loads script and styles on this page only.
	 *
	 * @param string $hook Hook suffix of the current admin page.
	 */
	public function enqueue( $hook ): void {
		if ( '' !== $this->hook && $hook === $this->hook ) {
			Screen::enqueue();
		}
	}
}
