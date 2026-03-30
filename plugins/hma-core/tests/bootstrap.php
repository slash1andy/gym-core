<?php
/**
 * PHPUnit bootstrap for HMA Core unit tests.
 *
 * For unit tests we use Brain\Monkey to mock WordPress/WooCommerce functions
 * without needing a full WordPress install. Integration tests should use a
 * separate bootstrap that loads wp-load.php.
 *
 * @package HMA_Core\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Stub constants so classes that reference them can be loaded without WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

define( 'HMA_CORE_VERSION',  '1.0.0' );
define( 'HMA_CORE_FILE',     dirname( __DIR__ ) . '/hma-core.php' );
define( 'HMA_CORE_PATH',     dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'HMA_CORE_URL',      'http://example.com/wp-content/plugins/hma-core/' );
define( 'HMA_CORE_BASENAME', 'hma-core/hma-core.php' );

// WordPress time constants used by plugin classes.
define( 'YEAR_IN_SECONDS',  31536000 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'WEEK_IN_SECONDS',  604800 );
define( 'DAY_IN_SECONDS',   86400 );
define( 'HOUR_IN_SECONDS',  3600 );
define( 'MINUTE_IN_SECONDS', 60 );

// WordPress cookie constants used by Manager::set_cookie().
define( 'COOKIEPATH',   '/' );
define( 'COOKIE_DOMAIN', '' );

// Stub the WooCommerce Blocks IntegrationInterface so BlockIntegration.php
// can be autoloaded in unit tests without the WC Blocks package installed.
if ( ! interface_exists( \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface::class ) ) {
	// phpcs:disable
	namespace Automattic\WooCommerce\Blocks\Integrations {
		interface IntegrationInterface {
			public function get_name(): string;
			public function initialize(): void;
			public function get_script_handles(): array;
			public function get_editor_script_handles(): array;
			public function get_script_data(): array;
		}
	}
	// phpcs:enable
}
