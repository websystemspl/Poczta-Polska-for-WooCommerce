<?php
/**
 * Description of the chosen pickup point (classic checkout).
 *
 * Override in yourtheme/poczta-polska-for-woocommerce/checkout/pickup-point-summary.php.
 *
 * @package PocztaPolska\WooCommerce
 * @version 1.0.0
 *
 * @var \PocztaPolska\WooCommerce\En\Dto\PickupPoint|null $point      Chosen point.
 * @var string                                              $point_type PUNKT or AUTOMAT.
 */

defined( 'ABSPATH' ) || exit;

use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
?>
<?php if ( null === $point ) : ?>
	<span class="ppwc-pickup__empty">
		<?php echo esc_html( PickupPoint::TYPE_LOCKER === $point_type ? __( 'No parcel locker chosen.', 'poczta-polska-for-woocommerce' ) : __( 'No pickup point chosen.', 'poczta-polska-for-woocommerce' ) ); ?>
	</span>
<?php else : ?>
	<strong class="ppwc-pickup__name"><?php echo esc_html( $point->name ); ?></strong><br />
	<span class="ppwc-pickup__address"><?php echo esc_html( $point->address_line() ); ?></span>
	<?php if ( $point->supports_cod() ) : ?>
		<span class="ppwc-pickup__cod">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: maximum amount, e.g. 4 000,00 zł. */
					__( 'Cash on delivery available (up to %s).', 'poczta-polska-for-woocommerce' ),
					html_entity_decode( wp_strip_all_tags( wc_price( $point->cod_limit ) ), ENT_QUOTES, 'UTF-8' )
				)
			);
			?>
		</span>
	<?php endif; ?>
<?php endif; ?>
