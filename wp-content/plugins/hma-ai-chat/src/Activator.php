<?php
/**
 * Plugin activation handler.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat;

/**
 * Handles plugin activation tasks.
 *
 * @since 0.1.0
 */
class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	public static function activate() {
		self::create_tables();
		self::init_webhook_secret();
	}

	/**
	 * Create custom database tables.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Conversations table.
		$conversations_table = $wpdb->prefix . 'hma_ai_conversations';
		$conversations_sql   = "CREATE TABLE IF NOT EXISTS $conversations_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			agent varchar(64) NOT NULL,
			title varchar(255) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY agent (agent),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $conversations_sql );

		// Messages table.
		$messages_table = $wpdb->prefix . 'hma_ai_messages';
		$messages_sql   = "CREATE TABLE IF NOT EXISTS $messages_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL,
			content longtext NOT NULL,
			tokens_used int(11) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY role (role),
			FOREIGN KEY (conversation_id) REFERENCES $conversations_table(id) ON DELETE CASCADE
		) $charset_collate;";

		dbDelta( $messages_sql );

		// Pending actions table.
		$pending_table = $wpdb->prefix . 'hma_ai_pending_actions';
		$pending_sql   = "CREATE TABLE IF NOT EXISTS $pending_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			agent varchar(64) NOT NULL,
			action_type varchar(128) NOT NULL,
			action_data longtext NOT NULL,
			status varchar(30) DEFAULT 'pending',
			run_id varchar(255) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			approved_at datetime DEFAULT NULL,
			approved_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY agent (agent),
			KEY status (status),
			KEY run_id (run_id),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $pending_sql );

		// Store the schema version.
		update_option( 'hma_ai_chat_db_version', HMA_AI_CHAT_VERSION );
	}

	/**
	 * Initialize webhook shared secret if not present.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	private static function init_webhook_secret() {
		$secret = get_option( 'hma_ai_chat_webhook_secret' );
		if ( empty( $secret ) ) {
			$validator = new Security\WebhookValidator();
			$validator->generate_secret();
		}
	}
}
