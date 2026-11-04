<?php
/**
 * Connection tab: one card per Elektroniczny Nadawca environment with the environment switch,
 * the WebAPI credentials and the "Test connection" button.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{
 *     name: string,
 *     environments: list<array{
 *         key: string,
 *         label: string,
 *         description: string,
 *         active: bool,
 *         login: array{type: string, id: string, name: string, value: string, stored: bool, clear_name: string},
 *         password: array{type: string, id: string, name: string, value: string, stored: bool, clear_name: string},
 *         expiry: string,
 *         button: string
 *     }>
 * } $args
 */

use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;
?>
<p><?php esc_html_e( 'Enter the WebAPI login and password for Elektroniczny Nadawca. The test environment (INT) lets you check the plugin without sending real shipments.', 'poczta-polska-for-woocommerce' ); ?></p>

<fieldset class="ppwc-environments">
	<legend class="screen-reader-text"><?php esc_html_e( 'Environment', 'poczta-polska-for-woocommerce' ); ?></legend>
	<?php foreach ( $args['environments'] as $ppwc_env ) : ?>
		<div class="ppwc-environment<?php echo $ppwc_env['active'] ? ' is-active' : ''; ?>">
			<div class="ppwc-environment__header">
				<label class="ppwc-environment__switch">
					<input type="radio" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $ppwc_env['key'] ); ?>" <?php checked( $ppwc_env['active'] ); ?> />
					<span class="ppwc-environment__title"><?php echo esc_html( $ppwc_env['label'] ); ?></span>
				</label>
				<span class="ppwc-environment__badge"><?php esc_html_e( 'Active', 'poczta-polska-for-woocommerce' ); ?></span>
			</div>
			<p class="description"><?php echo esc_html( $ppwc_env['description'] ); ?></p>

			<p class="ppwc-environment__field">
				<label for="<?php echo esc_attr( $ppwc_env['login']['id'] ); ?>"><?php esc_html_e( 'Login', 'poczta-polska-for-woocommerce' ); ?></label>
				<?php Template::view( 'admin/credential-field.php', $ppwc_env['login'] ); ?>
			</p>
			<div class="ppwc-environment__field">
				<label for="<?php echo esc_attr( $ppwc_env['password']['id'] ); ?>"><?php esc_html_e( 'Password', 'poczta-polska-for-woocommerce' ); ?></label>
				<?php Template::view( 'admin/credential-field.php', $ppwc_env['password'] ); ?>
			</div>
			<?php if ( '' !== $ppwc_env['expiry'] ) : ?>
				<p class="description">
					<?php
					/* translators: %s: date. */
					echo esc_html( sprintf( __( 'The password is valid until %s.', 'poczta-polska-for-woocommerce' ), $ppwc_env['expiry'] ) );
					?>
				</p>
			<?php endif; ?>

			<p class="ppwc-environment__test">
				<button type="button" class="button ppwc-test-connection" data-environment="<?php echo esc_attr( $ppwc_env['key'] ); ?>"><?php echo esc_html( $ppwc_env['button'] ); ?></button>
			</p>
			<div class="ppwc-test-connection-result" role="status" aria-live="polite"></div>
		</div>
	<?php endforeach; ?>
</fieldset>
<p class="description"><?php esc_html_e( 'Shipments are created in the selected environment. The connection test uses the saved login and password, so save your changes first.', 'poczta-polska-for-woocommerce' ); ?></p>
