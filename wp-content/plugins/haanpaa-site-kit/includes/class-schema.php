<?php
/**
 * JSON-LD schema injection for LocalBusiness + MartialArtsSchool +
 * SportsActivityLocation, plus per-class Event schema on the schedule page.
 *
 * Renders one location entry per gym_location taxonomy term (rockford, beloit).
 * Address, phone, hours, and geo are stored as term meta by class-seeder.php.
 *
 * Schedule pages additionally emit one Event per class instance over a
 * 14-day rolling window so Google rich-results / Events markup picks up
 * weekly recurring classes.
 *
 * @package Haanpaa
 */

namespace Haanpaa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Schema {

	public static function init() {
		add_action( 'wp_head', [ __CLASS__, 'render' ], 5 );
		add_action( 'wp_head', [ __CLASS__, 'render_schedule_events' ], 6 );
	}

	public static function render() {
		if ( ! taxonomy_exists( 'gym_location' ) ) {
			return;
		}

		$terms = get_terms( [
			'taxonomy'   => 'gym_location',
			'hide_empty' => false,
		] );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		$graph = [];
		foreach ( $terms as $term ) {
			$address      = get_term_meta( $term->term_id, '_gym_address', true );
			$city_state   = get_term_meta( $term->term_id, '_gym_city_state_zip', true );
			$phone        = get_term_meta( $term->term_id, '_gym_phone', true );
			$hours_json   = get_term_meta( $term->term_id, '_gym_hours_json', true );
			$lat          = get_term_meta( $term->term_id, '_gym_geo_lat', true );
			$lng          = get_term_meta( $term->term_id, '_gym_geo_lng', true );
			$is_primary   = (bool) get_term_meta( $term->term_id, '_gym_is_primary', true );
			$hours        = $hours_json ? json_decode( $hours_json, true ) : [];

			// Parse "City, State ZIP" into components (best-effort).
			$locality = $city_state;
			$region   = '';
			$postal   = '';
			if ( preg_match( '/^(.+),\s*([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/', $city_state, $m ) ) {
				$locality = $m[1];
				$region   = $m[2];
				$postal   = $m[3];
			}

			$term_url = get_term_link( $term );

			$entry = [
				'@type'     => [ 'LocalBusiness', 'MartialArtsSchool', 'SportsActivityLocation' ],
				'@id'       => ( ! is_wp_error( $term_url ) ? $term_url : home_url() ) . '#location',
				'name'      => $term->name . ' — Haanpaa Martial Arts',
				'url'       => ! is_wp_error( $term_url ) ? $term_url : home_url(),
				'telephone' => $phone,
				'address'   => [
					'@type'           => 'PostalAddress',
					'streetAddress'   => $address,
					'addressLocality' => $locality,
					'addressRegion'   => $region,
					'postalCode'      => $postal,
					'addressCountry'  => 'US',
				],
				'sport'     => [ 'Brazilian Jiu-Jitsu', 'Muay Thai Kickboxing', 'Mixed Martial Arts' ],
			];

			if ( $lat && $lng ) {
				$entry['geo'] = [
					'@type'     => 'GeoCoordinates',
					'latitude'  => $lat,
					'longitude' => $lng,
				];
			}

			if ( is_array( $hours ) && $hours ) {
				$entry['openingHoursSpecification'] = array_map( function( $row ) {
					return [
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => $row['day']    ?? '',
						'opens'     => $row['opens']  ?? '',
						'closes'    => $row['closes'] ?? '',
					];
				}, $hours );
			}

			if ( $is_primary ) {
				$entry['parentOrganization'] = [
					'@type' => 'Organization',
					'name'  => 'Haanpaa Martial Arts',
					'url'   => home_url(),
				];
			}

			$graph[] = $entry;
		}

		if ( empty( $graph ) ) {
			return;
		}

		$payload = [ '@context' => 'https://schema.org', '@graph' => $graph ];
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ) . "</script>\n";
	}

	/**
	 * Emits one schema.org/Event per upcoming class occurrence over the next
	 * 14 days. Only runs on the schedule page so we don't pollute every URL
	 * with a 100+ entry @graph.
	 *
	 * Reads the same gym_class CPT + meta the schedule pattern reads:
	 *   _gym_class_day_of_week, _gym_class_start_time, _gym_class_duration_minutes,
	 *   _gym_class_instructor, _gym_class_status (must be "active").
	 *
	 * @return void
	 */
	public static function render_schedule_events(): void {
		// Only inject on the schedule page.
		if ( ! is_page() ) {
			return;
		}
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		if ( 'schedule' !== $slug && 'page-schedule' !== get_page_template_slug( get_queried_object_id() ) ) {
			return;
		}

		if ( ! post_type_exists( 'gym_class' ) ) {
			return;
		}

		$classes = get_posts( [
			'post_type'      => 'gym_class',
			'posts_per_page' => -1,
			'meta_query'     => [
				[ 'key' => '_gym_class_status', 'value' => 'active', 'compare' => '=' ],
			],
			'no_found_rows'  => true,
		] );

		if ( empty( $classes ) ) {
			return;
		}

		$day_map = [
			'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
			'friday' => 5, 'saturday' => 6, 'sunday' => 7,
		];

		$events     = [];
		$today      = new \DateTimeImmutable( 'today', wp_timezone() );
		$horizon    = $today->modify( '+14 days' );
		$site_name  = 'Haanpaa Martial Arts';

		foreach ( $classes as $class ) {
			$day_raw    = strtolower( (string) get_post_meta( $class->ID, '_gym_class_day_of_week', true ) );
			$start_time = (string) get_post_meta( $class->ID, '_gym_class_start_time', true );
			$duration   = (int) get_post_meta( $class->ID, '_gym_class_duration_minutes', true );
			$instructor = (string) get_post_meta( $class->ID, '_gym_class_instructor', true );

			if ( ! isset( $day_map[ $day_raw ] ) || '' === $start_time ) {
				continue;
			}
			if ( $duration <= 0 ) {
				$duration = 60;
			}

			$loc_term = null;
			$loc_terms = get_the_terms( $class->ID, 'gym_location' );
			if ( ! empty( $loc_terms ) && ! is_wp_error( $loc_terms ) ) {
				$loc_term = $loc_terms[0];
			}

			$loc_address = $loc_term
				? [
					'@type'           => 'PostalAddress',
					'streetAddress'   => get_term_meta( $loc_term->term_id, '_gym_address', true ),
					'addressLocality' => get_term_meta( $loc_term->term_id, '_gym_city_state_zip', true ),
				]
				: null;

			$location_node = $loc_term
				? [
					'@type'   => 'Place',
					'name'    => $loc_term->name . ' — ' . $site_name,
					'address' => $loc_address,
				]
				: null;

			// Walk the 14-day window and emit one Event per occurrence.
			$cursor = $today;
			while ( $cursor <= $horizon ) {
				if ( (int) $cursor->format( 'N' ) === $day_map[ $day_raw ] ) {
					$start_dt = $cursor->modify( $start_time );
					$end_dt   = $start_dt->modify( '+' . $duration . ' minutes' );

					$event = [
						'@type'             => 'Event',
						'name'              => get_the_title( $class ),
						'startDate'         => $start_dt->format( DATE_ATOM ),
						'endDate'           => $end_dt->format( DATE_ATOM ),
						'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
						'eventStatus'       => 'https://schema.org/EventScheduled',
						'organizer'         => [
							'@type' => 'Organization',
							'name'  => $site_name,
							'url'   => home_url(),
						],
					];
					if ( $location_node ) {
						$event['location'] = $location_node;
					}
					if ( '' !== $instructor ) {
						$event['performer'] = [ '@type' => 'Person', 'name' => $instructor ];
					}
					$events[] = $event;
				}
				$cursor = $cursor->modify( '+1 day' );
			}
		}

		if ( empty( $events ) ) {
			return;
		}

		$payload = [ '@context' => 'https://schema.org', '@graph' => $events ];
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ) . "</script>\n";
	}
}
