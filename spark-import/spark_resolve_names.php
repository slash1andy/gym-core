<?php
/**
 * Resolve student names in spark_attendance.csv to email addresses
 * by matching against existing WordPress users.
 *
 * Usage:
 *   wp eval-file spark-import/spark_resolve_names.php
 *
 * Input:  spark-import/spark_attendance.csv   (student_name keyed)
 * Output: spark-import/spark_attendance_resolved.csv (email keyed, ready for import)
 *         spark-import/spark_attendance_unmatched.csv (rows that couldn't be resolved)
 *         spark-import/spark_name_map.csv (the name→email map used, for auditing)
 *
 * Matching strategy (in priority order):
 *   1. Exact display_name match
 *   2. Exact "first_name last_name" match from user meta
 *   3. Case-insensitive display_name match
 *   4. Case-insensitive "first_name last_name" match
 *   5. Fuzzy: last_name match + first_name starts-with (handles nicknames)
 *
 * @package HaanpaaMartialArts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script must be run via WP-CLI: wp eval-file spark_resolve_names.php' );
}

$base_dir  = dirname( __FILE__ ) . '/';
$input     = $base_dir . 'spark_attendance.csv';
$output    = $base_dir . 'spark_attendance_resolved.csv';
$unmatched = $base_dir . 'spark_attendance_unmatched.csv';
$map_file  = $base_dir . 'spark_name_map.csv';

if ( ! file_exists( $input ) ) {
	WP_CLI::error( "Input file not found: {$input}" );
}

WP_CLI::log( 'Building name→email lookup from WordPress users...' );

/*
 * Build lookup arrays from all WordPress users.
 * We index by multiple name variants for flexible matching.
 */
$users = get_users( array(
	'number' => -1,
	'fields' => array( 'ID', 'user_email', 'display_name' ),
) );

$exact_map       = array(); // display_name → email (exact)
$meta_map        = array(); // "first last" → email (exact from meta)
$lower_map       = array(); // lowercase display_name → email
$lower_meta_map  = array(); // lowercase "first last" → email
$last_name_index = array(); // lowercase last_name → array of [ first_name_lower, email ]

foreach ( $users as $user ) {
	$email        = strtolower( $user->user_email );
	$display_name = trim( $user->display_name );

	// Index by display_name.
	if ( ! empty( $display_name ) ) {
		$exact_map[ $display_name ] = $email;
		$lower_map[ strtolower( $display_name ) ] = $email;
	}

	// Index by first_name + last_name from user meta.
	$first = trim( get_user_meta( $user->ID, 'first_name', true ) );
	$last  = trim( get_user_meta( $user->ID, 'last_name', true ) );

	if ( ! empty( $first ) && ! empty( $last ) ) {
		$full = $first . ' ' . $last;
		$meta_map[ $full ] = $email;
		$lower_meta_map[ strtolower( $full ) ] = $email;

		// Build last-name index for fuzzy matching.
		$last_lower = strtolower( $last );
		if ( ! isset( $last_name_index[ $last_lower ] ) ) {
			$last_name_index[ $last_lower ] = array();
		}
		$last_name_index[ $last_lower ][] = array(
			'first_lower' => strtolower( $first ),
			'email'       => $email,
		);
	}
}

WP_CLI::log( sprintf( 'Loaded %d users into lookup.', count( $users ) ) );

/**
 * Resolve a student name to an email address.
 *
 * @param string $name The student name from Spark.
 * @return array [ 'email' => string|null, 'method' => string ]
 */
function resolve_name( $name, $exact_map, $meta_map, $lower_map, $lower_meta_map, $last_name_index ) {
	// 1. Exact display_name match.
	if ( isset( $exact_map[ $name ] ) ) {
		return array( 'email' => $exact_map[ $name ], 'method' => 'exact_display' );
	}

	// 2. Exact meta (first_name + last_name) match.
	if ( isset( $meta_map[ $name ] ) ) {
		return array( 'email' => $meta_map[ $name ], 'method' => 'exact_meta' );
	}

	$lower = strtolower( $name );

	// 3. Case-insensitive display_name match.
	if ( isset( $lower_map[ $lower ] ) ) {
		return array( 'email' => $lower_map[ $lower ], 'method' => 'ci_display' );
	}

	// 4. Case-insensitive meta match.
	if ( isset( $lower_meta_map[ $lower ] ) ) {
		return array( 'email' => $lower_meta_map[ $lower ], 'method' => 'ci_meta' );
	}

	// 5. Fuzzy: last name match + first name starts-with.
	$parts = explode( ' ', $name );
	if ( count( $parts ) >= 2 ) {
		$spark_first = strtolower( $parts[0] );
		$spark_last  = strtolower( end( $parts ) );

		if ( isset( $last_name_index[ $spark_last ] ) ) {
			foreach ( $last_name_index[ $spark_last ] as $candidate ) {
				// Match if WP first name starts with Spark first name or vice versa.
				// This handles "EJ" matching "Elijah", "Mike" matching "Michael", etc.
				if (
					str_starts_with( $candidate['first_lower'], $spark_first ) ||
					str_starts_with( $spark_first, $candidate['first_lower'] )
				) {
					return array( 'email' => $candidate['email'], 'method' => 'fuzzy_last_first' );
				}
			}

			// If only one user has this last name, it's likely a match.
			if ( count( $last_name_index[ $spark_last ] ) === 1 ) {
				return array(
					'email'  => $last_name_index[ $spark_last ][0]['email'],
					'method' => 'fuzzy_last_only',
				);
			}
		}
	}

	return array( 'email' => null, 'method' => 'unmatched' );
}

