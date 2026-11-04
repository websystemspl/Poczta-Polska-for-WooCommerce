<?php
/**
 * Pickup point in order screens and emails.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Points;

use PocztaPolska\WooCommerce\Admin\SettingsPage;
use PocztaPolska\WooCommerce\Checkout\Assets;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the pickup point to customers (thank you page, My account, emails) and to
 * the shop manager, who can change it on the map (OPZ II.1.3.d).
 */
final class OrderPointDisplay {

	public const AJAX_CHANGE = 'ppwc_change_order_point';

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
		add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'render_customer' ) );
		add_filter( 'render_block_woocommerce/order-confirmation-shipping-address', array( $this, 'append_to_confirmation_block' ) );
		add_action( 'woocommerce_email_customer_details', array( $this, 'render_email' ), 25, 3 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'render_admin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'wp_ajax_' . self::AJAX_CHANGE, array( $this, 'change' ) );
	}

	/**
	 * Thank you page and My account order view.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function render_customer( $order ): void {
		$point = $order instanceof \WC_Order ? OrderPoint::get( $order ) : null;

		if ( null === $point ) {
			return;
		}

		Template::render(
			'order/pickup-point.php',
			array(
				'point' => $point,
				'label' => OrderPoint::type_label( $point['type'] ),
			)
		);
	}

	/**
	 * Adds the point to the shipping address block of the block-based order confirmation page.
	 *
	 * The block renders nothing when the visitor may not see the order, so the point is
	 * added only to rendered content.
	 *
	 * @param string $block_content Rendered block.
	 */
	public function append_to_confirmation_block( $block_content ): string {
		$block_content = (string) $block_content;
		$order         = wc_get_order( absint( get_query_var( 'order-received' ) ) );

		if ( '' === trim( $block_content ) || ! $order instanceof \WC_Order ) {
			return $block_content;
		}

		$point = OrderPoint::get( $order );

		if ( null === $point ) {
			return $block_content;
		}

		return $block_content . Template::capture(
			'order/pickup-point.php',
			array(
				'point' => $point,
				'label' => OrderPoint::type_label( $point['type'] ),
			)
		);
	}

	/**
	 * Customer and admin emails, below the addresses.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Whether the email goes to the shop manager.
	 * @param bool      $plain_text    Whether the email is plain text.
	 */
	public function render_email( $order, $sent_to_admin = false, $plain_text = false ): void {
		$point = $order instanceof \WC_Order ? OrderPoint::get( $order ) : null;

		if ( null === $point ) {
			return;
		}

		Template::render(
			$plain_text ? 'emails/plain/pickup-point.php' : 'emails/pickup-point.php',
			array(
				'point'         => $point,
				'label'         => OrderPoint::type_label( $point['type'] ),
				'sent_to_admin' => (bool) $sent_to_admin,
			)
		);
	}

	/**
	 * Order edit screen (HPOS and legacy): point details and a "Change" button.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function render_admin( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$point = OrderPoint::get( $order );
		$type  = null !== $point ? $point['type'] : self::order_point_type( $order );

		if ( null === $type ) {
			return;
		}

		Template::view(
			'admin/order-pickup-point.php',
			array(
				'order_id'   => $order->get_id(),
				'point_type' => $type,
				'label'      => OrderPoint::type_label( $type ),
				'text'       => null !== $point ? $point['name'] . ', ' . $point['address'] : '',
				'pni'        => null !== $point ? $point['pni'] : 0,
			)
		);
	}

	/**
	 * Loads the map on the order edit screen and on the plugin settings screen
	 * (parcel locker for posting).
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		$screen   = get_current_screen();
		$order    = null !== $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );
		$settings = 'toplevel_page_' . SettingsPage::MENU_SLUG === $hook_suffix;

		if ( ! $order && ! $settings ) {
			return;
		}

		Assets::register_assets();
		wp_enqueue_style( Assets::HANDLE_STYLE );
		wp_enqueue_script( 'ppwc-admin-points', PPWC_PLUGIN_URL . 'assets/js/admin-points.js', array( Assets::HANDLE_MAP ), PPWC_VERSION, array( 'in_footer' => true ) );
		wp_add_inline_script(
			'ppwc-admin-points',
			'window.ppwcAdminPoints = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => self::AJAX_CHANGE,
					'nonce'   => wp_create_nonce( self::AJAX_CHANGE ),
					'i18n'    => array(
						'saving' => __( 'Saving…', 'poczta-polska-for-woocommerce' ),
						'error'  => __( 'The point could not be saved.', 'poczta-polska-for-woocommerce' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Changes the pickup point of an order (AJAX, shop managers only).
	 */
	public function change(): void {
		check_ajax_referer( self::AJAX_CHANGE, 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$pni      = isset( $_POST['pni'] ) ? absint( wp_unslash( $_POST['pni'] ) ) : 0;
		$order    = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'poczta-polska-for-woocommerce' ) ), 403 );
		}

		$current = OrderPoint::get( $order );
		$type    = null !== $current ? $current['type'] : self::order_point_type( $order );
		$point   = $this->resolver->resolve( $pni );

		if ( null === $type || null === $point || $point->type !== $type || ! $point->is_pickup_point() ) {
			wp_send_json_error( array( 'message' => __( 'This point cannot be used for the shipping method of the order.', 'poczta-polska-for-woocommerce' ) ), 400 );
		}

		OrderPoint::change( $order, $point );

		wp_send_json_success(
			array(
				'text' => $point->name . ', ' . $point->address_line(),
				'pni'  => $point->pni,
			)
		);
	}

	/**
	 * Returns the point type required by the order shipping method, null for home delivery.
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function order_point_type( \WC_Order $order ): ?string {
		foreach ( $order->get_shipping_methods() as $item ) {
			$service = PointSelection::chosen_service( array( $item->get_method_id() ) );

			if ( null !== $service && null !== $service->point_type ) {
				return $service->point_type;
			}
		}

		return null;
	}
}
