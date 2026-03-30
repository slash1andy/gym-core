<?php
/**
 * Plugin uninstall handler.
 *
 * Runs only when the plugin is deleted through the WordPress admin UI.
 * Removes all plugin data: options, transients, and any custom tables.
 *
 * @package Gym_Core
 */

// Safety guard — only execute from the WordPress uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'gym_core_settings' );
delete_option( 'gym_core_version' );
delete_option( 'gym_core_activated' );

// Clear any scheduled WP-Cron events registered by this plugin.
$cron_hooks = array(
	'gym_core_daily_maintenance',
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
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_locations" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_members" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_class_schedules" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_belt_ranks" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_attendance" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_badges" );
// phpcs:enable WordPress.DB.DirectDatabaseQuery
