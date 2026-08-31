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
	 * Two engines, not one.
	 *
	 * The plugin says it targets evergreen browsers and was measured on Chromium
	 * alone for three releases. That is how a cross-fade shipped that Firefox
	 * turned into a hard cut: `transition-behavior: allow-discrete` on `display`
	 * is reported as supported there and does not defer the change. Nothing in
	 * a single-engine suite could have said so.
	 *
	 * WebKit is a third, and it is NOT here — deliberately, and not because it
	 * does not matter. Its binary downloads fine; launching it on this machine
	 * needs three system libraries (libicu74, libxml2, libflite1) that only
	 * `sudo` can install. Adding a project that cannot start would turn every
	 * run red, and making it skip itself when unavailable would be worse: a
	 * silent skip reads exactly like a pass.
	 *
	 * So it is left out and said out loud. To add it, once
	 * `sudo npx playwright install-deps` has run:
	 *
	 *     { name: 'webkit', use: { ...devices[ 'Desktop Safari' ] } },
	 *
	 * What stands in for it today is a MANUAL check: Loïc opened the site in
	 * Safari on 01/09/2026 and reported the cross-fade correct. That is a real
	 * measurement and it is why the `visibility` mechanism is trusted there —
	 * but it was made by a person, once, and this file cannot replay it.
	 */
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'firefox', use: { ...devices[ 'Desktop Firefox' ] } },
	],
} );
