<?php
/**
 * CRUD operations for belt rank data.
 *
 * Provides methods to read, create, and update rank records in the
 * gym_ranks and gym_rank_history custom tables. All rank changes are
 * recorded in history for a complete audit trail.
 *
 * @package Gym_Core
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Rank;

use Gym_Core\Data\TableManager;

/**
 * Data access layer for belt rank records.
 */
final class RankStore {

	/**
	 * Returns the current rank for a user in a given program.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $program Program slug (e.g., 'adult-bjj').
	 * @return object|null Row object with belt, stripes, promoted_at, promoted_by, or null.
	 */
	public function get_rank( int $user_id, string $program ): ?object {
		global $wpdb;
		$tables = TableManager::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT belt, stripes, promoted_at, promoted_by FROM {$tables['ranks']} WHERE user_id = %d AND program = %s",
				$user_id,
				$program
			)
		);

		return $result ?: null;
	}

	/**
	 * Returns all current ranks for a user across all programs.
	 *
	 * @since 1.2.0
	 *
	 * @param int $user_id User ID.
	 * @return array<int, object> Array of rank row objects.
	 */
	public function get_all_ranks( int $user_id ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT program, belt, stripes, promoted_at, promoted_by FROM {$tables['ranks']} WHERE user_id = %d ORDER BY program",
				$user_id
			)
		) ?: array();
	}

	/**
	 * Promotes a user to a new belt/stripe level.
	 *
	 * Updates the current rank in gym_ranks and appends a record to
	 * gym_rank_history. Fires the gym_core_rank_changed action hook.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id     User ID being promoted.
	 * @param string $program     Program slug.
	 * @param string $new_belt    New belt slug.
	 * @param int    $new_stripes New stripe count.
	 * @param int    $promoted_by User ID of the promoter (instructor/coach).
	 * @param string $notes       Optional notes for the audit trail.
	 * @return bool True on success.
	 */
	public function promote( int $user_id, string $program, string $new_belt, int $new_stripes, int $promoted_by, string $notes = '' ): bool {
		global $wpdb;
		$tables = TableManager::get_table_names();

		// Wrap read-then-write in a transaction to prevent race conditions.
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Get the current rank for history.
		$current   = $this->get_rank( $user_id, $program );
		$from_belt = $current->belt ?? null;
		$from_str  = isset( $current->stripes ) ? (int) $current->stripes : null;
		$now       = gmdate( 'Y-m-d H:i:s' );

		// Update or insert the current rank.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rank_result = $wpdb->replace(
			$tables['ranks'],
			array(
				'user_id'     => $user_id,
				'program'     => $program,
				'belt'        => $new_belt,
				'stripes'     => $new_stripes,
				'promoted_at' => $now,
				'promoted_by' => $promoted_by,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d' )
		);

		if ( false === $rank_result ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return false;
		}

		// Record in rank history (never deleted — audit trail).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$history_result = $wpdb->insert(
			$tables['rank_history'],
			array(
				'user_id'      => $user_id,
				'program'      => $program,
				'from_belt'    => $from_belt,
				'from_stripes' => $from_str,
				'to_belt'      => $new_belt,
				'to_stripes'   => $new_stripes,
				'promoted_at'  => $now,
				'promoted_by'  => $promoted_by,
				'notes'        => $notes,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s' )
		);

		if ( false === $history_result ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return false;
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		/**
		 * Fires when a member's belt rank changes.
		 *
		 * @since 1.2.0
		 *
		 * @param int    $user_id     The member who was promoted.
		 * @param string $program     Program slug.
		 * @param string $new_belt    New belt slug.
		 * @param int    $new_stripes New stripe count.
		 * @param string $from_belt   Previous belt slug (null if first rank).
		 * @param int    $promoted_by User ID of the promoter.
		 */
		do_action( 'gym_core_rank_changed', $user_id, $program, $new_belt, $new_stripes, $from_belt, $promoted_by );

		return true;
	}

	/**
	 * Adds a stripe to a member's current belt.
	 *
	 * Validates against max_stripes from RankDefinitions before adding.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id     User ID.
	 * @param string $program     Program slug.
	 * @param int    $promoted_by User ID of the promoter.
	 * @param string $notes       Optional notes.
	 * @return bool True on success, false if at max stripes or no current rank.
	 */
	public function add_stripe( int $user_id, string $program, int $promoted_by, string $notes = '' ): bool {
		$current = $this->get_rank( $user_id, $program );

		if ( ! $current ) {
			return false;
		}

		$belt_def    = RankDefinitions::get_belts( $program );
		$current_def = null;

		foreach ( $belt_def as $def ) {
			if ( $def['slug'] === $current->belt ) {
				$current_def = $def;
				break;
			}
		}

		if ( ! $current_def || (int) $current->stripes >= $current_def['max_stripes'] ) {
			return false;
		}

		return $this->promote(
			$user_id,
			$program,
			$current->belt,
			(int) $current->stripes + 1,
			$promoted_by,
			$notes
		);
	}

	/**
	 * Returns the full promotion history for a user.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $program Optional program slug to filter by.
	 * @return array<int, object> Array of rank history row objects.
	 */
	public function get_history( int $user_id, string $program = '' ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();

		if ( '' !== $program ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$tables['rank_history']} WHERE user_id = %d AND program = %s ORDER BY promoted_at DESC",
					$user_id,
					$program
				)
			) ?: array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tables['rank_history']} WHERE user_id = %d ORDER BY promoted_at DESC",
				$user_id
			)
		) ?: array();
	}

	/**
	 * Returns members at a given belt level in a program.
	 *
	 * @since 1.2.0
	 *
	 * @param string $program   Program slug.
	 * @param string $belt_slug Belt slug.
	 * @return array<int, object> Array of rank row objects (includes user_id).
	 */
	public function get_members_at_belt( string $program, string $belt_slug ): array {
		global $wpdb;
		$tables = TableManager::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, belt, stripes, promoted_at, promoted_by FROM {$tables['ranks']} WHERE program = %s AND belt = %s ORDER BY stripes DESC, promoted_at ASC",
				$program,
				$belt_slug
			)
		) ?: array();
	}

	/**
	 * Returns the total number of ranked members per program.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, int> Program slug => count.
	 */
	public function get_member_counts_by_program(): array {
		global $wpdb;
		$tables = TableManager::get_table_names();

		$table_name = esc_sql( $tables['ranks'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT program, COUNT(*) as count FROM `{$table_name}` WHERE %d = %d GROUP BY program", 1, 1 ),
			OBJECT_K
		);

		$counts = array();
		if ( $results ) {
			foreach ( $results as $program => $row ) {
				$counts[ $program ] = (int) $row->count;
			}
		}

		return $counts;
	}
}
