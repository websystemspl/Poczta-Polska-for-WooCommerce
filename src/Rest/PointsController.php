<?php
/**
 * REST endpoints for pickup points.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Rest;

use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\Points\PointResolver;
use PocztaPolska\WooCommerce\Points\PointsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only access to the public list of Poczta Polska points:
 * GET /ppwc/v1/points?search=&type=&cod= and GET /ppwc/v1/points/{pni}.
 *
 * The data is the same that Poczta Polska publishes on its own map,
 * so the endpoints are public. They never return order or customer data.
 */
final class PointsController {

	public const NAMESPACE = 'ppwc/v1';

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/points',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 2,
						'maxLength'         => 60,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'type'   => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( PickupPoint::TYPE_POINT, PickupPoint::TYPE_LOCKER ),
					),
					'cod'    => array(
						'type'    => 'number',
						'default' => 0,
						'minimum' => 0,
						'maximum' => 20000,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/points/(?P<pni>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'pni' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);
	}

	/**
	 * Searches points by postcode, city or street.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	public function search( \WP_REST_Request $request ): \WP_REST_Response {
		$points = ( new PointsRepository() )->search(
			(string) $request->get_param( 'search' ),
			(string) $request->get_param( 'type' ),
			(float) $request->get_param( 'cod' )
		);

		return new \WP_REST_Response( array_map( array( self::class, 'prepare' ), $points ) );
	}

	/**
	 * Returns one point.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get( \WP_REST_Request $request ) {
		$point = ( new PointResolver() )->resolve( (int) $request->get_param( 'pni' ) );

		if ( null === $point ) {
			return new \WP_Error( 'ppwc_point_not_found', __( 'Pickup point not found.', 'poczta-polska-for-woocommerce' ), array( 'status' => 404 ) );
		}

		return new \WP_REST_Response( self::prepare( $point ) );
	}

	/**
	 * Converts a point to its public representation.
	 *
	 * @param PickupPoint $point Point.
	 * @return array<string, mixed>
	 */
	public static function prepare( PickupPoint $point ): array {
		return array(
			'pni'       => $point->pni,
			'name'      => $point->name,
			'type'      => $point->type,
			'network'   => $point->network,
			'address'   => $point->address_line(),
			'cod_limit' => $point->cod_limit,
			'pickup'    => $point->is_pickup_point(),
		);
	}
}
