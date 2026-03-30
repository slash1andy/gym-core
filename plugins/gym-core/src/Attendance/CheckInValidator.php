<?php
/**
 * Validates check-in requests before recording attendance.
 *
 * Enforces business rules: active membership required, location/program
 * eligibility, and duplicate check-in prevention.
 *
 * @package Gym_Core
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Attendance;

/**
 * Validates whether a member can check in to a class.
 */
final class CheckInValidator {

	/**
	 * Attendance store instance.
	 *
	 * @var AttendanceStore
	 */
	private AttendanceStore $store;

	/**
	 * Constructor.
	 *
	 * @param AttendanceStore $store Attendance data store.
	 */
	public function __construct( AttendanceStore $store ) {
		$this->store = $store;
	}

	/**
	 * Validates a check-in request and returns errors if invalid.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id  User ID attempting to check in.
	 * @param int    $class_id Class post ID (0 for open mat).
	 * @param string $location Location slug.
	 * @return \WP_Error|true True if valid, WP_Error with all validation failures.
	 */
	public function validate( int $user_id, int $class_id, string $location ) {
		$errors = new \WP_Error();

		// 1. User must exist.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$errors->add( 'invalid_user', __( 'Member not found.', 'gym-core' ) );
			return $errors;
		}

		// 2. Check for active membership (WooCommerce Memberships integration).
		if ( ! $this->has_active_membership( $user_id ) ) {
			$errors->add( 'no_active_membership', __( 'No active membership found. Please renew your membership to check in.', 'gym-core' ) );
		}

		// 3. Duplicate check-in prevention.
		if ( $class_id > 0 && $this->should_prevent_duplicates() && $this->store->has_checked_in_today( $user_id, $class_id ) ) {
			$errors->add( 'duplicate_checkin', __( 'Already checked in to this class today.', 'gym-core' ) );
		}

		// 4. Class must exist and be active (if a specific class is given).
		if ( $class_id > 0 ) {
			$class_post = get_post( $class_id );
			if ( ! $class_post || 'gym_class' !== $class_post->post_type ) {
				$errors->add( 'invalid_class', __( 'Class not found.', 'gym-core' ) );
			} else {
				$class_status = get_post_meta( $class_id, '_gym_class_status', true );
				if ( 'active' !== $class_status ) {
					$errors->add( 'class_not_active', __( 'This class is not currently active.', 'gym-core' ) );
				}
			}
		}

		// 5. Location must be valid.
		if ( '' === $location ) {
			$errors->add( 'missing_location', __( 'Location is required for check-in.', 'gym-core' ) );
		}

		/**
		 * Filters the check-in validation errors.
		 *
		 * Allows extensions to add custom validation rules.
		 *
		 * @since 1.2.0
		 *
		 * @param \WP_Error $errors   Current validation errors.
		 * @param int       $user_id  Member user ID.
		 * @param int       $class_id Class post ID.
		 * @param string    $location Location slug.
		 */
		$errors = apply_filters( 'gym_core_checkin_validation', $errors, $user_id, $class_id, $location );

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return true;
	}

	/**
	 * Checks if a user has an active WooCommerce Membership.
	 *
	 * Falls back to true if WooCommerce Memberships is not installed,
	 * so the gym can operate without the membership plugin during early
	 * milestones.
	 *
	 * @since 1.2.0
	 *
	 * @param int $user_id User ID.
	 * @return bool True if member has active access, or if Memberships is not installed.
	 */
	private function has_active_membership( int $user_id ): bool {
		// If WooCommerce Memberships is not active, allow check-in.
		if ( ! function_exists( 'wc_memberships_is_user_active_member' ) ) {
			return true;
		}

		// Check all membership plans — any active plan qualifies.
		return wc_memberships_is_user_active_member( $user_id );
	}

	/**
	 * Whether duplicate check-in prevention is enabled.
	 *
	 * @return bool
	 */
	private function should_prevent_duplicates(): bool {
		return 'yes' === get_option( 'gym_core_prevent_duplicate_checkin', 'yes' );
	}
}
