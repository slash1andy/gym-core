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
use Gym_Core\SMS\SmsOptOut;
use Gym_Core\Integrations\CrmSmsBridge;

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
	 * @var SmsOptOut
	 */
	private SmsOptOut $opt_out;

	/**
	 * Constructor.
	 *
	 * @param TwilioClient $twilio  Twilio API client.
	 * @param SmsOptOut    $opt_out TCPA opt-out store.
	 */
	public function __construct( TwilioClient $twilio, SmsOptOut $opt_out ) {
		parent::__construct();
		$this->twilio  = $twilio;
		$this->opt_out = $opt_out;
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
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'Recipient phone number (E.164 format)', 'gym-core' ),
					),
					'message'       => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'Message body (max 1600 chars). Required unless template_slug is provided.', 'gym-core' ),
					),
					'template_slug' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'Use a predefined message template slug instead of raw message.', 'gym-core' ),
					),
					'variables'     => array(
						'type'              => 'object',
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => static function ( $value ) {
							return is_array( $value ) ? map_deep( $value, 'sanitize_text_field' ) : array();
						},
						'description'       => __( 'Template variable substitutions (e.g., {"first_name": "John"}).', 'gym-core' ),
					),
					'contact_id'    => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
						'description'       => __( 'CRM contact ID for rate limiting and conversation tracking.', 'gym-core' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sms/conversations/(?P<contact_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversation_history' ),
				'permission_callback' => array( $this, 'permissions_send_sms' ),
				'args'                => array_merge(
					$this->pagination_route_args(),
					array(
						'contact_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
							'description'       => __( 'Jetpack CRM contact ID.', 'gym-core' ),
						),
					)
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

		// TCPA opt-out gate — must not send to opted-out numbers.
		if ( $this->opt_out->is_opted_out( $clean_phone ) ) {
			return $this->error_response(
				'sms_opted_out',
				__( 'User has opted out of SMS.', 'gym-core' ),
				422
			);
		}

		// Rate limit check — by contact_id if provided, otherwise by sending user.
		$rate_key = $contact_id ?: get_current_user_id();
		if ( $this->twilio->is_rate_limited( $rate_key ) ) {
			return $this->error_response(
				'rate_limited',
				__( 'SMS rate limit exceeded. Please wait before sending again.', 'gym-core' ),
				429
			);
		}

		// Send.
		$result = $this->twilio->send( $clean_phone, $message );

		if ( ! $result['success'] ) {
			return $this->error_response( 'send_failed', $result['error'] ?? __( 'Failed to send SMS.', 'gym-core' ), 502 );
		}

		// Record send for rate limiting.
		$this->twilio->record_send( $rate_key );

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
	 * Returns SMS conversation history for a CRM contact.
	 *
	 * Queries Jetpack CRM activity logs for sms_sent and sms_received entries.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_conversation_history( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! CrmSmsBridge::is_crm_active() ) {
			return $this->error_response( 'crm_unavailable', __( 'Jetpack CRM is not active.', 'gym-core' ), 503 );
		}

		$contact_id = (int) $request->get_param( 'contact_id' );
		$page       = (int) $request->get_param( 'page' );
		$per_page   = (int) $request->get_param( 'per_page' );
		$offset     = ( $page - 1 ) * $per_page;

		global $wpdb;
		$table = $wpdb->prefix . 'zbs_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE zbsl_objid = %d AND zbsl_objtype = 1 AND zbsl_type IN ('sms_sent', 'sms_received')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT zbsl_type AS type, zbsl_shortdesc AS title, zbsl_longdesc AS body, zbsl_created AS created_at
				FROM {$table}
				WHERE zbsl_objid = %d
				AND zbsl_objtype = 1
				AND zbsl_type IN ('sms_sent', 'sms_received')
				ORDER BY zbsl_created DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id,
				$per_page,
				$offset
			),
			ARRAY_A
		) ?: array();

		$messages = array();
		foreach ( $results as $row ) {
			$messages[] = array(
				'direction'  => 'sms_sent' === $row['type'] ? 'outbound' : 'inbound',
				'title'      => $row['title'],
				'body'       => $row['body'],
				'created_at' => $row['created_at'],
			);
		}

		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		return $this->success_response( $messages, $this->pagination_meta( $total, $total_pages, $page, $per_page ) );
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
