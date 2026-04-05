<?php
/**
 * Custom capabilities and roles for the gym management system.
 *
 * @package Gym_Core
 */

declare( strict_types=1 );

namespace Gym_Core;

/**
 * Registers custom WordPress capabilities and roles.
 *
 * Capabilities control access to gym-specific features like rank promotions,
 * attendance tracking, SMS messaging, and curriculum management. Two custom
 * roles (Head Coach, Coach) receive appropriate subsets of these capabilities.
 *
 * @since 1.1.0
 */
final class Capabilities {

	/**
	 * All gym-specific capabilities.
	 *
	 * @var string[]
	 */
	public const ALL_CAPS = array(
		'gym_promote_student',
		'gym_view_ranks',
		'gym_check_in_member',
		'gym_view_attendance',
		'gym_view_achievements',
		'gym_send_sms',
		'gym_manage_curriculum',
		'gym_manage_announcements',
		'gym_view_briefing',
		'gym_process_sale',
	);

	/**
	 * Capabilities assigned to the gym_coach role.
	 *
	 * @var string[]
	 */
	public const COACH_CAPS = array(
		'gym_promote_student',
		'gym_view_ranks',
		'gym_check_in_member',
		'gym_view_attendance',
		'gym_view_achievements',
		'gym_view_briefing',
	);

	/**
	 * Capabilities assigned to the gym_head_coach role.
	 *
	 * Head coaches receive all gym capabilities plus edit_users.
	 *
	 * @var string[]
	 */
	public const HEAD_COACH_CAPS = array(
		'gym_promote_student',
		'gym_view_ranks',
		'gym_check_in_member',
		'gym_view_attendance',
		'gym_view_achievements',
		'gym_send_sms',
		'gym_manage_curriculum',
		'gym_manage_announcements',
		'gym_view_briefing',
		'gym_process_sale',
		'edit_users',
	);

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'maybe_sync_caps' ) );
	}

	/**
	 * Re-syncs capabilities when the plugin version changes.
	 *
	 * This ensures that any capabilities added in a plugin update are applied
	 * without requiring the user to deactivate and reactivate.
	 *
	 * @return void
	 */
	public function maybe_sync_caps(): void {
		$stored_version = get_option( 'gym_core_caps_version', '' );

		if ( GYM_CORE_VERSION === $stored_version ) {
			return;
		}

		self::install_roles();
		self::grant_admin_caps();
		update_option( 'gym_core_caps_version', GYM_CORE_VERSION );
	}

	/**
	 * Adds custom roles and grants administrator all gym capabilities.
	 *
	 * Called on plugin activation. Safe to call multiple times — WordPress
	 * silently skips add_role() if the role already exists, and add_cap()
	 * is idempotent.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::install_roles();
		self::grant_admin_caps();
		update_option( 'gym_core_caps_version', GYM_CORE_VERSION );
	}

	/**
	 * Removes custom roles on plugin deactivation.
	 *
	 * Administrator capabilities are intentionally preserved so that any
	 * content gated by gym capabilities remains accessible to admins even
	 * while the plugin is inactive.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		remove_role( 'gym_head_coach' );
		remove_role( 'gym_coach' );
	}

	/**
	 * Installs the gym_head_coach and gym_coach roles.
	 *
	 * Removes existing roles first to ensure capability lists are current
	 * after a plugin update that adds new capabilities.
	 *
	 * @return void
	 */
	private static function install_roles(): void {
		// Remove first so capability changes are picked up on update.
		remove_role( 'gym_head_coach' );
		remove_role( 'gym_coach' );

		// Build capability arrays in the format WordPress expects: [ 'cap' => true ].
		$head_coach_caps = array();
		foreach ( self::HEAD_COACH_CAPS as $cap ) {
			$head_coach_caps[ $cap ] = true;
		}
		// Head coaches also inherit standard editor-like capabilities for reading.
		$head_coach_caps['read'] = true;

		$coach_caps = array();
		foreach ( self::COACH_CAPS as $cap ) {
			$coach_caps[ $cap ] = true;
		}
		$coach_caps['read'] = true;

		add_role(
			'gym_head_coach',
			__( 'Head Coach', 'gym-core' ),
			$head_coach_caps
		);

		add_role(
			'gym_coach',
			__( 'Coach', 'gym-core' ),
			$coach_caps
		);
	}

	/**
	 * Grants all gym capabilities to the administrator role.
	 *
	 * @return void
	 */
	private static function grant_admin_caps(): void {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		foreach ( self::ALL_CAPS as $cap ) {
			$admin->add_cap( $cap );
		}
	}
}
