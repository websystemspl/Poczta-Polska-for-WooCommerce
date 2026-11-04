/**
 * Shipments in Elektroniczny Nadawca from the order screen. OPZ II.2.1, II.2.2.
 *
 * These tests create real shipments in EN, so they run only with PPWC_E2E_EN=1 and only when the
 * plugin works in the test environment with the card from PPWC_E2E_EN_CARD.
 * Sending the buffer (PPWC_E2E_EN_SEND=1) sends every shipment of today's buffer of that card.
 * Ordering a courier is not automated: it orders a real pickup.
 */
import { expect, test, type Page } from '@playwright/test';
import { addToCart, classic, orderIdFromThankYou, useFallbackSearch } from '../support/checkout';
import { adminState, env } from '../support/env';

test.describe.configure( { mode: 'serial' } );

test.describe( 'Shipments in Elektroniczny Nadawca', () => {
	test.skip( ! env.en, 'Set PPWC_E2E_EN=1 to create shipments in Elektroniczny Nadawca.' );

	let orderId = 0;

	const orderScreen = ( page: Page ) => page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	const box = ( page: Page ) => page.locator( '.ppwc-shipments' );

	test( 'the plugin works in the test environment with the test card', async ( { browser } ) => {
		const page = await browser.newPage( { storageState: adminState } );

		await page.goto( '/wp-admin/admin.php?page=ppwc' );
		await expect( page.locator( 'input[name="ppwc_settings[environment]"][value="test"]' ) ).toBeChecked();

		await page.goto( '/wp-admin/admin.php?page=ppwc&tab=contract' );
		await expect( page.locator( 'select[name="ppwc_settings[card_id_test]"]' ) ).toHaveValue( env.enCard );
		await page.close();
	} );

	test( 'a customer places a courier order', async ( { page } ) => {
		await useFallbackSearch( page );
		await addToCart( page, env.productLight );
		await classic.open( page );
		await classic.fillAddress( page );
		await classic.chooseRate( page, 'ppwc_pocztex_courier' );
		await classic.choosePayment( page, 'bacs' );
		await classic.placeOrder( page );

		orderId = await orderIdFromThankYou( page );
		expect( orderId ).toBeGreaterThan( 0 );
	} );

	test.describe( 'shop manager', () => {
		test.use( { storageState: adminState } );

		test( 'creates the shipment from the order', async ( { page } ) => {
			await orderScreen( page );
			await box( page ).locator( '[data-ppwc-operation="create"]' ).click();
			await page.waitForLoadState( 'load' );

			await expect( box( page ).locator( 'tbody tr' ).first() ).toContainText( /PX\d{8,}|\d{20}/ );
			await expect( box( page ).locator( 'tbody tr' ).first() ).toContainText( 'W buforze' );
		} );

		test( 'sees the tracking number in the orders list', async ( { page } ) => {
			await page.goto( '/wp-admin/admin.php?page=wc-orders' );

			await expect( page.locator( `#order-${ orderId } .column-ppwc_tracking` ) ).toContainText( /PX\d{8,}|\d{20}/ );
		} );

		test( 'downloads the label and the shipment is locked', async ( { page } ) => {
			await orderScreen( page );

			const [ download ] = await Promise.all( [ page.waitForEvent( 'download' ), box( page ).locator( 'a[href*="ppwc_labels"]' ).first().click() ] );
			const pdf = await download.createReadStream();
			const head = await new Promise<string>( ( resolve ) => pdf.once( 'data', ( chunk: Buffer ) => resolve( chunk.subarray( 0, 5 ).toString() ) ) );

			expect( head ).toBe( '%PDF-' );

			await orderScreen( page );
			await expect( box( page ).locator( 'tbody tr' ).first() ).toContainText( 'etykieta wydrukowana' );
			await expect( box( page ).locator( '[data-ppwc-operation="recreate"]' ) ).toHaveCount( 0 );
		} );

		test( 'sends the buffer or deletes the shipment', async ( { page } ) => {
			if ( env.enSend ) {
				await page.goto( '/wp-admin/admin.php?page=ppwc-buffers' );
				page.once( 'dialog', ( dialog ) => dialog.accept() );

				const buffer = page.locator( '.ppwc-buffer', { hasText: `#${ orderId }` } );

				await buffer.locator( 'button[value="send"]' ).click();
				await expect( page.locator( '.notice-success' ) ).toContainText( 'wysłany' );
				return;
			}

			await orderScreen( page );
			page.once( 'dialog', ( dialog ) => dialog.accept() );
			await box( page ).locator( '[data-ppwc-operation="delete"]' ).click();
			await page.waitForLoadState( 'load' );

			await expect( box( page ).locator( 'tbody tr' ) ).toHaveCount( 0 );
		} );
	} );
} );
