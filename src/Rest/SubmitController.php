<?php
/**
 * Public REST endpoints of a form.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Rest;

use Formwerk\Submissions\Handler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Lets the form script fetch a fresh token and send a submission.
 *
 * These routes are open to everyone, like the forms themselves. The signed token,
 * the honeypot and the rate limit stand in for a nonce.
 */
final class SubmitController {

	/**
	 * Submission handler.
	 *
	 * @var Handler
	 */
	private Handler $handler;

	/**
	 * Constructor.
	 *
	 * @param Handler $handler Submission handler.
	 */
	public function __construct( Handler $handler ) {
		$this->handler = $handler;
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			SmtpController::NAMESPACE,
			'/forms/(?P<id>\d+)/token',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'token' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			SmtpController::NAMESPACE,
			'/forms/(?P<id>\d+)/submissions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Issues a token. Pages may be cached for longer than a token lives, so the script asks for a fresh one.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function token( WP_REST_Request $request ): WP_REST_Response {
		$response = new WP_REST_Response( array( 'token' => $this->handler->token( (int) $request['id'] ) ) );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );

		return $response;
	}

	/**
	 * Receives a submission.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function submit( WP_REST_Request $request ): WP_REST_Response {
		$form_id = (int) $request['id'];
		$result  = $this->handler->handle( $form_id, $request->get_body_params() );

		$body = array(
			'status'  => $result['status'],
			'message' => $result['message'],
			'errors'  => $result['errors'],
		);
		if ( ! empty( $result['renew'] ) ) {
			// The person filled in the form already; the new token can be used at once.
			$body['token'] = $this->handler->token( $form_id, true );
		}
		$response = new WP_REST_Response( $body, $result['http'] );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );

		return $response;
	}
}
