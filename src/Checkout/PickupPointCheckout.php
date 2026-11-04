<?php
/**
 * Pickup point at checkout.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Checkout;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\Points\OrderPoint;
use PocztaPolska\WooCommerce\Points\PointResolver;
use PocztaPolska\WooCommerce\Points\PointSelection;
use PocztaPolska\WooCommerce\Points\PointValidator;
use PocztaPolska\WooCommerce\Rest\PointsController;
use PocztaPolska\WooCommerce\Shipping\Service;
use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Lets customers choose a pickup point in the classic checkout and the Checkout block,
 * validates it on the server and stores it in the order.
 *
 * The browser only sends a PNI. Point details come from the local copy or from
 * Elektroniczny Nadawca, so customers cannot inject names or addresses.
 */
final class PickupPointCheckout {

	/**
	 * Store API namespace.
	 */
	public const STORE_API_NAMESPACE = 'ppwc';

	/**
	 * Name of the classic checkout field.
	 */
	private const FIELD = 'ppwc_pickup_point';

	/**
	 * Gateway ID of WooCommerce cash on delivery.
	 */
	private const COD_GATEWAY = 'cod';

	/**
	 * Point resolver.
	 *
	 * @var PointResolver
	 */
	private PointResolver $resolver;

	/**
	 * Constructor.
	 *
	 * @param PointResolver|null $resolver Point resolver.
	 */
	public function __construct( ?PointResolver $resolver = null ) {
		$this->resolver = $resolver ?? new PointResolver();
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		// Classic checkout.
		add_action( 'woocommerce_after_shipping_rate', array( $this, 'render_classic' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'store_from_order_review' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_classic' ) );
		add_action( 'woocommerce_checkout_order_processed', array( PointSelection::class, 'clear' ) );

		// Checkout block (Store API).
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register_store_api();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api' ) );
		}
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_and_save_store_api' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( PointSelection::class, 'clear' ) );

		// Cash on delivery.
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'hide_cod' ) );
	}

	/**
	 * Hides cash on delivery when the chosen Poczta Polska method does not allow it.
	 *
	 * Other shipping methods are left alone: their COD rules belong to the COD gateway settings.
	 * The checkout validation enforces the same rule on the server.
	 *
	 * @param array<string, mixed> $gateways Available gateways.
	 * @return array<string, mixed>
	 */
	public function hide_cod( $gateways ): array {
		$gateways = is_array( $gateways ) ? $gateways : array();

		if ( ! isset( $gateways[ self::COD_GATEWAY ] ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $gateways;
		}

		$service = PointSelection::chosen_service();

		if ( null !== $service && ! $service->supports_cod ) {
			unset( $gateways[ self::COD_GATEWAY ] );
		}

		return $gateways;
	}

	/**
	 * Renders the point picker under the chosen Poczta Polska method (classic checkout).
	 *
	 * @param \WC_Shipping_Rate $rate  Shipping rate.
	 * @param int|string        $index Package index.
	 */
	public function render_classic( $rate, $index ): void {
		if ( ! is_checkout() || 0 !== (int) $index || ! $rate instanceof \WC_Shipping_Rate ) {
			return;
		}

		$service = PointSelection::chosen_service( array( $rate->get_id() ) );

		if ( null === $service || ! $service->needs_pickup_point() || PointSelection::chosen_rate_id() !== $rate->get_id() ) {
			return;
		}

		$type    = (string) $service->point_type;
		$point   = $this->resolver->resolve( PointSelection::get( $type ) );
		$total   = (float) WC()->cart->get_total( 'edit' );
		$payment = PointSelection::chosen_payment_method();
		$problem = null !== $point && self::COD_GATEWAY === $payment ? PointValidator::error( $service, $point, true, $total ) : null;

		Template::render(
			'checkout/pickup-point.php',
			array(
				'point_type'   => $type,
				'point'        => $point,
				'problem'      => $problem,
				'order_total'  => $total,
				'field_name'   => self::FIELD . '[' . $type . ']',
				'button_label' => null !== $point
					? __( 'Change', 'poczta-polska-for-woocommerce' )
					: ( PickupPoint::TYPE_LOCKER === $type ? __( 'Choose a parcel locker', 'poczta-polska-for-woocommerce' ) : __( 'Choose a pickup point', 'poczta-polska-for-woocommerce' ) ),
			)
		);
	}

	/**
	 * Stores the PNI sent with the classic order review refresh.
	 *
	 * WooCommerce verifies the update_order_review nonce before this action.
	 *
	 * @param string $post_data Serialized checkout form.
	 */
	public function store_from_order_review( $post_data ): void {
		parse_str( is_string( $post_data ) ? $post_data : '', $fields );

		if ( isset( $fields[ self::FIELD ] ) && is_array( $fields[ self::FIELD ] ) ) {
			$this->store_choices( $fields[ self::FIELD ] );
		}
	}

	/**
	 * Validates the point when the classic checkout is submitted.
	 *
	 * WooCommerce verifies the checkout nonce before this action.
	 *
	 * @param array<string, mixed> $data   Posted checkout data.
	 * @param \WP_Error            $errors Errors.
	 */
	public function validate_classic( $data, $errors ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WC_Checkout::process_checkout().
		$posted = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? array_map( 'absint', wp_unslash( $_POST[ self::FIELD ] ) ) : array();
		$this->store_choices( $posted );

		$methods = is_array( $data ) && isset( $data['shipping_method'] ) ? (array) $data['shipping_method'] : null;
		$service = PointSelection::chosen_service( $methods );

		if ( null === $service ) {
			return;
		}

		$cod   = is_array( $data ) && self::COD_GATEWAY === ( $data['payment_method'] ?? '' ) ? (float) WC()->cart->get_total( 'edit' ) : null;
		$error = $this->error_for( $service, $cod );

		if ( null !== $error && $errors instanceof \WP_Error ) {
			$errors->add( 'ppwc_pickup_point', $error );
		}
	}

	/**
	 * Stores the point in a new order (classic checkout, after validation).
	 *
	 * @param \WC_Order $order Order.
	 */
	public function save_classic( $order ): void {
		if ( $order instanceof \WC_Order ) {
			$this->store_in_order( $order, PointSelection::chosen_service() );
		}
	}

	/**
	 * Registers the Store API extension: point data in the cart response and an update callback.
	 */
	public function register_store_api(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => self::STORE_API_NAMESPACE,
				'data_callback'   => array( $this, 'store_api_cart_data' ),
				'schema_callback' => array( $this, 'store_api_cart_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => self::STORE_API_NAMESPACE,
				'callback'  => array( $this, 'store_api_update' ),
			)
		);
	}

	/**
	 * Chosen points exposed in the Store API cart response.
	 *
	 * @return array<string, mixed>
	 */
	public function store_api_cart_data(): array {
		$points = array();

		foreach ( array( PickupPoint::TYPE_POINT, PickupPoint::TYPE_LOCKER ) as $type ) {
			$point           = $this->resolver->resolve( PointSelection::get( $type ) );
			$points[ $type ] = null !== $point ? PointsController::prepare( $point ) : null;
		}

		return array( 'points' => $points );
	}

	/**
	 * Schema of the cart extension data.
	 *
	 * @return array<string, mixed>
	 */
	public function store_api_cart_schema(): array {
		return array(
			'points' => array(
				'description' => __( 'Chosen pickup points by type (PUNKT, AUTOMAT).', 'poczta-polska-for-woocommerce' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * Stores a point chosen in the Checkout block (extensionCartUpdate with namespace "ppwc").
	 *
	 * @param array<string, mixed> $data Data: type (PUNKT or AUTOMAT) and pni.
	 */
	public function store_api_update( $data ): void {
		$data = is_array( $data ) ? $data : array();

		$this->store_choices( array( (string) ( $data['type'] ?? '' ) => $data['pni'] ?? 0 ) );
	}

	/**
	 * Validates the point and stores it in the order when the Checkout block places an order.
	 *
	 * @param \WC_Order        $order   Order.
	 * @param \WP_REST_Request $request Request.
	 * @throws RouteException When the point is missing or invalid.
	 */
	public function validate_and_save_store_api( $order, $request ): void {
		// The draft order is updated during checkout too, only placing the order is validated.
		if ( ! $order instanceof \WC_Order || ! $request instanceof \WP_REST_Request || 'POST' !== $request->get_method() ) {
			return;
		}

		$service = PointSelection::chosen_service( $this->order_rate_ids( $order ) );

		if ( null === $service ) {
			return;
		}

		$cod   = self::COD_GATEWAY === $order->get_payment_method() ? (float) $order->get_total( 'edit' ) : null;
		$error = $this->error_for( $service, $cod );

		if ( null !== $error ) {
			throw new RouteException( 'ppwc_pickup_point', esc_html( $error ), 400 );
		}

		$this->store_in_order( $order, $service );
	}

	/**
	 * Returns the validation error for the current choice, null when valid.
	 *
	 * @param Service    $service    Chosen service.
	 * @param float|null $cod_amount COD amount in PLN, null when paying otherwise.
	 */
	private function error_for( Service $service, ?float $cod_amount ): ?string {
		if ( ! $service->needs_pickup_point() ) {
			return PointValidator::error( $service, null, false, $cod_amount );
		}

		$pni = PointSelection::get( (string) $service->point_type );

		return PointValidator::error( $service, $this->resolver->resolve( $pni ), $pni > 0, $cod_amount );
	}

	/**
	 * Stores the chosen point in the order.
	 *
	 * @param \WC_Order    $order   Order.
	 * @param Service|null $service Chosen service.
	 */
	private function store_in_order( \WC_Order $order, ?Service $service ): void {
		if ( null === $service || ! $service->needs_pickup_point() ) {
			return;
		}

		$point = $this->resolver->resolve( PointSelection::get( (string) $service->point_type ) );

		if ( null !== $point ) {
			OrderPoint::store( $order, $point );
		}
	}

	/**
	 * Stores submitted choices: point type => PNI.
	 *
	 * @param array<mixed> $choices Choices.
	 */
	private function store_choices( array $choices ): void {
		foreach ( $choices as $type => $pni ) {
			if ( in_array( $type, array( PickupPoint::TYPE_POINT, PickupPoint::TYPE_LOCKER ), true ) && is_scalar( $pni ) ) {
				PointSelection::set( $type, absint( $pni ) );
			}
		}
	}

	/**
	 * Returns the rate IDs of the order shipping lines ("method_id:instance_id").
	 *
	 * @param \WC_Order $order Order.
	 * @return list<string>
	 */
	private function order_rate_ids( \WC_Order $order ): array {
		$ids = array();

		foreach ( $order->get_shipping_methods() as $item ) {
			$ids[] = $item->get_method_id() . ':' . $item->get_instance_id();
		}

		return $ids;
	}
}
