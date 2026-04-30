<?php
/**
 * One-time content seeder for plugin activation.
 *
 * Seeds gym_location term meta (address, phone, hours, geo) for the existing
 * rockford and beloit taxonomy terms. Also creates placeholder hp_testimonial
 * posts if none exist. All operations are idempotent — safe to re-activate.
 *
 * @package Haanpaa
 */

namespace Haanpaa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Seeder {

	public static function init() {
		// Nothing to hook at runtime — seed only runs on activation.
	}

	public static function run(): void {
		self::seed_location_meta();
		self::seed_testimonials();
	}

	private static function seed_location_meta(): void {
		if ( ! taxonomy_exists( 'gym_location' ) ) {
			return;
		}

		$locations = [
			'rockford' => [
				'_gym_is_primary'    => true,
				'_gym_address'       => '4911 26th Avenue',
				'_gym_city_state_zip'=> 'Rockford, IL 61109',
				'_gym_phone'         => '(815) 451-3001',
				'_gym_geo_lat'       => '42.2384',
				'_gym_geo_lng'       => '-89.0327',
				'_gym_hours_json'    => wp_json_encode( [
					[ 'day' => 'Monday',    'opens' => '06:00', 'closes' => '20:00' ],
					[ 'day' => 'Tuesday',   'opens' => '06:00', 'closes' => '20:00' ],
					[ 'day' => 'Wednesday', 'opens' => '06:00', 'closes' => '20:00' ],
					[ 'day' => 'Thursday',  'opens' => '06:00', 'closes' => '20:00' ],
					[ 'day' => 'Friday',    'opens' => '06:00', 'closes' => '20:00' ],
					[ 'day' => 'Saturday',  'opens' => '09:00', 'closes' => '13:00' ],
				] ),
			],
			'beloit' => [
				'_gym_is_primary'    => false,
				'_gym_address'       => '610 4th Street',
				'_gym_city_state_zip'=> 'Beloit, WI 53511',
				'_gym_phone'         => '(815) 451-3001',
				'_gym_geo_lat'       => '',
				'_gym_geo_lng'       => '',
				'_gym_hours_json'    => wp_json_encode( [
					[ 'day' => 'Monday',    'opens' => '17:00', 'closes' => '19:00' ],
					[ 'day' => 'Wednesday', 'opens' => '17:00', 'closes' => '19:00' ],
					[ 'day' => 'Friday',    'opens' => '17:00', 'closes' => '19:00' ],
					[ 'day' => 'Saturday',  'opens' => '10:00', 'closes' => '11:30' ],
				] ),
			],
		];

		foreach ( $locations as $slug => $meta ) {
			$term = get_term_by( 'slug', $slug, 'gym_location' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			// Only seed each key if not already set (idempotent).
			foreach ( $meta as $key => $value ) {
				if ( '' === get_term_meta( $term->term_id, $key, true ) && false === get_term_meta( $term->term_id, $key, true ) ) {
					update_term_meta( $term->term_id, $key, $value );
				} elseif ( metadata_exists( 'term', $term->term_id, $key ) ) {
					// Already set — skip.
					continue;
				} else {
					update_term_meta( $term->term_id, $key, $value );
				}
			}
		}
	}

	private static function seed_testimonials(): void {
		$existing = get_posts( [
			'post_type'   => 'hp_testimonial',
			'post_status' => 'any',
			'numberposts' => 1,
		] );
		if ( $existing ) {
			return;
		}

		$placeholders = [
			[
				'title'   => '[Student name]',
				'content' => 'I walked in expecting to feel out of place. Three classes in, I was rolling with people who\'d been training for years and they made me feel like I belonged.',
				'context' => 'Adult BJJ · 6 months in',
			],
			[
				'title'   => '[Parent name]',
				'content' => 'My daughter was the quiet kid. Two months at Haanpaa and she\'s the one helping new kids tie their belts.',
				'context' => 'Kids program parent',
			],
			[
				'title'   => '[Student name]',
				'content' => 'The Brazilian Jiu-Jitsu program is second to none, and Muay Thai is incredible.',
				'context' => 'Adult · multi-program',
			],
		];

		foreach ( $placeholders as $p ) {
			$post_id = wp_insert_post( [
				'post_type'    => 'hp_testimonial',
				'post_status'  => 'publish',
				'post_title'   => $p['title'],
				'post_content' => $p['content'],
			] );
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, 'hp_context', $p['context'] );
			}
		}
	}
}
