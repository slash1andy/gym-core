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
	 * Returns attendance history for a user.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param int    $limit   Max records to return (0 = no limit).
	 * @param int    $offset  Offset for pagination.
	 * @param string $from    Optional start date (Y-m-d format).
	 * @param string $to      Optional end date (Y-m-d format).
	 * @return array<int, object> Array of attendance row objects.
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

		$where = $wpdb->prepare( 'WHERE user_id = %d', $user_id );

		if ( '' !== $from ) {
			$where .= $wpdb->prepare( ' AND checked_in_at >= %s', $from . ' 00:00:00' );
		}
		if ( '' !== $to ) {
			$where .= $wpdb->prepare( ' AND checked_in_at <= %s', $to . ' 23:59:59' );
		}

		$sql = "SELECT * FROM {$tables['attendance']} {$where} ORDER BY checked_in_at DESC";

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql ) ?: array();
	}

	/**
	 * Returns the total attendance count for a user.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $from    Optional start date filter.
	 * @return int Total check-in count.
	 */
	public function get_total_count( int $user_id, string $from = '' ): int {
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
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['attendance']} WHERE user_id = %d",
				$user_id
			)
		);
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
	 * @return array<int, object> Array of attendance row objects with user data.
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
	 * Returns today's attendance for a specific class.
	 *
	 * @since 1.2.0
	 *
	 * @param int $class_id Class post ID.
	 * @return array<int, object> Array of attendance row objects.
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
	 * @return array<int, object> Objects with week_start and count.
	 */
	public function get_weekly_trend( int $user_id, int $weeks = 12 ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();
		$since  = gmdate( 'Y-m-d', strtotime( "-{$weeks} weeks" ) );

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
