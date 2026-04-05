<?php
/**
 * WP-CLI import commands for gym-core data migration.
 *
 * Reads CSV files produced by CoWork playbooks and bulk-inserts into
 * custom database tables. Supports --dry-run, --batch-size, and --skip-existing.
 *
 * Usage:
 *   wp gym import belt-ranks --file=belt-ranks.csv
 *   wp gym import attendance --file=attendance.csv
 *   wp gym import achievements --file=badges.csv
 *   wp gym import users --file=members.csv
 *   wp gym import notes --file=member-notes.csv
 *
 * @package Gym_Core
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\CLI;

use Gym_Core\Data\TableManager;

/**
 * Import data from CSV files into gym-core custom tables.
 */
final class ImportCommand {

	/**
	 * Registers the WP-CLI command group.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'gym import', self::class );
	}

	/**
	 * Import belt rank records from CSV into gym_ranks and gym_rank_history.
	 *
	 * ## OPTIONS
	 *
	 * --file=<file>
	 * : Path to the CSV file.
	 *
	 * [--dry-run]
	 * : Validate without writing to the database.
	 *
	 * [--batch-size=<number>]
	 * : Records per batch insert. Default 500.
	 *
	 * [--skip-existing]
	 * : Skip records where user_id+program already exists in gym_ranks.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gym import belt-ranks --file=belt-ranks.csv
	 *     wp gym import belt-ranks --file=belt-ranks.csv --dry-run
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function belt_ranks( array $args, array $assoc_args ): void {
		$file       = $assoc_args['file'] ?? '';
		$dry_run    = isset( $assoc_args['dry-run'] );
		$batch_size = (int) ( $assoc_args['batch-size'] ?? 500 );
		$skip       = isset( $assoc_args['skip-existing'] );

		$rows = $this->read_csv( $file );
		if ( empty( $rows ) ) {
			return;
		}

		$required = array( 'user_id', 'program', 'belt' );
		if ( ! $this->validate_columns( $rows[0] ?? array(), $required ) ) {
			return;
		}

		global $wpdb;
		$tables   = TableManager::get_table_names();
		$imported = 0;
		$skipped  = 0;

		foreach ( $rows as $i => $row ) {
			$user_id = (int) ( $row['user_id'] ?? 0 );
			$program = sanitize_text_field( $row['program'] ?? '' );
			$belt    = sanitize_text_field( $row['belt'] ?? '' );
			$stripes = (int) ( $row['stripes'] ?? 0 );
			$date    = $row['promoted_at'] ?? gmdate( 'Y-m-d H:i:s' );
			$by      = ! empty( $row['promoted_by'] ) ? (int) $row['promoted_by'] : null;

			if ( 0 === $user_id || '' === $program || '' === $belt ) {
				\WP_CLI::warning( "Row {$i}: missing required field (user_id, program, or belt). Skipping." );
				++$skipped;
				continue;
			}

			if ( $skip ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$tables['ranks']} WHERE user_id = %d AND program = %s",
						$user_id,
						$program
					)
				);
				if ( $exists ) {
					++$skipped;
					continue;
				}
			}

			if ( $dry_run ) {
				\WP_CLI::log( "[DRY RUN] Would import: user={$user_id} program={$program} belt={$belt} stripes={$stripes}" );
				++$imported;
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->replace(
				$tables['ranks'],
				array(
					'user_id'     => $user_id,
					'program'     => $program,
					'belt'        => $belt,
					'stripes'     => $stripes,
					'promoted_at' => $date,
					'promoted_by' => $by,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d' )
			);

			// Also record in rank history for audit trail.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$tables['rank_history'],
				array(
					'user_id'      => $user_id,
					'program'      => $program,
					'from_belt'    => null,
					'from_stripes' => null,
					'to_belt'      => $belt,
					'to_stripes'   => $stripes,
					'promoted_at'  => $date,
					'promoted_by'  => $by,
					'notes'        => sanitize_text_field( $row['notes'] ?? 'Imported from Spark Membership' ),
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s' )
			);

			++$imported;
		}

		$this->report( 'belt-ranks', $imported, $skipped, $dry_run );
	}

	/**
	 * Import attendance records from CSV into gym_attendance.
	 *
	 * ## OPTIONS
	 *
	 * --file=<file>
	 * : Path to the CSV file.
	 *
	 * [--dry-run]
	 * : Validate without writing to the database.
	 *
	 * [--batch-size=<number>]
	 * : Records per batch insert. Default 500.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gym import attendance --file=attendance.csv
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function attendance( array $args, array $assoc_args ): void {
		$file       = $assoc_args['file'] ?? '';
		$dry_run    = isset( $assoc_args['dry-run'] );
		$batch_size = (int) ( $assoc_args['batch-size'] ?? 500 );

		$rows = $this->read_csv( $file );
		if ( empty( $rows ) ) {
			return;
		}

		$required = array( 'user_id', 'location', 'checked_in_at' );
		if ( ! $this->validate_columns( $rows[0] ?? array(), $required ) ) {
			return;
		}

		global $wpdb;
		$tables   = TableManager::get_table_names();
		$imported = 0;
		$skipped  = 0;
		$batch    = array();

		foreach ( $rows as $i => $row ) {
			$user_id  = (int) ( $row['user_id'] ?? 0 );
			$location = sanitize_text_field( $row['location'] ?? '' );
			$date     = $row['checked_in_at'] ?? '';

			if ( 0 === $user_id || '' === $location || '' === $date ) {
				\WP_CLI::warning( "Row {$i}: missing required field. Skipping." );
				++$skipped;
				continue;
			}

			if ( $dry_run ) {
				++$imported;
				continue;
			}

			$batch[] = $wpdb->prepare(
				'(%d, %d, %s, %s, %s)',
				$user_id,
				! empty( $row['class_id'] ) ? (int) $row['class_id'] : 0,
				$location,
				$date,
				sanitize_text_field( $row['method'] ?? 'imported' )
			);

			if ( count( $batch ) >= $batch_size ) {
				$this->flush_attendance_batch( $tables['attendance'], $batch );
				$imported += count( $batch );
				$batch     = array();
			}
		}

		if ( ! empty( $batch ) && ! $dry_run ) {
			$this->flush_attendance_batch( $tables['attendance'], $batch );
			$imported += count( $batch );
		} elseif ( $dry_run ) {
			// Count was tracked in loop.
		}

		$this->report( 'attendance', $imported, $skipped, $dry_run );
	}

	/**
	 * Import achievement/badge records from CSV into gym_achievements.
	 *
	 * ## OPTIONS
	 *
	 * --file=<file>
	 * : Path to the CSV file.
	 *
	 * [--dry-run]
	 * : Validate without writing to the database.
	 *
	 * [--skip-existing]
	 * : Skip records where user_id+badge_slug already exists.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gym import achievements --file=badges.csv
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function achievements( array $args, array $assoc_args ): void {
		$file    = $assoc_args['file'] ?? '';
		$dry_run = isset( $assoc_args['dry-run'] );
		$skip    = isset( $assoc_args['skip-existing'] );

		$rows = $this->read_csv( $file );
		if ( empty( $rows ) ) {
			return;
		}

		$required = array( 'user_id', 'badge_slug' );
		if ( ! $this->validate_columns( $rows[0] ?? array(), $required ) ) {
			return;
		}

		global $wpdb;
		$tables   = TableManager::get_table_names();
		$imported = 0;
		$skipped  = 0;

		foreach ( $rows as $i => $row ) {
			$user_id    = (int) ( $row['user_id'] ?? 0 );
			$badge_slug = sanitize_key( $row['badge_slug'] ?? '' );

			if ( 0 === $user_id || '' === $badge_slug ) {
				\WP_CLI::warning( "Row {$i}: missing required field. Skipping." );
				++$skipped;
				continue;
			}

			if ( $dry_run ) {
				\WP_CLI::log( "[DRY RUN] Would import: user={$user_id} badge={$badge_slug}" );
				++$imported;
				continue;
			}

			$data = array(
				'user_id'    => $user_id,
				'badge_slug' => $badge_slug,
				'earned_at'  => $row['earned_at'] ?? gmdate( 'Y-m-d H:i:s' ),
				'metadata'   => ! empty( $row['metadata'] ) ? sanitize_text_field( $row['metadata'] ) : null,
			);

			if ( $skip ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$tables['achievements']} WHERE user_id = %d AND badge_slug = %s",
						$user_id,
						$badge_slug
					)
				);
				if ( $exists ) {
					++$skipped;
					continue;
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->replace(
				$tables['achievements'],
				$data,
				array( '%d', '%s', '%s', '%s' )
			);

			++$imported;
		}

		$this->report( 'achievements', $imported, $skipped, $dry_run );
	}

	/**
	 * Import WordPress users with gym-specific meta from CSV.
	 *
	 * Creates WP users and sets user meta for belt_rank, membership_tier,
	 * home_location, and spark_member_id. Does NOT set passwords — users
	 * must reset via email on first login.
	 *
	 * ## OPTIONS
	 *
	 * --file=<file>
	 * : Path to the CSV file.
	 *
	 * [--dry-run]
	 * : Validate without writing to the database.
	 *
	 * [--skip-existing]
	 * : Skip users whose email already exists in WordPress.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gym import users --file=members.csv --dry-run
	 *     wp gym import users --file=members.csv --skip-existing
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function users( array $args, array $assoc_args ): void {
		$file    = $assoc_args['file'] ?? '';
		$dry_run = isset( $assoc_args['dry-run'] );
		$skip    = isset( $assoc_args['skip-existing'] );

		$rows = $this->read_csv( $file );
		if ( empty( $rows ) ) {
			return;
		}

		$required = array( 'email', 'first_name', 'last_name' );
		if ( ! $this->validate_columns( $rows[0] ?? array(), $required ) ) {
			return;
		}

		$imported = 0;
		$skipped  = 0;

		// Standard WP user fields — everything else becomes user meta.
		$wp_fields = array( 'email', 'first_name', 'last_name', 'username', 'role', 'display_name' );

		foreach ( $rows as $i => $row ) {
			$email = sanitize_email( $row['email'] ?? '' );

			if ( '' === $email || ! is_email( $email ) ) {
				\WP_CLI::warning( "Row {$i}: invalid or missing email. Skipping." );
				++$skipped;
				continue;
			}

			if ( $skip && email_exists( $email ) ) {
				++$skipped;
				continue;
			}

			if ( $dry_run ) {
				$name = ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' );
				\WP_CLI::log( "[DRY RUN] Would import: {$email} ({$name})" );
				++$imported;
				continue;
			}

			$existing_id = email_exists( $email );
			if ( $existing_id ) {
				// Update existing user's meta only.
				$user_id = (int) $existing_id;
				wp_update_user(
					array(
						'ID'           => $user_id,
						'first_name'   => sanitize_text_field( $row['first_name'] ?? '' ),
						'last_name'    => sanitize_text_field( $row['last_name'] ?? '' ),
						'display_name' => sanitize_text_field(
							$row['display_name'] ?? ( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) )
						),
					)
				);
			} else {
				$username = sanitize_user( $row['username'] ?? strstr( $email, '@', true ), true );

				// Ensure unique username.
				if ( username_exists( $username ) ) {
					$username .= wp_rand( 100, 999 );
				}

				$user_id = wp_insert_user(
					array(
						'user_login'   => $username,
						'user_email'   => $email,
						'first_name'   => sanitize_text_field( $row['first_name'] ?? '' ),
						'last_name'    => sanitize_text_field( $row['last_name'] ?? '' ),
						'display_name' => sanitize_text_field(
							$row['display_name'] ?? ( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) )
						),
						'role'         => sanitize_text_field( $row['role'] ?? 'customer' ),
						'user_pass'    => wp_generate_password( 24, true, true ),
					)
				);

				if ( is_wp_error( $user_id ) ) {
					\WP_CLI::warning( "Row {$i}: failed to create user {$email}: " . $user_id->get_error_message() );
					++$skipped;
					continue;
				}
			}

			// Set any non-standard columns as user meta.
			foreach ( $row as $key => $value ) {
				if ( in_array( $key, $wp_fields, true ) || '' === $value ) {
					continue;
				}
				update_user_meta( $user_id, sanitize_key( $key ), sanitize_text_field( $value ) );
			}

			++$imported;
		}

		$this->report( 'users', $imported, $skipped, $dry_run );
	}

	/**
	 * Import member notes from CSV into Jetpack CRM contact notes.
	 *
	 * Reads a CSV with member_email, note_date, note_content, and optional
	 * note_type columns. Looks up the WP user by email, resolves the Jetpack
	 * CRM contact, and creates a CRM activity log entry for each row.
	 *
	 * ## OPTIONS
	 *
	 * --file=<file>
	 * : Path to the CSV file.
	 *
	 * [--dry-run]
	 * : Validate without writing to the database.
	 *
	 * [--batch-size=<number>]
	 * : Records per progress tick. Default 500.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gym import notes --file=member-notes.csv
	 *     wp gym import notes --file=member-notes.csv --dry-run
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function notes( array $args, array $assoc_args ): void {
		$file       = $assoc_args['file'] ?? '';
		$dry_run    = isset( $assoc_args['dry-run'] );
		$batch_size = (int) ( $assoc_args['batch-size'] ?? 500 );

		if ( ! function_exists( 'zeroBS_getContactByEmail' ) && ! function_exists( 'zeroBSCRM_addUpdateLog' ) ) {
			\WP_CLI::error( 'Jetpack CRM is not active. Cannot import notes without it.' );
			return;
		}

		$rows = $this->read_csv( $file );
		if ( empty( $rows ) ) {
			return;
		}

		$required = array( 'member_email', 'note_date', 'note_content' );
		if ( ! $this->validate_columns( $rows[0] ?? array(), $required ) ) {
			return;
		}

		$imported = 0;
		$skipped  = 0;

		// Cache email → CRM contact ID lookups to avoid repeated queries.
		$contact_cache = array();

		foreach ( $rows as $i => $row ) {
			$email        = sanitize_email( $row['member_email'] ?? '' );
			$note_date    = sanitize_text_field( $row['note_date'] ?? '' );
			$note_content = sanitize_text_field( $row['note_content'] ?? '' );
			$note_type    = sanitize_text_field( $row['note_type'] ?? 'note' );

			if ( '' === $email || '' === $note_content ) {
				\WP_CLI::warning( "Row {$i}: missing required field (member_email or note_content). Skipping." );
				++$skipped;
				continue;
			}

			// Resolve CRM contact ID (with cache).
			if ( ! isset( $contact_cache[ $email ] ) ) {
				$contact_cache[ $email ] = $this->resolve_crm_contact_by_email( $email );
			}

			$contact_id = $contact_cache[ $email ];

			if ( 0 === $contact_id ) {
				\WP_CLI::warning( "Row {$i}: no WP user or CRM contact found for {$email}. Skipping." );
				++$skipped;
				continue;
			}

			if ( $dry_run ) {
				\WP_CLI::log( "[DRY RUN] Would import note for {$email} (contact #{$contact_id}): {$note_content}" );
				++$imported;
				continue;
			}

			if ( function_exists( 'zeroBSCRM_addUpdateLog' ) ) {
				zeroBSCRM_addUpdateLog( // @phpstan-ignore-line
					$contact_id,
					-1,
					-1,
					array(
						'type'      => $note_type,
						'shortdesc' => $note_content,
						'longdesc'  => '',
						'created'   => '' !== $note_date ? strtotime( $note_date ) : time(),
					)
				);
			}

			++$imported;

			if ( 0 === $imported % $batch_size ) {
				\WP_CLI::log( sprintf( 'Progress: %d imported so far...', $imported ) );
			}
		}

		$this->report( 'notes', $imported, $skipped, $dry_run );
	}

	/**
	 * Resolves a CRM contact ID from an email address.
	 *
	 * First looks up the WP user by email, then attempts to find the
	 * corresponding Jetpack CRM contact. Falls back to direct CRM email
	 * lookup if no WP user is found.
	 *
	 * @param string $email Email address to look up.
	 * @return int CRM contact ID, or 0 if not found.
	 */
	private function resolve_crm_contact_by_email( string $email ): int {
		// Try Jetpack CRM's native email lookup.
		if ( function_exists( 'zeroBS_getContactByEmail' ) ) {
			$contact = zeroBS_getContactByEmail( $email ); // @phpstan-ignore-line
			if ( ! empty( $contact ) && isset( $contact['id'] ) ) {
				return (int) $contact['id'];
			}
		}

		// Fallback: look up WP user, then try CRM contact ID from user meta.
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return 0;
		}

