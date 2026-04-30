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
	 * WordPress user with an active subscription are excluded. Lookups are
	 * batched: a single SELECT resolves all emails to user IDs, and a single
	 * wcs_get_subscriptions() call primes the gym_prospect_$user_id transients
	 * so each is_prospect() invocation is a transient hit.
	 *
	 * @since 2.3.0
	 *
	 * @param array<int, array<string, mixed>> $contacts CRM contact arrays.
	 * @return array<int, array<string, mixed>> Filtered prospect-only contacts.
	 */
	public static function filter_prospects( array $contacts ): array {
		if ( array() === $contacts ) {
			return array();
		}

		$emails = array();
		foreach ( $contacts as $contact ) {
			$email = strtolower( trim( (string) ( $contact['email'] ?? '' ) ) );
			if ( '' !== $email ) {
				$emails[ $email ] = true;
			}
		}
		$emails = array_keys( $emails );

		$email_to_user_id = array() === $emails
			? array()
			: self::resolve_emails_to_user_ids( $emails );

		if ( array() !== $email_to_user_id ) {
			self::prime_active_subscription_users( array_values( $email_to_user_id ) );
		}

		return array_values(
			array_filter(
				$contacts,
				static function ( array $contact ) use ( $email_to_user_id ): bool {
					$email = strtolower( trim( (string) ( $contact['email'] ?? '' ) ) );

					if ( '' === $email ) {
						return true; // No email — treat as prospect.
					}

					$user_id = $email_to_user_id[ $email ] ?? 0;

					if ( 0 === $user_id ) {
						return true; // No WP account — always a prospect.
					}

					return self::is_prospect( $user_id );
				}
			)
		);
	}

	/**
	 * Resolves a list of emails to WordPress user IDs in a single query.
	 *
	 * @param array<int, string> $emails Lowercased, trimmed, non-empty emails.
	 * @return array<string, int> Map of email => user ID. Emails with no
	 *                             matching user are absent from the map.
	 */
	private static function resolve_emails_to_user_ids( array $emails ): array {
		if ( array() === $emails ) {
			return array();
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, user_email FROM {$wpdb->users} WHERE user_email IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$emails
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$wanted = array_flip( $emails );
		$map    = array();
		foreach ( $rows as $row ) {
			$email = strtolower( (string) ( $row['user_email'] ?? '' ) );
			if ( '' === $email || ! isset( $wanted[ $email ] ) ) {
				continue;
			}
			$map[ $email ] = (int) ( $row['ID'] ?? 0 );
		}

		return $map;
	}

	/**
	 * Primes the gym_prospect_* transient for the given user IDs.
	 *
	 * Issues a single wcs_get_subscriptions() call for currently-active
	 * subscriptions, then sets the per-user transient to '0' (member) or '1'
	 * (prospect) so subsequent is_prospect() lookups never reach the per-user
	 * wcs_get_users_subscriptions() path. The system-wide fetch is bounded by
	 * the gym's active-member count.
	 *
	 * @param array<int, int> $user_ids User IDs that need a prospect determination.
	 */
	private static function prime_active_subscription_users( array $user_ids ): void {
		if ( array() === $user_ids || ! function_exists( 'wcs_get_subscriptions' ) ) {
			return;
		}

		$needs_prime = array();
		foreach ( $user_ids as $user_id ) {
			if ( $user_id <= 0 ) {
				continue;
			}
			if ( false === get_transient( 'gym_prospect_' . $user_id ) ) {
				$needs_prime[ $user_id ] = true;
			}
		}

		if ( array() === $needs_prime ) {
			return;
		}

		$subscriptions = wcs_get_subscriptions(
			array(
				'subscription_status'    => self::ACTIVE_STATUSES,
				'subscriptions_per_page' => -1,
			)
		);

		$members = array();
		if ( is_array( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_user_id' ) ) {
					continue;
				}
				$uid = (int) $subscription->get_user_id();
				if ( $uid > 0 ) {
					$members[ $uid ] = true;
				}
			}
		}

		foreach ( array_keys( $needs_prime ) as $user_id ) {
			set_transient(
				'gym_prospect_' . $user_id,
				isset( $members[ $user_id ] ) ? '0' : '1',
				self::CACHE_TTL
			);
		}
	}
}
