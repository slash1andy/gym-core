<?php
/**
 * PHPUnit bootstrap for Gym Core unit tests.
 *
 * For unit tests we use Brain\Monkey to mock WordPress/WooCommerce functions
 * without needing a full WordPress install. Integration tests should use a
 * separate bootstrap that loads wp-load.php.
 *
 * @package Gym_Core\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Patchwork explicitly so its stream-wrapper is registered before any
// stub file declares functions that tests will redefine via Brain\Monkey.
// Without this, Patchwork throws DefinedTooEarly when a test calls
// Functions\expect() on a function that was already parsed.
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';

// Allow Mockery to mock final classes (e.g. BadgeEngine, StreakTracker, FoundationsClearance).
// Restrict to src/ only — bypass-finals must not patch PHPUnit internals, since PHP 8.2+ readonly
// classes cannot extend non-readonly parents, and stripping readonly globally breaks PHPUnit 11.
DG\BypassFinals::allowPaths( [ realpath( dirname( __DIR__ ) . '/src' ) ] );
DG\BypassFinals::enable();

// Stub constants so classes that reference them can be loaded without WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

define( 'GYM_CORE_VERSION',  '1.0.0' );
define( 'GYM_CORE_FILE',     dirname( __DIR__ ) . '/gym-core.php' );
define( 'GYM_CORE_PATH',     dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'GYM_CORE_URL',      'http://example.com/wp-content/plugins/gym-core/' );
define( 'GYM_CORE_BASENAME', 'gym-core/gym-core.php' );

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

// WordPress $wpdb fetch-mode constants used by Sales/ProspectFilter and others.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

// Stub WP_Error for unit tests.
require_once __DIR__ . '/stubs/WP_Error.php';

// Stub the WooCommerce Blocks IntegrationInterface so BlockIntegration.php
// can be autoloaded in unit tests without the WC Blocks package installed.
if ( ! interface_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
	require_once __DIR__ . '/stubs/IntegrationInterface.php';
}

// Stub WordPress and WooCommerce classes used by the API module so that
// controllers can be autoloaded and tested without a full WP/WC install.
require_once __DIR__ . '/stubs/wordpress-classes.php';

// Real (no-op) global declarations for WooCommerce Subscriptions helpers so
// function_exists() inside the SUT returns true. Tests override return values
// via Brain\Monkey's Functions\expect()/when().
require_once __DIR__ . '/stubs/wc-subscriptions-functions.php';
