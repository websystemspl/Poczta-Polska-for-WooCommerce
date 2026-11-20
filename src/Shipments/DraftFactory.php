<?php
/**
 * Shipment drafts from orders.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipments;

use PocztaPolska\WooCommerce\Points\OrderPoint;
use PocztaPolska\WooCommerce\Sender;
use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\SettingsSchema;
use PocztaPolska\WooCommerce\Shipping\Methods\AbstractMethod;
use PocztaPolska\WooCommerce\Shipping\PackageWeight;
use PocztaPolska\WooCommerce\Shipping\Service;
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;
use PocztaPolska\WooCommerce\Support\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a shipment draft from an order (OPZ II.2.2.a: recipient data comes from the order).
 */
final class DraftFactory {

	/**
	 * Ways EN confirms the delivery to the recipient (potwierdzenieEDoreczeniaType).
	 */
	public const CONFIRMATIONS = array( 'SMS', 'EMAIL' );

	/**
	 * Delivery hour options of Pocztex Kurier offered by the plugin (see SettingsSchema::POCZTEX_HOURS).
	 */
	public const HOURS = SettingsSchema::POCZTEX_HOURS;

	/**
	 * Creates a draft.
	 *
	 * @param \WC_Order            $order     Order.
	 * @param array<string, mixed> $overrides Changes from the shop manager: service, parcels (int), weights (int[]),
	 *                                        format, mobile, phone (landline), pickup_pni, office_id,
	 *                                        fragile (yes|no), insurance (PLN), value (declared value, PLN),
	 *                                        envelope (yes|no), oversize (yes|no), confirmation (SMS|EMAIL),
	 *                                        hour (DO_GODZ_12), distance (km). Missing service options
	 *                                        come from the shipment defaults of the service group.
	 * @throws ShipmentException When the order has no Poczta Polska service.
	 */
	public static function from_order( \WC_Order $order, array $overrides = array() ): ShipmentDraft {
		$method  = self::order_method( $order );
		$service = ServiceRegistry::get( (string) ( $overrides['service'] ?? ( null !== $method ? $method->id : self::order_service_id( $order ) ) ) );

		if ( null === $service ) {
			throw new ShipmentException( esc_html__( 'The order has no Poczta Polska shipping method. Choose the service for the shipment.', 'poczta-polska-for-woocommerce' ) );
		}

		$parcels = $service->requires_weight ? 1 : max( 1, min( 20, (int) ( $overrides['parcels'] ?? 1 ) ) );
		$weights = isset( $overrides['weights'] ) && is_array( $overrides['weights'] ) ? array_values( array_map( 'intval', $overrides['weights'] ) ) : self::split_weight( self::order_weight( $order, $service ), $parcels );
		$guids   = array_map( array( self::class, 'guid' ), range( 1, max( 1, count( $weights ) ) ) );
		$guid    = (string) array_shift( $guids );

		$format = self::format( $service, $method, (string) ( $overrides['format'] ?? '' ) );
		$door   = $service->is_courier_type() && ! $service->needs_pickup_point();

		return new ShipmentDraft(
			$service,
			$guid,
			$guids,
			self::recipient( $order, $overrides ),
			$weights,
			$format,
			self::cod_amount( $order ),
			$service->needs_pickup_point() ? (int) ( $overrides['pickup_pni'] ?? ( OrderPoint::get( $order )['pni'] ?? 0 ) ) : 0,
			self::replace_order_number( Settings::pocztex( $service, 'description' ), $order ),
			self::customer_number( $guid ),
			array(
				'fragile'         => isset( $overrides['fragile'] ) ? 'yes' === $overrides['fragile'] : 'yes' === Settings::pocztex( $service, 'fragile' ),
				'content'         => Settings::pocztex( $service, 'content' ),
				'insurance'       => isset( $overrides['insurance'] ) ? max( 0, (int) $overrides['insurance'] ) : (int) Settings::pocztex( $service, 'insurance' ),
				'value'           => max( 0, (int) round( (float) ( $overrides['value'] ?? Settings::pocztex( $service, 'value' ) ) * 100 ) ),
				'envelope'        => isset( $overrides['envelope'] ) ? 'yes' === $overrides['envelope'] : $door && 'yes' === Settings::pocztex( $service, 'envelope' ) && self::fits_envelope( $format, $weights ),
				'oversize'        => isset( $overrides['oversize'] ) ? 'yes' === $overrides['oversize'] : 'yes' === Settings::pocztex( $service, 'oversize' ),
				'confirmation'    => self::allowed( (string) ( $overrides['confirmation'] ?? Settings::pocztex( $service, 'confirmation' ) ), self::CONFIRMATIONS ),
				'hour'            => self::allowed( (string) ( $overrides['hour'] ?? ( $door ? Settings::pocztex( $service, 'hour' ) : '' ) ), self::HOURS ),
				'distance'        => max( 0, (int) ( $overrides['distance'] ?? ( null !== $method && $method->id === $service->id ? $method->get_option( 'distance', 0 ) : 0 ) ) ),
				'category'        => Settings::get( 'ecommerce_category' ),
				'cod_account'     => Settings::get( 'cod_account' ),
				'cod_method'      => Settings::get( 'cod_method' ),
				'cod_title'       => self::replace_order_number( Settings::get( 'cod_title' ), $order ),
				'posting_channel' => Settings::get( 'posting_channel' ),
				'posting_pni'     => self::posting_pni( (int) ( $overrides['office_id'] ?? 0 ) ),
			),
			self::contact( $order, $overrides )
		);
	}

