<?php
/**
 * Order location recording and admin filtering.
 *
 * @package Gym_Core\Location
 */

declare( strict_types=1 );

namespace Gym_Core\Location;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Records the visitor's active location on each new order and exposes
 * admin-side filtering to search orders by location.
 *
 * All order data operations use WooCommerce CRUD methods
 * ($order->get_meta / update_meta_data / save_meta_data) — never
 * get_post_meta / update_post_meta — for full HPOS compatibility.
 */
class OrderLocation {

	/**
	 * Order meta key for the associated location.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY = '_gym_location';

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
		// Classic checkout — fires after the WC_Order is created and saved.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'save_location_to_order' ) );

		// Block (Store API) checkout — fires after payment processing.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_location_to_order' ) );

		// Admin order list — filter UI dropdown.
		// restrict_manage_posts covers legacy post-based orders.
		// woocommerce_order_list_table_restrict_manage_orders covers HPOS.
		add_action( 'restrict_manage_posts', array( $this, 'render_order_location_filter' ) );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'render_order_location_filter' ) );

		// Apply the filter — legacy post-based orders.
		add_filter( 'request', array( $this, 'apply_legacy_order_filter' ) );

		// Apply the filter — HPOS direct SQL.
		add_filter( 'woocommerce_orders_table_query_clauses', array( $this, 'apply_hpos_order_filter' ), 10, 3 );

		// Display the location in the order detail panel.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_location_in_order_admin' ) );
	}

	/**
	 * Saves the current visitor location to the order as meta.
	 *
	 * Attached to both classic and block checkout events. Uses CRUD methods
	 * so the write goes to the correct storage backend (HPOS or posts).
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The newly created order object.
	 * @return void
	 */
	public function save_location_to_order( \WC_Order $order ): void {
		$location = $this->manager->get_current_location();

		if ( '' === $location ) {
			return;
		}

		$order->update_meta_data( self::META_KEY, $location );
		$order->save_meta_data();
	}

	/**
	 * Returns the location associated with an order.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The order object.
	 * @return string Location slug, or empty string if none recorded.
	 */
	public function get_order_location( \WC_Order $order ): string {
		$meta = $order->get_meta( self::META_KEY, true );
		return is_string( $meta ) ? sanitize_key( $meta ) : '';
	}

	/**
	 * Renders the location filter dropdown on the admin orders list.
	 *
	 * Guards against double-output by checking the current admin context
	 * before rendering.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_order_location_filter(): void {
		global $typenow, $pagenow;

		$is_legacy_orders = 'edit.php' === $pagenow && 'shop_order' === $typenow;
		$is_hpos_orders   = isset( $_GET['page'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& 'wc-orders' === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $is_legacy_orders && ! $is_hpos_orders ) {
			return;
		}

		$selected = isset( $_GET['gym_location'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( wp_unslash( $_GET['gym_location'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		$locations = array(
			''                 => __( 'All locations', 'gym-core' ),
			Taxonomy::ROCKFORD => __( 'Rockford', 'gym-core' ),
			Taxonomy::BELOIT   => __( 'Beloit', 'gym-core' ),
		);

		echo '<select name="gym_location" id="gym_location_filter">';
		foreach ( $locations as $slug => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $slug ),
				selected( $selected, $slug, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Applies the location filter to legacy (post-based) order queries.
	 *
	 * Hooked into 'request' which runs before WP_Query processes the admin
	 * orders list for stores using post-based order storage.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $vars The request query vars.
	 * @return array<string, mixed> Modified query vars.
	 */
	public function apply_legacy_order_filter( array $vars ): array {
		global $typenow;

		if ( 'shop_order' !== $typenow ) {
			return $vars;
		}

		if ( ! isset( $_GET['gym_location'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $vars;
		}

		$location = sanitize_key( wp_unslash( $_GET['gym_location'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $location || ! Taxonomy::is_valid( $location ) ) {
			return $vars;
		}

		$existing = isset( $vars['meta_query'] ) ? (array) $vars['meta_query'] : array();

		$vars['meta_query'] = array_merge(
			$existing,
			array(
				array(
					'key'     => self::META_KEY,
					'value'   => $location,
					'compare' => '=',
				),
			)
		);

		return $vars;
	}

	/**
	 * Applies the location filter to HPOS order SQL queries.
	 *
	 * Hooked into woocommerce_orders_table_query_clauses, which allows direct
	 * modification of the SQL built by the HPOS data store. Joins the
	 * wc_orders_meta table to restrict results to orders with the selected
	 * location meta value.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $clauses    SQL query clauses (join, where, etc.).
	 * @param mixed                 $query      The internal order query object (unused).
	 * @param array<string, mixed>  $query_args The query arguments (unused).
	 * @return array<string, string> Modified SQL clauses.
	 */
	public function apply_hpos_order_filter( array $clauses, $query, array $query_args ): array {
		if ( ! isset( $_GET['gym_location'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $clauses;
		}

		$location = sanitize_key( wp_unslash( $_GET['gym_location'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $location || ! Taxonomy::is_valid( $location ) ) {
			return $clauses;
		}

		global $wpdb;

		$clauses['join'] = ( $clauses['join'] ?? '' ) . $wpdb->prepare(
			" INNER JOIN {$wpdb->prefix}wc_orders_meta AS gym_loc ON gym_loc.order_id = {$wpdb->prefix}wc_orders.id AND gym_loc.meta_key = %s AND gym_loc.meta_value = %s",
			self::META_KEY,
			$location
		);

		return $clauses;
	}

	/**
	 * Displays the order's associated location in the WooCommerce order admin panel.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The current order object.
	 * @return void
	 */
	public function display_location_in_order_admin( \WC_Order $order ): void {
		$location = $this->get_order_location( $order );

		if ( '' === $location ) {
			return;
		}

		$labels = array(
			Taxonomy::ROCKFORD => __( 'Rockford', 'gym-core' ),
			Taxonomy::BELOIT   => __( 'Beloit', 'gym-core' ),
		);

		$label = $labels[ $location ] ?? esc_html( $location );

		printf(
			'<p class="gym-order-location"><strong>%s</strong> %s</p>',
			esc_html__( 'Location:', 'gym-core' ),
			esc_html( $label )
		);
	}
}