		// Jetpack CRM stores a WP user → contact link.
		if ( function_exists( 'zeroBS_getCustomerIDWithEmail' ) ) {
			$id = zeroBS_getCustomerIDWithEmail( $email ); // @phpstan-ignore-line
			if ( $id ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/**
	 * Reads a CSV file and returns an array of associative arrays.
	 *
	 * @param string $file File path.
	 * @return array<int, array<string, string>> Rows from the CSV. Exits via WP_CLI::error() on failure.
	 */
	private function read_csv( string $file ): array {
		if ( '' === $file ) {
			\WP_CLI::error( 'The --file argument is required.' );
			return null;
		}

		if ( ! file_exists( $file ) ) {
			\WP_CLI::error( "File not found: {$file}" );
			return null;
		}

		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			\WP_CLI::error( "Cannot open file: {$file}" );
			return null;
		}

		$headers = fgetcsv( $handle );
		if ( false === $headers ) {
			\WP_CLI::error( 'CSV file is empty or has no header row.' );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return null;
		}

		// Normalize header names.
		$headers = array_map( 'trim', $headers );
		$headers = array_map( 'strtolower', $headers );

		$rows = array();
		while ( ( $data = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			if ( count( $data ) !== count( $headers ) ) {
				continue; // Skip malformed rows.
			}
			$rows[] = array_combine( $headers, $data );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		\WP_CLI::log( sprintf( 'Read %d rows from %s', count( $rows ), basename( $file ) ) );

		return $rows;
	}

	/**
	 * Validates that required columns exist in the CSV header.
	 *
	 * @param array<string, string> $row      First row (used for column check).
	 * @param array<int, string>    $required Required column names.
	 * @return bool
	 */
	private function validate_columns( array $row, array $required ): bool {
		$missing = array_diff( $required, array_keys( $row ) );
		if ( ! empty( $missing ) ) {
			\WP_CLI::error( 'Missing required columns: ' . implode( ', ', $missing ) );
			return false;
		}
		return true;
	}

	/**
	 * Flushes a batch of attendance records with a single INSERT.
	 *
	 * @param string        $table Table name.
	 * @param array<string> $batch Array of prepared value strings.
	 * @return void
	 */
	private function flush_attendance_batch( string $table, array $batch ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			"INSERT INTO {$table} (user_id, class_id, location, checked_in_at, method) VALUES "
			. implode( ', ', $batch )
		);
	}

	/**
	 * Outputs a summary report.
	 *
	 * @param string $type     Import type label.
	 * @param int    $imported Count of imported records.
	 * @param int    $skipped  Count of skipped records.
	 * @param bool   $dry_run  Whether this was a dry run.
	 * @return void
	 */
	private function report( string $type, int $imported, int $skipped, bool $dry_run ): void {
		$prefix = $dry_run ? '[DRY RUN] ' : '';
		\WP_CLI::success(
			sprintf(
				'%s%s import complete: %d imported, %d skipped.',
				$prefix,
				$type,
				$imported,
				$skipped
			)
		);
	}
}
