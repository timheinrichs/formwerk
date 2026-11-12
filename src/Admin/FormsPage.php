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
			'dashicons-feedback',
			58
		);
		add_submenu_page( self::SLUG, __( 'Forms', 'formwerk' ), __( 'Forms', 'formwerk' ), Plugin::capability(), self::SLUG, array( $this, 'render' ) );
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
