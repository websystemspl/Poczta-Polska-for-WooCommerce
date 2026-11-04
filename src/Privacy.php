<?php
/**
 * Personal data: privacy policy, export and erasure.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

use PocztaPolska\WooCommerce\Points\OrderPoint;
use PocztaPolska\WooCommerce\Shipments\ShipmentRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin data to the WordPress privacy tools through the WooCommerce order exporter and eraser.
 *
 * The plugin stores the chosen pickup point in orders and tracking numbers in its shipments table.
 * Recipient addresses stay in the order (WooCommerce) and in Elektroniczny Nadawca.
 */
final class Privacy {

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'woocommerce_privacy_export_order_personal_data', array( $this, 'export' ), 10, 2 );
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( $this, 'erase' ) );
	}

	/**
	 * Suggests text for the privacy policy (Settings → Privacy).
	 */
	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			__( 'Poczta Polska for WooCommerce', 'poczta-polska-for-woocommerce' ),
			wp_kses_post( wpautop( $this->policy_text() ) )
		);
	}

	/**
	 * Adds the pickup point and tracking numbers to the export of an order.
	 *
	 * @param array<int, array{name: string, value: string}> $data  Personal data of the order.
	 * @param \WC_Order                                      $order Order.
	 * @return array<int, array{name: string, value: string}>
	 */
	public function export( $data, $order ): array {
		$data = is_array( $data ) ? $data : array();

		if ( ! $order instanceof \WC_Order ) {
			return $data;
		}

		$point = OrderPoint::get( $order );

		if ( null !== $point ) {
			$data[] = array(
				'name'  => __( 'Poczta Polska pickup point', 'poczta-polska-for-woocommerce' ),
				/* translators: 1: point name, 2: point address, 3: point ID (PNI). */
				'value' => sprintf( __( '%1$s, %2$s (PNI %3$d)', 'poczta-polska-for-woocommerce' ), $point['name'], $point['address'], $point['pni'] ),
			);
		}

		$tracking = array();

		foreach ( ( new ShipmentRepository() )->for_order( $order->get_id() ) as $shipment ) {
			$tracking = array_merge( $tracking, $shipment->tracking_numbers() );
		}

		if ( array() !== $tracking ) {
			$data[] = array(
				'name'  => __( 'Poczta Polska tracking numbers', 'poczta-polska-for-woocommerce' ),
				'value' => implode( ', ', $tracking ),
			);
		}

		return $data;
	}

	/**
	 * Removes the pickup point from an order when WooCommerce anonymises it.
	 *
	 * WooCommerce calls this only when "Remove personal data from orders on request" is enabled.
	 * Tracking numbers stay: they identify a postal service, not a person, and the shop may need
	 * them for complaints; the addresses linked to them are anonymised in the order by WooCommerce.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function erase( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$order->delete_meta_data( OrderPoint::META_KEY );
		$order->save();

		$repository = new ShipmentRepository();

		// Phone numbers changed by the shop manager for a shipment, and the recipient copy of rows
		// written before data minimisation.
		foreach ( $repository->for_order( $order->get_id() ) as $shipment ) {
			if ( isset( $shipment->data['draft']['recipient'] ) || isset( $shipment->data['draft']['contact'] ) ) {
				$data = $shipment->data;
				unset( $data['draft']['recipient'], $data['draft']['contact'] );
				$repository->update( $shipment->id, array( 'data' => $data ) );
			}
		}
	}

	/**
	 * Text for the privacy policy.
	 */
	private function policy_text(): string {
		return implode(
			"\n\n",
			array(
				__( 'This store sends parcels with Poczta Polska S.A. To register a shipment, the recipient name, delivery address, phone number, email address and, for cash on delivery, the amount are sent to Poczta Polska through its Elektroniczny Nadawca service. Poczta Polska processes them to deliver the parcel and to notify the recipient.', 'poczta-polska-for-woocommerce' ),
				__( 'When you choose a pickup point or a parcel locker, the Poczta Polska map is loaded from mapa.ecommerce.poczta-polska.pl. Your browser sends your IP address, your delivery address (to centre the map) and the address you search for to Poczta Polska. The chosen point is saved in your order.', 'poczta-polska-for-woocommerce' ),
				__( 'The store keeps the tracking numbers of your parcels with the order. Recipient data stays in the order; the plugin keeps only phone numbers that the store corrected for a shipment, and removes them together with the order data.', 'poczta-polska-for-woocommerce' ),
			)
		);
	}
}
