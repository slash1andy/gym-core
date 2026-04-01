<?php
/**
 * Twilio inbound SMS webhook handler.
 *
 * Receives incoming SMS messages from Twilio, validates the request
 * signature, matches the sender to a CRM contact, stores the message,
 * and fires action hooks for downstream processing.
 *
 * @package Gym_Core
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\SMS;

/**
 * Handles inbound SMS via Twilio webhook.
 */
final class InboundHandler {

	/**
	 * Twilio client for signature validation.
	 *
	 * @var TwilioClient
	 */
	private TwilioClient $client;

	/**
	 * Constructor.
	 *
	 * @param TwilioClient $client Twilio API client.
	 */
	public function __construct( TwilioClient $client ) {
		$this->client = $client;
	}

	/**
	 * Registers the webhook endpoint.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_twiml_response' ), 10, 4 );
	}

	/**
	 * Bypasses WP REST JSON encoding for TwiML (application/xml) responses.
	 *
	 * Without this, WP REST infrastructure JSON-encodes the XML string,
	 * breaking Twilio's expected TwiML response format.
	 *
	 * @since 1.3.0
	 *
	 * @param bool              $served  Whether the request has already been served.
	 * @param \WP_HTTP_Response $result  Result to send to the client.
	 * @param \WP_REST_Request  $request Request used to generate the response.
	 * @param \WP_REST_Server   $server  Server instance.
	 * @return bool True if served, false to let WP handle it.
	 */
	public function serve_twiml_response( bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server ): bool {
		if ( $served ) {
			return $served;
		}

		$content_type = $result->get_headers()['Content-Type'] ?? '';

		if ( 'application/xml' !== $content_type ) {
			return $served;
		}

		// Send raw XML without JSON encoding.
		header( 'Content-Type: application/xml; charset=' . get_option( 'blog_charset' ) );
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- TwiML XML already escaped via esc_xml().

		return true;
	}

	/**
	 * Registers the Twilio webhook REST route.
	 *
	 * This endpoint does NOT require WordPress authentication — it validates
	 * via Twilio's request signature instead.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_webhook_route(): void {
		register_rest_route(
			'gym/v1',
			'/sms/webhook',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Auth via Twilio signature.
			)
		);
	}

	/**
	 * Handles an inbound SMS webhook request from Twilio.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request The webhook request.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		// Validate Twilio signature.
		$signature = $request->get_header( 'X-Twilio-Signature' );

		if ( empty( $signature ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Missing signature' ),
				403
			);
		}

		// Use a configurable webhook URL, falling back to rest_url().
		// Reconstructing from $_SERVER is unreliable behind proxies/CDNs.
		$url = get_option( 'gym_core_twilio_webhook_url', '' );

		if ( empty( $url ) ) {
			$url = rest_url( 'gym/v1/sms/webhook' );

			// Respect X-Forwarded-Proto for SSL detection behind proxies.
			$forwarded_proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) )
				: '';

			if ( 'https' === $forwarded_proto && 0 === strpos( $url, 'http://' ) ) {
				$url = 'https://' . substr( $url, 7 );
			}
		}
		$params = $request->get_body_params();

		if ( ! $this->client->validate_webhook_signature( $url, $params, $signature ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Invalid signature' ),
				403
			);
		}

		// Extract message data from Twilio's POST payload.
		$from    = sanitize_text_field( $params['From'] ?? '' );
		$to      = sanitize_text_field( $params['To'] ?? '' );
		$body    = wp_kses( $params['Body'] ?? '', array() );
		$sms_sid = sanitize_text_field( $params['MessageSid'] ?? '' );

		if ( '' === $from || '' === $body ) {
			return new \WP_REST_Response(
				array( 'error' => 'Missing required fields' ),
				400
			);
		}

		// Check for opt-out keywords (TCPA compliance).
		$opt_out_keywords = array( 'stop', 'unsubscribe', 'cancel', 'quit', 'end' );
		if ( in_array( strtolower( trim( $body ) ), $opt_out_keywords, true ) ) {
			$this->handle_opt_out( $from );

			return $this->twiml_response(
				__( 'You have been unsubscribed. Reply START to re-subscribe.', 'gym-core' )
			);
		}

		// Check for opt-in keyword.
		if ( 'start' === strtolower( trim( $body ) ) ) {
			$this->handle_opt_in( $from );

			return $this->twiml_response(
				__( 'Welcome back! You have been re-subscribed to SMS notifications.', 'gym-core' )
			);
		}

		/**
		 * Fires when an inbound SMS is received from a contact.
		 *
		 * @since 1.3.0
		 *
		 * @param string $from    Sender phone number (E.164).
		 * @param string $body    Message body.
		 * @param string $to      Recipient phone number (our Twilio number).
		 * @param string $sms_sid Twilio message SID.
		 */
		do_action( 'gym_core_sms_received', $from, $body, $to, $sms_sid );

		// Return empty TwiML (no auto-reply by default).
		return $this->twiml_response();
	}

	/**
	 * Handles an opt-out request (TCPA compliance).
	 *
	 * @param string $phone Phone number opting out.
	 * @return void
	 */
	private function handle_opt_out( string $phone ): void {
		/**
		 * Fires when a contact opts out of SMS.
		 *
		 * Consumers should update the contact's SMS preference in
		 * Jetpack CRM and/or user meta.
		 *
		 * @since 1.3.0
		 *
		 * @param string $phone Phone number that opted out.
		 */
		do_action( 'gym_core_sms_opt_out', $phone );
	}

	/**
	 * Handles an opt-in request.
	 *
	 * @param string $phone Phone number opting in.
	 * @return void
	 */
	private function handle_opt_in( string $phone ): void {
		/**
		 * Fires when a contact opts back in to SMS.
		 *
		 * @since 1.3.0
		 *
		 * @param string $phone Phone number that opted in.
		 */
		do_action( 'gym_core_sms_opt_in', $phone );
	}

	/**
	 * Returns a TwiML XML response.
	 *
	 * @param string $message Optional reply message. Empty = no reply.
	 * @return \WP_REST_Response
	 */
	private function twiml_response( string $message = '' ): \WP_REST_Response {
		$twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>';

		if ( '' !== $message ) {
			$twiml .= '<Message>' . esc_xml( $message ) . '</Message>';
		}

		$twiml .= '</Response>';

		$response = new \WP_REST_Response( $twiml, 200 );
		$response->header( 'Content-Type', 'application/xml' );

		return $response;
	}
}
