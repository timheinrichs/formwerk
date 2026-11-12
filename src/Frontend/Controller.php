<?php
/**
 * Puts forms on the site and receives submissions sent without JavaScript.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Frontend;

use Formwerk\Forms\FormRepository;
use Formwerk\Plugin;
use Formwerk\Submissions\Handler;
use Formwerk\Submissions\RateLimiter;

/**
 * Shortcode, assets and the fallback for browsers without JavaScript.
 */
final class Controller {

	public const SHORTCODE   = 'formwerk';
	public const FLASH_PARAM = 'formwerk_result';

	public const STYLE_OPTION = 'formwerk_frontend_style';
	public const STYLE_THEME  = 'theme';
	public const STYLE_OWN    = 'own';

	private const FLASH_PREFIX = 'formwerk_flash_';
	private const FLASH_TTL    = 300;

	/**
	 * Form storage.
	 *
	 * @var FormRepository
	 */
	private FormRepository $forms;

	/**
	 * Submission handler.
	 *
	 * @var Handler
	 */
	private Handler $handler;

	/**
	 * HTML renderer.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * How often each form was printed on this page so far.
	 *
	 * @var array<int, int>
	 */
	private array $instances = array();

	/**
	 * Result read from the address, once per request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $flash = null;

	/**
	 * Constructor.
	 *
	 * @param FormRepository $forms    Form storage.
	 * @param Handler        $handler  Submission handler.
	 * @param Renderer       $renderer HTML renderer.
	 */
	public function __construct( FormRepository $forms, Handler $handler, Renderer $renderer ) {
		$this->forms    = $forms;
		$this->handler  = $handler;
		$this->renderer = $renderer;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'admin_post_nopriv_formwerk_submit', array( $this, 'receive' ) );
		add_action( 'admin_post_formwerk_submit', array( $this, 'receive' ) );
		add_action( 'template_redirect', array( $this, 'prevent_caching' ) );
	}

	/**
	 * Makes script and styles known; they are only loaded on pages that show a form.
	 */
	public function register_assets(): void {
		if ( ! is_readable( Plugin::path( 'build/frontend.asset.php' ) ) ) {
			return;
		}
		$asset = require Plugin::path( 'build/frontend.asset.php' );

		wp_register_script( 'formwerk-frontend', plugins_url( 'build/frontend.js', FORMWERK_FILE ), $asset['dependencies'], $asset['version'], array( 'strategy' => 'defer' ) );
		wp_register_style( 'formwerk-frontend', plugins_url( 'build/frontend.css', FORMWERK_FILE ), array(), $asset['version'] );

		$block = array(
			'api_version'     => 3,
			'attributes'      => array(
				'formId' => array(
					'type'    => 'integer',
					'default' => 0,
				),
			),
			'style_handles'   => array( 'formwerk-frontend' ),
			'render_callback' => array( $this, 'block' ),
		);
		if ( is_readable( Plugin::path( 'build/block.asset.php' ) ) ) {
			$block_asset = require Plugin::path( 'build/block.asset.php' );
			wp_register_script( 'formwerk-block', plugins_url( 'build/block.js', FORMWERK_FILE ), $block_asset['dependencies'], $block_asset['version'], true );
			wp_set_script_translations( 'formwerk-block', 'formwerk', Plugin::path( 'languages' ) );
			$block['editor_script_handles'] = array( 'formwerk-block' );
		}
		register_block_type( 'formwerk/form', $block );
	}

	/**
	 * Block "Formwerk form".
	 *
	 * @param mixed $attributes Block attributes.
	 */
	public function block( $attributes ): string {
		$form_id = is_array( $attributes ) ? (int) ( $attributes['formId'] ?? 0 ) : 0;

		return 0 === $form_id ? '' : sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $this->render_form( $form_id ) );
	}

	/**
	 * Shortcode [formwerk id="12"].
	 *
	 * @param mixed $attributes Shortcode attributes.
	 */
	public function shortcode( $attributes ): string {
		$attributes = shortcode_atts( array( 'id' => 0 ), is_array( $attributes ) ? $attributes : array(), self::SHORTCODE );

		return $this->render_form( (int) $attributes['id'] );
	}

	/**
	 * HTML of a form, including the result of an attempt without JavaScript.
	 *
	 * @param int $form_id Form ID.
	 */
	public function render_form( int $form_id ): string {
		$form = $this->forms->find( $form_id );
		if ( null === $form ) {
			// Visitors see nothing; people who can fix it see why.
			return current_user_can( Plugin::capability() )
				? '<p class="formwerk-form__missing">' . esc_html(
					sprintf(
						/* translators: %d: form ID. */
						__( 'Formwerk: the form with the ID %d does not exist. Only you can see this note.', 'formwerk' ),
						$form_id
					)
				) . '</p>'
				: '';
		}
		wp_enqueue_script( 'formwerk-frontend' );
		wp_enqueue_style( 'formwerk-frontend' );

		// The same form can appear twice on a page (content and footer); each copy needs its own IDs.
		$this->instances[ $form_id ] = ( $this->instances[ $form_id ] ?? 0 ) + 1;
		$dom_id                      = 'formwerk-' . $form_id . ( $this->instances[ $form_id ] > 1 ? '-' . $this->instances[ $form_id ] : '' );

		$flash  = $this->flash();
		$state  = isset( $flash['target'] ) && $flash['target'] === $dom_id ? $flash : array();
		$styled = get_option( self::STYLE_OPTION ) === self::STYLE_OWN;

		return $this->renderer->render( $form, $dom_id, $this->handler->token( $form_id ), $state, $styled );
	}

	/**
	 * Keeps pages that show a personal result out of page caches.
	 */
	public function prevent_caching(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checks whether the parameter is present.
		if ( ! isset( $_GET[ self::FLASH_PARAM ] ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constant understood by caching plugins.
		}
		nocache_headers();
	}

	/**
	 * Receives a form sent without JavaScript and sends the browser back to the page.
	 */
	public function receive(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public form; protected by a signed token instead of a nonce, so pages can be cached.
		$request = wp_unslash( $_POST );
		// phpcs:enable
		$request = is_array( $request ) ? $request : array();
		$form_id = isset( $request['formwerk_form'] ) ? (int) $request['formwerk_form'] : 0;
		$result  = $this->handler->handle( $form_id, $request );
		$target  = isset( $request['formwerk_instance'] ) && is_string( $request['formwerk_instance'] ) && 1 === preg_match( '/^formwerk-' . $form_id . '(-[0-9]{1,3})?$/', $request['formwerk_instance'] )
			? $request['formwerk_instance']
			: 'formwerk-' . $form_id;

		// Back to the page the form is on. Browsers do not always send a referrer, so the address
		// the form sent along comes first.
		$page = isset( $request['formwerk_page'] ) && is_string( $request['formwerk_page'] ) ? esc_url_raw( $request['formwerk_page'] ) : '';
		$back = Handler::is_own_url( $page ) ? $page : (string) wp_get_referer();
		$back = '' !== $back ? remove_query_arg( self::FLASH_PARAM, $back ) : home_url( '/' );

		// No stored result for a form that does not exist, and not more than a few per connection:
		// anonymous requests must not be able to fill the database.
		if ( 404 === $result['http'] || ! $this->may_store_flash() ) {
			wp_safe_redirect( $back, 303 );
			exit;
		}

		$key = strtolower( wp_generate_password( 24, false ) );
		set_transient(
			self::FLASH_PREFIX . $key,
			array(
				'target'  => $target,
				'status'  => $result['status'],
				'message' => $result['message'],
				'errors'  => $result['errors'],
				'values'  => Handler::OK === $result['status'] ? array() : $result['values'],
			),
			self::FLASH_TTL
		);

		wp_safe_redirect( add_query_arg( self::FLASH_PARAM, $key, $back ) . '#' . $target, 303 );
		exit;
	}

	/**
	 * Result of the attempt the browser was just redirected from. It can be read once.
	 *
	 * @return array<string, mixed>
	 */
	private function flash(): array {
		if ( null !== $this->flash ) {
			return $this->flash;
		}
		$this->flash = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Random one-time key, read-only.
		$key = isset( $_GET[ self::FLASH_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::FLASH_PARAM ] ) ) : '';
		if ( '' === $key ) {
			return $this->flash;
		}
		$flash = get_transient( self::FLASH_PREFIX . $key );
		if ( is_array( $flash ) ) {
			delete_transient( self::FLASH_PREFIX . $key );
			$this->flash = $flash;
		}

		return $this->flash;
	}

	/**
	 * Allows a limited number of stored results per connection and time window.
	 */
	private function may_store_flash(): bool {
		$address = (string) apply_filters( 'formwerk_client_address', isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );

		return ( new RateLimiter() )->hit( 'flash|' . hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 600 ) <= 30;
	}
}
