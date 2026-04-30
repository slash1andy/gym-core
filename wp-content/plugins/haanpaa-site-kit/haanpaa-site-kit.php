<?php
/**
 * Plugin Name:       Haanpaa Martial Arts — Site Kit
 * Plugin URI:        https://teamhaanpaa.com
 * Description:       Brand patterns, custom post types, Interactivity API behaviors, and a Jetpack CRM lead-capture handler for the Haanpaa Martial Arts block-theme site. Pairs with the bundled "haanpaa" block theme.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Haanpaa Martial Arts
 * License:           GPL-2.0-or-later
 * Text Domain:       haanpaa
 *
 * @package Haanpaa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAANPAA_PLUGIN_FILE', __FILE__ );
define( 'HAANPAA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HAANPAA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HAANPAA_VERSION', '1.0.0' );

require_once HAANPAA_PLUGIN_DIR . 'includes/class-cpts.php';
require_once HAANPAA_PLUGIN_DIR . 'includes/class-patterns.php';
require_once HAANPAA_PLUGIN_DIR . 'includes/class-interactivity.php';
require_once HAANPAA_PLUGIN_DIR . 'includes/class-schema.php';
require_once HAANPAA_PLUGIN_DIR . 'includes/class-jetpack-crm.php';
require_once HAANPAA_PLUGIN_DIR . 'includes/class-sms.php';
require_once HAANPAA_PLUGIN_DIR . 'includes/class-seeder.php';

add_action( 'plugins_loaded', function () {
	Haanpaa\CPTs::init();
	Haanpaa\Patterns::init();
	Haanpaa\Interactivity::init();
	Haanpaa\Schema::init();
	Haanpaa\Jetpack_CRM::init();
	Haanpaa\SMS::init();
} );

register_activation_hook( __FILE__, function () {
	require_once HAANPAA_PLUGIN_DIR . 'includes/class-cpts.php';
	require_once HAANPAA_PLUGIN_DIR . 'includes/class-seeder.php';
	Haanpaa\CPTs::register();
	Haanpaa\Seeder::run();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
