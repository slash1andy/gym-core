<?php
/**
 * Simplify the WordPress admin menu based on user role.
 *
 * @package Gym_Core\Admin
 */

declare(strict_types=1);

namespace Gym_Core\Admin;

/**
 * Removes unnecessary admin menu items for non-administrator roles.
 */
final class MenuManager {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'simplify_menu' ), 999 );
	}

	/**
	 * Simplify the admin menu based on the current user's role.
	 *
	 * @return void
	 */
	public function simplify_menu(): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();

		// Items to remove for ALL non-admin roles.
		$universal_remove = array(
			'themes.php',
			'plugins.php',
			'tools.php',
			'edit-comments.php',
			'jetpack',
		);

		foreach ( $universal_remove as $slug ) {
			remove_menu_page( $slug );
		}

		// Role-specific allow-lists.
		if ( $this->user_has_role( $user, 'gym_coach' ) || $this->user_has_role( $user, 'gym_head_coach' ) ) {
			$this->apply_coach_menu( $user );
		} elseif ( $this->user_has_role( $user, 'shop_manager' ) ) {
			$this->apply_shop_manager_menu();
		}
	}

	/**
	 * Restrict coaches to Gym, Profile, and Dashboard only.
	 * Head coaches also keep Users.
	 *
	 * @param \WP_User $user Current user object.
	 * @return void
	 */
	private function apply_coach_menu( \WP_User $user ): void {
		$allowed = array(
			'index.php',
			'gym-core',
			'profile.php',
		);

		if ( $this->user_has_role( $user, 'gym_head_coach' ) ) {
			$allowed[] = 'users.php';
		}

		$this->remove_all_except( $allowed );
	}

	/**
	 * Restrict shop managers to Gym, WooCommerce, Gym CRM, Dashboard, and Profile.
	 *
	 * @return void
	 */
	private function apply_shop_manager_menu(): void {
		$allowed = array(
			'index.php',
			'gym-core',
			'woocommerce',
			'zerobscrm',
			'profile.php',
		);

		$this->remove_all_except( $allowed );
	}

	/**
	 * Remove all top-level menu pages except those in the allow-list.
	 *
	 * @param array<string> $allowed_slugs Menu slugs to keep.
	 * @return void
	 */
	private function remove_all_except( array $allowed_slugs ): void {
		global $menu;

		foreach ( $menu as $item ) {
			if ( ! isset( $item[2] ) ) {
				continue;
			}

			$slug = $item[2];

			if ( ! in_array( $slug, $allowed_slugs, true ) ) {
				remove_menu_page( $slug );
			}
		}
	}

	/**
	 * Check whether a user has a specific role.
	 *
	 * @param \WP_User $user User object.
	 * @param string   $role Role slug.
	 * @return bool
	 */
	private function user_has_role( \WP_User $user, string $role ): bool {
		return in_array( $role, $user->roles, true );
	}
}
