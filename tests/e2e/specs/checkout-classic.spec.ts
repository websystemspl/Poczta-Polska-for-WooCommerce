/**
 * Classic checkout ([woocommerce_checkout] shortcode): methods, pickup points and cash on delivery.
 * OPZ II.1.2, II.1.3.a-e, II.1.4.
 */
import { expect, test } from '@playwright/test';
import { addToCart, classic, orderIdFromThankYou, pointAddress, useFallbackSearch } from '../support/checkout';
import { env } from '../support/env';

test.describe( 'Classic checkout', () => {
	test.beforeEach( async ( { page } ) => {
		await useFallbackSearch( page );
	} );

	test( 'offers the four Poczta Polska methods for an address in Poland', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await classic.open( page );
		await classic.fillAddress( page );

		for ( const method of [ 'ppwc_pocztex_courier', 'ppwc_pocztex_point', 'ppwc_pocztex_locker', 'ppwc_ecommerce' ] as const ) {
			await expect( classic.rate( page, method ) ).toHaveCount( 1 );
		}
	} );

	test( 'offers no domestic Poczta Polska method for an address abroad', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await classic.open( page );
		await classic.fillAddress( page, 'DE' );

		await expect( page.locator( 'input.shipping_method[value^="ppwc_"]' ) ).toHaveCount( 0 );
	} );

	test( 'does not place a pickup point order without a point', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await classic.open( page );
		await classic.fillAddress( page );
		await classic.chooseRate( page, 'ppwc_pocztex_point' );
		await classic.choosePayment( page, 'bacs' );
		await classic.placeOrder( page );

		await expect( page.locator( '.woocommerce-error, .wc-block-components-notice-banner.is-error' ).first() ).toContainText( 'Wybierz punkt odbioru na mapie.' );
		await expect( page ).not.toHaveURL( /order-received/ );
	} );

	test( 'places a pickup point order and shows the point on the confirmation @mobile', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await classic.open( page );
		await classic.fillAddress( page );
		await classic.chooseRate( page, 'ppwc_pocztex_point' );
		await classic.choosePayment( page, 'bacs' );
		await classic.choosePoint( page, env.pointSearch, env.pointPni );
		await classic.placeOrder( page );

		expect( await orderIdFromThankYou( page ) ).toBeGreaterThan( 0 );
		await expect( page.getByText( await pointAddress( page, env.pointPni ) ).first() ).toBeVisible();
	} );

	test( 'blocks cash on delivery at a point that does not accept it', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await classic.open( page );
		await classic.fillAddress( page );
		await classic.chooseRate( page, 'ppwc_pocztex_point' );
		await classic.choosePayment( page, 'bacs' );
		await classic.choosePoint( page, env.pointWithoutCodSearch, env.pointWithoutCodPni );
		await classic.choosePayment( page, 'cod' );
		await classic.placeOrder( page );

		await expect( page.locator( '.woocommerce-error, .wc-block-components-notice-banner.is-error' ).first() ).toBeVisible();
		await expect( page ).not.toHaveURL( /order-received/ );
	} );

	test( 'hides cash on delivery for the eCommerce letter', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await classic.open( page );
		await classic.fillAddress( page );
		await classic.chooseRate( page, 'ppwc_ecommerce' );

		await expect( page.locator( '#payment_method_cod' ) ).toHaveCount( 0 );
		await expect( page.locator( '#payment_method_bacs' ) ).toHaveCount( 1 );
	} );
} );
