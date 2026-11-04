/**
 * Test settings. Every value can be changed with an environment variable, so the tests run on any
 * shop with the plugin (see tests/e2e/README.md).
 */
const value = ( name: string, fallback: string ): string => process.env[ name ] ?? fallback;

export const env = {
	baseUrl: value( 'PPWC_E2E_BASE_URL', 'https://poczta.localhost' ),
	ignoreHttpsErrors: '1' === value( 'PPWC_E2E_IGNORE_HTTPS_ERRORS', '1' ),
	adminUser: value( 'PPWC_E2E_ADMIN_USER', 'admin' ),
	adminPassword: value( 'PPWC_E2E_ADMIN_PASSWORD', '' ),

	// Pages.
	blocksCheckout: value( 'PPWC_E2E_BLOCKS_CHECKOUT', '/checkout/' ),
	classicCheckout: value( 'PPWC_E2E_CLASSIC_CHECKOUT', '/zamowienie-classic/' ),

	// Products: light with weight, without weight, more expensive than the point cash on delivery limit.
	productLight: value( 'PPWC_E2E_PRODUCT_LIGHT', '13' ),
	productNoWeight: value( 'PPWC_E2E_PRODUCT_NO_WEIGHT', '31' ),
	productExpensive: value( 'PPWC_E2E_PRODUCT_EXPENSIVE', '32' ),

	// Points found by the fallback search (local copy of the EN points).
	pointSearch: value( 'PPWC_E2E_POINT_SEARCH', '01-560' ),
	pointPni: value( 'PPWC_E2E_POINT_PNI', '536973' ),
	pointWithoutCodSearch: value( 'PPWC_E2E_POINT_NO_COD_SEARCH', '26-415' ),
	pointWithoutCodPni: value( 'PPWC_E2E_POINT_NO_COD_PNI', '531604' ),
	lockerSearch: value( 'PPWC_E2E_LOCKER_SEARCH', '08-440' ),
	lockerPni: value( 'PPWC_E2E_LOCKER_PNI', '213458' ),

	// Operations in Elektroniczny Nadawca. Off by default: they create real shipments in EN.
	en: '1' === value( 'PPWC_E2E_EN', '0' ),
	enSend: '1' === value( 'PPWC_E2E_EN_SEND', '0' ),
	// The only card the tests may use. They stop when the plugin uses another one.
	enCard: value( 'PPWC_E2E_EN_CARD', '458350' ),
};

export const customer = {
	email: 'e2e@example.com',
	firstName: 'Jan',
	lastName: 'Testowy',
	address: 'Pługowa 39',
	postcode: '94-238',
	city: 'Łódź',
	phone: '601302890',
};

export const adminState = 'tests/e2e/.auth/admin.json';
