<?php
/**
 * Plugin uninstall handler.
 *
 * Runs only when the plugin is deleted through the WordPress admin UI.
 * Removes all plugin data: options, transients, and any custom tables.
 *
 * @package HMA_Core
 */

// Safety guard — only execute from the WordPress uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'hma_core_settings' );
delete_option( 'hma_core_version' );
delete_option( 'hma_core_activated' );

// Clear any scheduled WP-Cron events registered by this plugin.
$cron_hooks = array(
	'hma_core_daily_maintenance',
);

foreach ( $cron_hooks as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
}

// Future: drop custom database tables.
// Uncomment and extend as tables are added during development.
//
// phpcs:disable WordPress.DB.DirectDatabaseQuery
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hma_locations" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hma_members" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hma_class_schedules" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hma_belt_ranks" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hma_attendance" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hma_badges" );
// phpcs:enable WordPress.DB.DirectDatabaseQuery