/*
 * Process the CSV.
 */
WP_CLI::log( 'Processing attendance CSV...' );

$in_handle  = fopen( $input, 'r' );
$out_handle = fopen( $output, 'w' );
$unm_handle = fopen( $unmatched, 'w' );
$map_handle = fopen( $map_file, 'w' );

// Read and write headers.
$header = fgetcsv( $in_handle );
// Output header: replace student_name with email.
fputcsv( $out_handle, array( 'email', 'location', 'checked_in_at', 'class_name', 'method' ) );
fputcsv( $unm_handle, array( 'student_name', 'location', 'checked_in_at', 'class_name', 'method' ) );
fputcsv( $map_handle, array( 'student_name', 'email', 'match_method' ) );

$stats = array(
	'total'              => 0,
	'resolved'           => 0,
	'unmatched'          => 0,
	'exact_display'      => 0,
	'exact_meta'         => 0,
	'ci_display'         => 0,
	'ci_meta'            => 0,
	'fuzzy_last_first'   => 0,
	'fuzzy_last_only'    => 0,
);

// Cache name resolutions to avoid re-resolving the same name.
$resolution_cache = array();

while ( ( $row = fgetcsv( $in_handle ) ) !== false ) {
	$stats['total']++;
	$student_name = $row[0];

	// Check cache first.
	if ( ! isset( $resolution_cache[ $student_name ] ) ) {
		$resolution_cache[ $student_name ] = resolve_name(
			$student_name,
			$exact_map,
			$meta_map,
			$lower_map,
			$lower_meta_map,
			$last_name_index
		);

		// Write to name map (one entry per unique name).
		fputcsv( $map_handle, array(
			$student_name,
			$resolution_cache[ $student_name ]['email'] ?? '',
			$resolution_cache[ $student_name ]['method'],
		) );
	}

	$result = $resolution_cache[ $student_name ];

	if ( null !== $result['email'] ) {
		$stats['resolved']++;
		$stats[ $result['method'] ]++;
		fputcsv( $out_handle, array(
			$result['email'],
			$row[1], // location
			$row[2], // checked_in_at
			$row[3], // class_name
			'imported',
		) );
	} else {
		$stats['unmatched']++;
		fputcsv( $unm_handle, $row );
	}

	if ( 0 === $stats['total'] % 10000 ) {
		WP_CLI::log( sprintf( '  Processed %s rows...', number_format( $stats['total'] ) ) );
	}
}

fclose( $in_handle );
fclose( $out_handle );
fclose( $unm_handle );
fclose( $map_handle );

/*
 * Summary.
 */
WP_CLI::success( 'Name resolution complete!' );
WP_CLI::log( '' );
WP_CLI::log( '=== Resolution Summary ===' );
WP_CLI::log( sprintf( 'Total rows:       %s', number_format( $stats['total'] ) ) );
WP_CLI::log( sprintf( 'Resolved:         %s (%.1f%%)', number_format( $stats['resolved'] ), ( $stats['resolved'] / $stats['total'] ) * 100 ) );
WP_CLI::log( sprintf( 'Unmatched:        %s (%.1f%%)', number_format( $stats['unmatched'] ), ( $stats['unmatched'] / $stats['total'] ) * 100 ) );
WP_CLI::log( '' );
WP_CLI::log( '=== Match Methods ===' );
WP_CLI::log( sprintf( 'Exact display:    %s', number_format( $stats['exact_display'] ) ) );
WP_CLI::log( sprintf( 'Exact meta:       %s', number_format( $stats['exact_meta'] ) ) );
WP_CLI::log( sprintf( 'CI display:       %s', number_format( $stats['ci_display'] ) ) );
WP_CLI::log( sprintf( 'CI meta:          %s', number_format( $stats['ci_meta'] ) ) );
WP_CLI::log( sprintf( 'Fuzzy last+first: %s', number_format( $stats['fuzzy_last_first'] ) ) );
WP_CLI::log( sprintf( 'Fuzzy last only:  %s', number_format( $stats['fuzzy_last_only'] ) ) );
WP_CLI::log( '' );
WP_CLI::log( sprintf( 'Unique names:     %s', number_format( count( $resolution_cache ) ) ) );
WP_CLI::log( '' );
WP_CLI::log( "Output files:" );
WP_CLI::log( "  Resolved:  {$output}" );
WP_CLI::log( "  Unmatched: {$unmatched}" );
WP_CLI::log( "  Name map:  {$map_file}" );
