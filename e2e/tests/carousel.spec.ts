import { expect, test } from '@playwright/test';
import { FIXTURES } from '../fixtures';
import { streamUrl, visibleSlide, waitForBurst } from '../helpers';

test.describe( 'the carousel', () => {
	test( 'ships one image and streams the rest', async ( { page, request } ) => {
		// The initial state is read from the markup the server sent, not from
		// the live DOM: data-init fires 500 ms after load, so a DOM assertion
		// here races the burst it is meant to precede -- and would pass or fail
		// depending on how fast the machine is that day.
		const html = await ( await request.get( `/${ FIXTURES.many.slug }/` ) ).text();
		const track = html.slice( html.indexOf( 'hcfd-track' ), html.indexOf( '<noscript>' ) );

		// One image in the page. This is the whole point of the plugin, so it
		// is asserted before anything else.
		expect( ( track.match( /class="hcfd-slide"/g ) ?? [] ).length ).toBe( 1 );

		const responses: number[] = [];
		page.on( 'response', ( r ) => {
			if ( r.url().includes( '/hcfd/v1/slides' ) ) {
				responses.push( r.status() );
			}
		} );

		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		expect( responses ).toEqual( [ 200 ] );
		await expect( page.locator( '.hcfd-track > .hcfd-slide' ) ).toHaveCount( FIXTURES.many.slides );
		await expect( page.locator( '.hcfd-slide:not([hidden])' ) ).toHaveCount( 1 );
		expect( await visibleSlide( page ) ).toBe( `1 of ${ FIXTURES.many.slides }` );
	} );

	test( 'rotates on a cadence the burst delivered', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		// The element carrying data-on-interval arrives with the burst; it is
		// not in the initial markup, which is what lets a cached page still get
		// today's cadence.
		await expect( page.locator( '[data-on-interval__duration\\.5s__viewtransition]' ) ).toHaveCount( 1 );

		const before = await visibleSlide( page );
		await page.waitForTimeout( 6_500 );
		const after = await visibleSlide( page );

		expect( after ).not.toBe( before );
	} );

	test( 'asks the browser for a view transition, under the shipped setting', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		// The slides come from the server, so what happens between two of them
		// is a View Transition rather than a CSS animation. The name is per
		// instance: two elements sharing one at the same moment make the
		// browser abandon the transition entirely.
		const name = await page.evaluate(
			() => getComputedStyle( document.querySelector( '.hcfd-slide:not([hidden])' )! ).viewTransitionName
		);

		expect( name ).toMatch( /^hcfd-[a-f0-9]{12}$/ );

		// Turning it off has to remove BOTH halves. A __viewtransition modifier
		// left behind with no name on any slide makes the browser cross-fade
		// the whole page -- more motion than switching it off asked for, not
		// less.
		const modifiers = await page.evaluate( () =>
			[ ...document.querySelectorAll( '*' ) ].flatMap( ( el ) =>
				[ ...el.attributes ].map( ( a ) => a.name ).filter( ( n ) => n.includes( 'viewtransition' ) )
			)
		);

		expect( modifiers.length ).toBeGreaterThan( 0 );
	} );

	test( 'a single image never rotates and needs no shell', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.one.slug }/` );

		await expect( page.locator( '.hcfd-slide' ) ).toHaveCount( 1 );
		await expect( page.locator( '.hcfd-button' ) ).toHaveCount( 0 );
		await expect( page.locator( '.hcfd-carousel' ) ).toHaveCount( 0 );

		// And it never asks the server for anything.
		let called = false;
		page.on( 'request', ( r ) => {
			if ( r.url().includes( '/hcfd/v1/slides' ) ) {
				called = true;
			}
		} );
		await page.waitForTimeout( 1_500 );
		expect( called ).toBe( false );
	} );

	test( 'no image means nothing at all, not an empty box', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.none.slug }/` );

		await expect( page.locator( '.hcfd-slide' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-block-hcfd-carousel' ) ).toHaveCount( 0 );
	} );

	test( 'the slides that are not on screen still exist for a crawler', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );

		// <noscript> content is inert while scripting is on, so these images
		// cost a visitor nothing -- and they are what a reader mode, a crawler
		// or a visitor without JavaScript gets instead of a single photograph.
		const inNoscript = await page.evaluate( () => {
			const noscript = document.querySelector( '.hcfd-track noscript' );
			return ( noscript?.textContent ?? '' ).match( /class="hcfd-slide"/g )?.length ?? 0;
		} );

		expect( inNoscript ).toBe( FIXTURES.many.slides - 1 );
	} );

	test( 'two carousels on one page do not drive each other', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.twice.slug }/` );
		await page.waitForFunction(
			() => document.querySelectorAll( '.hcfd-track > .hcfd-slide' ).length >= 5,
			null,
			{ timeout: 15_000 }
		);

		const ids = await page.evaluate( () =>
			[ ...document.querySelectorAll( '.hcfd-carousel' ) ].map( ( c ) => c.id )
		);
		expect( ids ).toHaveLength( 2 );
		expect( ids[ 0 ] ).not.toBe( ids[ 1 ] );

		// Datastar signals are global to the document: without a namespace per
		// instance, pausing one would stop the other.
		await page.locator( '.hcfd-carousel' ).first().locator( '.hcfd-button--toggle' ).click();

		const firstBefore = await visibleSlide( page, `#${ ids[ 0 ] }` );
		const secondBefore = await visibleSlide( page, `#${ ids[ 1 ] }` );
		await page.waitForTimeout( 6_500 );

		expect( await visibleSlide( page, `#${ ids[ 0 ] }` ) ).toBe( firstBefore );
		expect( await visibleSlide( page, `#${ ids[ 1 ] }` ) ).not.toBe( secondBefore );
	} );

	test( 'the stream is a single burst that closes at once', async ( { page, request } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		const url = await streamUrl( page );

		const started = Date.now();
		const response = await request.get( url );
		const body = await response.text();
		const elapsed = Date.now() - started;

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-type' ] ).toContain( 'text/event-stream' );

		// Nothing before the first event: a stray byte from another plugin's
		// output buffer would make the whole stream unparseable.
		expect( body.startsWith( 'event: datastar-patch-elements' ) ).toBe( true );

		// Slides, then the count, then the cadence -- in that order, because a
		// cadence that starts before the count is right steps onto slides that
		// are not there.
		const events = [ ...body.matchAll( /^event: (.+)$/gm ) ].map( ( m ) => m[ 1 ] );
		expect( events ).toEqual( [
			'datastar-patch-elements',
			'datastar-patch-signals',
			'datastar-patch-elements',
		] );

		// It must not hold the connection. On PHP-FPM one held connection is
		// one worker taken out of a small pool.
		expect( elapsed ).toBeLessThan( 5_000 );
	} );
} );