	/**
	 * Returns the contract card for a shipment of the order: the method card or the default card.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function card_id( \WC_Order $order ): int {
		$method = self::order_method( $order );

		return (int) ( null !== $method ? $method->card_id() : Settings::get_for_env( 'card_id' ) );
	}

	/**
	 * Returns the Poczta Polska shipping method instance of the order, if it still exists.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function order_method( \WC_Order $order ): ?AbstractMethod {
		foreach ( $order->get_shipping_methods() as $item ) {
			if ( null === ServiceRegistry::get( $item->get_method_id() ) ) {
				continue;
			}

			$method = \WC_Shipping_Zones::get_shipping_method( (int) $item->get_instance_id() );

			if ( $method instanceof AbstractMethod ) {
				return $method;
			}
		}

		return null;
	}

	/**
	 * Returns the service ID of the order shipping line, also when the method instance was deleted.
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function order_service_id( \WC_Order $order ): string {
		foreach ( $order->get_shipping_methods() as $item ) {
			if ( null !== ServiceRegistry::get( $item->get_method_id() ) ) {
				return $item->get_method_id();
			}
		}

		return '';
	}

	/**
	 * Recipient from the shipping address, or from the billing address when the order has none.
	 *
	 * @param \WC_Order            $order     Order.
	 * @param array<string, mixed> $overrides Changes from the shop manager.
	 * @return array<string, string>
	 */
	private static function recipient( \WC_Order $order, array $overrides ): array {
		$type    = $order->has_shipping_address() ? 'shipping' : 'billing';
		$get     = static fn( string $field ): string => trim( (string) $order->{"get_{$type}_{$field}"}() );
		$person  = trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) );
		$company = $get( 'company' );
		$address = Sender::split_address( $get( 'address_1' ) );
		$extra   = $get( 'address_2' );
		$phone   = self::order_phone( $order );
		$mobile  = Validator::mobile( (string) ( $overrides['mobile'] ?? $phone ) );
		// A landline entered by the shop manager is sent next to the mobile; the order phone only when it is not a mobile.
		$phone = isset( $overrides['phone'] ) ? (string) Validator::phone( (string) $overrides['phone'] ) : ( null === $mobile ? (string) Validator::phone( $phone ) : '' );

		// Villages without street names: EN requires a street, the city takes its place.
		if ( '' === $address['street'] ) {
			$address['street'] = $get( 'city' );
		}

		if ( '' !== $extra && '' === $address['flat_number'] && mb_strlen( $extra ) <= 11 ) {
			$address['flat_number'] = $extra;
		} elseif ( '' !== $extra ) {
			$address['street'] = trim( $address['street'] . ' ' . $extra );
		}

		return array(
			'name'         => '' !== $company ? $company : $person,
			'name2'        => '' !== $company ? $person : '',
			'street'       => $address['street'],
			'house_number' => $address['house_number'],
			'flat_number'  => $address['flat_number'],
			'postcode'     => $get( 'postcode' ),
			'city'         => $get( 'city' ),
			'country'      => $get( 'country' ),
			'mobile'       => (string) $mobile,
			'phone'        => $phone,
			'email'        => (string) $order->get_billing_email(),
		);
	}

	/**
	 * Phone of the order: the shipping phone, or the billing phone when there is none.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function order_phone( \WC_Order $order ): string {
		return '' !== trim( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $order->get_billing_phone();
	}

	/**
	 * Mobile and landline that the shop manager changed compared with the order.
	 *
	 * @param \WC_Order            $order     Order.
	 * @param array<string, mixed> $overrides Changes from the shop manager.
	 * @return array<string, string>
	 */
	private static function contact( \WC_Order $order, array $overrides ): array {
		$phone   = self::order_phone( $order );
		$contact = array();

		if ( isset( $overrides['mobile'] ) && Validator::mobile( (string) $overrides['mobile'] ) !== Validator::mobile( $phone ) ) {
			$contact['mobile'] = (string) Validator::mobile( (string) $overrides['mobile'] );
		}

		if ( isset( $overrides['phone'] ) && '' !== (string) $overrides['phone'] ) {
			$contact['phone'] = (string) Validator::phone( (string) $overrides['phone'] );
		}

		return $contact;
	}

	/**
	 * Weight of the order in grams. Products without a weight count with the default Pocztex weight
	 * when all of them lack it; letters need real weights.
	 *
	 * @param \WC_Order $order   Order.
	 * @param Service   $service Service.
	 */
	private static function order_weight( \WC_Order $order, Service $service ): int {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item instanceof \WC_Order_Item_Product ? $item->get_product() : null;

			if ( $product instanceof \WC_Product && $product->needs_shipping() ) {
				$items[] = array(
					'weight'   => (string) $product->get_weight(),
					'quantity' => (float) $item->get_quantity(),
				);
			}
		}

		$weight = PackageWeight::from_items( $items, (string) get_option( 'woocommerce_weight_unit', 'kg' ) );

		if ( $weight->grams > 0 || $service->requires_weight ) {
			return $weight->grams;
		}

		return max( 1, (int) Settings::pocztex( $service, 'weight' ) );
	}

	/**
	 * Splits a weight evenly between parcels.
	 *
	 * @param int $grams   Total weight.
	 * @param int $parcels Number of parcels.
	 * @return list<int>
	 */
	private static function split_weight( int $grams, int $parcels ): array {
		$base    = intdiv( $grams, $parcels );
		$weights = array_fill( 0, $parcels, max( 1, $base ) );

		$weights[0] += $grams - $base * $parcels;

		return $weights;
	}

	/**
	 * Returns the parcel format.
	 *
	 * @param Service             $service  Service.
	 * @param AbstractMethod|null $method   Method instance.
	 * @param string              $override Format chosen by the shop manager.
	 */
	private static function format( Service $service, ?AbstractMethod $method, string $override ): string {
		// A format chosen by the shop manager is kept even when the service does not allow it:
		// ShipmentBuilder::problems() then names the problem instead of silently changing the format.
		if ( '' !== $override ) {
			return $override;
		}

		if ( null !== $method && $method->id === $service->id ) {
			return $method->format();
		}

		$default = $service->requires_weight ? Settings::get( 'ecommerce_size' ) : Settings::pocztex( $service, 'format' );

		return $service->allows_format( $default ) ? $default : $service->formats[ array_key_last( $service->formats ) ];
	}

	/**
	 * Returns the value when it is one of the allowed ones, otherwise "" (the service is not used).
	 *
	 * @param string       $value   Value.
	 * @param list<string> $allowed Allowed values.
	 */
	private static function allowed( string $value, array $allowed ): string {
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Whether the Pocztex envelope from the defaults fits the shipment: one parcel in format S up to 1 kg
	 * (the same rule ShipmentBuilder::problems() checks for an envelope chosen by hand).
	 *
	 * @param string    $format  Parcel format.
	 * @param list<int> $weights Parcel weights in grams.
	 */
	public static function fits_envelope( string $format, array $weights ): bool {
		return 'S' === $format && 1 === count( $weights ) && array_sum( $weights ) <= ShipmentBuilder::ENVELOPE_MAX_WEIGHT;
	}

	/**
	 * Cash on delivery in grosze for orders paid on delivery (EN FAQ 8: round, never truncate).
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function cod_amount( \WC_Order $order ): int {
		return 'cod' === $order->get_payment_method() ? (int) round( (float) $order->get_total( 'edit' ) * 100 ) : 0;
	}

	/**
	 * Posting point for the posting channel from the settings. At the post office it is the office
	 * of the buffer, so the shipment and the buffer name the same place.
	 *
	 * @param int $office_id Posting office chosen for the shipment, 0 for the default one.
	 */
	private static function posting_pni( int $office_id ): int {
		return match ( Settings::get( 'posting_channel' ) ) {
			'PP'    => $office_id > 0 ? $office_id : (int) Settings::get_for_env( 'posting_office_id' ),
			'APM'   => (int) Settings::get( 'posting_locker_pni' ),
			default => 0,
		};
	}

	/**
	 * Replaces {order_number} in a template.
	 *
	 * @param string    $template Template.
	 * @param \WC_Order $order    Order.
	 */
	private static function replace_order_number( string $template, \WC_Order $order ): string {
		return str_replace( '{order_number}', (string) $order->get_order_number(), $template );
	}

	/**
	 * Shop shipment number sent as numerPrzesylkiKlienta (max. 25 characters). EN keeps it when it gives
	 * a shipment a new GUID after a buffer date change, so the shop can find its shipment again.
	 *
	 * @param string $guid GUID of the main parcel.
	 */
	public static function customer_number( string $guid ): string {
		return 'PPWC-' . substr( $guid, 0, 20 );
	}

	/**
	 * Returns a new shipment GUID: 32 uppercase hexadecimal characters (EN guidType).
	 */
	private static function guid(): string {
		return strtoupper( bin2hex( random_bytes( 16 ) ) );
	}
}
