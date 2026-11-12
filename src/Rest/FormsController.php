<?php
/**
 * REST endpoints for the form builder.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Rest;

use Formwerk\Forms\FormRepository;
use Formwerk\Frontend\Controller as FrontendController;
use Formwerk\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * List, load, save and delete forms.
 */
final class FormsController {

	/**
	 * Form storage.
	 *
	 * @var FormRepository
	 */
	private FormRepository $forms;

	/**
	 * Constructor.
	 *
	 * @param FormRepository $forms Form storage.
	 */
	public function __construct( FormRepository $forms ) {
		$this->forms = $forms;
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		$permission = static function (): bool {
			return current_user_can( Plugin::capability() );
		};

		register_rest_route(
			SmtpController::NAMESPACE,
			'/forms',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $permission,
				),
			)
		);
		register_rest_route(
			SmtpController::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'settings' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => $permission,
					'args'                => array(
						'frontend_style' => array(
							'type'     => 'string',
							'enum'     => array( FrontendController::STYLE_THEME, FrontendController::STYLE_OWN ),
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			SmtpController::NAMESPACE,
			'/forms/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $permission,
				),
			)
		);
	}

	/**
	 * Site-wide settings of the forms.
	 */
	public function settings(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'frontend_style' => FrontendController::STYLE_OWN === get_option( FrontendController::STYLE_OPTION ) ? FrontendController::STYLE_OWN : FrontendController::STYLE_THEME,
			)
		);
	}

	/**
	 * Saves the site-wide settings.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function save_settings( WP_REST_Request $request ): WP_REST_Response {
		update_option( FrontendController::STYLE_OPTION, (string) $request['frontend_style'], true );

		return $this->settings();
	}

	/**
	 * Lists the forms.
	 */
	public function index(): WP_REST_Response {
		return new WP_REST_Response( array( 'forms' => $this->forms->all() ) );
	}

	/**
	 * Returns one form; ID 0 returns the defaults for a new form.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$form = 0 === $id ? $this->forms->blank() : $this->forms->find( $id );

		return null === $form ? $this->missing() : new WP_REST_Response( $form );
	}

	/**
	 * Creates or updates a form, or reports what blocks saving.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'formwerk_invalid_request', __( 'The form was not saved, because the request contained no data.', 'formwerk' ), array( 'status' => 400 ) );
		}
		$result = $this->forms->save( (int) $request['id'], $params );

		if ( array() !== $result['errors'] ) {
			$errors = array();
			foreach ( $result['errors'] as $error ) {
				$error['message'] = $this->error_message( $error );
				$errors[]         = $error;
			}
			$missing = 'form_missing' === $result['errors'][0]['code'];

			return new WP_Error(
				$missing ? 'formwerk_form_missing' : 'formwerk_invalid_form',
				$missing ? $errors[0]['message'] : __( 'The form was not saved. Please correct the points listed below.', 'formwerk' ),
				array(
					'status' => $missing ? 404 : 400,
					'errors' => $errors,
				)
			);
		}

		return new WP_REST_Response( $this->forms->find( $result['id'] ) );
	}

	/**
	 * Deletes a form. Its submissions stay.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete( WP_REST_Request $request ) {
		return $this->forms->delete( (int) $request['id'] ) ? new WP_REST_Response( array( 'deleted' => true ) ) : $this->missing();
	}

	/**
	 * Error for a form that does not exist.
	 */
	private function missing(): WP_Error {
		return new WP_Error( 'formwerk_form_missing', __( 'This form no longer exists. It may have been deleted in another tab.', 'formwerk' ), array( 'status' => 404 ) );
	}

	/**
	 * Says what is wrong and what to do about it.
	 *
	 * @param array<string, mixed> $error Error from the repository.
	 */
	private function error_message( array $error ): string {
		switch ( $error['code'] ) {
			case 'title_required':
				return __( 'Give the form a name, so you can find it again.', 'formwerk' );
			case 'fields_required':
				return __( 'Add at least one field that visitors can fill in.', 'formwerk' );
			case 'label_required':
				return __( 'Give this field a label, so visitors know what to enter.', 'formwerk' );
			case 'options_required':
				return __( 'Add at least one option to choose from.', 'formwerk' );
			case 'type_invalid':
				return __( 'This field type is not supported. Remove the field and add it again.', 'formwerk' );
			case 'too_many_fields':
				return __( 'A form can have at most 100 fields. Remove some fields.', 'formwerk' );
			case 'mail_to_required':
				return __( 'Enter the email address that should receive the submissions.', 'formwerk' );
			case 'mail_subject_required':
				return __( 'Enter a subject for the notification email.', 'formwerk' );
			case 'mail_body_required':
				return __( 'The email text is empty. Insert {all_fields} to send every field.', 'formwerk' );
			case 'mail_to_invalid':
				return __( 'Enter complete email addresses, separated by commas, for example info@example.com.', 'formwerk' );
			case 'mail_to_placeholder':
				return __( 'Only fixed email addresses can be used here, no placeholders. A confirmation to the person who sent the form will follow in a later version.', 'formwerk' );
			case 'placeholder_unknown':
				return sprintf(
					/* translators: %s: list of placeholders. */
					__( 'These placeholders do not match any field and would be sent as they are: %s. Remove them or insert a placeholder from the list.', 'formwerk' ),
					implode( ', ', (array) $error['placeholders'] )
				);
			case 'form_missing':
				return __( 'This form no longer exists. It may have been deleted in another tab.', 'formwerk' );
			default:
				return __( 'The form could not be saved. Please try again.', 'formwerk' );
		}//end switch
	}
}
