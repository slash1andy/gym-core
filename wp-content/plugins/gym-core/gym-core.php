<?php
/**
 * Plugin Name:       Gym Core
 * Plugin URI:        https://www.teamhaanpaa.com/
 * Description:       Core functionality for Haanpaa Martial Arts — multi-location support, membership integration, class scheduling, belt rank tracking, attendance/check-in, gamification (badges & streaks), Twilio SMS, and REST API endpoints for AI agents.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * Author:            Andrew Wikel
 * Author URI:        https://www.teamhaanpaa.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gym-core
 * Domain Path:       /languages
 * WC requires at least: 10.3
 * WC tested up to:   10.3
 *
 * @package Gym_Core
 */

declare( strict_types=1 );

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'GYM_CORE_VERSION', '1.0.0' );
define( 'GYM_CORE_FILE', __FILE__ );
define( 'GYM_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'GYM_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GYM_CORE_BASENAME', plugin_basename( __FILE__ ) );

// Composer PSR-4 autoloader.
if ( file_exists( GYM_CORE_PATH . 'vendor/autoload.php' ) ) {
	require_once GYM_CORE_PATH . 'vendor/autoload.php';
}

/**
 * Declare WooCommerce feature compatibility before WooCommerce initializes.
 *
 * Must be hooked to `before_woocommerce_init` and use FeaturesUtil directly —
 * do not wrap in a class method, as this fires before the plugin is fully loaded.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			// High-Performance Order Storage (HPOS) — mandatory for all WC plugins.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				GYM_CORE_FILE,
				true
			);

			// Cart & Checkout Blocks compatibility.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				GYM_CORE_FILE,
				true
			);
		}
	}
);

// Activation and deactivation hooks must be registered at top-level scope
// (not inside another hook callback) to work correctly.
register_activation_hook( GYM_CORE_FILE, array( 'Gym_Core\\Activator', 'activate' ) );
register_deactivation_hook( GYM_CORE_FILE, array( 'Gym_Core\\Deactivator', 'deactivate' ) );

/**
 * Bootstrap the plugin after all plugins have loaded.
 *
 * We wait for `plugins_loaded` so we can verify WooCommerce is active before
 * attempting to use any WC APIs.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		// Verify WooCommerce is active before loading any plugin functionality.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' .
						esc_html__( 'Gym Core requires WooCommerce to be installed and active.', 'gym-core' ) .
						'</p></div>';
				}
			);
			return;
		}

		// Verify PHP version at runtime as a belt-and-suspenders check.
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' .
						esc_html__( 'Gym Core requires PHP 8.0 or higher.', 'gym-core' ) .
						'</p></div>';
				}
			);
			return;
		}

		Gym_Core\Plugin::instance()->init();

		// Register WP-CLI commands when running in CLI mode.
		Gym_Core\CLI\ImportCommand::register();
	}
);
