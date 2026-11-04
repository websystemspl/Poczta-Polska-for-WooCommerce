<?php
/**
 * Pickup point of an order: thank you page and My account.
 *
 * Override in yourtheme/poczta-polska-for-woocommerce/order/pickup-point.php.
 *
 * @package PocztaPolska\WooCommerce
 * @version 1.0.0
 *
 * @var array{pni: int, name: string, address: string, type: string} $point Stored point.
 * @var string                                                        $label Heading: "Pickup point" or "Pocztex parcel locker".
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="woocommerce-customer-details ppwc-order-point">
	<h2 class="woocommerce-column__title"><?php echo esc_html( $label ); ?></h2>
	<address>
		<?php echo esc_html( $point['name'] ); ?><br />
		<?php echo esc_html( $point['address'] ); ?>
	</address>
</section>
