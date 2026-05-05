<?php
declare(strict_types=1);
/**
 * Webhook validation and security.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\Security;

/**
 * Validates Paperclip webhook requests.
 *
 * @since 0.1.0
 */
class WebhookValidator {

	/**
	 * Option key for webhook secret.
	 *
	 * @since 0.1.0
	 */
	const SECRET_KEY = 'hma_ai_chat_webhook_secret';

	/**
	 * Option key for the previous secret during rotation grace period.
	 *
	 * @since 0.1.0
	 */
	const PREVIOUS_SECRET_KEY = 'hma_ai_chat_webhook_secret_previous';

	/**
	 * Option key for the rotation timestamp.
	 *
	 * @since 0.1.0
	 */
	const ROTATION_TIMESTAMP_KEY = 'hma_ai_chat_webhook_rotation_at';

	/**
	 * Grace period in seconds during which both secrets are accepted.
	 * Default: 5 minutes.
	 *
	 * @since 0.1.0
	 */
	const ROTATION_GRACE_PERIOD = 300;

	/**
	 * Option key for IP allowlist.
	 *
	 * @since 0.1.0
	 */
	const IP_ALLOWLIST_KEY = 'hma_ai_chat_ip_allowlist';

	/**
	 * Option key for whether the IP allowlist is enforced.
	 *
	 * When true, an empty allowlist means *deny all*. When false (legacy
	 * default), an empty allowlist falls open. Existing installs preserve
	 * legacy behavior on upgrade; fresh installs ship with enforcement on.
	 *
	 * @since 0.4.1
	 */
	const IP_ALLOWLIST_ENFORCE_KEY = 'hma_ai_chat_ip_allowlist_enforce';

	/**
	 * Expected header name for HMAC signatures.
	 *
	 * @since 0.4.2
	 */
	const SIGNATURE_HEADER = 'X-HMA-Signature';

	/**
	 * Tolerance window in seconds for HMAC timestamp validation.
	 * Prevents replay attacks: requests older than 5 minutes are rejected.
	 *
	 * @since 0.4.2
	 */
	const TIMESTAMP_TOLERANCE = 300;

