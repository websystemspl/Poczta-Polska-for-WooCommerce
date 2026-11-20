<?php
/**
 * Activation and deactivation.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

use PocztaPolska\WooCommerce\Database\Schema;
use PocztaPolska\WooCommerce\En\Dictionary;

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
	 * One-time changes of saved settings when the plugin is updated (updates do not run activation).
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::VERSION_OPTION, '' );

		if ( '' === $installed || version_compare( $installed, PPWC_VERSION, '>=' ) ) {
			return;
		}

		// 0.3.0: the insurance amount is one of the EN tiers; the first tier is the default asked by Poczta Polska.
		if ( version_compare( $installed, '0.3.0', '<' ) ) {
			$settings = get_option( Settings::OPTION, array() );

			if ( is_array( $settings ) && '0' === (string) ( $settings['pocztex_insurance'] ?? '' ) ) {
				$settings['pocztex_insurance'] = (string) Dictionary::POCZTEX_INSURANCE_FALLBACK[0];
				update_option( Settings::OPTION, $settings );
			}
		}

		// 0.4.0: Pocztex Today has its own shipment defaults; they start as a copy of the Pocztex ones.
		if ( version_compare( $installed, '0.4.0', '<' ) ) {
			$settings = get_option( Settings::OPTION, array() );

			if ( is_array( $settings ) ) {
				foreach ( array( 'format', 'weight', 'fragile', 'content', 'insurance', 'description' ) as $key ) {
					if ( isset( $settings[ SettingsSchema::PREFIX_POCZTEX . $key ] ) && ! isset( $settings[ SettingsSchema::PREFIX_POCZTEX_TODAY . $key ] ) ) {
						$settings[ SettingsSchema::PREFIX_POCZTEX_TODAY . $key ] = $settings[ SettingsSchema::PREFIX_POCZTEX . $key ];
					}
				}

				update_option( Settings::OPTION, $settings );
			}
		}

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
