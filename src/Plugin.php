<?php
/**
 * Main plugin loader.
 *
 * @package HMA_Core
 */

declare( strict_types=1 );

namespace HMA_Core;

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

		/**
		 * Fires after HMA Core has finished loading.
		 *
		 * Use this hook to register extensions or override behaviour.
		 *
		 * @since 1.0.0
		 */
		do_action( 'hma_core_loaded' );
	}

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'hma-core',
			false,
			dirname( HMA_CORE_BASENAME ) . '/languages'
		);
	}
}
