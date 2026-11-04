/**
 * Customer steps on the block checkout and the classic checkout.
 */
import { expect, type Locator, type Page } from '@playwright/test';
import { customer, env } from './env';

export type Method = 'ppwc_pocztex_courier' | 'ppwc_pocztex_point' | 'ppwc_pocztex_locker' | 'ppwc_ecommerce';
export type Payment = 'bacs' | 'cod';

/**
 * Blocks the Poczta Polska map script, so the plugin opens its fallback search over the local copy
 * of the points. The external map cannot be clicked reliably in tests; one test checks it separately.
 */
export async function useFallbackSearch( page: Page ): Promise<void> {
	await page.route( /ppwidget\.js/, ( route ) => route.abort() );
}

/**
 * Empties the cart and adds a product.
 */
export async function addToCart( page: Page, productId: string, quantity = 1 ): Promise<void> {
	await page.context().clearCookies();
	await page.goto( `/?add-to-cart=${ productId }&quantity=${ quantity }` );
}

/**
 * Checks a radio button and makes sure it stays checked: the block checkout redraws the form after
 * cart updates and a click during a redraw is lost.
 */
async function checkStable( page: Page, radio: Locator ): Promise<void> {
	await expect( async () => {
		await radio.check();
		await page.waitForTimeout( 500 );
		await expect( radio ).toBeChecked( { timeout: 1_000 } );
	} ).toPass( { timeout: 30_000 } );
}

/**
 * Chooses a point in the fallback search dialog.
 */
async function pickInFallback( page: Page, search: string, pni: string ): Promise<void> {
	const dialog = page.locator( 'dialog.ppwc-fallback' );

	// The block checkout redraws the form after cart updates; a click during a redraw is lost, so
	// click again like a customer would.
	await expect( async () => {
		if ( ! ( await dialog.isVisible() ) ) {
			await page.locator( '.ppwc-pickup__choose' ).click();
		}
		await expect( dialog ).toBeVisible( { timeout: 3_000 } );
	} ).toPass( { timeout: 20_000 } );

	await dialog.locator( 'input[type="search"]' ).fill( search );
	await dialog.locator( 'button[type="submit"]' ).click();

	const result = dialog.locator( `.ppwc-fallback__results button[data-pni="${ pni }"]` );

	await expect( result ).toBeVisible();
	await result.click();
	await expect( dialog ).toBeHidden();
}

/**
 * Block checkout.
 */
export const blocks = {
	async open( page: Page ): Promise<void> {
		await page.goto( env.blocksCheckout );
		await expect( page.locator( '#shipping-first_name' ) ).toBeVisible();
	},

	async fillAddress( page: Page, country = 'PL' ): Promise<void> {
		await page.fill( '#email', customer.email );
		await page.selectOption( '#shipping-country', country );
		await page.fill( '#shipping-first_name', customer.firstName );
		await page.fill( '#shipping-last_name', customer.lastName );
		await page.fill( '#shipping-address_1', 'PL' === country ? customer.address : 'Unter den Linden 1' );
		await page.fill( '#shipping-postcode', 'PL' === country ? customer.postcode : '10117' );
		await page.fill( '#shipping-city', 'PL' === country ? customer.city : 'Berlin' );
		const saved = page
			.waitForResponse( ( response ) => /wc\/store\/v1\/batch/.test( response.url() ) && ( response.request().postData() ?? '' ).includes( 'update-customer' ) && ( response.request().postData() ?? '' ).includes( '"shipping_address"' ), { timeout: 10_000 } )
			.catch( () => null );

		await page.fill( '#shipping-phone', customer.phone );
		await page.locator( '#shipping-phone' ).blur();
		await saved;
		await this.settle( page );
	},

	/**
	 * Waits until the cart and the shipping rates are updated.
	 */
	async settle( page: Page ): Promise<void> {
		await page.waitForLoadState( 'networkidle' );
		await expect( page.locator( '.wc-block-components-loading-mask' ) ).toHaveCount( 0 );
	},

	rate( page: Page, method: Method ): Locator {
		// The radio IDs carry a counter that changes between page loads; the value is the rate ID.
		return page.locator( `.wc-block-components-shipping-rates-control input[value^="${ method }:"]` );
	},

	async chooseRate( page: Page, method: Method ): Promise<void> {
		await checkStable( page, this.rate( page, method ) );
		await this.settle( page );
	},

	async choosePayment( page: Page, payment: Payment ): Promise<void> {
		const radio = page.locator( `#radio-control-wc-payment-method-options-${ payment }` );

		if ( await radio.isChecked() ) {
			return;
		}

		// The checkout sends the payment method about two seconds later and then redraws the form.
		const saved = page
			.waitForResponse( ( response ) => /wc\/store\/v1\/checkout/.test( response.url() ) && 'GET' !== response.request().method(), { timeout: 10_000 } )
			.catch( () => null );

		await checkStable( page, radio );
		await saved;
		await this.settle( page );
	},

	async choosePoint( page: Page, search: string, pni: string ): Promise<void> {
		await pickInFallback( page, search, pni );
		await this.settle( page );
		await expect( page.locator( '.ppwc-pickup__summary:not(.ppwc-pickup__empty)' ) ).toBeVisible();
	},

	async placeOrder( page: Page ): Promise<void> {
		await page.locator( '.wc-block-components-checkout-place-order-button' ).click();
	},
};

