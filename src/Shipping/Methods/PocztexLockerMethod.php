<?php
/**
 * Pocztex Parcel Locker shipping method.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping\Methods;

use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Pocztex collected from a Pocztex parcel locker chosen on the Poczta Polska map. Formats S-L.
 */
final class PocztexLockerMethod extends AbstractMethod {

	protected const SERVICE = ServiceRegistry::POCZTEX_LOCKER;

	/**
	 * {@inheritDoc}
	 */
	protected function default_title(): string {
		return __( 'Pocztex Parcel Locker', 'poczta-polska-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Pocztex collected from a Pocztex parcel locker chosen on the Poczta Polska map. Formats S-L.', 'poczta-polska-for-woocommerce' );
	}
}
