<?php
/**
 * Plugin deactivation handler.
 *
 * @package Gym_Core
 */

declare( strict_types=1 );

namespace Gym_Core;

/**
 * Handles clean-up tasks when the plugin is deactivated.
 *
 * Deactivation is non-destructive — data is preserved for re-activation.
 * Permanent data removal belongs in uninstall.php.
 */
final class Deactivator {

	/**
	 * Runs on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::clear_scheduled_events();

		// Flush rewrite rules to remove any CPT/taxonomy rewrite slugs.
		flush_rewrite_rules();
	}

	/**
	 * Unschedules all WP-Cron events registered by this plugin.
	 *
	 * @return void
	 */
	private static function clear_scheduled_events(): void {
		$hooks = array(
			'gym_core_daily_maintenance',
		);

		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}
}
