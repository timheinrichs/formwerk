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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'warn_when_broken' ) );
	}

	/**
	 * Adds the menu entry.
	 */
	public function add_menu(): void {
		$this->hook = (string) add_menu_page(
			__( 'Email delivery', 'formwerk' ),
			'Formwerk',
			Plugin::capability(),
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-feedback',
			58
		);
		add_submenu_page(
			self::SLUG,
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
	 * Prints the page shell; the content is rendered by JavaScript.
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Email delivery', 'formwerk' ); ?></h1>
			<?php if ( ! $this->has_build() ) : ?>
				<div class="notice notice-error inline">
					<p>
						<?php esc_html_e( 'This page cannot be shown, because files of the Formwerk plugin are missing. If you do not maintain this website yourself, please contact the person who does.', 'formwerk' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'For developers: install the release ZIP from GitHub, or run "npm run build" in the plugin folder.', 'formwerk' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div data-formwerk-screen="smtp">
					<noscript>
						<div class="notice notice-warning inline">
							<p><?php esc_html_e( 'This page needs JavaScript. Please enable it in your browser.', 'formwerk' ); ?></p>
						</div>
					</noscript>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Loads script and styles on this page only.
	 *
	 * @param string $hook Hook suffix of the current admin page.
	 */
	public function enqueue( $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook || ! $this->has_build() ) {
			return;
		}
		$asset = require Plugin::path( 'build/admin.asset.php' );

		wp_enqueue_script(
			'formwerk-admin',
			plugins_url( 'build/admin.js', FORMWERK_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'formwerk-admin', 'formwerk', Plugin::path( 'languages' ) );
		wp_add_inline_script(
			'formwerk-admin',
			'window.formwerkAdmin = ' . wp_json_encode(
				array( 'userEmail' => wp_get_current_user()->user_email )
			) . ';',
			'before'
		);

		wp_enqueue_style(
			'formwerk-admin',
			plugins_url( 'build/admin.css', FORMWERK_FILE ),
			array( 'wp-components' ),
			$asset['version']
		);
		wp_style_add_data( 'formwerk-admin', 'rtl', 'replace' );
	}

	/**
	 * Whether the compiled admin files exist.
	 */
	private function has_build(): bool {
		foreach ( array( 'build/admin.asset.php', 'build/admin.js', 'build/admin.css' ) as $file ) {
			if ( ! is_readable( Plugin::path( $file ) ) ) {
				return false;
			}
		}

		return true;
	}
}
