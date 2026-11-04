<?php
/**
 * Activation and deactivation.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

use PocztaPolska\WooCommerce\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation and deactivation.
 *
 * Removing data happens only on uninstall (uninstall.php).
 */
final class Lifecycle {

	public const VERSION_OPTION = 'ppwc_version';

	/**
	 * Action Scheduler group for all background jobs of the plugin.
	 */
	public const ACTION_GROUP = 'ppwc';

	/**
	 * Runs on plugin activation.
	 */
	public static function activate(): void {
		Schema::install();
		update_option( self::VERSION_OPTION, PPWC_VERSION, false );
	}

	/**
	 * Runs on plugin deactivation. Stops scheduled jobs, keeps settings and data.
	 */
	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), self::ACTION_GROUP );
		}
	}
}
