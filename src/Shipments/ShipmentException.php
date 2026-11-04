<?php
/**
 * Shipment operation error.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipments;

defined( 'ABSPATH' ) || exit;

/**
 * A shipment operation cannot be done. The message is meant for the shop manager.
 */
final class ShipmentException extends \RuntimeException {
}
