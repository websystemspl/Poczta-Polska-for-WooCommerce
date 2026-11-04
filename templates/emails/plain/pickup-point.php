<?php
/**
 * Pickup point in plain text emails.
 *
 * Override in yourtheme/poczta-polska-for-woocommerce/emails/plain/pickup-point.php.
 *
 * @package PocztaPolska\WooCommerce
 * @version 1.0.0
 *
 * @var array{pni: int, name: string, address: string, type: string} $point         Stored point.
 * @var string                                                        $label         Heading.
 * @var bool                                                          $sent_to_admin Whether the email goes to the shop manager.
 */

defined( 'ABSPATH' ) || exit;

echo "\n" . esc_html( wc_strtoupper( $label ) ) . "\n";
echo esc_html( $point['name'] ) . "\n";
echo esc_html( $point['address'] ) . "\n";

if ( $sent_to_admin ) {
	/* translators: %d: point ID (PNI). */
	echo esc_html( sprintf( __( 'Point ID (PNI): %d', 'poczta-polska-for-woocommerce' ), $point['pni'] ) ) . "\n";
}
