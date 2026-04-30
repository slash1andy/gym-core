<?php
/**
 * PHPUnit bootstrap for HMA AI Chat unit tests.
 *
 * Uses Brain\Monkey to mock WordPress functions without a full WordPress install.
 *
 * @package HMA_AI_Chat\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Patchwork explicitly so its stream-wrapper is registered before any
// stub file declares functions that tests will redefine via Brain\Monkey.
// Without this, Patchwork throws DefinedTooEarly when a test calls
// Functions\expect() on a function that was already parsed.
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';

// Allow Mockery to mock final classes. Restrict to src/ only so that
// bypass-finals does not patch PHPUnit internals.
DG\BypassFinals::allowPaths( [ realpath( dirname( __DIR__ ) . '/src' ) ] );
DG\BypassFinals::enable();

// Stub constants so classes that reference them can be loaded without WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

define( 'HMA_AI_CHAT_VERSION', '0.5.1' );
define( 'HMA_AI_CHAT_FILE', dirname( __DIR__ ) . '/hma-ai-chat.php' );
define( 'HMA_AI_CHAT_PATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'HMA_AI_CHAT_URL', 'http://example.com/wp-content/plugins/hma-ai-chat/' );

// WordPress time constants.
define( 'YEAR_IN_SECONDS',   31536000 );
define( 'MONTH_IN_SECONDS',  2592000 );
define( 'WEEK_IN_SECONDS',   604800 );
define( 'DAY_IN_SECONDS',    86400 );
define( 'HOUR_IN_SECONDS',   3600 );
define( 'MINUTE_IN_SECONDS', 60 );

// WordPress cookie constants.
define( 'COOKIEPATH',    '/' );
define( 'COOKIE_DOMAIN', '' );

// WordPress $wpdb fetch-mode constants.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

// Stub WP_Error.
require_once __DIR__ . '/stubs/WP_Error.php';

// Stub WordPress classes used by the plugin.
require_once __DIR__ . '/stubs/wordpress-classes.php';
