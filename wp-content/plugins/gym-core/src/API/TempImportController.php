<?php
/**
 * TEMPORARY: One-time REST endpoint for Spark data import.
 *
 * DELETE THIS FILE after the import is complete.
 *
 * Trigger via:
 *   curl -X POST https://haanpaa-staging.mystagingwebsite.com/wp-json/gym/v1/import/spark \
 *     -H "X-Import-Token: <token>"
 *
 * Add ?dry_run=1 for a dry run.
 *
 * @package Gym_Core
 */

declare( strict_types=1 );

namespace Gym_Core\API;

use Gym_Core\Data\TableManager;

final class TempImportController {

	/**
	 * One-time secret. Checked via hash_equals to prevent timing attacks.
	 */
	private const TOKEN = 'b629f866424b8273ae5f21b387074130a06c59aa90891d588304a64e872fc51f';

	/**
	 * Register the temporary route.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'gym/v1',
			'/import/spark',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle_import' ),
				'permission_callback' => array( self::class, 'check_token' ),
			)
		);
	}

	/**
	 * Verify the import token.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool|\WP_Error
	 */
	public static function check_token( \WP_REST_Request $request ) {
		$token = $request->get_header( 'X-Import-Token' );
		if ( ! $token || ! hash_equals( self::TOKEN, $token ) ) {
			return new \WP_Error( 'forbidden', 'Invalid import token.', array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Run the full Spark import: users, then belt ranks.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public static function handle_import( \WP_REST_Request $request ): \WP_REST_Response {
		// Increase limits for bulk import.
		// phpcs:ignore WordPress.PHP.IniSet.Risky
		set_time_limit( 300 );

		$dry_run  = (bool) $request->get_param( 'dry_run' );
		$data_dir = GYM_CORE_PATH . 'data/import/';
		$log      = array();

		// Step 1: Import users.
		$user_result = self::import_users( $data_dir . 'spark_users.csv', $dry_run );
		$log[]       = $user_result;

		// Step 2: Import belt ranks (resolves email→user_id inline).
		$rank_result = self::import_belt_ranks( $data_dir . 'spark_belt_ranks_by_email.csv', $dry_run );
		$log[]       = $rank_result;

		return new \WP_REST_Response(
			array(
				'dry_run' => $dry_run,
				'results' => $log,
			),
			200
		);
	}

	/**
	 * Import users from CSV.
	 *
	 * @param string $file    CSV file path.
	 * @param bool   $dry_run Whether to skip writes.
	 * @return array<string, mixed>
	 */
	private static function import_users( string $file, bool $dry_run ): array {
		$rows = self::read_csv( $file );
		if ( null === $rows ) {
			return array(
				'step'    => 'users',
				'error'   => "Cannot read {$file}",
				'imported' => 0,
				'skipped'  => 0,
			);
		}

		$wp_fields = array( 'email', 'first_name', 'last_name', 'username', 'role', 'display_name' );
		$imported  = 0;
		$skipped   = 0;
		$warnings  = array();

		foreach ( $rows as $i => $row ) {
			$email = sanitize_email( $row['email'] ?? '' );

			if ( '' === $email || ! is_email( $email ) ) {
				$warnings[] = "Row {$i}: invalid email, skipped.";
				++$skipped;
				continue;
			}

			if ( $dry_run ) {
				++$imported;
				continue;
			}

			$existing_id = email_exists( $email );
			if ( $existing_id ) {
				// Update existing user meta only.
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
					$warnings[] = "Row {$i}: failed to create {$email}: " . $user_id->get_error_message();
					++$skipped;
					continue;
				}
			}

			// Set non-standard columns as user meta.
			foreach ( $row as $key => $value ) {
				if ( in_array( $key, $wp_fields, true ) || '' === $value ) {
					continue;
				}
				update_user_meta( $user_id, sanitize_key( $key ), sanitize_text_field( $value ) );
			}

			++$imported;
		}

		return array(
			'step'     => 'users',
			'total'    => count( $rows ),
			'imported' => $imported,
			'skipped'  => $skipped,
			'warnings' => $warnings,
		);
	}

	/**
	 * Import belt ranks from CSV, resolving email→user_id inline.
	 *
	 * @param string $file    CSV file path.
	 * @param bool   $dry_run Whether to skip writes.
	 * @return array<string, mixed>
	 */
	private static function import_belt_ranks( string $file, bool $dry_run ): array {
		$rows = self::read_csv( $file );
		if ( null === $rows ) {
			return array(
				'step'    => 'belt_ranks',
				'error'   => "Cannot read {$file}",
				'imported' => 0,
				'skipped'  => 0,
			);
		}

		global $wpdb;
		$tables   = TableManager::get_table_names();
		$imported = 0;
		$skipped  = 0;
		$warnings = array();

		foreach ( $rows as $i => $row ) {
			$email   = sanitize_email( $row['email'] ?? '' );
			$program = sanitize_text_field( $row['program'] ?? '' );
			$belt    = sanitize_text_field( $row['belt'] ?? '' );
			$stripes = (int) ( $row['stripes'] ?? 0 );
			$date    = $row['promoted_at'] ?? gmdate( 'Y-m-d H:i:s' );

			if ( '' === $email || '' === $program || '' === $belt ) {
				$warnings[] = "Row {$i}: missing required field, skipped.";
				++$skipped;
				continue;
			}

			// Resolve email to user_id.
			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				$warnings[] = "Row {$i}: no WP user found for {$email}, skipped.";
				++$skipped;
				continue;
			}
			$user_id = (int) $user->ID;

			if ( $dry_run ) {
				++$imported;
				continue;
			}

			// Check if rank already exists for this user+program.
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

			// Insert current rank.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->replace(
				$tables['ranks'],
				array(
					'user_id'     => $user_id,
					'program'     => $program,
					'belt'        => $belt,
					'stripes'     => $stripes,
					'promoted_at' => $date,
					'promoted_by' => null,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d' )
			);

			// Insert audit trail.
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
					'promoted_by'  => null,
					'notes'        => sanitize_text_field( $row['notes'] ?? 'Imported from Spark Membership' ),
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s' )
			);

			++$imported;
		}

		return array(
			'step'     => 'belt_ranks',
			'total'    => count( $rows ),
			'imported' => $imported,
			'skipped'  => $skipped,
			'warnings' => $warnings,
		);
	}

	/**
	 * Read CSV into associative arrays.
	 *
	 * @param string $file File path.
	 * @return array<int, array<string, string>>|null
	 */
	private static function read_csv( string $file ): ?array {
		if ( ! file_exists( $file ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file, 'r' );
		if ( false === $handle ) {
			return null;
		}

		$headers = fgetcsv( $handle );
		if ( false === $headers ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			return null;
		}

		$headers = array_map( 'trim', $headers );
		$headers = array_map( 'strtolower', $headers );

		$rows = array();
		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			if ( count( $data ) !== count( $headers ) ) {
				continue;
			}
			$rows[] = array_combine( $headers, $data );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		return $rows;
	}
}
