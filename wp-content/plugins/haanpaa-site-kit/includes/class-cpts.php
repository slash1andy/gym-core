<?php
/**
 * Custom post types and term meta for the Haanpaa site kit.
 *
 * Kept CPTs: hp_testimonial (student reviews), hp_trial_lead (CRM fallback).
 * Location, program, class, and instructor data all live in gym-core's existing
 * structures (gym_location taxonomy, gym_program taxonomy, gym_class CPT, WP users).
 *
 * Also registers address/hours/contact term meta on the gym_location taxonomy so
 * the schema, header, and locations pattern can pull structured data without a
 * separate CPT.
 *
 * @package Haanpaa
 */

namespace Haanpaa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CPTs {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'init', [ __CLASS__, 'register_location_term_meta' ] );
	}

	public static function register() {
		register_post_type( 'hp_testimonial', [
			'labels'       => [
				'name'          => 'Testimonials',
				'singular_name' => 'Testimonial',
				'add_new_item'  => 'Add Testimonial',
				'edit_item'     => 'Edit Testimonial',
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-format-quote',
			'supports'     => [ 'title', 'editor', 'thumbnail' ],
			'rewrite'      => false,
		] );

		register_post_meta( 'hp_testimonial', 'hp_who',     [ 'type' => 'string', 'single' => true, 'show_in_rest' => true ] );
		register_post_meta( 'hp_testimonial', 'hp_context', [ 'type' => 'string', 'single' => true, 'show_in_rest' => true ] );
		register_post_meta( 'hp_testimonial', 'hp_rating',  [ 'type' => 'integer', 'single' => true, 'show_in_rest' => true ] );

		register_post_type( 'hp_trial_lead', [
			'labels'       => [
				'name'          => 'Trial Leads',
				'singular_name' => 'Trial Lead',
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-groups',
			'supports'     => [ 'title', 'editor' ],
			'rewrite'      => false,
		] );
	}

	/**
	 * Extends the gym_location taxonomy (owned by gym-core) with address,
	 * contact, and hours term meta so the schema and location pattern can pull
	 * structured data without a separate CPT.
	 */
	public static function register_location_term_meta() {
		if ( ! taxonomy_exists( 'gym_location' ) ) {
			return;
		}

		$string_keys = [
			'_gym_address',
			'_gym_city_state_zip',
			'_gym_phone',
			'_gym_hours_json',
			'_gym_geo_lat',
			'_gym_geo_lng',
		];

		foreach ( $string_keys as $key ) {
			register_term_meta( 'gym_location', $key, [
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'auth_callback' => function() { return current_user_can( 'manage_options' ); },
			] );
		}

		register_term_meta( 'gym_location', '_gym_is_primary', [
			'type'         => 'boolean',
			'single'       => true,
			'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'manage_options' ); },
		] );
	}
}
