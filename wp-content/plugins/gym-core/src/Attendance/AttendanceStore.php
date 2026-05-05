<?php
/**
 * CRUD operations for attendance records.
 *
 * Provides methods to record check-ins, query attendance history, and
 * generate aggregate stats. All writes fire the gym_core_attendance_recorded
 * action hook for downstream consumers (gamification, promotion eligibility).
 *
 * @package Gym_Core
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Attendance;

use Gym_Core\Data\TableManager;

/**
 * Data access layer for attendance records.
 */
class AttendanceStore {

	/**
	 * Object-cache group for per-user attendance counts. Member dashboards
	 * read get_total_count() multiple times per request; check-ins
	 * invalidate the entry inline.
	 */
	public const COUNT_CACHE_GROUP = 'gym_core_attendance';

	/**
	 * Records a check-in for a member.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id  User ID.
	 * @param string $location Location slug (rockford, beloit).
	 * @param int    $class_id Class post ID (0 for open mat / no specific class).
	 * @param string $method   Check-in method (qr, search, manual, imported).
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function record_checkin( int $user_id, string $location, int $class_id = 0, string $method = 'manual' ) {
		global $wpdb;
		$tables = TableManager::get_table_names();
		$now    = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$tables['attendance'],
			array(
				'user_id'       => $user_id,
				'class_id'      => $class_id,
				'location'      => $location,
				'checked_in_at' => $now,
				'method'        => $method,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$record_id = (int) $wpdb->insert_id;

		self::invalidate_total_count_cache( $user_id );

		/**
		 * Fires after a member checks in to a class.
		 *
		 * @since 1.2.0
		 *
		 * @param int    $record_id Attendance record ID.
		 * @param int    $user_id   Member user ID.
		 * @param int    $class_id  Class post ID (0 if no specific class).
		 * @param string $location  Location slug.
		 * @param string $method    Check-in method.
		 */
		do_action( 'gym_core_attendance_recorded', $record_id, $user_id, $class_id, $location, $method );

