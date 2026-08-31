import { defineConfig, devices } from '@playwright/test';

/**
 * BASE_URL is REQUIRED and has no default, deliberately.
 *
 * A test runner that falls back to some address when the variable is missing
 * will one day measure a site nobody meant to measure, and report the result as
 * if it came from the one under test. Failing to start is the cheaper outcome.
 */
const baseURL = process.env.BASE_URL;

if ( ! baseURL ) {
	throw new Error(
		'BASE_URL is not set. Point it at the WordPress site to test, e.g.\n' +
			'  BASE_URL=http://localhost:8888 npm test\n' +
			'There is no default: guessing one would mean testing the wrong site in silence.'
	);
}

export default defineConfig( {
	testDir: './tests',
	globalSetup: './global-setup.ts',
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: 0,
	reporter: process.env.CI ? 'list' : [ [ 'list' ], [ 'html', { open: 'never' } ] ],
	timeout: 60_000,
	use: {
		baseURL,
		trace: 'retain-on-failure',
	},
	/*
	 * Three engines, and only two of them start here.
	 *
	 * The plugin says it targets evergreen browsers and was measured on Chromium
	 * alone for three releases. That is how a cross-fade shipped that Firefox
	 * turned into a hard cut: `transition-behavior: allow-discrete` on `display`
	 * is reported as supported there and does not defer the change. Nothing in a
	 * single-engine suite could have said so.
	 *
	 * WEBKIT IS DECLARED AND WILL NOT LAUNCH ON EVERY MACHINE, which is worth
	 * understanding before assuming it is missing. Its binary installs like the
	 * others -- 293 MB sitting next to them. What it needs and they do not is
	 * three SYSTEM libraries (libicu74, libxml2, libflite1), and putting those
	 * on a machine takes root. Nothing about Playwright can work around that.
	 *
	 * So `npm test` runs the two that start natively, and `npm run test:all`
	 * runs all three inside the official Playwright image, which carries the
	 * libraries. No root, no change to the machine. Keep the image tag in
	 * package.json in step with the Playwright version above it, or the browsers
	 * in the image will not be the ones this config expects.
	 */
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'firefox', use: { ...devices[ 'Desktop Firefox' ] } },
		{ name: 'webkit', use: { ...devices[ 'Desktop Safari' ] } },
	],
} );
