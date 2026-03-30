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
