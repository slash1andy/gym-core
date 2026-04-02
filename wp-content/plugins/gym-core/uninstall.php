<?php
/**
 * Plugin uninstall handler.
 *
 * Runs only when the plugin is deleted through the WordPress admin UI.
 * Removes all plugin data: options, transients, and custom tables.
 *
 * @package Gym_Core
 */

// Safety guard — only execute from the WordPress uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load autoloader so we can use TableManager.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Remove plugin options.
delete_option( 'gym_core_settings' );
delete_option( 'gym_core_version' );
delete_option( 'gym_core_activated' );
delete_option( 'gym_core_db_version' );

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

// Drop custom database tables.
Gym_Core\Data\TableManager::drop_tables();
