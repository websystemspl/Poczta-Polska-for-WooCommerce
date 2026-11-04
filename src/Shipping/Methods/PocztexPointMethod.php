<?php
/**
 * Pocztex Pickup Point shipping method.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping\Methods;

use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Pocztex collected at a post office or a partner point (Żabka, Lewiatan, ABC and others) chosen on the Poczta Polska map.
 */
final class PocztexPointMethod extends AbstractMethod {

	protected const SERVICE = ServiceRegistry::POCZTEX_POINT;

	/**
	 * {@inheritDoc}
	 */
	protected function default_title(): string {
		return __( 'Pocztex Pickup Point', 'poczta-polska-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Pocztex collected at a post office or a partner point (Żabka, Lewiatan, ABC and others) chosen on the Poczta Polska map.', 'poczta-polska-for-woocommerce' );
	}
}
