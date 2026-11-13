<?php
/**
 * Shared parts of the React admin pages.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Admin;

use Formwerk\Plugin;

/**
 * Page shell and assets for pages that are rendered by the admin script.
 */
final class Screen {

	/**
	 * Prints the page shell; the content is rendered by JavaScript.
	 *
	 * @param string $title  Page heading.
	 * @param string $screen Name of the React screen.
	 */
	public static function render( string $title, string $screen ): void {
		?>
		<div class="wrap">
			<?php self::brand(); ?>
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( ! self::has_build() ) : ?>
				<div class="notice notice-error inline">
					<p>
						<?php esc_html_e( 'This page cannot be shown, because files of the Formwerk plugin are missing. If you do not maintain this website yourself, please contact the person who does.', 'formwerk' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'For developers: install the release ZIP from GitHub, or run "npm run build" in the plugin folder.', 'formwerk' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div data-formwerk-screen="<?php echo esc_attr( $screen ); ?>">
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
	 * The Formwerk logo above the page title. Decoration only: the title says where you are.
	 */
	public static function brand(): void {
		printf(
			'<div class="formwerk-brand"><img src="%s" alt="" width="105" height="24"></div>',
			esc_url( plugins_url( 'assets/logo/formwerk-logo.svg', FORMWERK_FILE ) )
		);
	}

	/**
	 * Loads the admin script and styles.
	 */
	public static function enqueue(): void {
		if ( ! self::has_build() ) {
			return;
		}
		$asset = require Plugin::path( 'build/admin.asset.php' );

		wp_enqueue_script( 'formwerk-admin', plugins_url( 'build/admin.js', FORMWERK_FILE ), $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'formwerk-admin', 'formwerk', Plugin::path( 'languages' ) );
		wp_add_inline_script(
			'formwerk-admin',
			'window.formwerkAdmin = ' . wp_json_encode( array( 'userEmail' => wp_get_current_user()->user_email ) ) . ';',
			'before'
		);

		wp_enqueue_style( 'formwerk-admin', plugins_url( 'build/admin.css', FORMWERK_FILE ), array( 'wp-components' ), $asset['version'] );
		wp_style_add_data( 'formwerk-admin', 'rtl', 'replace' );
	}

	/**
	 * Whether the compiled admin files exist.
	 */
	private static function has_build(): bool {
		foreach ( array( 'build/admin.asset.php', 'build/admin.js', 'build/admin.css' ) as $file ) {
			if ( ! is_readable( Plugin::path( $file ) ) ) {
				return false;
			}
		}

		return true;
	}
}
