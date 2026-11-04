<?php
/**
 * Plugin Name:          Poczta Polska for WooCommerce
 * Description:          Pocztex and eCommerce shipping methods with the Poczta Polska pickup point map, cash on delivery and shipment handling in Elektroniczny Nadawca (EN).
 * Version:              0.2.0
 * Requires at least:    6.8
 * Requires PHP:         8.2
 * Requires Plugins:     woocommerce
 * Author:               Web Systems
 * Author URI:           https://web-systems.pl
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          poczta-polska-for-woocommerce
 * Domain Path:          /languages
 * WC requires at least: 9.6
 * WC tested up to:      11.2
 *
 * @package PocztaPolska\WooCommerce
 */

defined( 'ABSPATH' ) || exit;

// Keep this file parseable on old PHP versions: the version guard below must run
// before any file that uses PHP 8.2 syntax is loaded.

define( 'PPWC_VERSION', '0.2.0' );
define( 'PPWC_PLUGIN_FILE', __FILE__ );
define( 'PPWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PPWC_MIN_PHP', '8.2' );

if ( version_compare( PHP_VERSION, PPWC_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', 'ppwc_php_version_notice' );
	return;
}

/**
 * Shows an error when the plugin runs on an unsupported PHP version.
 *
 * WordPress blocks activation thanks to the "Requires PHP" header, so this only
 * covers a server downgraded after activation.
 *
 * @return void
 */
function ppwc_php_version_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'Poczta Polska for WooCommerce requires PHP %1$s or newer. This server runs PHP %2$s, so the plugin is inactive.', 'poczta-polska-for-woocommerce' ),
				PPWC_MIN_PHP,
				PHP_VERSION
			)
		)
	);
}

require_once PPWC_PLUGIN_DIR . 'src/Autoloader.php';
\PocztaPolska\WooCommerce\Autoloader::register();

register_activation_hook( __FILE__, array( \PocztaPolska\WooCommerce\Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \PocztaPolska\WooCommerce\Lifecycle::class, 'deactivate' ) );

\PocztaPolska\WooCommerce\Plugin::instance()->boot();
