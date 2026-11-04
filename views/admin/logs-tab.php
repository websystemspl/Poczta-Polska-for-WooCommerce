<?php
/**
 * Pickup points copy, log link and audit log below the "Logs and data" form.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{queued: bool, points_text: string, points_error: string, refresh_url: string, log_url: string, entries: list<array{date: string, user: string, operation: string, details: string}>} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Pickup points copy', 'poczta-polska-for-woocommerce' ); ?></h2>
<?php if ( $args['queued'] ) : ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'The refresh was queued. It runs in the background within a few minutes.', 'poczta-polska-for-woocommerce' ); ?></p></div>
<?php endif; ?>
<p><?php echo esc_html( $args['points_text'] ); ?></p>
<?php if ( '' !== $args['points_error'] ) : ?>
	<div class="notice notice-warning inline"><p><?php echo esc_html( $args['points_error'] ); ?></p></div>
<?php endif; ?>
<p><a class="button" href="<?php echo esc_url( $args['refresh_url'] ); ?>"><?php esc_html_e( 'Refresh now', 'poczta-polska-for-woocommerce' ); ?></a></p>

<h2><?php esc_html_e( 'Elektroniczny Nadawca log', 'poczta-polska-for-woocommerce' ); ?></h2>
<p>
	<?php esc_html_e( 'Calls to Elektroniczny Nadawca and errors are saved in the WooCommerce log. Passwords, bank account numbers and personal data are masked.', 'poczta-polska-for-woocommerce' ); ?>
	<a href="<?php echo esc_url( $args['log_url'] ); ?>"><?php esc_html_e( 'Open the log', 'poczta-polska-for-woocommerce' ); ?></a>
</p>

<h2><?php esc_html_e( 'Audit log', 'poczta-polska-for-woocommerce' ); ?></h2>
<p><?php esc_html_e( 'The latest 50 administrative operations.', 'poczta-polska-for-woocommerce' ); ?></p>
<table class="widefat striped">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Date', 'poczta-polska-for-woocommerce' ); ?></th>
			<th scope="col"><?php esc_html_e( 'User', 'poczta-polska-for-woocommerce' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Operation', 'poczta-polska-for-woocommerce' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Details', 'poczta-polska-for-woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( array() === $args['entries'] ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No operations yet.', 'poczta-polska-for-woocommerce' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $args['entries'] as $ppwc_entry ) : ?>
			<tr>
				<td><?php echo esc_html( $ppwc_entry['date'] ); ?></td>
				<td><?php echo esc_html( $ppwc_entry['user'] ); ?></td>
				<td><?php echo esc_html( $ppwc_entry['operation'] ); ?></td>
				<td><?php echo esc_html( $ppwc_entry['details'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
