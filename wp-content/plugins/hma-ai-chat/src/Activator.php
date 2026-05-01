<?php
declare(strict_types=1);
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
		self::init_ip_allowlist_enforcement();
		Agents\AgentUserManager::provision();
	}

	/**
	 * Default IP allowlist enforcement to ON for fresh installs.
	 *
	 * Existing installs upgraded across this version keep the legacy fall-open
	 * behavior until the operator opts in via the settings page; only sites
	 * that have never set the option get the safe default.
	 *
	 * @since 0.4.1
	 * @internal
	 */
	private static function init_ip_allowlist_enforcement() {
		// add_option() is a no-op when the option already exists, so existing
		// installs are untouched and only fresh installs get the safe default.
		add_option( Security\WebhookValidator::IP_ALLOWLIST_ENFORCE_KEY, true );
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
			KEY created_at (created_at),
			KEY updated_at (updated_at)
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
			tool_calls longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY role (role),
			FOREIGN KEY (conversation_id) REFERENCES $conversations_table(id) ON DELETE CASCADE
		) $charset_collate;";

		dbDelta( $messages_sql );

		// Backfill tool_calls column on existing installs (dbDelta misses
		// columns added after the table already exists in some MySQL versions).
		$has_tool_calls = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM $messages_table LIKE %s",
				'tool_calls'
			)
		);
		if ( null === $has_tool_calls ) {
			$wpdb->query( "ALTER TABLE $messages_table ADD COLUMN tool_calls longtext DEFAULT NULL AFTER tokens_used" );
		}

		// Pending actions table.
		// status_created composite index supports the audit-log query, which
		// always filters by status and orders by created_at DESC.
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
			KEY created_at (created_at),
			KEY status_created (status, created_at)
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

	/**
	 * Drop the stored "Joyous" finance-agent override after the Pippin rename.
	 *
	 * The chat page resolves the agent's display name from
	 * `hma_ai_chat_agent_overrides[finance][name]` first, falling back to the
	 * code default. Sites that customised the label while the default was
	 * "Joyous" have it pinned in the option, so the rename to "Pippin" never
	 * surfaces in the picker. We only drop the value when it's the literal
	 * old default — any other custom label the operator chose is preserved.
	 *
	 * @since 0.5.1
	 * @internal
	 */
	public static function clear_stale_finance_override(): void {
		$overrides = get_option( 'hma_ai_chat_agent_overrides', array() );

		if ( ! is_array( $overrides ) ) {
			return;
		}

		if ( ! isset( $overrides['finance']['name'] ) || 'Joyous' !== $overrides['finance']['name'] ) {
			return;
		}

		unset( $overrides['finance']['name'] );

		// If the finance override row is now empty, drop the whole row so it
		// doesn't linger as a no-op entry.
		if ( empty( $overrides['finance'] ) ) {
			unset( $overrides['finance'] );
		}

		update_option( 'hma_ai_chat_agent_overrides', $overrides );
	}
}
