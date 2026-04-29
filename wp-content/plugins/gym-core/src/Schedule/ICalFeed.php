<?php
/**
 * ICalendar (.ics) feed for the gym class schedule.
 *
 * Provides subscribable calendar feeds at:
 * - /gym-calendar.ics           (all locations)
 * - /gym-calendar/rockford.ics  (Rockford only)
 * - /gym-calendar/beloit.ics    (Beloit only)
 *
 * @package Gym_Core\Schedule
 * @since   2.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Schedule;

use Gym_Core\Location\Taxonomy as LocationTaxonomy;

/**
 * Generates and serves iCalendar feeds for gym classes.
 */
final class ICalFeed {

	/**
	 * Query variable used to identify calendar feed requests.
	 *
	 * @var string
	 */
	private const QUERY_VAR = 'gym_ical_feed';

	/**
	 * Query variable for location filtering.
	 *
	 * @var string
	 */
	private const LOCATION_VAR = 'gym_ical_location';

	/**
	 * Transient key prefix for cached feed output.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'gym_ical_feed_';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Defensive upper bound for the iCal class query.
	 *
	 * @var int
	 */
	private const MAX_QUERY_RESULTS = 200;

	/**
	 * Location addresses keyed by taxonomy slug.
	 *
	 * @var array<string, string>
	 */
	private const ADDRESSES = array(
		'rockford' => '4911 26th Avenue, Rockford, IL 61109',
		'beloit'   => '610 4th St, Beloit, WI 53511',
	);

	/**
	 * Day-of-week to iCal BYDAY abbreviation map.
	 *
	 * @var array<string, string>
	 */
	private const DAY_MAP = array(
		'monday'    => 'MO',
		'tuesday'   => 'TU',
		'wednesday' => 'WE',
		'thursday'  => 'TH',
		'friday'    => 'FR',
		'saturday'  => 'SA',
		'sunday'    => 'SU',
	);

	/**
	 * Day-of-week to PHP date('N') number map (ISO-8601: 1=Monday).
	 *
	 * @var array<string, int>
	 */
	private const DAY_NUMBER = array(
		'monday'    => 1,
		'tuesday'   => 2,
		'wednesday' => 3,
		'thursday'  => 4,
		'friday'    => 5,
		'saturday'  => 6,
		'sunday'    => 7,
	);

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_feed' ) );

