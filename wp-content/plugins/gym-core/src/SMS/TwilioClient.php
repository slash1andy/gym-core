<?php
/**
 * Twilio API client for sending and receiving SMS.
 *
 * Uses the Twilio REST API directly via wp_remote_post() — no Twilio
 * PHP SDK dependency. Credentials are read from WooCommerce settings
 * (gym_core_twilio_*). Sending is queued via Action Scheduler for
 * rate limiting and retry.
 *
 * @package Gym_Core
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\SMS;

/**
 * Sends and validates SMS via the Twilio API.
 */
class TwilioClient {

	/**
	 * Twilio API base URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://api.twilio.com/2010-04-01';

	/**
	 * Per-request HTTP timeout in seconds.
	 *
	 * @var int
	 */
	private const HTTP_TIMEOUT = 10;

	/**
	 * Status codes that warrant a single retry with backoff.
	 *
	 * @var array<int, int>
	 */
	private const RETRY_STATUS_CODES = array( 429, 503 );

	/**
	 * Backoff before the second attempt, in microseconds.
	 *
	 * @var int
	 */
	private const RETRY_BACKOFF_USEC = 500000;

	/**
	 * Sends an SMS message via Twilio.
	 *
	 * @since 1.3.0
	 *
	 * @param string $to      Recipient phone number (E.164 format).
	 * @param string $body    Message body (max 1600 chars).
	 * @param string $from    Optional sender number override. Uses settings default if empty.
	 * @return array{success: bool, sid: string|null, error: string|null}
	 */
	public function send( string $to, string $body, string $from = '' ): array {
		$account_sid           = $this->get_account_sid();
		$auth_token            = $this->get_auth_token();
		$messaging_service_sid = $this->get_messaging_service_sid();
		$from_number           = '' !== $from ? $from : $this->get_from_number();

		// Either a Messaging Service SID OR a From number is required (Twilio rule).
		$has_sender = '' !== $messaging_service_sid || '' !== $from_number;

		if ( '' === $account_sid || '' === $auth_token || ! $has_sender ) {
			return array(
				'success' => false,
				'sid'     => null,
				'error'   => __( 'Twilio credentials are not configured.', 'gym-core' ),
			);
		}

		$to   = self::sanitize_phone( $to );
		$body = mb_substr( $body, 0, 1600 );

		if ( '' === $to ) {
			return array(
				'success' => false,
				'sid'     => null,
				'error'   => __( 'Invalid phone number.', 'gym-core' ),
			);
		}

		$url = sprintf(
			'%s/Accounts/%s/Messages.json',
			self::API_BASE,
			$account_sid
		);

		// Prefer Messaging Service SID over plain From number when both are set
		// (Twilio's API treats MessagingServiceSid as authoritative).
		$body_args = array(
			'To'   => $to,
			'Body' => $body,
		);

		if ( '' !== $messaging_service_sid ) {
			$body_args['MessagingServiceSid'] = $messaging_service_sid;
		} else {
			$body_args['From'] = $from_number;
		}

		$request_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			),
			'body'    => $body_args,
			'timeout' => self::HTTP_TIMEOUT,
		);

		// Up to 2 attempts; retry only on transient Twilio statuses.
		$attempt     = 0;
		$status_code = 0;
		do {
			++$attempt;
			$response = wp_remote_post( $url, $request_args );

			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'sid'     => null,
					'error'   => $response->get_error_message(),
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			if ( ! in_array( $status_code, self::RETRY_STATUS_CODES, true ) ) {
				break;
			}

			if ( $attempt < 2 ) {
				usleep( self::RETRY_BACKOFF_USEC );
			}
		} while ( $attempt < 2 );

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			/**
			 * Fires after an SMS is successfully sent.
			 *
			 * @since 1.3.0
			 *
			 * @param string $to   Recipient phone number.
			 * @param string $body Message body.
			 * @param string $sid  Twilio message SID.
			 */
			do_action( 'gym_core_sms_sent', $to, $body, $response_body['sid'] ?? '' );

			return array(
				'success' => true,
				'sid'     => $response_body['sid'] ?? null,
				'error'   => null,
			);
		}

		return array(
			'success' => false,
			'sid'     => null,
			'error'   => $response_body['message'] ?? __( 'Twilio API error.', 'gym-core' ),
		);
	}

	/**
	 * Validates a Twilio webhook request signature.
	 *
	 * Implements Twilio's signature validation algorithm:
	 * HMAC-SHA1 of (URL + sorted POST params) using auth token as key.
	 *
	 * @since 1.3.0
	 *
	 * @param string               $url       The full webhook URL.
	 * @param array<string,string> $params    POST parameters from Twilio.
	 * @param string               $signature The X-Twilio-Signature header value.
	 * @return bool True if the signature is valid.
	 */
	public function validate_webhook_signature( string $url, array $params, string $signature ): bool {
		$auth_token = $this->get_auth_token();

		if ( '' === $auth_token ) {
			return false;
		}

		// Build the data string: URL + sorted POST param key/value pairs.
		ksort( $params );
		$data = $url;
		foreach ( $params as $key => $value ) {
			$data .= $key . $value;
		}

		$expected = base64_encode( hash_hmac( 'sha1', $data, $auth_token, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return hash_equals( $expected, $signature );
	}

	/**
	 * Checks whether sending to a contact is rate-limited.
	 *
	 * @since 1.3.0
	 *
	 * @param int $contact_id CRM contact ID.
	 * @return bool True if the contact has been sent to within the rate limit window.
	 */
	public function is_rate_limited( int $contact_id ): bool {
		$transient_key = 'gym_sms_rate_' . $contact_id;
		return false !== get_transient( $transient_key );
	}

	/**
	 * Records a send event for rate limiting.
	 *
	 * @since 1.3.0
	 *
	 * @param int $contact_id CRM contact ID.
	 * @return void
	 */
	public function record_send( int $contact_id ): void {
		$rate_limit    = (int) get_option( 'gym_core_sms_rate_limit', 1 );
		$transient_key = 'gym_sms_rate_' . $contact_id;
		set_transient( $transient_key, time(), (int) ( HOUR_IN_SECONDS / max( 1, $rate_limit ) ) );
	}

	/**
	 * Sanitizes and validates a phone number to E.164 format.
	 *
	 * @since 1.3.0
	 *
	 * @param string $phone Raw phone input.
	 * @return string Sanitized E.164 phone number, or empty string if invalid.
	 */
	public static function sanitize_phone( string $phone ): string {
		// Strip everything except digits and leading +.
		$phone = preg_replace( '/[^\d+]/', '', $phone );

		if ( empty( $phone ) ) {
			return '';
		}

		// If it doesn't start with +, assume US number and prepend +1.
		if ( '+' !== $phone[0] ) {
			// Strip leading 1 if present (e.g., 1XXXXXXXXXX).
			if ( strlen( $phone ) === 11 && '1' === $phone[0] ) {
				$phone = '+' . $phone;
			} elseif ( strlen( $phone ) === 10 ) {
				$phone = '+1' . $phone;
			} else {
				return ''; // Can't determine format.
			}
		}

		// Basic validation: E.164 is + followed by 10-15 digits.
		if ( ! preg_match( '/^\+\d{10,15}$/', $phone ) ) {
			return '';
		}

		return $phone;
	}

	/**
	 * Returns the configured Account SID.
	 *
	 * Read order: settings option first, `wp-config.php` constant as
	 * emergency override fallback. Inverted from earlier behaviour so the
	 * admin UI is the primary source of truth (constants stay as a break-glass
	 * mechanism for incident response).
	 *
	 * @return string
	 */
	private function get_account_sid(): string {
		$value = (string) get_option( 'gym_core_twilio_account_sid', '' );

		if ( '' !== $value ) {
			return $value;
		}

		if ( defined( 'GYM_CORE_TWILIO_ACCOUNT_SID' ) && '' !== GYM_CORE_TWILIO_ACCOUNT_SID ) {
			return (string) GYM_CORE_TWILIO_ACCOUNT_SID;
		}

		return '';
	}

	/**
	 * Returns the configured Auth Token (decrypted).
	 *
	 * Routes through `CredentialStore::get()` which handles transparent
	 * decryption + the constant fallback.
	 *
	 * @return string
	 */
	public function get_auth_token(): string {
		return CredentialStore::get();
	}

	/**
	 * Returns the configured Messaging Service SID.
	 *
	 * @return string
	 */
	private function get_messaging_service_sid(): string {
		$value = (string) get_option( 'gym_core_twilio_messaging_service_sid', '' );

		if ( '' !== $value ) {
			return $value;
		}

		if ( defined( 'GYM_CORE_TWILIO_MESSAGING_SERVICE_SID' ) && '' !== GYM_CORE_TWILIO_MESSAGING_SERVICE_SID ) {
			return (string) GYM_CORE_TWILIO_MESSAGING_SERVICE_SID;
		}

		return '';
	}

	/**
	 * Returns the configured From number.
	 *
	 * Read order: settings option first, `wp-config.php` constant fallback.
	 *
	 * @return string
	 */
	private function get_from_number(): string {
		$value = (string) get_option( 'gym_core_twilio_phone_number', '' );

		if ( '' !== $value ) {
			return $value;
		}

		if ( defined( 'GYM_CORE_TWILIO_PHONE_NUMBER' ) && '' !== GYM_CORE_TWILIO_PHONE_NUMBER ) {
			return (string) GYM_CORE_TWILIO_PHONE_NUMBER;
		}

		return '';
	}
}
