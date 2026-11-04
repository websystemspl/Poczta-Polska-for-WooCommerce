<?php
/**
 * Pocztex Courier shipping method.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping\Methods;

use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Pocztex door-to-door delivery by courier.
 */
final class PocztexCourierMethod extends AbstractMethod {

	protected const SERVICE = ServiceRegistry::POCZTEX_COURIER;

	/**
	 * {@inheritDoc}
	 */
	protected function default_title(): string {
		return __( 'Pocztex Courier', 'poczta-polska-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Pocztex door-to-door delivery by courier.', 'poczta-polska-for-woocommerce' );
	}
}
