<?php
/**
 * Pickup point picker under the chosen Poczta Polska method (classic checkout).
 *
 * Override in yourtheme/poczta-polska-for-woocommerce/checkout/pickup-point.php.
 * Keep the class names, data attributes and the hidden field: the script relies on them.
 *
 * @package PocztaPolska\WooCommerce
 * @version 1.1.0
 *
 * @var string                                              $point_type   PUNKT or AUTOMAT.
 * @var \PocztaPolska\WooCommerce\En\Dto\PickupPoint|null $point        Chosen point.
 * @var string|null                                         $problem      Problem with the chosen point, e.g. no cash on delivery.
 * @var float                                               $order_total  Order total in PLN.
 * @var string                                              $field_name   Name of the hidden field.
 * @var string                                              $button_label Button label.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ppwc-pickup" data-point-type="<?php echo esc_attr( $point_type ); ?>" data-order-total="<?php echo esc_attr( wc_format_decimal( $order_total, 2 ) ); ?>">
	<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( null !== $point ? (string) $point->pni : '' ); ?>" />
	<div class="ppwc-pickup__summary" aria-live="polite">
		<?php
		\PocztaPolska\WooCommerce\Support\Template::render(
			'checkout/pickup-point-summary.php',
			array(
				'point'      => $point,
				'point_type' => $point_type,
			)
		);
		?>
	</div>
	<?php if ( null !== $problem ) : ?>
		<p class="ppwc-pickup__problem" role="alert"><?php echo esc_html( $problem ); ?></p>
	<?php endif; ?>
	<button type="button" class="button ppwc-pickup__choose"><?php echo esc_html( $button_label ); ?></button>
</div>
