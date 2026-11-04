/**
 * End-to-end tests of Poczta Polska for WooCommerce (OPZ II.7.4.j).
 * Instructions: tests/e2e/README.md.
 */
import { defineConfig, devices } from '@playwright/test';
import { env } from './tests/e2e/support/env';

export default defineConfig( {
	testDir: 'tests/e2e/specs',
	outputDir: 'tests/e2e/results',
	globalSetup: './tests/e2e/global-setup.ts',
	timeout: 120_000,
	expect: { timeout: 20_000 },
	// One shop, one EN account: tests run one after another.
	workers: 1,
	fullyParallel: false,
	retries: 0,
	reporter: [ [ 'list' ], [ 'html', { outputFolder: 'tests/e2e/report', open: 'never' } ] ],
	use: {
		baseURL: env.baseUrl,
		ignoreHTTPSErrors: env.ignoreHttpsErrors,
		locale: 'pl-PL',
		timezoneId: 'Europe/Warsaw',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'mobile', use: { ...devices[ 'Pixel 7' ] }, grep: /@mobile/ },
	],
} );
