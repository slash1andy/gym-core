<?php
/**
 * Block checkout integration for location context.
 *
 * @package Gym_Core\Location
 */

declare( strict_types=1 );

namespace Gym_Core\Location;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Registers gym location data with the WooCommerce block checkout integration
 * registry.
 *
 * Implementing IntegrationInterface allows the current location and the list
 * of available locations to be passed to block scripts via the block data
 * mechanism, avoiding a secondary fetch. The data is accessible from
 * JavaScript via getSetting('gym-location', 'key').
 *
 * This class is instantiated only when WooCommerce Blocks is loaded, so
 * IntegrationInterface is guaranteed to exist at instantiation time.
 */
class BlockIntegration implements IntegrationInterface {

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
	 * Returns the unique name of this integration.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'gym-location';
	}

	/**
	 * Initialises the integration.
	 *
	 * No extra scripts are enqueued at this stage; all data is passed via
	 * get_script_data() below.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Intentionally empty — data is passed via get_script_data().
	}

	/**
	 * Returns script handles registered for the block checkout frontend.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string>
	 */
	public function get_script_handles(): array {
		return array();
	}

	/**
	 * Returns script handles registered for the block editor.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string>
	 */
	public function get_editor_script_handles(): array {
		return array();
	}

	/**
	 * Returns data passed to block scripts via the block data mechanism.
	 *
	 * Accessible from JavaScript via:
	 *   import { getSetting } from '@woocommerce/settings';
	 *   const data = getSetting( 'gym-location_data', {} );
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data(): array {
		return array(
			'currentLocation' => $this->manager->get_current_location(),
			'locations'       => Taxonomy::get_location_labels(),
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'gym_core_location_nonce' ),
		);
	}
}
