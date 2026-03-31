<?php
/**
 * Store API extension for location context.
 *
 * @package Gym_Core\Location
 */

declare( strict_types=1 );

namespace Gym_Core\Location;

/**
 * Extends the WooCommerce Store API Cart and Checkout endpoints with
 * location context, making the active location available to block-based
 * checkout without a secondary API request.
 *
 * The extension uses the woocommerce_store_api_register_endpoint_data
 * function introduced in WooCommerce 6.6 and must be registered inside the
 * woocommerce_blocks_loaded action.
 */
class StoreApiExtension {

	/**
	 * Store API extension namespace.
	 *
	 * Used as the key under extensions.{namespace} in Store API responses.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NAMESPACE = 'gym-core';

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
	 * Registers the Store API endpoint data extensions.
	 *
	 * Must be called inside the woocommerce_blocks_loaded action. Guards
	 * against missing Store API gracefully so the plugin does not fatal
	 * on WooCommerce versions before 6.6.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		// Extend the Cart endpoint — location is available during cart review.
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'cart',
				'namespace'       => self::NAMESPACE,
				'data_callback'   => array( $this, 'get_location_data' ),
				'schema_callback' => array( $this, 'get_location_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		// Extend the Checkout endpoint — location is available at order submission.
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => self::NAMESPACE,
				'data_callback'   => array( $this, 'get_location_data' ),
				'schema_callback' => array( $this, 'get_location_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Returns the location data for the Store API response.
	 *
	 * Included under extensions.gym-core in Cart and Checkout responses.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Location data.
	 */
	public function get_location_data(): array {
		$slug = $this->manager->get_current_location();

		return array(
			'slug'  => $slug,
			'label' => $this->get_location_label( $slug ),
		);
	}

	/**
	 * Returns the JSON schema for the location extension data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> JSON schema definition.
	 */
	public function get_location_schema(): array {
		return array(
			'slug'  => array(
				'description' => __( 'The active location slug.', 'gym-core' ),
				'type'        => array( 'string', 'null' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'enum'        => array_merge( Taxonomy::VALID_LOCATIONS, array( '' ) ),
			),
			'label' => array(
				'description' => __( 'The active location display name.', 'gym-core' ),
				'type'        => array( 'string', 'null' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * Returns the human-readable label for a location slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The location slug.
	 * @return string The location label, or empty string for unknown slugs.
	 */
	private function get_location_label( string $slug ): string {
		$labels = Taxonomy::get_location_labels();
		return $labels[ $slug ] ?? '';
	}
}
