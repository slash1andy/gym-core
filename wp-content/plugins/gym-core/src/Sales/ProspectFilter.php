<?php
/**
 * Prospect filter — determines whether a user is a prospect (not yet a member).
 *
 * A prospect is anyone without an active WooCommerce subscription.
 * CRM-only contacts (no WordPress user account) are always prospects.
 *
 * @package Gym_Core\Sales
 * @since   2.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Sales;

/**
 * Stateless utility for prospect-vs-member determination.
 */
final class ProspectFilter {

	/**
	 * Transient TTL in seconds (5 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 300;

	/**
	 * Subscription statuses that indicate an active member.
	 *
	 * @var list<string>
	 */
	private const ACTIVE_STATUSES = array( 'active', 'pending-cancel' );

	/**
	 * Checks whether a user ID represents a prospect.
	 *
	 * @since 2.3.0
	 *
	 * @param int $user_id WordPress user ID. Pass 0 for CRM-only contacts.
	 * @return bool True if the user is a prospect (no active subscription).
	 */
	public static function is_prospect( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return true;
		}

		$transient_key = 'gym_prospect_' . $user_id;
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$is_prospect = ! self::has_active_subscription( $user_id );

		set_transient( $transient_key, $is_prospect ? '1' : '0', self::CACHE_TTL );

		return $is_prospect;
	}

	/**
	 * Checks whether a user has any active WooCommerce subscription.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	private static function has_active_subscription( int $user_id ): bool {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return false;
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			if ( in_array( $subscription->get_status(), self::ACTIVE_STATUSES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Filters an array of CRM contacts, returning only prospects.
	 *
	 * Each contact must have an 'email' key. Contacts whose email maps to a
	 * WordPress user with an active subscription are excluded.
	 *
	 * @since 2.3.0
	 *
	 * @param array<int, array<string, mixed>> $contacts CRM contact arrays.
	 * @return array<int, array<string, mixed>> Filtered prospect-only contacts.
	 */
	public static function filter_prospects( array $contacts ): array {
		return array_values(
			array_filter(
				$contacts,
				static function ( array $contact ): bool {
					$email = strtolower( trim( (string) ( $contact['email'] ?? '' ) ) );

					if ( '' === $email ) {
						return true; // No email — treat as prospect.
					}

					$user = get_user_by( 'email', $email );

					if ( ! $user ) {
						return true; // No WP account — always a prospect.
					}

					return self::is_prospect( $user->ID );
				}
			)
		);
	}
}
