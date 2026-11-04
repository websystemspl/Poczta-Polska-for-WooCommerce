<?php
/**
 * Pickup point in HTML emails, below the addresses.
 *
 * Override in yourtheme/poczta-polska-for-woocommerce/emails/pickup-point.php.
 *
 * @package PocztaPolska\WooCommerce
 * @version 1.0.0
 *
 * @var array{pni: int, name: string, address: string, type: string} $point         Stored point.
 * @var string                                                        $label         Heading.
 * @var bool                                                          $sent_to_admin Whether the email goes to the shop manager.
 */

defined( 'ABSPATH' ) || exit;
?>
<div style="margin-bottom: 40px;">
	<h2><?php echo esc_html( $label ); ?></h2>
	<p>
		<?php echo esc_html( $point['name'] ); ?><br />
		<?php echo esc_html( $point['address'] ); ?>
		<?php if ( $sent_to_admin ) : ?>
			<br />
			<?php
			/* translators: %d: point ID (PNI). */
			echo esc_html( sprintf( __( 'Point ID (PNI): %d', 'poczta-polska-for-woocommerce' ), $point['pni'] ) );
			?>
		<?php endif; ?>
	</p>
</div>
