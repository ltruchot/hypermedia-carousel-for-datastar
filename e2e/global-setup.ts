import { execFileSync } from 'node:child_process';
import { FIXTURES } from './fixtures';

/**
 * Creates the fixture pages, when the environment says how to reach WP-CLI.
 *
 * WP_CLI holds the command that runs WP-CLI against the site under test —
 * `wp`, `wp-env run cli wp`, a `docker run …` line, whatever your setup needs.
 * Leave it unset and the suite assumes the pages already exist, which is what
 * you want on a site you cannot drive from a shell.
 *
 * It stays an environment variable rather than a committed value on purpose:
 * how a given machine reaches its WordPress is that machine's business, not
 * this repository's.
 */
async function globalSetup(): Promise< void > {
	const wpCli = process.env.WP_CLI;

	if ( ! wpCli ) {
		console.log( 'WP_CLI is not set: assuming the fixture pages already exist.' );
		return;
	}

	const run = ( args: string[] ): string => {
		const parts = wpCli.split( /\s+/ ).filter( Boolean );
		return execFileSync( parts[ 0 ], [ ...parts.slice( 1 ), ...args ], {
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		} ).trim();
	};

	// Real attachments from the site under test, so the suite exercises the
	// media library rather than a fabricated one.
	const ids = run( [
		'post', 'list', '--post_type=attachment', '--post_mime_type=image',
		'--posts_per_page=5', '--field=ID', '--format=ids',
	] )
		.split( /\s+/ )
		.filter( Boolean )
		.map( Number );

	if ( ids.length < 5 ) {
		throw new Error(
			`The site under test has ${ ids.length } image attachments; the suite needs 5.`
		);
	}

	for ( const fixture of Object.values( FIXTURES ) ) {
		const existing = run( [ 'post', 'list', '--post_type=page', `--name=${ fixture.slug }`, '--field=ID' ] );

		if ( existing ) {
			run( [ 'post', 'delete', existing, '--force' ] );
		}

		run( [
			'post', 'create',
			'--post_type=page',
			'--post_status=publish',
			`--post_title=${ fixture.title }`,
			`--post_name=${ fixture.slug }`,
			`--post_content=${ fixture.content( ids ) }`,
		] );
	}

	console.log( `Fixture pages created from attachments ${ ids.join( ', ' ) }.` );
}

export default globalSetup;
