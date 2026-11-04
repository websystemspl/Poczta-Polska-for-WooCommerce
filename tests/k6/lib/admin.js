/**
 * Shop manager actions in wp-admin for the bulk scenario.
 */
import http from 'k6/http';
import { fail } from 'k6';

/**
 * Logs the manager in. Cookies stay in the jar of the virtual user.
 */
export function login( config ) {
	if ( ! config.adminPassword ) {
		fail( 'PPWC_K6_ADMIN_PASSWORD is required' );
	}

	http.cookieJar().set( config.baseUrl, 'wordpress_test_cookie', 'WP Cookie check' );

	const response = http.post(
		config.baseUrl + '/wp-login.php',
		{
			log: config.adminUser,
			pwd: config.adminPassword,
			'wp-submit': 'Log In',
			redirect_to: config.baseUrl + '/wp-admin/',
			testcookie: '1',
		},
		{ tags: { name: 'login' } }
	);

	if ( 200 !== response.status || ! response.body.includes( 'wp-admin-bar' ) ) {
		fail( 'login failed for ' + config.adminUser + ' (HTTP ' + response.status + ')' );
	}
}

/**
 * The orders list with its bulk action nonce.
 */
export function ordersList( config ) {
	const url = config.baseUrl + '/wp-admin/admin.php?page=wc-orders';
	const response = http.get( url, { tags: { name: 'orders_list' } } );
	const match = /id="_wpnonce" name="_wpnonce" value="([a-f0-9]+)"/.exec( response.body );

	if ( 200 !== response.status || ! match ) {
		fail( 'orders list without a bulk action nonce (HTTP ' + response.status + ')' );
	}

	return { url, nonce: match[ 1 ], body: response.body };
}

/**
 * Runs a bulk action of the orders list (HPOS) and returns the page shown afterwards.
 *
 * @param {object}   config  Test settings.
 * @param {object}   list    Result of ordersList().
 * @param {string}   action  Bulk action, e.g. ppwc_create_shipments.
 * @param {number[]} ids     Order IDs.
 * @param {string}   name    Metric name.
 * @param {string}   timeout Request timeout.
 */
export function bulkAction( config, list, action, ids, name, timeout = '300s' ) {
	const query = ids.map( ( id ) => 'id%5B%5D=' + id ).join( '&' );

	return http.get(
		list.url + '&_wpnonce=' + list.nonce + '&action=' + action + '&' + query,
		{ headers: { Referer: list.url }, tags: { name }, timeout }
	);
}

/**
 * Checks that the plugin works on the test environment of EN with the allowed contract card.
 */
export function assertTestEnvironment( config ) {
	const response = http.get( config.baseUrl + '/wp-admin/admin.php?page=ppwc&tab=contract', { tags: { name: 'settings' } } );
	const select = /name="ppwc_settings\[card_id_(test|production)\]"[\s\S]*?<\/select>/.exec( response.body );
	const card = select ? /<option value="(\d+)"\s+selected/.exec( select[ 0 ] ) : null;

	if ( ! select || 'test' !== select[ 1 ] ) {
		fail( 'the plugin is not on the EN test environment, the bulk scenario stops' );
	}

	if ( ! card || card[ 1 ] !== config.enCard ) {
		fail( 'the plugin uses card ' + ( card ? card[ 1 ] : '?' ) + ', only ' + config.enCard + ' is allowed' );
	}
}

/**
 * Buffers of the plugin on the Buffers screen, with the number of shipments in each.
 *
 * @return {{ nonce: string, buffers: Array<{ id: number, shipments: number }> }}
 */
export function buffers( config ) {
	const response = http.get( config.baseUrl + '/wp-admin/admin.php?page=ppwc-buffers', { tags: { name: 'buffers' } } );
	const nonce = /name="_wpnonce" value="([a-f0-9]+)"/.exec( response.body );
	const result = { nonce: nonce ? nonce[ 1 ] : '', buffers: [] };

	for ( const block of response.body.split( 'class="postbox ppwc-buffer"' ).slice( 1 ) ) {
		const id = /name="buffer_id" value="(\d+)"/.exec( block );

		if ( id ) {
			result.buffers.push( { id: Number( id[ 1 ] ), shipments: ( block.match( /name="shipments\[\]"/g ) || [] ).length } );
		}
	}

	return result;
}

/**
 * Deletes a buffer in EN through the Buffers screen.
 */
export function deleteBuffer( config, nonce, bufferId ) {
	return http.post(
		config.baseUrl + '/wp-admin/admin-post.php',
		{ action: 'ppwc_buffers', operation: 'delete_buffer', buffer_id: String( bufferId ), _wpnonce: nonce },
		{ tags: { name: 'delete_buffer' }, timeout: '120s' }
	);
}
