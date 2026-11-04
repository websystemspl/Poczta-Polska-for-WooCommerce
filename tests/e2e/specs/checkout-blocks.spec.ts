/**
 * Block checkout (Cart and Checkout blocks): methods, pickup points and cash on delivery.
 * OPZ II.1.2, II.1.3.a-e, II.1.4.
 */
import { expect, test } from '@playwright/test';
import { addToCart, blocks, orderIdFromThankYou, pointAddress, useFallbackSearch } from '../support/checkout';
import { env } from '../support/env';

test.describe( 'Block checkout', () => {
	test.beforeEach( async ( { page } ) => {
		await useFallbackSearch( page );
	} );

	test( 'offers the four Poczta Polska methods for an address in Poland', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await blocks.open( page );
		await blocks.fillAddress( page );

		for ( const method of [ 'ppwc_pocztex_courier', 'ppwc_pocztex_point', 'ppwc_pocztex_locker', 'ppwc_ecommerce' ] as const ) {
			await expect( blocks.rate( page, method ) ).toHaveCount( 1 );
		}
	} );

	test( 'hides the eCommerce letter for a product without weight', async ( { page } ) => {
		await addToCart( page, env.productNoWeight );
		await blocks.open( page );
		await blocks.fillAddress( page );

		await expect( blocks.rate( page, 'ppwc_pocztex_courier' ) ).toHaveCount( 1 );
		await expect( blocks.rate( page, 'ppwc_ecommerce' ) ).toHaveCount( 0 );
	} );

	test( 'offers no domestic Poczta Polska method for an address abroad', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await blocks.open( page );
		await blocks.fillAddress( page, 'DE' );

		await expect( page.locator( '.wc-block-components-shipping-rates-control input[value^="ppwc_"]' ) ).toHaveCount( 0 );
	} );

	test( 'does not place a pickup point order without a point', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await blocks.open( page );
		await blocks.fillAddress( page );
		await blocks.chooseRate( page, 'ppwc_pocztex_point' );
		await blocks.choosePayment( page, 'bacs' );
		await blocks.placeOrder( page );

		await expect( page.getByText( 'Wybierz punkt odbioru na mapie.' ).first() ).toBeVisible();
		await expect( page ).not.toHaveURL( /order-received/ );
	} );

	test( 'places a pickup point order and shows the point on the confirmation @mobile', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await blocks.open( page );
		await blocks.fillAddress( page );
		await blocks.chooseRate( page, 'ppwc_pocztex_point' );
		await blocks.choosePayment( page, 'bacs' );
		await blocks.choosePoint( page, env.pointSearch, env.pointPni );
		await blocks.placeOrder( page );

		expect( await orderIdFromThankYou( page ) ).toBeGreaterThan( 0 );
		await expect( page.getByText( await pointAddress( page, env.pointPni ) ).first() ).toBeVisible();
	} );

	test( 'places a parcel locker order with cash on delivery', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await blocks.open( page );
		await blocks.fillAddress( page );
		await blocks.chooseRate( page, 'ppwc_pocztex_locker' );
		await blocks.choosePayment( page, 'cod' );
		await blocks.choosePoint( page, env.lockerSearch, env.lockerPni );
		await blocks.placeOrder( page );

		expect( await orderIdFromThankYou( page ) ).toBeGreaterThan( 0 );
	} );

	test( 'blocks cash on delivery at a point that does not accept it', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await blocks.open( page );
		await blocks.fillAddress( page );
		await blocks.chooseRate( page, 'ppwc_pocztex_point' );
		await blocks.choosePayment( page, 'bacs' );
		await blocks.choosePoint( page, env.pointWithoutCodSearch, env.pointWithoutCodPni );
		await blocks.choosePayment( page, 'cod' );
		await blocks.placeOrder( page );

		await expect( page.locator( '.ppwc-pickup__problem' ).first() ).toBeVisible();
		await expect( page ).not.toHaveURL( /order-received/ );
	} );

	test( 'blocks cash on delivery above the limit of the point', async ( { page } ) => {
		await addToCart( page, env.productExpensive );
		await blocks.open( page );
		await blocks.fillAddress( page );
		await blocks.chooseRate( page, 'ppwc_pocztex_locker' );
		await blocks.choosePayment( page, 'bacs' );
		await blocks.choosePoint( page, env.lockerSearch, env.lockerPni );
		await blocks.choosePayment( page, 'cod' );
		await blocks.placeOrder( page );

		await expect( page.locator( '.ppwc-pickup__problem' ).first() ).toBeVisible();
		await expect( page ).not.toHaveURL( /order-received/ );
	} );

	test( 'hides cash on delivery for the eCommerce letter', async ( { page } ) => {
		await addToCart( page, env.productLight );
		await blocks.open( page );
		await blocks.fillAddress( page );
		await blocks.chooseRate( page, 'ppwc_ecommerce' );

		await expect( page.locator( '#radio-control-wc-payment-method-options-cod' ) ).toHaveCount( 0 );
		await expect( page.locator( '#radio-control-wc-payment-method-options-bacs' ) ).toHaveCount( 1 );
	} );

	test( 'opens the Poczta Polska map', async ( { page } ) => {
		await page.unrouteAll();
		await addToCart( page, env.productLight );
		await blocks.open( page );
		await blocks.fillAddress( page );
		await blocks.chooseRate( page, 'ppwc_pocztex_point' );
		await page.locator( '.ppwc-pickup__choose' ).click();

		// The map of Poczta Polska is an external service: the test checks that it opens, not its content.
		await expect( page.locator( 'iframe#pp-widget-iframe' ) ).toBeVisible();
	} );
} );
