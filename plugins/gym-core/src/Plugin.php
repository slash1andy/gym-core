<?php
/**
 * Main plugin loader.
 *
 * @package Gym_Core
 */

declare( strict_types=1 );

namespace Gym_Core;

/**
 * Singleton that bootstraps all plugin modules.
 *
 * Usage: Plugin::instance()->init()
 * Do not instantiate directly — use the static factory.
 */
final class Plugin {

	/**
	 * The single instance of this class.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Private constructor — prevents direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initializes the plugin by registering all service providers.
	 *
	 * Called once from `plugins_loaded` after WooCommerce is confirmed active.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_textdomain();
		$this->register_admin_modules();
		$this->register_location_modules();

		/**
		 * Fires after Gym Core has finished loading.
		 *
		 * Use this hook to register extensions or override behaviour.
		 *
		 * @since 1.0.0
		 */
		do_action( 'gym_core_loaded' );
	}

	/**
	 * Registers admin modules (settings pages, user profile fields).
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function register_admin_modules(): void {
		if ( is_admin() ) {
			$settings = new Admin\Settings();
			$settings->register_hooks();
		}
	}

	/**
	 * Bootstraps all location-related modules.
	 *
	 * Registers the taxonomy, manager AJAX handler, product filter, order
	 * location recording, and frontend selector. Block checkout integration
	 * is deferred until woocommerce_blocks_loaded to ensure the Blocks package
	 * is available before we reference its classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_location_modules(): void {
		$taxonomy = new Location\Taxonomy();
		$taxonomy->register_hooks();

		$manager = new Location\Manager();
		$manager->register_hooks();

		$product_filter = new Location\ProductFilter( $manager );
		$product_filter->register_hooks();

		$order_location = new Location\OrderLocation( $manager );
		$order_location->register_hooks();

		$selector = new Frontend\LocationSelector( $manager );
		$selector->register_hooks();

		// Block checkout integration — deferred until WooCommerce Blocks is ready.
		add_action(
			'woocommerce_blocks_loaded',
			static function () use ( $manager ): void {
				$store_api = new Location\StoreApiExtension( $manager );
				$store_api->register();

				// Register block checkout integration when the registry is available.
				add_action(
					'woocommerce_blocks_checkout_block_registration',
					static function ( $registry ) use ( $manager ): void {
						$registry->register( new Location\BlockIntegration( $manager ) );
					}
				);
			}
		);
	}

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'gym-core',
			false,
			dirname( GYM_CORE_BASENAME ) . '/languages'
		);
	}
}
