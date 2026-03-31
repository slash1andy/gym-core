<?php
/**
 * SMS REST controller.
 *
 * @package Gym_Core\API
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\SMS\TwilioClient;
use Gym_Core\SMS\MessageTemplates;

/**
 * Handles REST endpoints for SMS sending and conversation history.
 *
 * Routes:
 *   POST /gym/v1/sms/send                       Send an SMS
 *   GET  /gym/v1/sms/templates                   List available templates
 *   GET  /gym/v1/sms/conversations/{contact_id}  Conversation history
 *
 * Note: The inbound Twilio webhook is registered by InboundHandler separately
 * since it uses Twilio signature auth rather than WP user auth.
 */
class SMSController extends BaseController {

	/**
	 * @var TwilioClient
	 */
	private TwilioClient $twilio;

	/**
	 * Constructor.
	 *
	 * @param TwilioClient $twilio Twilio API client.
	 */
	public function __construct( TwilioClient $twilio ) {
		parent::__construct();
		$this->twilio = $twilio;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/sms/send',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_sms' ),
				'permission_callback' => array( $this, 'permissions_send_sms' ),
				'args'                => array(
					'phone'         => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Recipient phone number (E.164 format)', 'gym-core' ),
					),
					'message'       => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Message body (max 1600 chars). Required unless template_slug is provided.', 'gym-core' ),
					),
					'template_slug' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Use a predefined message template slug instead of raw message.', 'gym-core' ),
					),
					'variables'     => array(
						'type'        => 'object',
						'description' => __( 'Template variable substitutions (e.g., {"first_name": "John"}).', 'gym-core' ),
					),
					'contact_id'    => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'CRM contact ID for rate limiting and conversation tracking.', 'gym-core' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sms/templates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_templates' ),
				'permission_callback' => array( $this, 'permissions_send_sms' ),
			)
		);
	}

	/**
	 * Permission: manage_options or gym_send_sms.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_send_sms( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'gym_send_sms' ) ) {
			return true;
		}

		return $this->error_response( 'rest_forbidden', __( 'You do not have permission to send SMS.', 'gym-core' ), 403 );
	}

	/**
	 * Sends an SMS message.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_sms( \WP_REST_Request $request ) {
		$phone         = $request->get_param( 'phone' );
		$message       = $request->get_param( 'message' );
		$template_slug = $request->get_param( 'template_slug' );
		$variables     = $request->get_param( 'variables' ) ?: array();
		$contact_id    = $request->get_param( 'contact_id' );

		// Resolve message from template if provided.
		if ( $template_slug ) {
			$vars_array = is_array( $variables ) ? array_map( 'sanitize_text_field', $variables ) : array();
			$message    = MessageTemplates::render( $template_slug, $vars_array );

			if ( null === $message ) {
				return $this->error_response(
					'invalid_template',
					/* translators: %s: template slug */
					sprintf( __( 'Unknown template: %s', 'gym-core' ), $template_slug ),
					400
				);
			}
		}

		if ( empty( $message ) ) {
			return $this->error_response( 'missing_message', __( 'Message body or template_slug is required.', 'gym-core' ), 400 );
		}

		// Sanitize phone.
		$clean_phone = TwilioClient::sanitize_phone( $phone );
		if ( '' === $clean_phone ) {
			return $this->error_response( 'invalid_phone', __( 'Invalid phone number. Use E.164 format.', 'gym-core' ), 400 );
		}

		// Rate limit check.
		if ( $contact_id && $this->twilio->is_rate_limited( $contact_id ) ) {
			return $this->error_response(
				'rate_limited',
				__( 'SMS rate limit exceeded for this contact. Please wait before sending again.', 'gym-core' ),
				429
			);
		}

		// Send.
		$result = $this->twilio->send( $clean_phone, $message );

		if ( ! $result['success'] ) {
			return $this->error_response( 'send_failed', $result['error'] ?? __( 'Failed to send SMS.', 'gym-core' ), 502 );
		}

		// Record send for rate limiting.
		if ( $contact_id ) {
			$this->twilio->record_send( $contact_id );
		}

		return $this->success_response(
			array(
				'sid'     => $result['sid'],
				'to'      => $clean_phone,
				'body'    => $message,
				'sent_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			null,
			201
		);
	}

	/**
	 * Returns all available message templates.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_templates( \WP_REST_Request $request ): \WP_REST_Response {
		$templates = MessageTemplates::get_all();
		$result    = array();

		foreach ( $templates as $slug => $template ) {
			$result[] = array(
				'slug'        => $slug,
				'name'        => $template['name'],
				'body'        => $template['body'],
				'description' => $template['description'],
			);
		}

		return $this->success_response( $result );
	}
}
