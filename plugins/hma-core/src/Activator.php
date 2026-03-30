<?php
/**
 * Plugin activation handler.
 *
 * @package HMA_Core
 */

declare( strict_types=1 );

namespace HMA_Core;

/**
 * Handles all tasks that must run exactly once when the plugin is activated.
 */
final class Activator {

	/**
	 * Runs on plugin activation.
	 *
	 * WordPress calls this via register_activation_hook(). It fires during the
	 * request that activates the plugin, before plugins_loaded, so only use
	 * WordPress core APIs here — not WooCommerce APIs.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::check_requirements();
		self::set_defaults();
		self::schedule_events();
		self::seed_location_terms();

		// Flush rewrite rules so any CPTs registered on activation are immediately reachable.
		flush_rewrite_rules();

		// Record activation timestamp for onboarding and upgrade logic.
		update_option( 'hma_core_activated', gmdate( 'Y-m-d H:i:s' ) );
		update_option( 'hma_core_version', HMA_CORE_VERSION );
	}

	/**
	 * Verifies minimum environment requirements and aborts activation if unmet.
	 *
	 * @return void
	 */
	private static function check_requirements(): void {
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			// deactivate_plugins() and wp_die() are safe to use in an activation hook.
			deactivate_plugins( HMA_CORE_BASENAME );
			wp_die(
				esc_html__( 'HMA Core requires PHP 8.0 or higher. Please upgrade your PHP version.', 'hma-core' ),
				esc_html__( 'Plugin Activation Error', 'hma-core' ),
				array( 'back_link' => true )
			);
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '10.3', '<' ) ) {
			deactivate_plugins( HMA_CORE_BASENAME );
			wp_die(
				esc_html__( 'HMA Core requires WooCommerce 10.3 or higher.', 'hma-core' ),
				esc_html__( 'Plugin Activation Error', 'hma-core' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Seeds default option values on first activation.
	 *
	 * Uses add_option() so existing values are never overwritten on re-activation.
	 *
	 * @return void
	 */
	private static function set_defaults(): void {
		add_option(
			'hma_core_settings',
			array(
				'locations'      => array(),
				'sms_enabled'    => false,
				'gamification'   => true,
				'ai_api_enabled' => false,
			)
		);
	}

	/**
	 * Seeds the hma_location taxonomy terms for both gym locations.
	 *
	 * Registers the taxonomy first (it hasn't fired via init yet at activation
	 * time), then inserts 'rockford' and 'beloit' if they don't already exist.
	 *
	 * @return void
	 */
	private static function seed_location_terms(): void {
		$taxonomy = new Location\Taxonomy();
		$taxonomy->register_taxonomy();
		Location\Taxonomy::seed_terms();
	}

	/**
	 * Registers recurring WP-Cron events.
	 *
	 * @return void
	 */
	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'hma_core_daily_maintenance' ) ) {
			wp_schedule_event( time(), 'daily', 'hma_core_daily_maintenance' );
		}
	}
}
