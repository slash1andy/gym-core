<?php
/**
 * TCPA opt-out store for SMS.
 *
 * Persists SMS opt-out status keyed by phone number (E.164) in a WordPress
 * option so that opted-out contacts are never sent SMS regardless of which
 * code path initiates the send (REST controller, CRM bridge, AutomateWoo
 * action, or promotion notifier).
 *
 * The store is intentionally phone-based rather than user-meta-based because:
 * 1. Inbound STOP replies identify the sender by phone, not WP user ID.
 * 2. Several send paths (AutomateWoo, CRM bridge) resolve phone without a WP user.
 *
 * @package Gym_Core\SMS
 * @since   1.3.1
 */

declare( strict_types=1 );

namespace Gym_Core\SMS;

/**
 * Manages TCPA opt-out state keyed by phone number.
 */
final class SmsOptOut {

	/**
	 * WordPress option key that stores the set of opted-out phone numbers.
	 *
	 * Stored as a JSON-encoded array of E.164 strings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'gym_core_sms_opted_out';

	/**
	 * Registers action hooks for inbound opt-out and opt-in messages.
	 *
	 * Hooked in Plugin::init() alongside the SMS module.
	 *
	 * @since 1.3.1
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'gym_core_sms_opt_out', array( $this, 'handle_opt_out' ) );
		add_action( 'gym_core_sms_opt_in', array( $this, 'handle_opt_in' ) );
	}

	/**
	 * Records an opt-out for the given phone number.
	 *
	 * Hooked to `gym_core_sms_opt_out`.
	 *
	 * @since 1.3.1
	 *
	 * @param string $phone Phone number (E.164 or raw — will be sanitized).
	 * @return void
	 */
	public function handle_opt_out( string $phone ): void {
		$phone = TwilioClient::sanitize_phone( $phone );

		if ( '' === $phone ) {
			return;
		}

		$opted_out = $this->get_opted_out_set();

		if ( ! in_array( $phone, $opted_out, true ) ) {
			$opted_out[] = $phone;
			$this->save_opted_out_set( $opted_out );
		}
	}

	/**
	 * Removes an opt-out for the given phone number (START / re-opt-in).
	 *
	 * Hooked to `gym_core_sms_opt_in`.
	 *
	 * @since 1.3.1
	 *
	 * @param string $phone Phone number (E.164 or raw — will be sanitized).
	 * @return void
	 */
	public function handle_opt_in( string $phone ): void {
		$phone = TwilioClient::sanitize_phone( $phone );

		if ( '' === $phone ) {
			return;
		}

		$opted_out = $this->get_opted_out_set();
		$filtered  = array_values( array_filter( $opted_out, fn( string $p ) => $p !== $phone ) );

		if ( count( $filtered ) !== count( $opted_out ) ) {
			$this->save_opted_out_set( $filtered );
		}
	}

	/**
	 * Returns true if the given phone number has opted out of SMS.
	 *
	 * @since 1.3.1
	 *
	 * @param string $phone Phone number (E.164 or raw — will be sanitized).
	 * @return bool
	 */
	public function is_opted_out( string $phone ): bool {
		$phone = TwilioClient::sanitize_phone( $phone );

		if ( '' === $phone ) {
			return false;
		}

		return in_array( $phone, $this->get_opted_out_set(), true );
	}

	/**
	 * Returns the current set of opted-out phone numbers.
	 *
	 * @return array<int, string>
	 */
	private function get_opted_out_set(): array {
		$raw = get_option( self::OPTION_KEY, '[]' );

		if ( ! is_string( $raw ) ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Persists the opt-out set.
	 *
	 * @param array<int, string> $phones Opted-out phone numbers.
	 * @return void
	 */
	private function save_opted_out_set( array $phones ): void {
		update_option( self::OPTION_KEY, wp_json_encode( $phones ) );
	}
}
