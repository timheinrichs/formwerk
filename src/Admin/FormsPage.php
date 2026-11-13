<?php
/**
 * Admin page "Formwerk → Forms".
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Admin;

use Formwerk\Plugin;

/**
 * Adds the top-level menu and the form builder page.
 */
final class FormsPage {

	public const SLUG = 'formwerk';

	/**
	 * Hook suffix of the page.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds the menu entries.
	 */
	public function add_menu(): void {
		$this->hook = (string) add_menu_page(
			__( 'Forms', 'formwerk' ),
			'Formwerk',
			Plugin::capability(),
			self::SLUG,
			array( $this, 'render' ),
			self::menu_icon(),
			58
		);
		add_submenu_page( self::SLUG, __( 'Forms', 'formwerk' ), __( 'Forms', 'formwerk' ), Plugin::capability(), self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * The Formwerk mark as a one-colour SVG. WordPress colours it like the other menu icons,
	 * in every admin colour scheme; the three form parts are cut out of the tile.
	 */
	private static function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path fill="black" fill-rule="evenodd" d="M14 0h36a14 14 0 0 1 14 14v36a14 14 0 0 1-14 14H14A14 14 0 0 1 0 50V14A14 14 0 0 1 14 0Zm3 15v9h30v-9Zm0 12.5v9h20v-9ZM17 40v9h9v-9Z"/></svg>';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress expects menu icons as base64 data URIs.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Prints the page.
	 */
	public function render(): void {
		Screen::render( __( 'Forms', 'formwerk' ), 'forms' );
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
