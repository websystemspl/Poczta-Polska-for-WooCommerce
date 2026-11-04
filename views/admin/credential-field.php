<?php
/**
 * Login or password field. A stored password is never sent back to the browser.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{type: string, id: string, name: string, value: string, stored: bool, clear_name: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( 'password' === $args['type'] ) : ?>
	<input type="password" class="regular-text" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $args['stored'] ? __( 'Password saved', 'poczta-polska-for-woocommerce' ) : '' ); ?>" />
	<?php if ( $args['stored'] ) : ?>
		<p class="description"><?php esc_html_e( 'Leave empty to keep the saved password.', 'poczta-polska-for-woocommerce' ); ?></p>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $args['clear_name'] ); ?>" value="1" />
			<?php esc_html_e( 'Remove the saved password', 'poczta-polska-for-woocommerce' ); ?>
		</label>
	<?php endif; ?>
<?php else : ?>
	<input type="text" class="regular-text" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $args['value'] ); ?>" autocomplete="off" />
<?php endif; ?>
