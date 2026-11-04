<?php
/**
 * Checkout block integration.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Checkout;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the pickup point picker to the Checkout block.
 */
final class BlocksIntegration implements IntegrationInterface {

	private const HANDLE = 'ppwc-checkout-blocks';

	/**
	 * Registers the integration with the Checkout block.
	 */
	public static function register(): void {
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			static function ( $registry ): void {
				if ( is_object( $registry ) && method_exists( $registry, 'register' ) ) {
					$registry->register( new self() );
				}
			}
		);
	}

	/**
	 * Integration name.
	 */
	public function get_name(): string {
		return 'ppwc';
	}

	/**
	 * Registers the script.
	 */
	public function initialize(): void {
		Assets::register_assets();

		wp_register_script(
			self::HANDLE,
			PPWC_PLUGIN_URL . 'assets/js/checkout-blocks.js',
			array( 'wc-blocks-checkout', 'wc-settings', 'wp-plugins', 'wp-element', 'wp-data', 'wp-i18n', Assets::HANDLE_MAP ),
			PPWC_VERSION,
			array( 'in_footer' => true )
		);
		wp_set_script_translations( self::HANDLE, 'poczta-polska-for-woocommerce', PPWC_PLUGIN_DIR . 'languages' );
		wp_enqueue_style( Assets::HANDLE_STYLE );
	}

	/**
	 * Front-end script handles.
	 *
	 * @return string[]
	 */
	public function get_script_handles(): array {
		return array( self::HANDLE );
	}

	/**
	 * Editor script handles (the picker is not shown in the editor).
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles(): array {
		return array();
	}

	/**
	 * Data available in the script as wc.wcSettings.getSetting( 'ppwc_data' ).
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data(): array {
		$point_types = array();

		foreach ( ServiceRegistry::all() as $service ) {
			if ( null !== $service->point_type ) {
				$point_types[ $service->id ] = $service->point_type;
			}
		}

		return array(
			'pointTypes' => $point_types,
			'locale'     => str_replace( '_', '-', determine_locale() ),
			'logoUrl'    => PPWC_PLUGIN_URL . Assets::LOGO_POCZTEX,
		);
	}
}
