<?php
/**
 * Plugin uninstall script.
 *
 * @package HMA_AI_Chat
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop custom tables (order matters — messages before conversations due to FK).
$tables = array(
	$wpdb->prefix . 'hma_ai_messages',
	$wpdb->prefix . 'hma_ai_pending_actions',
	$wpdb->prefix . 'hma_ai_conversations',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS $table" );
}

// Delete plugin options.
$options = array(
	'hma_ai_chat_webhook_secret',
	'hma_ai_chat_webhook_secret_previous',
	'hma_ai_chat_webhook_rotation_at',
	'hma_ai_chat_ip_allowlist',
	'hma_ai_chat_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * Fires when the plugin is uninstalled.
 *
 * @since 0.1.0
 */
do_action( 'hma_ai_chat_uninstall' );
