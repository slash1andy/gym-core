<?php
/**
 * Plugin Name: HMA AI Chat
 * Description: AI-powered chat interface for Haanpaa Martial Arts staff, built on WordPress 7.0 AI Client
 * Version: 0.1.0
 * Author: Andrew Wikel
 * License: GPL-2.0-or-later
 * Requires WP: 7.0
 * Requires PHP: 8.0
 * Text Domain: hma-ai-chat
 * Domain Path: /languages
 *
 * @package HMA_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

define( 'HMA_AI_CHAT_VERSION', '0.1.0' );
define( 'HMA_AI_CHAT_FILE', __FILE__ );
define( 'HMA_AI_CHAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'HMA_AI_CHAT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load Composer autoloader if available.
 */
if ( file_exists( HMA_AI_CHAT_PATH . 'vendor/autoload.php' ) ) {
	require_once HMA_AI_CHAT_PATH . 'vendor/autoload.php';
}

/**
 * Check for WordPress 7.0 and WP AI Client availability.
 *
 * @return bool True if requirements are met.
 */
function hma_ai_chat_check_requirements() {
	global $wp_version;

	// Strip pre-release suffixes (e.g., '7.0-RC2' → '7.0') so RC/beta/alpha passes.
	$wp_version_clean = preg_replace( '/[^0-9.].*/', '', $wp_version );
	if ( version_compare( $wp_version_clean, '7.0', '<' ) ) {
		add_action( 'admin_notices', function () {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					esc_html_e( 'HMA AI Chat requires WordPress 7.0 or later.', 'hma-ai-chat' );
					?>
				</p>
			</div>
			<?php
		} );
		return false;
	}

	if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
		add_action( 'admin_notices', function () {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					esc_html_e( 'HMA AI Chat requires WordPress 7.0\'s WP AI Client. Please ensure it is active.', 'hma-ai-chat' );
					?>
				</p>
			</div>
			<?php
		} );
		return false;
	}

	return true;
}

/**
 * Initialize the plugin.
 *
 * @internal
 */
function hma_ai_chat_init() {
	if ( ! hma_ai_chat_check_requirements() ) {
		return;
	}

	load_plugin_textdomain( 'hma-ai-chat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( class_exists( 'HMA_AI_Chat\\Plugin' ) ) {
		HMA_AI_Chat\Plugin::instance()->init();
	}
}

add_action( 'plugins_loaded', 'hma_ai_chat_init' );

/**
 * Activation hook.
 *
 * @internal
 */
function hma_ai_chat_activate() {
	if ( ! hma_ai_chat_check_requirements() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		return;
	}

	if ( class_exists( 'HMA_AI_Chat\\Activator' ) ) {
		HMA_AI_Chat\Activator::activate();
	}
}

/**
 * Deactivation hook.
 *
 * @internal
 */
function hma_ai_chat_deactivate() {
	if ( class_exists( 'HMA_AI_Chat\\Deactivator' ) ) {
		HMA_AI_Chat\Deactivator::deactivate();
	}
}

register_activation_hook( HMA_AI_CHAT_FILE, 'hma_ai_chat_activate' );
register_deactivation_hook( HMA_AI_CHAT_FILE, 'hma_ai_chat_deactivate' );
