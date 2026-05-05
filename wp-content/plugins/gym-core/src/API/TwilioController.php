<?php
/**
 * Twilio test-message REST controller.
 *
 * Adds POST `gym/v1/twilio/test-message` so the SMS settings page can verify
 * the configured Twilio credentials end-to-end without leaving the admin UI.
 *
 * Auth: requires `manage_woocommerce` and a valid `wp_rest` nonce supplied
 * via the `X-WP-Nonce` header (the WP REST default).
 *
 * @package Gym_Core\API
 * @since   4.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\SMS\TwilioClient;

/**
 * Handles the admin-only "Send test SMS" endpoint.
 */
class TwilioController extends BaseController {

	/**
	 * Twilio API client.
	 *
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
			'/twilio/test-message',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_test_message' ),
				'permission_callback' => array( $this, 'permissions_manage' ),
			)
		);
	}

	/**
	 * Sends a one-line test message to the current admin user's billing phone.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_test_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return $this->error_response( 'rest_not_logged_in', __( 'Authentication required.', 'gym-core' ), 401 );
		}

		$phone = (string) get_user_meta( $user_id, 'billing_phone', true );
		$phone = TwilioClient::sanitize_phone( $phone );

		if ( '' === $phone ) {
			return $this->error_response(
				'no_phone_on_profile',
				__( 'Set a valid billing phone on your user profile before sending a test SMS.', 'gym-core' ),
				422
			);
		}

		$body   = __( 'Twilio test from Gym Core: credentials look good.', 'gym-core' );
		$result = $this->twilio->send( $phone, $body );

		if ( ! ( $result['success'] ?? false ) ) {
			return $this->error_response(
				'send_failed',
				(string) ( $result['error'] ?? __( 'Failed to send test SMS.', 'gym-core' ) ),
				502
			);
		}

		return $this->success_response(
			array(
				'sid'  => $result['sid'] ?? null,
				'to'   => $phone,
				'body' => $body,
			),
			null,
			201
		);
	}
}
