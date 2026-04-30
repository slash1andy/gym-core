<?php
/**
 * JSON-LD schema injection for LocalBusiness + SportsActivityLocation.
 * Renders one entry per gym_location taxonomy term (rockford, beloit).
 * Address, phone, hours, and geo are stored as term meta by class-seeder.php.
 *
 * @package Haanpaa
 */

namespace Haanpaa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Schema {

	public static function init() {
		add_action( 'wp_head', [ __CLASS__, 'render' ], 5 );
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
				'@type'     => [ 'LocalBusiness', 'SportsActivityLocation' ],
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
}
