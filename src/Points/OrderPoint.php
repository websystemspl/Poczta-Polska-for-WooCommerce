<?php
/**
 * Pickup point stored in an order.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Points;

use PocztaPolska\WooCommerce\AuditLog;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the pickup point in order meta through the WooCommerce CRUD API (HPOS safe).
 */
final class OrderPoint {

	public const META_KEY = '_ppwc_pickup_point';

	/**
	 * Stores the point in the order. The caller saves the order.
	 *
	 * @param \WC_Order   $order Order.
	 * @param PickupPoint $point Point.
	 */
	public static function store( \WC_Order $order, PickupPoint $point ): void {
		$order->update_meta_data(
			self::META_KEY,
			array(
				'pni'        => $point->pni,
				'name'       => $point->name,
				'print_name' => $point->print_name,
				'type'       => $point->type,
				'network'    => $point->network,
				'address'    => $point->address_line(),
				'cod_limit'  => $point->cod_limit,
			)
		);
	}

	/**
	 * Changes the point of an order chosen by the shop manager: saves it with an order note
	 * (also shown to the customer) and an audit log entry. Does nothing when the point is the same.
	 *
	 * @param \WC_Order   $order Order.
	 * @param PickupPoint $point New point.
	 */
	public static function change( \WC_Order $order, PickupPoint $point ): void {
		$current = self::get( $order );

		if ( null !== $current && $current['pni'] === $point->pni ) {
			return;
		}

		self::store( $order, $point );
		$order->add_order_note(
			sprintf(
				/* translators: 1: point name, 2: point address, 3: PNI. */
				__( 'Pickup point changed to %1$s, %2$s (PNI %3$d).', 'poczta-polska-for-woocommerce' ),
				$point->name,
				$point->address_line(),
				$point->pni
			),
			0,
			true
		);
		$order->save();

		AuditLog::record(
			'order_point_changed',
			'order',
			(string) $order->get_id(),
			array(
				'from' => null !== $current ? $current['pni'] : 0,
				'to'   => $point->pni,
			)
		);
	}

	/**
	 * Returns the stored point, or null when the order has none.
	 *
	 * @param \WC_Order $order Order.
	 * @return array{pni: int, name: string, print_name: string, type: string, network: string, address: string, cod_limit: float}|null
	 */
	public static function get( \WC_Order $order ): ?array {
		$stored = $order->get_meta( self::META_KEY );

		if ( ! is_array( $stored ) || empty( $stored['pni'] ) ) {
			return null;
		}

		return array(
			'pni'        => (int) $stored['pni'],
			'name'       => (string) ( $stored['name'] ?? '' ),
			'print_name' => (string) ( $stored['print_name'] ?? '' ),
			'type'       => (string) ( $stored['type'] ?? '' ),
			'network'    => (string) ( $stored['network'] ?? '' ),
			'address'    => (string) ( $stored['address'] ?? '' ),
			'cod_limit'  => (float) ( $stored['cod_limit'] ?? 0 ),
		);
	}

	/**
	 * Label of a point type for customers.
	 *
	 * @param string $type PUNKT or AUTOMAT.
	 */
	public static function type_label( string $type ): string {
		return PickupPoint::TYPE_LOCKER === $type
			? __( 'Pocztex parcel locker', 'poczta-polska-for-woocommerce' )
			: __( 'Pickup point', 'poczta-polska-for-woocommerce' );
	}
}
