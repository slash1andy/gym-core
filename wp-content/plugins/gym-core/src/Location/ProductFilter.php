<?php
/**
 * Product filtering by location.
 *
 * @package Gym_Core\Location
 */

declare( strict_types=1 );

namespace Gym_Core\Location;

/**
 * Filters WooCommerce product queries by the visitor's active location.
 *
 * When a location is active, only products assigned to that gym_location
 * taxonomy term are shown in shop archives, search results, and shortcode
 * product lists. Products with no location term assigned are excluded.
 *
 * Filtering is skipped entirely when no location has been selected, so the
 * full catalogue remains visible to first-time visitors.
 */
class ProductFilter {

	/**
	 * The location manager.
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Manager $manager The location manager.
	 */
	public function __construct( Manager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Registers WordPress and WooCommerce hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_product_query', array( $this, 'filter_product_query' ) );
		add_filter( 'woocommerce_shortcode_products_query', array( $this, 'filter_shortcode_query' ) );
	}

	/**
	 * Appends a location tax_query to WooCommerce product archive queries.
	 *
	 * Fires via the woocommerce_product_query action, which receives the
	 * WP_Query used for shop archives and AJAX product loads.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $query The WooCommerce product query.
	 * @return void
	 */
	public function filter_product_query( \WP_Query $query ): void {
		$location = $this->manager->get_current_location();

		if ( '' === $location ) {
			return;
		}

		$tax_query = (array) $query->get( 'tax_query' );

		$tax_query[] = array(
			'taxonomy' => Taxonomy::SLUG,
			'field'    => 'slug',
			'terms'    => array( $location ),
			'operator' => 'IN',
		);

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Appends a location tax_query to shortcode-driven product queries.
	 *
	 * Fires via the woocommerce_shortcode_products_query filter, which covers
	 * [products], [recent_products], [featured_products], and related shortcodes.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $query_args The shortcode WP_Query arguments.
	 * @return array<string, mixed> Modified query arguments.
	 */
	public function filter_shortcode_query( array $query_args ): array {
		$location = $this->manager->get_current_location();

		if ( '' === $location ) {
			return $query_args;
		}

		$tax_query = isset( $query_args['tax_query'] )
			? (array) $query_args['tax_query']
			: array();

		$tax_query[] = array(
			'taxonomy' => Taxonomy::SLUG,
			'field'    => 'slug',
			'terms'    => array( $location ),
			'operator' => 'IN',
		);

		$query_args['tax_query'] = $tax_query;

		return $query_args;
	}
}