		return $record_id;
	}

	/**
	 * Checks if a user has already checked in to a class today.
	 *
	 * @since 1.2.0
	 *
	 * @param int $user_id  User ID.
	 * @param int $class_id Class post ID.
	 * @return bool True if already checked in today.
	 */
	public function has_checked_in_today( int $user_id, int $class_id ): bool {
		global $wpdb;
		$tables = TableManager::get_table_names();
		$today  = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['attendance']}
				WHERE user_id = %d AND class_id = %d AND DATE(checked_in_at) = %s",
				$user_id,
				$class_id,
				$today
			)
		);

		return $count > 0;
	}

	/**
	 * Returns the most recent check-in timestamp for each of the given users.
	 *
	 * One grouped query — replaces a per-user `get_user_history()` fan-out in
	 * roster-enrichment loops.
	 *
	 * @since 2.4.0
	 *
	 * @param int[] $user_ids List of user IDs to look up.
	 * @return array<int, string> Map of user_id => last `checked_in_at` (mysql datetime).
	 *                            Users with no recorded attendance are omitted.
	 */
	public function get_last_attended_for_users( array $user_ids ): array {
		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		if ( empty( $user_ids ) ) {
			return array();
		}

		global $wpdb;
		$tables = TableManager::get_table_names();

		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built from %d count above; values prepared.
			"SELECT user_id, MAX(checked_in_at) AS last_at
			FROM {$tables['attendance']}
			WHERE user_id IN ({$placeholders})
			GROUP BY user_id",
			$user_ids
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql ) ?: array();

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->user_id ] = (string) $row->last_at;
		}

		return $map;
	}

	/**
	 * Returns total attendance counts for many users in a single query.
	 *
	 * One grouped query — replaces a per-user `get_total_count()` fan-out in
	 * roster-enrichment loops. Each returned count is also stored in the
	 * per-user object cache used by `get_total_count()` so subsequent
	 * single-user lookups are free for the rest of the request.
	 *
	 * Users with zero check-ins are returned with a count of 0 so callers
	 * can detect first-timers (`count === 0`) without re-querying.
	 *
	 * @since 2.5.0
	 *
	 * @param int[] $user_ids List of user IDs to count.
	 * @return array<int, int> Map of user_id => total attendance count.
	 */
	public function get_total_counts_for_users( array $user_ids ): array {
		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		if ( empty( $user_ids ) ) {
			return array();
		}

		$counts = array_fill_keys( $user_ids, 0 );

		// Honor cached per-user totals — they may already be primed by
		// member-dashboard reads earlier in the request.
		$missing = array();
		foreach ( $user_ids as $uid ) {
			$cached = wp_cache_get( self::total_count_cache_key( $uid ), self::COUNT_CACHE_GROUP );
			if ( false !== $cached ) {
				$counts[ $uid ] = (int) $cached;
				continue;
			}
			$missing[] = $uid;
		}

		if ( empty( $missing ) ) {
			return $counts;
		}

		global $wpdb;
		$tables = TableManager::get_table_names();

		$placeholders = implode( ',', array_fill( 0, count( $missing ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built from %d count above; values prepared.
		$sql = $wpdb->prepare(
			"SELECT user_id, COUNT(*) AS cnt
			FROM {$tables['attendance']}
			WHERE user_id IN ({$placeholders})
			GROUP BY user_id",
			$missing
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql ) ?: array();

		foreach ( $rows as $row ) {
			$uid = (int) $row->user_id;
			$cnt = (int) $row->cnt;
			$counts[ $uid ] = $cnt;
		}

		// Prime the per-user cache so subsequent get_total_count() calls
		// short-circuit. Includes zero-count users so first-timer detection
		// stays a cache hit.
		foreach ( $missing as $uid ) {
			wp_cache_set(
				self::total_count_cache_key( $uid ),
				$counts[ $uid ],
				self::COUNT_CACHE_GROUP
			);
		}

		return $counts;
	}

	/**
	 * Returns per-user attendance counts since per-user "since" timestamps.
	 *
	 * Replaces a per-user `get_count_since()` fan-out in promotion-eligibility
	 * loops where each user has a different cut-off (e.g., `promoted_at`).
	 *
	 * @since 2.5.0
	 *
	 * @param array<int, string> $user_to_since Map of user_id => since (mysql datetime).
	 * @return array<int, int> Map of user_id => count since their cut-off.
	 *                          Users with zero qualifying check-ins return 0.
	 */
	public function get_counts_since_for_users( array $user_to_since ): array {
		$normalized = array();
		foreach ( $user_to_since as $uid => $since ) {
			$uid   = (int) $uid;
			$since = (string) $since;
			if ( $uid <= 0 || '' === $since ) {
				continue;
			}
			$normalized[ $uid ] = $since;
		}

		if ( empty( $normalized ) ) {
			return array();
		}

		$counts = array_fill_keys( array_keys( $normalized ), 0 );

		global $wpdb;
		$tables = TableManager::get_table_names();

		$clauses = array();
		$values  = array();
		foreach ( $normalized as $uid => $since ) {
			$clauses[] = '(user_id = %d AND checked_in_at >= %s)';
			$values[]  = $uid;
			$values[]  = $since;
		}

		$where = implode( ' OR ', $clauses );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from internal %d/%s placeholders only; values are prepared.
		$sql = $wpdb->prepare(
			"SELECT user_id, COUNT(*) AS cnt
			FROM {$tables['attendance']}
			WHERE {$where}
			GROUP BY user_id",
			$values
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql ) ?: array();

		foreach ( $rows as $row ) {
			$counts[ (int) $row->user_id ] = (int) $row->cnt;
		}

		return $counts;
	}

	/**
	 * Returns attendance history for a user.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param int    $limit   Max records to return (0 = no limit).
	 * @param int    $offset  Offset for pagination.
	 * @param string $from    Optional start date (Y-m-d format).
	 * @param string $to      Optional end date (Y-m-d format).
	 * @return array<int, \stdClass> Array of attendance row objects.
	 */
	public function get_user_history( int $user_id, int $limit = 50, int $offset = 0, string $from = '', string $to = '' ): array {
		// Validate date format before using in SQL.
		if ( $from && ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $from ) ) {
			return array();
		}
		if ( $to && ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $to ) ) {
			return array();
		}

		global $wpdb;
		$tables = TableManager::get_table_names();

		$sql_parts = array(
			'SELECT * FROM ' . $tables['attendance'],
			'WHERE user_id = %d',
		);
		$args      = array( $user_id );

		if ( '' !== $from ) {
			$sql_parts[] = 'AND checked_in_at >= %s';
			$args[]      = $from . ' 00:00:00';
		}
		if ( '' !== $to ) {
			$sql_parts[] = 'AND checked_in_at <= %s';
			$args[]      = $to . ' 23:59:59';
		}

		$sql_parts[] = 'ORDER BY checked_in_at DESC';

		if ( $limit > 0 ) {
			$sql_parts[] = 'LIMIT %d OFFSET %d';
			$args[]      = $limit;
			$args[]      = $offset;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( implode( ' ', $sql_parts ), $args ) ) ?: array();
	}

	/**
	 * Returns the total attendance count for a user.
	 *
	 * The unbounded ($from === '') variant is cached in the object cache —
	 * it's read multiple times per dashboard request and is invalidated
	 * whenever a check-in is recorded for the same user.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $from    Optional start date filter.
	 * @return int Total check-in count.
	 */
	public function get_total_count( int $user_id, string $from = '' ): int {
		if ( '' === $from ) {
			$cache_key = self::total_count_cache_key( $user_id );
			$cached    = wp_cache_get( $cache_key, self::COUNT_CACHE_GROUP );
			if ( false !== $cached ) {
				return (int) $cached;
			}
		}

		global $wpdb;
		$tables = TableManager::get_table_names();

		if ( '' !== $from ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$tables['attendance']} WHERE user_id = %d AND checked_in_at >= %s",
					$user_id,
					$from . ' 00:00:00'
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['attendance']} WHERE user_id = %d",
				$user_id
			)
		);

		wp_cache_set( self::total_count_cache_key( $user_id ), $total, self::COUNT_CACHE_GROUP );

		return $total;
	}

	/**
	 * Cache-key builder for the per-user total attendance count.
	 *
	 * @param int $user_id User ID.
	 * @return string Cache key.
	 */
	private static function total_count_cache_key( int $user_id ): string {
		return 'total_count_' . $user_id;
	}

	/**
	 * Invalidates the cached total attendance count for a user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function invalidate_total_count_cache( int $user_id ): void {
		wp_cache_delete( self::total_count_cache_key( $user_id ), self::COUNT_CACHE_GROUP );
	}

	/**
	 * Returns attendance count since a given date (used for promotion eligibility).
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $since   Date string (Y-m-d H:i:s).
	 * @return int Attendance count since the given date.
	 */
	public function get_count_since( int $user_id, string $since ): int {
		return $this->get_total_count( $user_id, substr( $since, 0, 10 ) );
	}

	/**
	 * Returns today's attendance for a given location.
	 *
	 * @since 1.2.0
	 *
	 * @param string $location Location slug.
	 * @return array<int, \stdClass> Array of attendance row objects with user data.
	 */
	public function get_today_by_location( string $location ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();
		$today  = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name
				FROM {$tables['attendance']} a
				INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
				WHERE a.location = %s AND DATE(a.checked_in_at) = %s
				ORDER BY a.checked_in_at DESC",
				$location,
				$today
			)
		) ?: array();
	}

	/**
	 * Returns today's attendance for multiple locations in a single query.
	 *
	 * Replaces the N-queries-per-location fan-out in AttendanceController::get_today()
	 * when no specific location is requested. Uses a single WHERE location IN (...)
	 * query and groups the results by location slug.
	 *
	 * @since 2.4.1
	 *
	 * @param string[] $locations Location slugs to query.
	 * @return array<string, array<int, object>> Results keyed by location slug.
	 *                                           Locations with no check-ins today
	 *                                           are included as empty arrays.
	 */
	public function get_today_all_locations( array $locations ): array {
		// Normalize and deduplicate; filter out empty strings.
		$locations = array_values( array_unique( array_filter( $locations ) ) );

		// Pre-fill keyed output so locations with zero check-ins always appear.
		$grouped = array_fill_keys( $locations, array() );

		if ( empty( $locations ) ) {
			return $grouped;
		}

		global $wpdb;
		$tables = TableManager::get_table_names();
		$today  = gmdate( 'Y-m-d' );

		$placeholders = implode( ', ', array_fill( 0, count( $locations ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name
				FROM {$tables['attendance']} a
				INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
				WHERE a.location IN ({$placeholders}) AND DATE(a.checked_in_at) = %s
				ORDER BY a.checked_in_at DESC",
				array_merge( $locations, array( $today ) )
			)
		) ?: array();

		foreach ( $rows as $row ) {
			$slug = (string) $row->location;
			if ( array_key_exists( $slug, $grouped ) ) {
				$grouped[ $slug ][] = $row;
			}
		}

		return $grouped;
	}

	/**
	 * Returns today's attendance for a specific class.
	 *
	 * @since 1.2.0
	 *
	 * @param int $class_id Class post ID.
	 * @return array<int, \stdClass> Array of attendance row objects.
	 */
	public function get_today_by_class( int $class_id ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();
		$today  = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name
				FROM {$tables['attendance']} a
				INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
				WHERE a.class_id = %d AND DATE(a.checked_in_at) = %s
				ORDER BY a.checked_in_at DESC",
				$class_id,
				$today
			)
		) ?: array();
	}

	/**
	 * Returns attendance counts grouped by week for trend reporting.
	 *
	 * @since 1.2.0
	 *
	 * @param int $user_id User ID.
	 * @param int $weeks   Number of weeks to look back.
	 * @return array<int, \stdClass> Objects with week_start and count.
	 */
	public function get_weekly_trend( int $user_id, int $weeks = 12 ): array {
		global $wpdb;
		$tables     = TableManager::get_table_names();
		$ts         = strtotime( "-{$weeks} weeks" );
		$since      = gmdate( 'Y-m-d', false !== $ts ? $ts : 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(DATE_SUB(checked_in_at, INTERVAL WEEKDAY(checked_in_at) DAY)) AS week_start,
						COUNT(*) AS count
				FROM {$tables['attendance']}
				WHERE user_id = %d AND checked_in_at >= %s
				GROUP BY week_start
				ORDER BY week_start DESC",
				$user_id,
				$since . ' 00:00:00'
			)
		) ?: array();
	}

	/**
	 * Returns the distinct weeks a user has attended (for streak calculation).
	 *
	 * @since 1.2.0
	 *
	 * @param int $user_id User ID.
	 * @return array<int, string> Array of week-start dates (Y-m-d, Monday-based).
	 */
	public function get_attended_weeks( int $user_id ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(DATE_SUB(checked_in_at, INTERVAL WEEKDAY(checked_in_at) DAY)) AS week_start
				FROM {$tables['attendance']}
				WHERE user_id = %d
				ORDER BY week_start DESC",
				$user_id
			)
		);

		return $results ?: array();
	}
}
