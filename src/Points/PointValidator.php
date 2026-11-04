<?php
/**
 * Validation of the chosen pickup point.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Points;

use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\Shipping\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that a point fits the shipping method and the payment.
 */
final class PointValidator {

	/**
	 * Returns an error message for the customer, or null when the point is valid.
	 *
	 * @param Service          $service    Chosen shipping service.
	 * @param PickupPoint|null $point      Resolved point, null when none was chosen or it does not exist.
	 * @param bool             $chosen     Whether the customer chose a point (to tell "missing" from "not found").
	 * @param float|null       $cod_amount Cash on delivery amount in PLN, null when paying otherwise.
	 */
	public static function error( Service $service, ?PickupPoint $point, bool $chosen, ?float $cod_amount ): ?string {
		// Cash on delivery is offered with Pocztex only, never with letters (OPZ II.1.3.c).
		if ( null !== $cod_amount && ! $service->supports_cod ) {
			return __( 'Cash on delivery is not available for the chosen shipping method. Choose another payment or shipping method.', 'poczta-polska-for-woocommerce' );
		}

		if ( ! $service->needs_pickup_point() ) {
			return null;
		}

		if ( null === $point ) {
			return $chosen
				? __( 'The chosen pickup point is not available any more. Please choose another one.', 'poczta-polska-for-woocommerce' )
				: ( PickupPoint::TYPE_LOCKER === $service->point_type
					? __( 'Please choose a parcel locker on the map.', 'poczta-polska-for-woocommerce' )
					: __( 'Please choose a pickup point on the map.', 'poczta-polska-for-woocommerce' ) );
		}

		if ( $point->type !== $service->point_type ) {
			return PickupPoint::TYPE_LOCKER === $service->point_type
				? __( 'The chosen place is not a parcel locker. Please choose a parcel locker on the map.', 'poczta-polska-for-woocommerce' )
				: __( 'The chosen place is a parcel locker. Please choose a pickup point on the map or change the shipping method.', 'poczta-polska-for-woocommerce' );
		}

		if ( ! $point->is_pickup_point() ) {
			return __( 'The chosen place does not hand out parcels. Please choose another point on the map.', 'poczta-polska-for-woocommerce' );
		}

		if ( null === $cod_amount ) {
			return null;
		}

		if ( ! $point->supports_cod() ) {
			return __( 'The chosen point does not accept cash on delivery. Choose another point or another payment method.', 'poczta-polska-for-woocommerce' );
		}

		if ( ! $point->accepts_cod_amount( $cod_amount ) ) {
			return sprintf(
				/* translators: %s: maximum cash on delivery amount, e.g. 4 000,00 zł. */
				__( 'The chosen point accepts cash on delivery up to %s. Choose another point or another payment method.', 'poczta-polska-for-woocommerce' ),
				html_entity_decode( wp_strip_all_tags( wc_price( $point->cod_limit ) ), ENT_QUOTES, 'UTF-8' )
			);
		}

		return null;
	}
}
