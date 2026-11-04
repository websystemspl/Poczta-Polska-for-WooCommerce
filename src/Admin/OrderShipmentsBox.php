<?php
/**
 * Shipment box on the order screen.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Admin;

use PocztaPolska\WooCommerce\En\Dictionary;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\Points\OrderPoint;
use PocztaPolska\WooCommerce\Points\PointResolver;
use PocztaPolska\WooCommerce\Points\PointValidator;
use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\Shipments\DraftFactory;
use PocztaPolska\WooCommerce\Shipments\EnvelopeStatus;
use PocztaPolska\WooCommerce\Shipments\Shipment;
use PocztaPolska\WooCommerce\Shipments\ShipmentBuilder;
use PocztaPolska\WooCommerce\Shipments\ShipmentException;
use PocztaPolska\WooCommerce\Shipments\ShipmentRepository;
use PocztaPolska\WooCommerce\Shipments\ShipmentService;
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;
use PocztaPolska\WooCommerce\Support\Template;
use PocztaPolska\WooCommerce\Support\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * "Poczta Polska shipment" box on the order edit screen (HPOS and legacy) and the label download.
 *
 * The order screen is a form itself, so actions go through AJAX instead of nested forms.
 */
final class OrderShipmentsBox {

	public const AJAX_ACTION = 'ppwc_shipment';

