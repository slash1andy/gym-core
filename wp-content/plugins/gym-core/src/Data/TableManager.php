<?php
/**
 * Custom database table management.
 *
 * Handles creation, migration, and versioning of all gym-core custom tables.
 * Uses dbDelta() for safe, idempotent schema changes.
 *
 * @package Gym_Core
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Data;

/**
 * Manages custom database table schemas and migrations.
 */
final class TableManager {

	/**
	 * Current schema version. Bump this when adding or altering tables.
	 *
	 * V2 — adds gym_funnel_log table for CRO instrumentation.
	 *
	 * @var int
	 */
	private const SCHEMA_VERSION = 2;

	/**
	 * Option key for tracking the installed schema version.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'gym_core_db_version';

	/**
	 * Creates or updates all custom tables if the schema version has changed.
	 *
	 * Safe to call on every activation — dbDelta() is idempotent.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function maybe_create_tables(): void {
		$installed_version = (int) get_option( self::VERSION_OPTION, 0 );

		if ( $installed_version >= self::SCHEMA_VERSION ) {
			return;
		}

		self::create_tables();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Forces table creation regardless of version check.
	 *
	 * Used during initial activation and manual migrations.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = self::get_ranks_table_sql( $wpdb->prefix, $charset_collate );
		dbDelta( $sql );

		$sql = self::get_rank_history_table_sql( $wpdb->prefix, $charset_collate );
		dbDelta( $sql );

		$sql = self::get_attendance_table_sql( $wpdb->prefix, $charset_collate );
		dbDelta( $sql );

		$sql = self::get_achievements_table_sql( $wpdb->prefix, $charset_collate );
		dbDelta( $sql );

		$sql = self::get_funnel_log_table_sql( $wpdb->prefix, $charset_collate );
		dbDelta( $sql );
	}

	/**
	 * Drops all custom tables. Used only during uninstall.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_funnel_log" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_achievements" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_rank_history" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_ranks" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gym_attendance" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Returns table names keyed by short identifier.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string>
	 */
	public static function get_table_names(): array {
		global $wpdb;

		return array(
			'ranks'        => $wpdb->prefix . 'gym_ranks',
			'rank_history' => $wpdb->prefix . 'gym_rank_history',
			'attendance'   => $wpdb->prefix . 'gym_attendance',
			'achievements' => $wpdb->prefix . 'gym_achievements',
			'funnel_log'   => $wpdb->prefix . 'gym_funnel_log',
		);
	}

	/**
	 * Current belt rank per member per program.
	 *
	 * @param string $prefix          Database table prefix.
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL statement.
	 */
	private static function get_ranks_table_sql( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}gym_ranks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			program varchar(64) NOT NULL,
			belt varchar(64) NOT NULL,
			stripes tinyint(1) unsigned NOT NULL DEFAULT 0,
			promoted_at datetime NOT NULL,
			promoted_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_program (user_id, program),
			KEY belt (belt),
			KEY promoted_at (promoted_at)
		) $charset_collate;";
	}

	/**
	 * Full promotion audit trail — every rank change is recorded here.
	 *
	 * @param string $prefix          Database table prefix.
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL statement.
	 */
	private static function get_rank_history_table_sql( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}gym_rank_history (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			program varchar(64) NOT NULL,
			from_belt varchar(64) DEFAULT NULL,
			from_stripes tinyint(1) unsigned DEFAULT NULL,
			to_belt varchar(64) NOT NULL,
			to_stripes tinyint(1) unsigned NOT NULL DEFAULT 0,
			promoted_at datetime NOT NULL,
			promoted_by bigint(20) unsigned DEFAULT NULL,
			notes text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY program (program),
			KEY promoted_at (promoted_at)
		) $charset_collate;";
	}

	/**
	 * Attendance check-in records.
	 *
	 * @param string $prefix          Database table prefix.
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL statement.
	 */
	private static function get_attendance_table_sql( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}gym_attendance (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			class_id bigint(20) unsigned DEFAULT NULL,
			location varchar(64) NOT NULL,
			checked_in_at datetime NOT NULL,
			method varchar(32) NOT NULL DEFAULT 'manual',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY class_id (class_id),
			KEY location (location),
			KEY checked_in_at (checked_in_at),
			KEY user_date (user_id, checked_in_at)
		) $charset_collate;";
	}

	/**
	 * Earned badges and achievements.
	 *
	 * @param string $prefix          Database table prefix.
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL statement.
	 */
	private static function get_achievements_table_sql( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}gym_achievements (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			badge_slug varchar(64) NOT NULL,
			earned_at datetime NOT NULL,
			metadata longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_badge (user_id, badge_slug),
			KEY badge_slug (badge_slug),
			KEY earned_at (earned_at)
		) $charset_collate;";
	}

	/**
	 * CRO funnel-event log — page-view, form-start, form-submit, confirmation.
	 * Used by FunnelLogger for site-side attribution (Jetpack Stats parity).
	 *
	 * @param string $prefix          Database table prefix.
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL statement.
	 */
	private static function get_funnel_log_table_sql( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}gym_funnel_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event varchar(64) NOT NULL,
			session_id varchar(191) NOT NULL,
			page_url varchar(2000) DEFAULT NULL,
			lead_source varchar(191) DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event (event),
			KEY session_id (session_id),
			KEY lead_source (lead_source),
			KEY created_at (created_at)
		) $charset_collate;";
	}
}
