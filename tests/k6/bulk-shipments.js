/**
 * Load test 2: shop managers creating shipments in bulk (OPZ II.7.4.e).
 *
 * setup() places PPWC_K6_ORDERS orders with Pocztex Kurier. Then PPWC_K6_MANAGERS managers run
 * the bulk action "Poczta Polska: create shipments" on the same orders at the same moment: the
 * time of the bulk action is measured, and the plugin must create exactly one shipment per
 * order despite the concurrent requests. teardown() deletes the shipments and the empty buffers
 * in Elektroniczny Nadawca and moves the orders to the trash (unless PPWC_K6_KEEP=1).
 *
 * The shipments are created in Elektroniczny Nadawca, so this scenario is off by default:
 *
 *   PPWC_K6_EN=1 PPWC_K6_ADMIN_PASSWORD='...' k6 run tests/k6/bulk-shipments.js
 *
 * It refuses to run unless the plugin uses the EN test environment and the allowed card.
 */
import { check, fail } from 'k6';
import http from 'k6/http';
import { Trend, Counter } from 'k6/metrics';
import { config } from './lib/config.js';
import { StoreSession, placeOrder } from './lib/store-api.js';
import { login, ordersList, bulkAction, assertTestEnvironment, buffers, deleteBuffer } from './lib/admin.js';

const bulkSeconds = new Trend( 'ppwc_bulk_create_seconds' );
const shipmentsCreated = new Counter( 'ppwc_shipments_created' );

export const options = {
	insecureSkipTLSVerify: config.insecure,
	setupTimeout: '15m',
	teardownTimeout: '15m',
	scenarios: {
		managers: {
			executor: 'per-vu-iterations',
			vus: config.managers,
			iterations: 1,
			maxDuration: '15m',
		},
	},
	thresholds: {
		http_req_failed: [ 'rate<0.01' ],
		checks: [ 'rate==1' ],
		'http_req_duration{name:bulk_create}': [ 'p(95)<120000' ],
	},
};

export function setup() {
	if ( ! config.en ) {
		fail( 'this scenario creates shipments in Elektroniczny Nadawca: run it with PPWC_K6_EN=1' );
	}

	login( config );
	assertTestEnvironment( config );

	const ids = [];

	for ( let i = 0; i < config.orders; i++ ) {
		const id = placeOrder( new StoreSession( config.baseUrl ), config, { method: 'ppwc_pocztex_courier', point: null } );

		if ( id > 0 ) {
			ids.push( id );
		}
	}

	if ( ids.length < config.orders ) {
		fail( 'only ' + ids.length + ' of ' + config.orders + ' orders were placed' );
	}

	console.log( 'orders for the bulk action: ' + ids.join( ', ' ) );

	return { ids };
}

export default function ( data ) {
	login( config );
	const list = ordersList( config );

	const started = Date.now();
	const response = bulkAction( config, list, 'ppwc_create_shipments', data.ids, 'bulk_create' );
	bulkSeconds.add( ( Date.now() - started ) / 1000 );

	check( response, { 'bulk action finished': ( r ) => 200 === r.status && r.body.includes( 'wc-orders' ) } );
}

export function teardown( data ) {
	login( config );

	// Every order has exactly one shipment, whoever of the managers created it.
	const before = buffers( config );
	const inBuffers = before.buffers.reduce( ( sum, buffer ) => sum + buffer.shipments, 0 );
	shipmentsCreated.add( inBuffers );

	console.log( 'shipments in the buffers of the plugin: ' + inBuffers + ' for ' + data.ids.length + ' orders' );

	if ( config.keep ) {
		console.log( 'PPWC_K6_KEEP=1: orders, shipments and buffers are kept' );
		return;
	}

	const list = ordersList( config );
	check( bulkAction( config, list, 'ppwc_delete_shipments', data.ids, 'bulk_delete' ), { 'shipments deleted': ( r ) => 200 === r.status } );

	for ( const buffer of buffers( config ).buffers.filter( ( b ) => 0 === b.shipments ) ) {
		check( deleteBuffer( config, before.nonce, buffer.id ), { 'empty buffer deleted': ( r ) => 200 === r.status } );
	}

	check( bulkAction( config, ordersList( config ), 'trash', data.ids, 'orders_trash' ), { 'orders trashed': ( r ) => 200 === r.status } );

	http.cookieJar().clear( config.baseUrl );
}