	public const LABELS_ACTION = 'ppwc_labels';

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'admin_post_' . self::LABELS_ACTION, array( $this, 'download_labels' ) );
	}

	/**
	 * Adds the box to the order screen.
	 */
	public function add_box(): void {
		add_meta_box( 'ppwc-shipments', __( 'Poczta Polska shipment', 'poczta-polska-for-woocommerce' ), array( $this, 'render' ), self::order_screens(), 'normal', 'high' );
	}

	/**
	 * Returns the URL that downloads the labels of shipments.
	 *
	 * @param int[] $ids Shipment IDs.
	 */
	public static function labels_url( array $ids ): string {
		// Not wp_nonce_url(): it HTML-encodes "&", which breaks redirects. Views escape with esc_url().
		return add_query_arg(
			array(
				'action'   => self::LABELS_ACTION,
				'ids'      => implode( ',', array_map( 'absint', $ids ) ),
				'_wpnonce' => wp_create_nonce( self::LABELS_ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Renders the box.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Post (legacy screen) or order (HPOS screen).
	 */
	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order instanceof \WP_Post ? $post_or_order->ID : 0 );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$shipments = ( new ShipmentRepository() )->for_order( $order->get_id() );
		$active    = array_filter( $shipments, static fn( Shipment $shipment ): bool => Shipment::STATUS_FAILED !== $shipment->status );
		$preview   = $this->preview( $order );
		$form      = self::form_values( $shipments, $preview );
		$point     = $form['pni'] > 0 ? ( new PointResolver() )->resolve( $form['pni'] ) : null;

		Template::view(
			'admin/order-shipments.php',
			array(
				'order_id'    => $order->get_id(),
				'shipments'   => array_map( array( $this, 'shipment_view' ), $shipments ),
				'can_create'  => array() === $active,
				'services'    => array_map( static fn( $service ): string => self::service_label( $service->id ), ServiceRegistry::all() ),
				'point_types' => array_filter( array_map( static fn( $service ): string => (string) $service->point_type, ServiceRegistry::all() ) ),
				'service'     => $form['service'],
				'formats'     => $preview['formats'],
				'format'      => $form['format'],
				'parcels'     => $form['parcels'],
				'weight'      => $form['weight'],
				'mobile'      => $form['mobile'],
				'phone'       => $form['phone'],
				'offices'     => array_map( static fn( array $office ): string => $office['print_name'], Dictionary::posting_offices() ),
				'office_id'   => $form['office_id'],
				'pni'         => null !== $point ? $point->pni : 0,
				'point'       => null !== $point ? $point->name . ', ' . $point->address_line() : '',
				'point_type'  => null !== $point ? $point->type : '',
				'problems'    => $preview['problems'],
				'cod'         => $preview['cod'],
				'cod_amount'  => 'cod' === $order->get_payment_method() ? (float) $order->get_total( 'edit' ) : 0.0,
				'address'     => self::map_address( $order ),
				'environment' => Settings::environment(),
			)
		);
	}

	/**
	 * Loads the script on the order screen.
	 */
	public function enqueue(): void {
		$screen = get_current_screen();

		if ( null === $screen || ! in_array( $screen->id, self::order_screens(), true ) ) {
			return;
		}

		wp_enqueue_style( 'ppwc-admin-shipments', PPWC_PLUGIN_URL . 'assets/css/admin-shipments.css', array(), PPWC_VERSION );
		wp_enqueue_script( 'ppwc-admin-shipments', PPWC_PLUGIN_URL . 'assets/js/admin-shipments.js', array(), PPWC_VERSION, array( 'in_footer' => true ) );
		wp_add_inline_script(
			'ppwc-admin-shipments',
			'window.ppwcShipments = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => self::AJAX_ACTION,
					'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
					'i18n'    => array(
						'working'     => __( 'Please wait…', 'poczta-polska-for-woocommerce' ),
						'confirm'     => __( 'Delete the shipment in Elektroniczny Nadawca?', 'poczta-polska-for-woocommerce' ),
						'error'       => __( 'The operation failed.', 'poczta-polska-for-woocommerce' ),
						/* translators: %d: point ID (PNI). Keep %d, the script replaces it. */
						'pointChosen' => __( 'Point PNI %d chosen, save the shipment to apply it.', 'poczta-polska-for-woocommerce' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Handles create, recreate, delete and check (AJAX).
	 */
	public function handle_ajax(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$order_id  = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$order     = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'poczta-polska-for-woocommerce' ) ), 403 );
		}

		$overrides = array(
			'service'    => isset( $_POST['service'] ) ? sanitize_key( wp_unslash( $_POST['service'] ) ) : '',
			'format'     => isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : '',
			'parcels'    => isset( $_POST['parcels'] ) ? absint( wp_unslash( $_POST['parcels'] ) ) : 1,
			'mobile'     => isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '',
			'phone'      => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'pickup_pni' => isset( $_POST['pickup_pni'] ) ? absint( wp_unslash( $_POST['pickup_pni'] ) ) : 0,
			'office_id'  => isset( $_POST['office_id'] ) ? absint( wp_unslash( $_POST['office_id'] ) ) : 0,
		);
		$weight    = isset( $_POST['weight'] ) ? absint( wp_unslash( $_POST['weight'] ) ) : 0;

		if ( $weight > 0 ) {
			$parcels              = max( 1, (int) $overrides['parcels'] );
			$overrides['weights'] = array_fill( 0, $parcels, max( 1, intdiv( $weight, $parcels ) ) );
		}

		$overrides = array_filter( $overrides, static fn( $value ): bool => '' !== $value && 0 !== $value );
		$service   = new ShipmentService();
		$shipment  = null;
		$point     = null;

		if ( in_array( $operation, array( 'create', 'recreate' ), true ) ) {
			$error = self::contact_error( $overrides ) ?? self::office_error( $overrides );
			$point = null === $error ? $this->chosen_point( $order, $overrides, $error ) : null;

			if ( null !== $error ) {
				wp_send_json_error( array( 'message' => $error ), 400 );
			}
		}

		if ( 'create' !== $operation ) {
			$shipment = ( new ShipmentRepository() )->find( isset( $_POST['shipment_id'] ) ? absint( wp_unslash( $_POST['shipment_id'] ) ) : 0 );

			if ( null === $shipment || $order_id !== $shipment->order_id || Settings::environment() !== $shipment->environment ) {
				wp_send_json_error( array( 'message' => __( 'The shipment was not found.', 'poczta-polska-for-woocommerce' ) ), 404 );
			}
		}

		try {
			switch ( $operation ) {
				case 'create':
					$result = $service->create( array( $order ), array( $order_id => $overrides ) )[ $order_id ] ?? '';
					break;
				case 'recreate':
					$result = null !== $shipment ? $service->recreate( $shipment, $overrides ) : '';
					break;
				case 'delete':
					if ( null !== $shipment ) {
						$service->delete( $shipment );
					}
					$result = null;
					break;
				case 'check':
					$result = null !== $shipment ? $service->reconcile( $shipment ) : '';
					break;
				default:
					wp_send_json_error( array( 'message' => __( 'Unknown operation.', 'poczta-polska-for-woocommerce' ) ), 400 );
			}
		} catch ( ShipmentException $exception ) {
			wp_send_json_error( array( 'message' => html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' ) ) );
		}

		if ( is_string( $result ) ) {
			wp_send_json_error( array( 'message' => $result ) );
		}

		// The parcel goes to the point chosen for the shipment, so the order shows the same point to the customer.
		if ( null !== $point ) {
			OrderPoint::change( $order, $point );
		}

		wp_send_json_success();
	}

	/**
	 * Checks the mobile and the landline entered by the shop manager.
	 *
	 * @param array<string, mixed> $overrides Changes from the shop manager.
	 */
	private static function contact_error( array $overrides ): ?string {
		if ( isset( $overrides['mobile'] ) && null === Validator::mobile( (string) $overrides['mobile'] ) ) {
			return __( 'Enter a Polish mobile number with 9 digits.', 'poczta-polska-for-woocommerce' );
		}

		if ( isset( $overrides['phone'] ) && null === Validator::phone( (string) $overrides['phone'] ) ) {
			return __( 'Enter the phone number with digits only (5 to 19, optionally with +).', 'poczta-polska-for-woocommerce' );
		}

		return null;
	}

	/**
	 * Checks that the chosen posting office belongs to the contract (list from EN).
	 *
	 * @param array<string, mixed> $overrides Changes from the shop manager.
	 */
	private static function office_error( array $overrides ): ?string {
		if ( ! isset( $overrides['office_id'] ) || isset( Dictionary::posting_offices()[ (int) $overrides['office_id'] ] ) ) {
			return null;
		}

		return __( 'Choose a posting office from the list.', 'poczta-polska-for-woocommerce' );
	}

	/**
	 * Resolves and checks the pickup point for a service that needs one: the point chosen in the box,
	 * or the point of the order. Returns null without an error for home delivery.
	 *
	 * @param \WC_Order            $order     Order.
	 * @param array<string, mixed> $overrides Changes from the shop manager.
	 * @param string|null          $error     Set to the error message when the point cannot be used.
	 */
	private function chosen_point( \WC_Order $order, array $overrides, ?string &$error ): ?PickupPoint {
		$method  = DraftFactory::order_method( $order );
		$service = ServiceRegistry::get( (string) ( $overrides['service'] ?? ( null !== $method ? $method->id : '' ) ) );

		if ( null === $service || ! $service->needs_pickup_point() ) {
			return null;
		}

		$pni   = (int) ( $overrides['pickup_pni'] ?? ( OrderPoint::get( $order )['pni'] ?? 0 ) );
		$point = $pni > 0 ? ( new PointResolver() )->resolve( $pni ) : null;
		$cod   = 'cod' === $order->get_payment_method() ? (float) $order->get_total( 'edit' ) : null;
		$error = PointValidator::error( $service, $point, $pni > 0, $cod );

		return null === $error ? $point : null;
	}

	/**
	 * Streams the labels of shipments as one PDF (admin-post handler).
	 */
	public function download_labels(): void {
		check_admin_referer( self::LABELS_ACTION );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'poczta-polska-for-woocommerce' ), 403 );
		}

		$ids       = isset( $_GET['ids'] ) ? array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ) ) ) ) : array();
		$shipments = array_values(
			array_filter(
				( new ShipmentRepository() )->find_many( $ids ),
				static fn( Shipment $shipment ): bool => Settings::environment() === $shipment->environment && current_user_can( 'edit_shop_order', $shipment->order_id )
			)
		);

		try {
			$pdf = ( new ShipmentService() )->labels( $shipments );
		} catch ( ShipmentException $exception ) {
			wp_die( esc_html( html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' ) ), esc_html__( 'Labels', 'poczta-polska-for-woocommerce' ), array( 'back_link' => true ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="etykiety-' . gmdate( 'Ymd-His' ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF file from Elektroniczny Nadawca.
		exit;
	}

	/**
	 * Label of a service for the shop manager.
	 *
	 * @param string $id Service ID.
	 */
	public static function service_label( string $id ): string {
		$labels = array(
			ServiceRegistry::POCZTEX_COURIER => __( 'Pocztex Courier', 'poczta-polska-for-woocommerce' ),
			ServiceRegistry::POCZTEX_POINT   => __( 'Pocztex Pickup Point', 'poczta-polska-for-woocommerce' ),
			ServiceRegistry::POCZTEX_LOCKER  => __( 'Pocztex Parcel Locker', 'poczta-polska-for-woocommerce' ),
			ServiceRegistry::ECOMMERCE       => __( 'eCommerce parcel', 'poczta-polska-for-woocommerce' ),
		);

		return $labels[ $id ] ?? $id;
	}

	/**
	 * Label of a shipment status.
	 *
	 * @param Shipment $shipment Shipment.
	 */
	public static function status_label( Shipment $shipment ): string {
		return match ( $shipment->status ) {
			Shipment::STATUS_PENDING => __( 'Unconfirmed: check in Elektroniczny Nadawca', 'poczta-polska-for-woocommerce' ),
			Shipment::STATUS_CREATED => '' !== $shipment->label_printed ? __( 'In buffer, label printed', 'poczta-polska-for-woocommerce' ) : __( 'In buffer', 'poczta-polska-for-woocommerce' ),
			Shipment::STATUS_SENT    => __( 'Sent to the post office', 'poczta-polska-for-woocommerce' ),
			default                  => __( 'Rejected', 'poczta-polska-for-woocommerce' ),
		};
	}

	/**
	 * Data of a shipment for the view.
	 *
	 * @param Shipment $shipment Shipment.
	 * @return array<string, mixed>
	 */
	private function shipment_view( Shipment $shipment ): array {
		$envelope = '';

		if ( $shipment->envelope_id > 0 ) {
			$status   = EnvelopeStatus::all( $shipment->environment )[ $shipment->envelope_id ]['status'] ?? '';
			$envelope = sprintf(
				/* translators: 1: envelope ID, 2: envelope status. */
				__( 'Envelope %1$d: %2$s', 'poczta-polska-for-woocommerce' ),
				$shipment->envelope_id,
				EnvelopeStatus::label( $status )
			);
		}

		return array(
			'id'          => $shipment->id,
			'envelope'    => $envelope,
			'service'     => self::service_label( $shipment->service ),
			'tracking'    => $shipment->tracking_numbers(),
			'sub_unknown' => $shipment->has_unknown_sub_tracking(),
			'status'      => self::status_label( $shipment ),
			'buffer_id'   => $shipment->buffer_id,
			'environment' => $shipment->environment,
			'error'       => $shipment->last_error,
			'labels_url'  => $shipment->is_in_buffer() ? self::labels_url( array( $shipment->id ) ) : '',
			'editable'    => $shipment->is_editable(),
			'deletable'   => $shipment->is_deletable(),
			'pending'     => Shipment::STATUS_PENDING === $shipment->status,
		);
	}

	/**
	 * Values for the create form and problems the shop manager should fix first.
	 *
	 * @param \WC_Order $order Order.
	 * @return array{service: string, formats: list<string>, format: string, weight: int, mobile: string, phone: string, pni: int, problems: list<string>, cod: string}
	 */
	private function preview( \WC_Order $order ): array {
		try {
			$draft = DraftFactory::from_order( $order );
		} catch ( ShipmentException $exception ) {
			return array(
				'service'  => ServiceRegistry::POCZTEX_COURIER,
				'formats'  => array( 'S', 'M', 'L', 'XL', '2XL' ),
				'format'   => 'S',
				'weight'   => (int) Settings::get( 'pocztex_weight' ),
				'mobile'   => (string) Validator::mobile( DraftFactory::order_phone( $order ) ),
				'phone'    => '',
				'pni'      => (int) ( OrderPoint::get( $order )['pni'] ?? 0 ),
				'problems' => array( html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' ) ),
				'cod'      => '',
			);
		}

		return array(
			'service'  => $draft->service->id,
			'formats'  => array_values( $draft->service->formats ),
			'format'   => $draft->format,
			'weight'   => $draft->total_weight(),
			'mobile'   => (string) ( $draft->recipient['mobile'] ?? '' ),
			'phone'    => (string) ( $draft->recipient['phone'] ?? '' ),
			'pni'      => $draft->pickup_pni > 0 ? $draft->pickup_pni : (int) ( OrderPoint::get( $order )['pni'] ?? 0 ),
			'problems' => ShipmentBuilder::problems( $draft ),
			'cod'      => $draft->cod_amount > 0 ? html_entity_decode( wp_strip_all_tags( wc_price( $draft->cod_amount / 100 ) ), ENT_QUOTES, 'UTF-8' ) : '',
		);
	}

	/**
	 * Delivery address that centres the map: street, postcode and city.
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function map_address( \WC_Order $order ): string {
		$type = $order->has_shipping_address() ? 'shipping' : 'billing';

		return trim( $order->{"get_{$type}_address_1"}() . ', ' . $order->{"get_{$type}_postcode"}() . ' ' . $order->{"get_{$type}_city"}(), ' ,' );
	}

	/**
	 * Values of the form: from the newest shipment that can still be changed, so saving keeps the
	 * earlier changes; otherwise from the order.
	 *
	 * @param Shipment[]                                                                                   $shipments Shipments of the order, newest first.
	 * @param array{service: string, format: string, weight: int, mobile: string, phone: string, pni: int} $preview   Values from the order.
	 * @return array{service: string, format: string, parcels: int, weight: int, mobile: string, phone: string, pni: int, office_id: int}
	 */
	private static function form_values( array $shipments, array $preview ): array {
		$values = array(
			'service'   => $preview['service'],
			'format'    => $preview['format'],
			'parcels'   => 1,
			'weight'    => $preview['weight'],
			'mobile'    => $preview['mobile'],
			'phone'     => $preview['phone'],
			'pni'       => $preview['pni'],
			'office_id' => (int) Settings::get_for_env( 'posting_office_id' ),
		);

		foreach ( $shipments as $shipment ) {
			if ( ! $shipment->is_editable() && Shipment::STATUS_FAILED !== $shipment->status ) {
				continue;
			}

			$draft   = isset( $shipment->data['draft'] ) && is_array( $shipment->data['draft'] ) ? $shipment->data['draft'] : array();
			$weights = isset( $draft['weights'] ) && is_array( $draft['weights'] ) ? array_map( 'intval', $draft['weights'] ) : array();
			$contact = isset( $draft['contact'] ) && is_array( $draft['contact'] ) ? $draft['contact'] : array();

			return array(
				'service'   => (string) ( $draft['service'] ?? $values['service'] ),
				'format'    => (string) ( $draft['format'] ?? $values['format'] ),
				'parcels'   => max( 1, count( $weights ) ),
				'weight'    => array() !== $weights ? (int) array_sum( $weights ) : $values['weight'],
				'mobile'    => (string) ( $contact['mobile'] ?? $values['mobile'] ),
				'phone'     => (string) ( $contact['phone'] ?? $values['phone'] ),
				'pni'       => (int) ( $draft['pickup_pni'] ?? 0 ) > 0 ? (int) $draft['pickup_pni'] : $values['pni'],
				'office_id' => $shipment->office_id > 0 ? $shipment->office_id : $values['office_id'],
			);
		}

		return $values;
	}

	/**
	 * Order edit screens: HPOS and legacy posts.
	 *
	 * @return list<string>
	 */
	private static function order_screens(): array {
		return array( 'woocommerce_page_wc-orders', 'shop_order' );
	}
}
