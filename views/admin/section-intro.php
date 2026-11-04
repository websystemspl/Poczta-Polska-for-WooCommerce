<?php
/**
 * Introduction of a settings section.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{text: string, link_url: string, link_text: string, problems: list<string>} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
	<?php echo esc_html( $args['text'] ); ?>
	<?php if ( '' !== $args['link_url'] ) : ?>
		<a href="<?php echo esc_url( $args['link_url'] ); ?>"><?php echo esc_html( $args['link_text'] ); ?></a>
	<?php endif; ?>
</p>
<?php if ( array() !== $args['problems'] ) : ?>
	<div class="notice notice-warning inline">
		<ul>
			<?php foreach ( $args['problems'] as $ppwc_problem ) : ?>
				<li><?php echo esc_html( $ppwc_problem ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>
