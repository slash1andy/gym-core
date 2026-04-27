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
	 * Returns location slugs mapped to human-readable labels.
	 *
	 * All code that needs location labels should call this method instead of
	 * hardcoding label maps. Makes adding a third location a one-line change.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, string> Slug => label.
	 */
	public static function get_location_labels(): array {
		$cached = wp_cache_get( 'gym_location_labels', 'gym_core' );
		if ( false !== $cached ) {
			return $cached;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => self::SLUG,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$labels = array();
		foreach ( $terms as $term ) {
			$labels[ $term->slug ] = $term->name;
		}
		wp_cache_set( 'gym_location_labels', $labels, 'gym_core', 300 );
		return $labels;
	}

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
	 * Iterates a constant default map rather than get_location_labels(), since
	 * on a fresh activation no terms exist yet — get_terms() would return an
	 * empty array and nothing would be seeded. Runtime label lookups still go
	 * through get_location_labels() (DB-backed), so admin-added locations work
	 * without a code change.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function seed_terms(): void {
		if ( ! taxonomy_exists( self::SLUG ) ) {
			return;
		}

		/**
		 * Filters the default location terms inserted on first activation.
		 *
		 * Only consulted at activation time — runtime label lookups query the
		 * taxonomy directly, so adding a third location via wp-admin works
		 * without touching this filter.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $defaults Slug => display name.
		 */
		$defaults = apply_filters(
			'gym_core_default_locations',
			array(
				self::ROCKFORD => 'Rockford',
				self::BELOIT   => 'Beloit',
			)
		);

		foreach ( $defaults as $slug => $name ) {
			$slug = (string) $slug;
			$name = (string) $name;

			if ( '' === $slug || '' === $name ) {
				continue;
			}

			if ( ! term_exists( $slug, self::SLUG ) ) {
				wp_insert_term(
					$name,
					self::SLUG,
					array( 'slug' => $slug )
				);
			}
		}

		// Drop the cached labels so subsequent reads pick up the freshly seeded terms.
		wp_cache_delete( 'gym_location_labels', 'gym_core' );
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
		$locations = self::get_location_labels();
		return isset( $locations[ $slug ] );
	}
}
