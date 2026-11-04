<?php
/**
 * PHPUnit bootstrap for unit tests (WordPress functions mocked with Brain Monkey).
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'PPWC_VERSION', 'test' );
define( 'PPWC_PLUGIN_FILE', dirname( __DIR__ ) . '/poczta-polska-for-woocommerce.php' );
define( 'PPWC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'PPWC_PLUGIN_URL', 'https://example.test/wp-content/plugins/poczta-polska-for-woocommerce/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';
require __DIR__ . '/Stubs/WP_Error.php';
