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
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ] } },
	],
} );
