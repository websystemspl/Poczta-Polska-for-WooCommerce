/**
 * Load test 1: customers placing orders with Poczta Polska methods (OPZ II.7.4.e).
 *
 * Each virtual user, over and over: new cart, product, address in Poland, Pocztex PUNKT with a
 * pickup point found by the fallback search (two in three orders) or Pocztex Kurier, order paid
 * by bank transfer. The same requests the Checkout Block sends. The Poczta Polska map is a
 * browser-only widget, so the point comes from the plugin's own REST search instead.
 *
 *   k6 run tests/k6/checkout.js
 *
 * No request goes to Elektroniczny Nadawca during checkout, so this scenario is safe to run
 * on any shop with the plugin configured (see tests/k6/README.md).
 */
import { sleep } from 'k6';
import http from 'k6/http';
import { Counter } from 'k6/metrics';
import { config } from './lib/config.js';
import { StoreSession, placeOrder, searchPoints } from './lib/store-api.js';

const orders = new Counter( 'ppwc_orders_placed' );

export const options = {
	insecureSkipTLSVerify: config.insecure,
	scenarios: {
		customers: {
			executor: 'ramping-vus',
			startVUs: 1,
			stages: [
				{ duration: '15s', target: config.vus },
				{ duration: config.duration, target: config.vus },
				{ duration: '10s', target: 0 },
			],
			gracefulRampDown: '30s',
		},
	},
	thresholds: {
		http_req_failed: [ 'rate<0.01' ],
		checks: [ 'rate>0.99' ],
		'http_req_duration{name:cart}': [ 'p(95)<1500' ],
		'http_req_duration{name:cart_update_customer}': [ 'p(95)<2000' ],
		'http_req_duration{name:points_search}': [ 'p(95)<800' ],
		'http_req_duration{name:cart_set_point}': [ 'p(95)<2000' ],
		'http_req_duration{name:checkout}': [ 'p(95)<3000' ],
	},
};

export function setup() {
	const points = searchPoints( config.baseUrl, config.pointSearch, 'PUNKT' );

	if ( 0 === points.length ) {
		throw new Error( 'no pickup points for "' + config.pointSearch + '": refresh the local copy of points in the plugin settings' );
	}

	return { pnis: points.map( ( point ) => point.pni ) };
}

export default function ( data ) {
	// A new customer every time.
	http.cookieJar().clear( config.baseUrl );
	const session = new StoreSession( config.baseUrl );

	const withPoint = Math.random() < 2 / 3;
	let choice = { method: 'ppwc_pocztex_courier', point: null };

	if ( withPoint ) {
		// The search is part of the measured flow, like a customer typing a postcode.
		const points = searchPoints( config.baseUrl, config.pointSearch, 'PUNKT' );
		const pnis = points.length ? points.map( ( point ) => point.pni ) : data.pnis;

		choice = { method: 'ppwc_pocztex_point', point: { type: 'PUNKT', pni: pnis[ Math.floor( Math.random() * pnis.length ) ] } };
	}

	if ( placeOrder( session, config, choice ) > 0 ) {
		orders.add( 1 );
	}

	sleep( 1 + Math.random() );
}
