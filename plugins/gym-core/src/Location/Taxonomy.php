<?php
/**
 * Location taxonomy registration.
 *
 * @package Gym_Core\Location
 */

declare( strict_types=1 );

namespace Gym_Core\Location;

/**
 * Registers the gym_location taxonomy and seeds the default location terms.
 *
 * The taxonomy is registered on 'product' for product-level location filtering.
 * Orders and users are associated with locations via meta keys instead of
 * taxonomy terms — this is required for HPOS compatibility, which does not
 * store orders as wp_posts.
 */
class Taxonomy {

	/**
	 * Taxonomy slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SLUG = 'gym_location';

	/**
	 * Rockford location term slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ROCKFORD = 'rockford';

	/**
	 * Beloit location term slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BELOIT = 'beloit';

	/**
	 * All valid location slugs.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	const VALID_LOCATIONS = array( self::ROCKFORD, self::BELOIT );

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Registers the gym_location taxonomy on the product post type.
	 *
	 * Capabilities are scoped to WooCommerce shop managers so that store
	 * admins can assign locations without requiring manage_options.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'                  => _x( 'Locations', 'taxonomy general name', 'gym-core' ),
			'singular_name'         => _x( 'Location', 'taxonomy singular name', 'gym-core' ),
			'search_items'          => __( 'Search locations', 'gym-core' ),
			'all_items'             => __( 'All locations', 'gym-core' ),
			'edit_item'             => __( 'Edit location', 'gym-core' ),
			'update_item'           => __( 'Update location', 'gym-core' ),
			'add_new_item'          => __( 'Add new location', 'gym-core' ),
			'new_item_name'         => __( 'New location name', 'gym-core' ),
			'menu_name'             => __( 'Locations', 'gym-core' ),
			'not_found'             => __( 'No locations found.', 'gym-core' ),
			'no_terms'              => __( 'No locations', 'gym-core' ),
			'items_list'            => __( 'Locations list', 'gym-core' ),
			'items_list_navigation' => __( 'Locations list navigation', 'gym-core' ),
			'back_to_items'         => __( '&larr; Go to locations', 'gym-core' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'gym-locations',
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'location' ),
			'capabilities'      => array(
				'manage_terms' => 'manage_woocommerce',
				'edit_terms'   => 'manage_woocommerce',
				'delete_terms' => 'manage_woocommerce',
				'assign_terms' => 'edit_products',
			),
		);

		register_taxonomy( self::SLUG, array( 'product' ), $args );
	}

	/**
	 * Seeds the default location terms if they do not already exist.
	 *
	 * Idempotent — safe to call on every activation. The taxonomy must be
	 * registered before calling this method.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function seed_terms(): void {
		if ( ! taxonomy_exists( self::SLUG ) ) {
			return;
		}

		$terms = array(
			self::ROCKFORD => __( 'Rockford', 'gym-core' ),
			self::BELOIT   => __( 'Beloit', 'gym-core' ),
		);

		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, self::SLUG ) ) {
				wp_insert_term(
					$name,
					self::SLUG,
					array( 'slug' => $slug )
				);
			}
		}
	}

	/**
	 * Checks whether a given slug is a valid gym location.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The location slug to validate.
	 * @return bool True if the slug is a valid location, false otherwise.
	 */
	public static function is_valid( string $slug ): bool {
		return in_array( $slug, self::VALID_LOCATIONS, true );
	}
}
