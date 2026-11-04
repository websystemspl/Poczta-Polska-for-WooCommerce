/**
 * Logs the shop manager in once and saves the session for the admin tests.
 */
import { chromium, type FullConfig } from '@playwright/test';
import { adminState, env } from './support/env';

export default async function globalSetup( config: FullConfig ): Promise<void> {
	if ( '' === env.adminPassword ) {
		throw new Error( 'Set PPWC_E2E_ADMIN_PASSWORD (see tests/e2e/README.md).' );
	}

	const browser = await chromium.launch();
	const page = await browser.newPage( { ignoreHTTPSErrors: env.ignoreHttpsErrors, baseURL: config.projects[ 0 ].use.baseURL } );

	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', env.adminUser );
	await page.fill( '#user_pass', env.adminPassword );
	await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );
	await page.context().storageState( { path: adminState } );
	await browser.close();
}
