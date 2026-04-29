<?php
/**
 * Real global function declarations for WooCommerce Subscriptions helpers.
 *
 * Brain\Monkey's Functions\stubs() shims a fixed body that suppresses
 * test-level Functions\expect() overrides; Functions\when() has the same
 * issue. Declaring real (no-op) global functions lets function_exists()
 * inside the SUT return true while still allowing Patchwork-based test
 * overrides via Functions\expect()/when().
 *
 * @package Gym_Core\Tests
 */

if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
	/**
	 * @param array<string, mixed> $args
	 * @return array<int, object>
	 */
	function wcs_get_subscriptions( array $args = array() ): array { // phpcs:ignore
		return array();
	}
}

if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
	/**
	 * @return array<int, object>
	 */
	function wcs_get_users_subscriptions( int $user_id ): array { // phpcs:ignore
		return array();
	}
}
