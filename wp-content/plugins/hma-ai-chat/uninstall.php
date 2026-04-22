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

// Delete agent user accounts and custom role.
$agent_user_ids = get_option( 'hma_ai_chat_agent_user_ids', array() );
if ( ! empty( $agent_user_ids ) ) {
	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}
	foreach ( $agent_user_ids as $slug => $uid ) {
		if ( get_userdata( $uid ) ) {
			wp_delete_user( $uid );
		}
	}
}
remove_role( 'hma_ai_agent' );

// Delete plugin options.
$options = array(
	'hma_ai_chat_webhook_secret',
	'hma_ai_chat_webhook_secret_previous',
	'hma_ai_chat_webhook_rotation_at',
	'hma_ai_chat_ip_allowlist',
	'hma_ai_chat_ip_allowlist_enforce',
	'hma_ai_chat_db_version',
	'hma_ai_chat_agent_user_ids',
	'hma_ai_chat_slack_webhook_url',
	'hma_ai_chat_sms_admin_numbers',
	'hma_ai_chat_notify_on_pending',
	'hma_ai_chat_notify_include_summary',
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