/**
 * Classic checkout ([woocommerce_checkout]).
 */
export const classic = {
	async open( page: Page ): Promise<void> {
		await page.goto( env.classicCheckout );
		await expect( page.locator( 'form.checkout' ) ).toBeVisible();
	},

	async fillAddress( page: Page, country = 'PL' ): Promise<void> {
		await page.selectOption( '#billing_country', country );
		await page.fill( '#billing_first_name', customer.firstName );
		await page.fill( '#billing_last_name', customer.lastName );
		await page.fill( '#billing_address_1', 'PL' === country ? customer.address : 'Unter den Linden 1' );
		await page.fill( '#billing_postcode', 'PL' === country ? customer.postcode : '10117' );
		await page.fill( '#billing_city', 'PL' === country ? customer.city : 'Berlin' );
		await page.fill( '#billing_phone', customer.phone );
		await page.fill( '#billing_email', customer.email );
		await page.locator( '#billing_email' ).blur();
		await this.settle( page );
	},

	/**
	 * Waits for WooCommerce to refresh the order review (update_order_review).
	 */
	async settle( page: Page ): Promise<void> {
		await page.waitForLoadState( 'networkidle' );
		await expect( page.locator( '.blockUI' ) ).toHaveCount( 0 );
	},

	rate( page: Page, method: Method ): Locator {
		return page.locator( `input.shipping_method[value^="${ method }:"]` );
	},

	async chooseRate( page: Page, method: Method ): Promise<void> {
		await this.rate( page, method ).check();
		await this.settle( page );
	},

	async choosePayment( page: Page, payment: Payment ): Promise<void> {
		await page.locator( `#payment_method_${ payment }` ).check();
		await this.settle( page );
	},

	async choosePoint( page: Page, search: string, pni: string ): Promise<void> {
		await pickInFallback( page, search, pni );
		await this.settle( page );
		await expect( page.locator( `.ppwc-pickup input[type="hidden"][value="${ pni }"]` ) ).toHaveCount( 1 );
	},

	async placeOrder( page: Page ): Promise<void> {
		await page.locator( '#place_order' ).click();
	},
};

/**
 * Returns the order ID from the order received page URL.
 */
export async function orderIdFromThankYou( page: Page ): Promise<number> {
	await page.waitForURL( /order-received\/(\d+)/ );
	const match = /order-received\/(\d+)/.exec( page.url() );

	return match ? Number( match[ 1 ] ) : 0;
}

/**
 * Returns the address of a point from the plugin REST API, as shown to the customer.
 */
export async function pointAddress( page: Page, pni: string ): Promise<string> {
	const response = await page.request.get( `/wp-json/ppwc/v1/points/${ pni }` );
	const point = ( await response.json() ) as { address: string };

	return point.address;
}
