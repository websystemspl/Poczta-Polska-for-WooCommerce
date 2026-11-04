<?php
/**
 * Result of a Poczta Polska bulk action in the orders list.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{type: string, message: string, errors: list<string>} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="notice notice-<?php echo esc_attr( $args['type'] ); ?> is-dismissible">
	<?php if ( '' !== $args['message'] ) : ?>
		<p><?php echo esc_html( $args['message'] ); ?></p>
	<?php endif; ?>
	<?php if ( array() !== $args['errors'] ) : ?>
		<ul>
			<?php foreach ( $args['errors'] as $ppwc_error ) : ?>
				<li><?php echo esc_html( $ppwc_error ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
