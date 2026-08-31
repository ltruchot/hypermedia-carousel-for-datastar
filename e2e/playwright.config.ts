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
	 * WebKit would be a third and is not installed on every machine; it is left
	 * out rather than made a silent skip that looks like a pass.
	 */
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'firefox', use: { ...devices[ 'Desktop Firefox' ] } },
	],
} );
