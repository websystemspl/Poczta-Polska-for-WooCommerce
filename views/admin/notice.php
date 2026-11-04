<?php
/**
 * Admin notice.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{type: string, message: string, links: list<array{url: string, text: string}>} $args Type: error, warning, success or info.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="notice notice-<?php echo esc_attr( $args['type'] ); ?>">
	<p><?php echo esc_html( $args['message'] ); ?></p>
	<?php if ( array() !== $args['links'] ) : ?>
		<p>
			<?php foreach ( $args['links'] as $ppwc_index => $ppwc_link ) : ?>
				<?php echo 0 < $ppwc_index ? ', ' : ''; ?><a href="<?php echo esc_url( $ppwc_link['url'] ); ?>"><?php echo esc_html( $ppwc_link['text'] ); ?></a>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>
</div>
