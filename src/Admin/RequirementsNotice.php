<?php
/**
 * Admin notices about the server environment.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Admin;

use PocztaPolska\WooCommerce\Requirements;
use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Warns store managers about a missing SOAP extension or an unpatched PHP version.
 */
final class RequirementsNotice {

	/**
	 * Constructor.
	 *
	 * @param Requirements $requirements Environment checks.
	 */
	public function __construct( private Requirements $requirements ) {
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Prints the notices for users who manage the store.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! $this->requirements->has_soap() ) {
			$this->print_notice(
				'error',
				__( 'Poczta Polska for WooCommerce needs the PHP SOAP extension to connect to Elektroniczny Nadawca. Ask your hosting provider to enable it.', 'poczta-polska-for-woocommerce' )
			);
		}

		if ( ! $this->requirements->is_php_patched() ) {
			$patched = $this->requirements->patched_php_version();

			$this->print_notice(
				'warning',
				null === $patched
					? sprintf(
						/* translators: %s: current PHP version. */
						__( 'Poczta Polska for WooCommerce: PHP %s does not receive security fixes anymore. Update PHP to a supported, patched version.', 'poczta-polska-for-woocommerce' ),
						$this->requirements->php_version()
					)
					: sprintf(
						/* translators: 1: current PHP version, 2: first patched PHP version. */
						__( 'Poczta Polska for WooCommerce: PHP %1$s may be missing critical security fixes (CVE-2026-109618, CVE-2026-109619). Update PHP to %2$s or newer, or confirm with your hosting provider that the fixes are backported.', 'poczta-polska-for-woocommerce' ),
						$this->requirements->php_version(),
						$patched
					)
			);
		}
	}

	/**
	 * Prints a single notice.
	 *
	 * @param string $type    Notice type: error, warning, info or success.
	 * @param string $message Plain text message.
	 */
	private function print_notice( string $type, string $message ): void {
		Template::view(
			'admin/notice.php',
			array(
				'type'    => $type,
				'message' => $message,
				'links'   => array(),
			)
		);
	}
}
