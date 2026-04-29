<?php
/**
 * White-label Jetpack CRM as "Gym CRM" in the WordPress admin.
 *
 * @package Gym_Core\Admin
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

/**
 * Rebrands Jetpack CRM menu items and removes unused submenus.
 */
final class CrmWhiteLabel {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! class_exists( 'ZeroBSCRM' ) ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'rebrand_crm_menu' ), 999 );
	}

	/**
	 * Rename the CRM top-level menu and remove unused submenu items.
	 *
	 * @return void
	 */
	public function rebrand_crm_menu(): void {
		global $menu, $submenu;

		// Rename "Jetpack CRM" to "Gym CRM" in the top-level menu.
		foreach ( $menu as &$item ) {
			if ( isset( $item[2] ) && false !== strpos( $item[2], 'zerobscrm' ) ) {
				$item[0] = sprintf(
					/* translators: %s: brand name */
					__( '%s CRM', 'gym-core' ),
					\Gym_Core\Utilities\Brand::name()
				);
				break;
			}
		}
		unset( $item );

		// Remove unused CRM submenu items.
		$remove_submenus = array(
			'zerobscrm-plugin-settings',
			'zerobscrm-extensions',
			'zerobscrm-datatools',
			'zerobscrm-export',
			'zerobscrm-csvlite',
		);

		foreach ( $remove_submenus as $slug ) {
			remove_submenu_page( 'zerobscrm', $slug );
		}
	}
}
