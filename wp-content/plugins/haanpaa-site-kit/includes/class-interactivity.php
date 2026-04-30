<?php
/**
 * Interactivity API behaviors: programs accordion, schedule day-tabs,
 * schedule location/program filters, free-trial 3-step wizard.
 *
 * @package Haanpaa
 */

namespace Haanpaa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Interactivity {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_stores' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	public static function register_stores() {
		if ( ! function_exists( 'wp_interactivity_state' ) ) { return; }

		wp_interactivity_state( 'haanpaa/programs', [
			'open' => 'bjj',
		] );

		wp_interactivity_state( 'haanpaa/schedule', [
			'day'      => 'Mon',
			'location' => 'rockford',
			'filter'   => 'all',
		] );

		wp_interactivity_state( 'haanpaa/trial', [
			'step'     => 1,
			'program'  => '',
			'time'     => '',
			'location' => 'rockford',
		] );

		wp_interactivity_state( 'haanpaa/faq', [
			'open' => 0,
		] );
	}

	public static function enqueue() {
		wp_register_script_module(
			'@haanpaa/interactivity',
			HAANPAA_PLUGIN_URL . 'assets/js/interactivity.js',
			[ [ 'id' => '@wordpress/interactivity' ] ],
			HAANPAA_VERSION
		);
		wp_enqueue_script_module( '@haanpaa/interactivity' );

		wp_enqueue_style(
			'haanpaa-patterns',
			HAANPAA_PLUGIN_URL . 'assets/css/patterns.css',
			[],
			HAANPAA_VERSION
		);

		// Resolve primary location phone from gym_location term meta.
		$primary_phone = '';
		if ( taxonomy_exists( 'gym_location' ) ) {
			$terms = get_terms( [ 'taxonomy' => 'gym_location', 'hide_empty' => false ] );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( get_term_meta( $term->term_id, '_gym_is_primary', true ) ) {
						$primary_phone = (string) get_term_meta( $term->term_id, '_gym_phone', true );
						break;
					}
				}
			}
		}

		$config = wp_json_encode( [
			'endpoint'  => esc_url_raw( rest_url( 'haanpaa/v1/trial' ) ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'nonce'     => wp_create_nonce( 'haanpaa_trial' ),
			'phone'     => $primary_phone ?: '(815) 451-3001',
		] );
		wp_add_inline_script( 'wp-interactivity', "window.haanpaaTrial=$config;", 'before' );
	}
}