		// Bust cache when a class is created, updated, or deleted.
		add_action( 'save_post_' . ClassPostType::POST_TYPE, array( $this, 'flush_cache' ) );
		add_action( 'delete_post', array( $this, 'flush_cache' ) );
	}

	/**
	 * Adds rewrite rules for the calendar feed endpoints.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		// Per-location feed: /gym-calendar/rockford.ics.
		add_rewrite_rule(
			'^gym-calendar/([a-z]+)\.ics$',
			'index.php?' . self::QUERY_VAR . '=1&' . self::LOCATION_VAR . '=$matches[1]',
			'top'
		);

		// All-locations feed: /gym-calendar.ics.
		add_rewrite_rule(
			'^gym-calendar\.ics$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Registers custom query variables.
	 *
	 * @since 2.3.0
	 *
	 * @param array<string> $vars Existing query variables.
	 * @return array<string>
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::LOCATION_VAR;
		return $vars;
	}

	/**
	 * Serves the iCal feed if the request matches and the feature is enabled.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function maybe_serve_feed(): void {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		if ( 'yes' !== get_option( 'gym_core_ical_enabled', 'yes' ) ) {
			status_header( 404 );
			exit;
		}

		$location = sanitize_key( (string) get_query_var( self::LOCATION_VAR, '' ) );

		// Validate location slug if provided.
		if ( '' !== $location && ! LocationTaxonomy::is_valid( $location ) ) {
			status_header( 404 );
			exit;
		}

		$cache_key = self::TRANSIENT_PREFIX . ( $location ?: 'all' );
		$output    = get_transient( $cache_key );

		if ( false === $output ) {
			$output = $this->generate_ical( $location );
			set_transient( $cache_key, $output, self::CACHE_TTL );
		}

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="gym-calendar.ics"' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Content-Type-Options: nosniff' );

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal plaintext feed.
		exit;
	}

	/**
	 * Flushes all cached iCal feed transients.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		delete_transient( self::TRANSIENT_PREFIX . 'all' );

		foreach ( LocationTaxonomy::VALID_LOCATIONS as $slug ) {
			delete_transient( self::TRANSIENT_PREFIX . $slug );
		}
	}

	/**
	 * Generates the iCalendar output string.
	 *
	 * @since 2.3.0
	 *
	 * @param string $location Optional location slug to filter by.
	 * @return string Complete iCalendar document.
	 */
	private function generate_ical( string $location = '' ): string {
		$args = array(
			'post_type'      => ClassPostType::POST_TYPE,
			'posts_per_page' => self::MAX_QUERY_RESULTS,
			'post_status'    => 'publish',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_gym_class_status',
					'value'   => 'active',
					'compare' => '=',
				),
			),
		);

		if ( '' !== $location ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => LocationTaxonomy::SLUG,
					'field'    => 'slug',
					'terms'    => $location,
				),
			);
		}

		$classes = get_posts( $args );

		$calendar_name = '' !== $location
			? sprintf( 'Gym Schedule — %s', ucfirst( $location ) )
			: 'Gym Class Schedule';

		$lines   = array();
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//Gym Core//Class Schedule//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';
		$lines[] = 'X-WR-CALNAME:' . $this->escape_text( $calendar_name );
		$lines[] = 'X-WR-TIMEZONE:America/Chicago';

		// VTIMEZONE component for America/Chicago.
		$lines[] = 'BEGIN:VTIMEZONE';
		$lines[] = 'TZID:America/Chicago';
		$lines[] = 'BEGIN:STANDARD';
		$lines[] = 'DTSTART:19701101T020000';
		$lines[] = 'RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=11';
		$lines[] = 'TZOFFSETFROM:-0500';
		$lines[] = 'TZOFFSETTO:-0600';
		$lines[] = 'TZNAME:CST';
		$lines[] = 'END:STANDARD';
		$lines[] = 'BEGIN:DAYLIGHT';
		$lines[] = 'DTSTART:19700308T020000';
		$lines[] = 'RRULE:FREQ=YEARLY;BYDAY=2SU;BYMONTH=3';
		$lines[] = 'TZOFFSETFROM:-0600';
		$lines[] = 'TZOFFSETTO:-0500';
		$lines[] = 'TZNAME:CDT';
		$lines[] = 'END:DAYLIGHT';
		$lines[] = 'END:VTIMEZONE';

		foreach ( $classes as $class ) {
			$event_lines = $this->build_vevent( $class );
			if ( ! empty( $event_lines ) ) {
				$lines = array_merge( $lines, $event_lines );
			}
		}

		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Builds VEVENT lines for a single gym class.
	 *
	 * @since 2.3.0
	 *
	 * @param \WP_Post $class The gym_class post.
	 * @return array<string> iCal lines for this event, or empty if incomplete.
	 */
	private function build_vevent( \WP_Post $class ): array {
		$day_of_week = get_post_meta( $class->ID, '_gym_class_day_of_week', true );
		$start_time  = get_post_meta( $class->ID, '_gym_class_start_time', true );
		$end_time    = get_post_meta( $class->ID, '_gym_class_end_time', true );
		$recurrence  = get_post_meta( $class->ID, '_gym_class_recurrence', true ) ?: 'weekly';

		// Skip classes without required schedule data.
		if ( empty( $day_of_week ) || empty( $start_time ) || empty( $end_time ) ) {
			return array();
		}

		if ( ! isset( self::DAY_MAP[ $day_of_week ] ) ) {
			return array();
		}

		// Calculate DTSTART as the next occurrence of this day of the week.
		$dtstart = $this->get_next_occurrence( $day_of_week, $start_time );
		$dtend   = $this->get_next_occurrence( $day_of_week, $end_time );

		$instructor_id = (int) get_post_meta( $class->ID, '_gym_class_instructor', true );
		$instructor    = '';
		if ( $instructor_id ) {
			$user = get_userdata( $instructor_id );
			if ( $user ) {
				$instructor = $user->display_name;
			}
		}

		// Determine location from taxonomy terms.
		$location_address = '';
		$location_terms   = get_the_terms( $class->ID, LocationTaxonomy::SLUG );
		if ( is_array( $location_terms ) && ! empty( $location_terms ) ) {
			$location_slug    = $location_terms[0]->slug;
			$addresses        = $this->get_addresses();
			$location_address = $addresses[ $location_slug ] ?? '';
		}

		// Build description.
		$description = wp_strip_all_tags( $class->post_content );
		if ( $instructor ) {
			$description = trim( $description . "\n\nInstructor: " . $instructor );
		}

		// Build RRULE.
		$rrule = $this->build_rrule( $day_of_week, $recurrence );

		$uid     = 'gym-class-' . $class->ID . '@' . $this->get_domain();
		$dtstamp = gmdate( 'Ymd\THis\Z' );

		$lines   = array();
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:' . $uid;
		$lines[] = 'DTSTAMP:' . $dtstamp;
		$lines[] = 'DTSTART;TZID=America/Chicago:' . $dtstart;
		$lines[] = 'DTEND;TZID=America/Chicago:' . $dtend;
		$lines[] = 'SUMMARY:' . $this->escape_text( $class->post_title );

		if ( '' !== $description ) {
			$lines[] = 'DESCRIPTION:' . $this->escape_text( $description );
		}

		if ( '' !== $location_address ) {
			$lines[] = 'LOCATION:' . $this->escape_text( $location_address );
		}

		if ( '' !== $rrule ) {
			$lines[] = $rrule;
		}

		$lines[] = 'STATUS:CONFIRMED';
		$lines[] = 'END:VEVENT';

		return $lines;
	}

	/**
	 * Builds an RRULE string for a class recurrence pattern.
	 *
	 * @since 2.3.0
	 *
	 * @param string $day_of_week Day slug (e.g. 'monday').
	 * @param string $recurrence  Recurrence type (weekly, biweekly, monthly).
	 * @return string RRULE line or empty string.
	 */
	private function build_rrule( string $day_of_week, string $recurrence ): string {
		$byday = self::DAY_MAP[ $day_of_week ] ?? '';

		if ( '' === $byday ) {
			return '';
		}

		switch ( $recurrence ) {
			case 'weekly':
				return 'RRULE:FREQ=WEEKLY;BYDAY=' . $byday;

			case 'biweekly':
				return 'RRULE:FREQ=WEEKLY;INTERVAL=2;BYDAY=' . $byday;

			case 'monthly':
				// Monthly on the same day of week (e.g., first Monday).
				return 'RRULE:FREQ=MONTHLY;BYDAY=' . $byday;

			default:
				return '';
		}
	}

	/**
	 * Calculates the next occurrence datetime string for a given day and time.
	 *
	 * Returns the date in iCal local datetime format: Ymd\THis.
	 * Uses the post publish date as the starting anchor when available;
	 * otherwise picks the upcoming occurrence from today.
	 *
	 * @since 2.3.0
	 *
	 * @param string $day_of_week Day slug (e.g. 'monday').
	 * @param string $time        Time in H:i (24hr) format.
	 * @return string Datetime in Ymd\THis format.
	 */
	private function get_next_occurrence( string $day_of_week, string $time ): string {
		$target_day  = self::DAY_NUMBER[ $day_of_week ] ?? 1;
		$today       = new \DateTime( 'now', new \DateTimeZone( 'America/Chicago' ) );
		$current_day = (int) $today->format( 'N' );

		$diff = $target_day - $current_day;
		if ( $diff < 0 ) {
			$diff += 7;
		}

		$date = clone $today;
		$date->modify( '+' . $diff . ' days' );

		$time_parts = explode( ':', $time );
		$date->setTime( (int) ( $time_parts[0] ?? 0 ), (int) ( $time_parts[1] ?? 0 ), 0 );

		return $date->format( 'Ymd\THis' );
	}

	/**
	 * Escapes text for use in iCalendar property values.
	 *
	 * Per RFC 5545: backslashes, semicolons, commas, and newlines must be escaped.
	 *
	 * @since 2.3.0
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text safe for iCal properties.
	 */
	private function escape_text( string $text ): string {
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( ';', '\\;', $text );
		$text = str_replace( ',', '\\,', $text );
		$text = str_replace( "\r\n", '\\n', $text );
		$text = str_replace( "\r", '\\n', $text );
		$text = str_replace( "\n", '\\n', $text );

		return $text;
	}

	/**
	 * Returns location addresses, allowing customization via filter.
	 *
	 * @since 2.3.0
	 *
	 * @return array<string, string>
	 */
	private function get_addresses(): array {
		return apply_filters( 'gym_core_ical_addresses', self::ADDRESSES );
	}

	/**
	 * Returns the site domain for UID generation.
	 *
	 * @since 2.3.0
	 *
	 * @return string Domain name.
	 */
	private function get_domain(): string {
		$parsed = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $parsed ) ? $parsed : 'localhost';
	}
}
