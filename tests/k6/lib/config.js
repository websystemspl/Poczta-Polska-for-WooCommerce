/**
 * Settings of the load tests. Every value can be changed with an environment variable
 * (see tests/k6/README.md).
 */
const value = ( name, fallback ) => ( __ENV[ name ] !== undefined && '' !== __ENV[ name ] ? __ENV[ name ] : fallback );

export const config = {
	baseUrl: value( 'PPWC_K6_BASE_URL', 'https://poczta.localhost' ).replace( /\/$/, '' ),
	// '1' accepts a self-signed certificate (local shop).
	insecure: '1' === value( 'PPWC_K6_INSECURE', '1' ),

	// Shop: a light product with a weight, and an address in Poland used for every order.
	product: Number( value( 'PPWC_K6_PRODUCT', '13' ) ),
	pointSearch: value( 'PPWC_K6_POINT_SEARCH', '94-238' ),
	customer: {
		firstName: 'Jan',
		lastName: 'Testowy',
		address: value( 'PPWC_K6_ADDRESS', 'Pługowa 39' ),
		postcode: value( 'PPWC_K6_POSTCODE', '94-238' ),
		city: value( 'PPWC_K6_CITY', 'Łódź' ),
		phone: '601302890',
		email: value( 'PPWC_K6_EMAIL', 'k6@example.com' ),
	},

	// Load profile of the checkout scenario.
	vus: Number( value( 'PPWC_K6_VUS', '5' ) ),
	duration: value( 'PPWC_K6_DURATION', '1m' ),

	// Shop manager for the bulk scenario.
	adminUser: value( 'PPWC_K6_ADMIN_USER', 'admin' ),
	adminPassword: value( 'PPWC_K6_ADMIN_PASSWORD', '' ),

	// Operations in Elektroniczny Nadawca. Off by default: they create shipments in EN.
	en: '1' === value( 'PPWC_K6_EN', '0' ),
	// The only contract card the bulk scenario may use. It stops when the plugin uses another one.
	enCard: value( 'PPWC_K6_EN_CARD', '458350' ),
	orders: Number( value( 'PPWC_K6_ORDERS', '20' ) ),
	managers: Number( value( 'PPWC_K6_MANAGERS', '2' ) ),
	// '1' keeps the orders, shipments and buffers created by the bulk scenario.
	keep: '1' === value( 'PPWC_K6_KEEP', '0' ),
};
