<?php
/**
 * Pickup point on the order edit screen.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{order_id: int, point_type: string, label: string, text: string, pni: int} $args PNI 0 when no point is chosen.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ppwc-admin-point" data-order-id="<?php echo esc_attr( (string) $args['order_id'] ); ?>" data-point-type="<?php echo esc_attr( $args['point_type'] ); ?>">
	<p>
		<strong><?php echo esc_html( $args['label'] ); ?>:</strong><br />
		<span class="ppwc-admin-point__text">
			<?php if ( $args['pni'] > 0 ) : ?>
				<?php echo esc_html( $args['text'] ); ?><br />
				<?php
				/* translators: %d: point ID (PNI). */
				echo esc_html( sprintf( __( 'PNI: %d', 'poczta-polska-for-woocommerce' ), $args['pni'] ) );
				?>
			<?php else : ?>
				<?php esc_html_e( 'Not chosen.', 'poczta-polska-for-woocommerce' ); ?>
			<?php endif; ?>
		</span>
	</p>
	<p><button type="button" class="button ppwc-admin-point__change"><?php esc_html_e( 'Change on the map', 'poczta-polska-for-woocommerce' ); ?></button></p>
</div>
