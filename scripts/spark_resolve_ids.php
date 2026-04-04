<?php
/**
 * Resolve email addresses to WordPress user IDs in a belt-ranks CSV.
 *
 * Reads spark_belt_ranks_by_email.csv (email, program, belt, stripes, promoted_at, notes)
 * and outputs spark_belt_ranks.csv (user_id, program, belt, stripes, promoted_at, notes).
 *
 * Usage (via WP-CLI):
 *   wp eval-file scripts/spark_resolve_ids.php spark-import/spark_belt_ranks_by_email.csv spark-import/spark_belt_ranks.csv
 *
 * @package Gym_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "This script must be run via WP-CLI: wp eval-file scripts/spark_resolve_ids.php <input> <output>\n";
	exit( 1 );
}

$input_file  = $args[0] ?? '';
$output_file = $args[1] ?? '';

if ( empty( $input_file ) || empty( $output_file ) ) {
	WP_CLI::error( 'Usage: wp eval-file scripts/spark_resolve_ids.php <input.csv> <output.csv>' );
}

if ( ! file_exists( $input_file ) ) {
	WP_CLI::error( "Input file not found: {$input_file}" );
}

$handle = fopen( $input_file, 'r' );
$header = fgetcsv( $handle );

if ( ! $header || ! in_array( 'email', $header, true ) ) {
	WP_CLI::error( 'Input CSV must have an "email" column.' );
}

$output = fopen( $output_file, 'w' );
fputcsv( $output, array( 'user_id', 'program', 'belt', 'stripes', 'promoted_at', 'notes' ) );

$resolved  = 0;
$not_found = 0;

while ( ( $row = fgetcsv( $handle ) ) !== false ) {
	$data  = array_combine( $header, $row );
	$email = trim( $data['email'] ?? '' );

	if ( empty( $email ) ) {
		++$not_found;
		continue;
	}

	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		WP_CLI::warning( "No WP user found for email: {$email}" );
		++$not_found;
		continue;
	}

	fputcsv( $output, array(
		$user->ID,
		$data['program'] ?? '',
		$data['belt'] ?? '',
		$data['stripes'] ?? '0',
		$data['promoted_at'] ?? '',
		$data['notes'] ?? '',
	) );

	++$resolved;
}

fclose( $handle );
fclose( $output );

WP_CLI::success( "Resolved {$resolved} records, {$not_found} unresolved. Output: {$output_file}" );