	/**
	 * Validate incoming webhook request.
	 *
	 * Accepts the current secret, and during a rotation grace period,
	 * also accepts the previous secret. Uses constant-time comparison
	 * to prevent timing attacks.
	 *
	 * @since 0.1.0
	 *
	 * @param string $auth_header Authorization header value.
	 * @return bool
	 */
	public function validate_request( $auth_header ) {
		if ( empty( $auth_header ) ) {
			return false;
		}

		$secret = $this->get_secret();
		if ( empty( $secret ) ) {
			return false;
		}

		// Extract token from "Bearer token" format.
		$parts = explode( ' ', $auth_header );
		if ( 2 !== count( $parts ) || 'Bearer' !== $parts[0] ) {
			return false;
		}

		$provided_token = $parts[1];

		// Check current secret first.
		if ( hash_equals( $secret, $provided_token ) ) {
			return true;
		}

		// During rotation grace period, also accept the previous secret.
		if ( $this->is_in_rotation_grace_period() ) {
			$previous_secret = get_option( self::PREVIOUS_SECRET_KEY, '' );
			if ( ! empty( $previous_secret ) && hash_equals( $previous_secret, $provided_token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate HMAC-over-body signature from the X-HMA-Signature header.
	 *
	 * Stronger than Bearer: the signature covers the request timestamp and
	 * the raw body, so a captured request cannot be replayed after the
	 * 5-minute timestamp tolerance window.
	 *
	 * Header format: `X-HMA-Signature: t=<unix_ts>,v1=<hmac_sha256_hex>`
	 * Signed payload: `"t={ts}\n{raw_body}"`
	 *
	 * @since 0.4.2
	 *
	 * @param string $raw_body   Raw request body string.
	 * @param string $sig_header Value of the X-HMA-Signature header.
	 * @return bool True when signature is valid and timestamp is fresh.
	 */
	public function validate_hmac_signature( string $raw_body, string $sig_header ): bool {
		if ( empty( $sig_header ) ) {
			return false;
		}

		$secret = $this->get_secret();
		if ( empty( $secret ) ) {
			return false;
		}

		// Parse "t={ts},v1={hmac}" into key-value pairs.
		$parsed = array();
		foreach ( explode( ',', $sig_header ) as $token ) {
			$parts = explode( '=', $token, 2 );
			if ( 2 === count( $parts ) ) {
				$parsed[ trim( $parts[0] ) ] = trim( $parts[1] );
			}
		}

		$ts           = $parsed['t'] ?? '';
		$provided_hmac = $parsed['v1'] ?? '';

		if ( '' === $ts || '' === $provided_hmac || ! ctype_digit( $ts ) ) {
			return false;
		}

		// Reject stale requests outside the tolerance window.
		if ( abs( time() - (int) $ts ) > self::TIMESTAMP_TOLERANCE ) {
			return false;
		}

		$signed_payload = "t={$ts}\n{$raw_body}";

		// Check current secret.
		$expected = hash_hmac( 'sha256', $signed_payload, $secret );
		if ( hash_equals( $expected, $provided_hmac ) ) {
			return true;
		}

		// During rotation grace period, also accept the previous secret.
		if ( $this->is_in_rotation_grace_period() ) {
			$previous_secret = get_option( self::PREVIOUS_SECRET_KEY, '' );
			if ( ! empty( $previous_secret ) ) {
				$expected_prev = hash_hmac( 'sha256', $signed_payload, $previous_secret );
				if ( hash_equals( $expected_prev, $provided_hmac ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check whether we are within the rotation grace period.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if within grace period.
	 */
	private function is_in_rotation_grace_period() {
		$rotation_at = (int) get_option( self::ROTATION_TIMESTAMP_KEY, 0 );
		if ( 0 === $rotation_at ) {
			return false;
		}

		$elapsed = time() - $rotation_at;

		// Grace period expired — clean up the previous secret.
		if ( $elapsed > self::ROTATION_GRACE_PERIOD ) {
			delete_option( self::PREVIOUS_SECRET_KEY );
			delete_option( self::ROTATION_TIMESTAMP_KEY );
			return false;
		}

		return true;
	}

	/**
	 * Validate request IP address against allowlist.
	 *
	 * Per security P0 sweep §A.4 the allowlist is now opt-in by default —
	 * an empty allowlist always denies, regardless of the enforcement toggle.
	 * The toggle is retained so an operator can EXPLICITLY opt out for a
	 * staging/testing window; in that mode an empty allowlist falls open
	 * but emits a `hma_ai_chat_webhook_no_ip_allowlist` action for monitoring.
	 *
	 * Behavior matrix:
	 * - Enforcement on (default) + empty allowlist => deny.
	 * - Enforcement on + populated allowlist => deny unless IP matches.
	 * - Enforcement off + empty allowlist => allow (explicit opt-out only).
	 * - Enforcement off + populated allowlist => deny unless IP matches.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function validate_ip() {
		$allowlist = $this->get_ip_allowlist();
		$enforce   = $this->is_ip_allowlist_enforced();

		if ( empty( $allowlist ) ) {
			if ( $enforce ) {
				// Fail closed — the secure default per security P0 sweep §A.4.
				do_action( 'hma_ai_chat_webhook_ip_denied_empty_allowlist' );
				return false;
			}
			// Operator has explicitly opted out of enforcement.
			do_action( 'hma_ai_chat_webhook_no_ip_allowlist' );
			return true;
		}

		$client_ip = $this->get_client_ip();
		return in_array( $client_ip, $allowlist, true );
	}

	/**
	 * Whether the IP allowlist is enforced (fails closed when empty).
	 *
	 * Defaults true everywhere per security P0 sweep §A.4. Operators who
	 * need a temporary opt-out (staging, an outage with no time to update
	 * the allowlist) can flip this off explicitly from the admin settings
	 * page; otherwise an empty allowlist denies all webhook traffic.
	 *
	 * @since 0.4.1
	 *
	 * @return bool
	 */
	public function is_ip_allowlist_enforced() {
		return (bool) get_option( self::IP_ALLOWLIST_ENFORCE_KEY, true );
	}

	/**
	 * Get the webhook shared secret.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_secret() {
		return get_option( self::SECRET_KEY, '' );
	}

	/**
	 * Generate a new webhook secret using cryptographically secure randomness.
	 *
	 * @since 0.1.0
	 *
	 * @return string The generated secret (64 hex characters).
	 */
	public function generate_secret() {
		$secret = bin2hex( random_bytes( 32 ) );

		update_option( self::SECRET_KEY, $secret, false );
		return $secret;
	}

	/**
	 * Rotate the webhook secret with a dual-secret grace period.
	 *
	 * Moves the current secret to PREVIOUS_SECRET_KEY so both are
	 * accepted during the grace period. This allows Paperclip's
	 * configuration to be updated without downtime.
	 *
	 * Flow:
	 * 1. Current secret moves to previous_secret.
	 * 2. New secret generated and stored as current.
	 * 3. Both accepted for ROTATION_GRACE_PERIOD seconds (default 5 min).
	 * 4. After grace period, previous secret auto-cleaned on next validation.
	 *
	 * @since 0.1.0
	 *
	 * @return string The new secret (configure this in Paperclip within the grace period).
	 */
	public function rotate_secret() {
		$current_secret = $this->get_secret();

		// Preserve current secret as the previous one.
		if ( ! empty( $current_secret ) ) {
			update_option( self::PREVIOUS_SECRET_KEY, $current_secret, false );
			update_option( self::ROTATION_TIMESTAMP_KEY, time(), false );
		}

		return $this->generate_secret();
	}

	/**
	 * Get IP allowlist.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_ip_allowlist() {
		$allowlist = get_option( self::IP_ALLOWLIST_KEY, array() );
		return is_array( $allowlist ) ? $allowlist : array();
	}

	/**
	 * Update IP allowlist.
	 *
	 * @since 0.1.0
	 *
	 * @param array $ips Array of IP addresses.
	 * @return bool
	 */
	public function update_ip_allowlist( $ips ) {
		if ( ! is_array( $ips ) ) {
			return false;
		}

		// Validate each IP address.
		foreach ( $ips as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return false;
			}
		}

		return update_option( self::IP_ALLOWLIST_KEY, $ips );
	}

	/**
	 * Get client IP address from request.
	 *
	 * Uses REMOTE_ADDR only. Forwarded headers (HTTP_X_FORWARDED_FOR,
	 * HTTP_CLIENT_IP) are trivially spoofable and should not be trusted
	 * for security decisions. If behind a trusted reverse proxy, configure
	 * the proxy to set REMOTE_ADDR correctly.
	 *
	 * @since 0.1.0
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip() {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	}
}
