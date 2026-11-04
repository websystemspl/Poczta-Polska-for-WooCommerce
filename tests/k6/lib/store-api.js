/**
 * WooCommerce Store API client: the same requests the Cart and Checkout Blocks send.
 */
import http from 'k6/http';
import { check, fail } from 'k6';

/**
 * One customer session: cart token and nonce from the Store API, cookies in its own jar.
 */
export class StoreSession {
	constructor( baseUrl ) {
		this.base = baseUrl + '/wp-json/wc/store/v1';
		this.nonce = '';
		this.token = '';
		this.jar = new http.CookieJar();
	}

	params( name, extra = {} ) {
		const headers = { 'Content-Type': 'application/json' };

		if ( this.nonce ) {
			headers.Nonce = this.nonce;
		}

		if ( this.token ) {
			headers[ 'Cart-Token' ] = this.token;
		}

		return Object.assign( { headers, jar: this.jar, tags: { name } }, extra );
	}

	remember( response ) {
		if ( response.headers.Nonce ) {
			this.nonce = response.headers.Nonce;
		}

		if ( response.headers[ 'Cart-Token' ] ) {
			this.token = response.headers[ 'Cart-Token' ];
		}
	}

	get( path, name ) {
		const response = http.get( this.base + path, this.params( name ) );
		this.remember( response );

		return response;
	}

	post( path, body, name, extra = {} ) {
		const response = http.post( this.base + path, JSON.stringify( body ), this.params( name, extra ) );
		this.remember( response );

		return response;
	}
}

/**
 * Address of the customer in the Store API format.
 */
export function address( customer, withEmail ) {
	const data = {
		first_name: customer.firstName,
		last_name: customer.lastName,
		address_1: customer.address,
		address_2: '',
		city: customer.city,
		state: '',
		postcode: customer.postcode,
		country: 'PL',
		phone: customer.phone,
	};

	if ( withEmail ) {
		data.email = customer.email;
	}

	return data;
}

/**
 * Places an order as a guest: cart, address, Poczta Polska shipping method, optional pickup point,
 * payment by bank transfer. Returns the order ID, or 0 when a step failed.
 *
 * @param {StoreSession} session Session.
 * @param {object}       config  Test settings.
 * @param {object}       choice  { method: 'ppwc_pocztex_point', point: { type: 'PUNKT', pni: 123 } | null }
 */
export function placeOrder( session, config, choice ) {
	let response = session.get( '/cart', 'cart' );

	if ( ! check( response, { 'cart loaded': ( r ) => 200 === r.status } ) ) {
		return 0;
	}

	response = session.post( '/cart/add-item', { id: config.product, quantity: 1 }, 'cart_add_item' );

	if ( ! check( response, { 'product added': ( r ) => 201 === r.status || 200 === r.status } ) ) {
		return 0;
	}

	response = session.post(
		'/cart/update-customer',
		{ shipping_address: address( config.customer, false ), billing_address: address( config.customer, true ) },
		'cart_update_customer'
	);

	if ( ! check( response, { 'address accepted': ( r ) => 200 === r.status } ) ) {
		return 0;
	}

	const packages = response.json( 'shipping_rates' ) || [];
	const rates = packages.length ? packages[ 0 ].shipping_rates : [];
	const rate = rates.find( ( r ) => r.rate_id.startsWith( choice.method + ':' ) );

	if ( ! check( rate, { 'Poczta Polska method offered': ( r ) => undefined !== r } ) ) {
		return 0;
	}

	response = session.post( '/cart/select-shipping-rate', { package_id: 0, rate_id: rate.rate_id }, 'cart_select_rate' );

	if ( ! check( response, { 'method selected': ( r ) => 200 === r.status } ) ) {
		return 0;
	}

	if ( choice.point ) {
		response = session.post(
			'/cart/extensions',
			{ namespace: 'ppwc', data: { type: choice.point.type, pni: choice.point.pni } },
			'cart_set_point'
		);

		if ( ! check( response, { 'point accepted': ( r ) => 200 === r.status } ) ) {
			return 0;
		}
	}

	response = session.post(
		'/checkout',
		{
			billing_address: address( config.customer, true ),
			shipping_address: address( config.customer, false ),
			payment_method: 'bacs',
		},
		'checkout'
	);

	if ( ! check( response, { 'order placed': ( r ) => 200 === r.status && r.json( 'order_id' ) > 0 } ) ) {
		if ( response.status >= 500 ) {
			fail( 'checkout failed with HTTP ' + response.status );
		}

		return 0;
	}

	return response.json( 'order_id' );
}

/**
 * Pickup points found by the fallback search of the plugin (local copy of the EN points).
 *
 * @param {string} baseUrl Shop URL.
 * @param {string} search  Postcode, city or street.
 * @param {string} type    PUNKT or AUTOMAT.
 * @return {Array<{pni: number}>}
 */
export function searchPoints( baseUrl, search, type ) {
	const response = http.get(
		baseUrl + '/wp-json/ppwc/v1/points?search=' + encodeURIComponent( search ) + '&type=' + type + '&cod=0',
		{ tags: { name: 'points_search' } }
	);

	if ( ! check( response, { 'points found': ( r ) => 200 === r.status && Array.isArray( r.json() ) && r.json().length > 0 } ) ) {
		return [];
	}

	return response.json();
}
