<?php
/**
 * "Test connection" button.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Admin;

use PocztaPolska\WooCommerce\En\ClientFactory;
use PocztaPolska\WooCommerce\En\Exception\EnException;
use PocztaPolska\WooCommerce\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the saved EN credentials with the hello call (AJAX).
 */
final class ConnectionTest {

	public const ACTION = 'ppwc_test_connection';

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Loads the script and the styles on the settings screen only.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'toplevel_page_' . SettingsPage::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'ppwc-admin-settings', PPWC_PLUGIN_URL . 'assets/css/admin-settings.css', array(), PPWC_VERSION );
		wp_enqueue_script( 'ppwc-admin-settings', PPWC_PLUGIN_URL . 'assets/js/admin-settings.js', array(), PPWC_VERSION, array( 'in_footer' => true ) );
		wp_add_inline_script(
			'ppwc-admin-settings',
			'window.ppwcSettings = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => self::ACTION,
					'nonce'   => wp_create_nonce( self::ACTION ),
					'i18n'    => array(
						'testing' => __( 'Connecting to Elektroniczny Nadawca…', 'poczta-polska-for-woocommerce' ),
						'error'   => __( 'The test failed. Try again or check the WooCommerce logs (source: poczta-polska).', 'poczta-polska-for-woocommerce' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Handles the AJAX request.
	 */
	public function handle(): void {
		check_ajax_referer( self::ACTION, 'nonce' );

		if ( ! current_user_can( SettingsPage::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'poczta-polska-for-woocommerce' ) ), 403 );
		}

		$environment = isset( $_POST['environment'] ) ? sanitize_key( wp_unslash( $_POST['environment'] ) ) : '';

		if ( ! isset( Settings::EN_ENDPOINTS[ $environment ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown environment.', 'poczta-polska-for-woocommerce' ) ), 400 );
		}

		$name = Settings::ENV_PRODUCTION === $environment ? __( 'production', 'poczta-polska-for-woocommerce' ) : __( 'test', 'poczta-polska-for-woocommerce' );

		try {
			$client = ClientFactory::create( $environment );
			$client->hello();
			$expiry = $client->password_expiry_date();
		} catch ( EnException $exception ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: environment name, 2: error message. */
						__( 'Connection to Elektroniczny Nadawca (%1$s) failed: %2$s', 'poczta-polska-for-woocommerce' ),
						$name,
						html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' )
					),
				)
			);
		}

		PasswordExpiry::store( $environment, $expiry );
		$days = PasswordExpiry::days_left( $environment ) ?? 0;

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: environment name, 2: password expiry date, 3: number of days. */
					_n(
						'Connected to Elektroniczny Nadawca (%1$s). The password is valid until %2$s (%3$d day left).',
						'Connected to Elektroniczny Nadawca (%1$s). The password is valid until %2$s (%3$d days left).',
						max( 0, $days ),
						'poczta-polska-for-woocommerce'
					),
					$name,
					wp_date( get_option( 'date_format' ), $expiry->getTimestamp() ),
					max( 0, $days )
				),
			)
		);
	}
}
