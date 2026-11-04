<?php
/**
 * "Poczta Polska" column in the orders list: tracking numbers and shipment status.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{rows: list<array{tracking: list<string>, status: string}>, failed: bool} $args
 */

defined( 'ABSPATH' ) || exit;

if ( array() === $args['rows'] ) {
	if ( $args['failed'] ) {
		echo '<mark class="order-status status-failed"><span>' . esc_html__( 'Error', 'poczta-polska-for-woocommerce' ) . '</span></mark>';
	} else {
		echo '&ndash;';
	}

	return;
}

foreach ( $args['rows'] as $ppwc_row ) :
	?>
	<div class="ppwc-tracking">
		<?php foreach ( $ppwc_row['tracking'] as $ppwc_number ) : ?>
			<code><?php echo esc_html( $ppwc_number ); ?></code><br>
		<?php endforeach; ?>
		<small><?php echo esc_html( $ppwc_row['status'] ); ?></small>
	</div>
	<?php
endforeach;
