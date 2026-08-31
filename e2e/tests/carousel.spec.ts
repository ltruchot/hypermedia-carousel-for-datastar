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
		// Asserted on the numbers, not on the wording: the site under test may
		// run in any language, and "1 of 5" is "1 sur 5" on this one.
		const label = await visibleSlide( page );
		expect( label ).toContain( '1' );
		expect( label ).toContain( String( FIXTURES.many.slides ) );
	} );

	test( 'rotates on a cadence the burst delivered', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		// The element carrying data-on-interval arrives with the burst; it is
		// not in the initial markup, which is what lets a cached page still get
		// today's cadence.
		await expect( page.locator( '[data-on-interval__duration\\.5000ms]' ) ).toHaveCount( 1 );

		const before = await visibleSlide( page );
		await page.waitForTimeout( 6_500 );
		const after = await visibleSlide( page );

		expect( after ).not.toBe( before );
	} );

	test( 'the swap cross-fades two slides, and asks for nothing wider', async ( { page } ) => {
		await page.addInitScript( () => {
			( window as unknown as Record< string, number > ).__vt = 0;
			const real = document.startViewTransition?.bind( document );
			if ( real ) {
				document.startViewTransition = ( ...args: Parameters< typeof real > ) => {
					( window as unknown as Record< string, number > ).__vt++;
					return real( ...args );
				};
			}
		} );

		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		// Lengthened so the middle of the fade can be looked at. This changes how
		// long the fade takes, not what takes part in it.
		await page.addStyleTag( { content: '.hcfd-carousel{--hcfd-fade:2s}' } );
		await page.evaluate( () => {
			( window as unknown as Record< string, number > ).__swap = 0;
			new MutationObserver( () => {
				( window as unknown as Record< string, number > ).__swap++;
			} ).observe( document.querySelector( '.hcfd-track' )!, {
				subtree: true,
				attributes: true,
				attributeFilter: [ 'hidden' ],
			} );
		} );

		await page.waitForFunction(
			() => ( window as unknown as Record< string, number > ).__swap > 0,
			null,
			{ timeout: 20_000 }
		);
		await page.waitForTimeout( 900 );

		const layers = await page.evaluate( () =>
			[ ...document.querySelectorAll( '.hcfd-track > .hcfd-slide' ) ]
				.map( ( el, order ) => ( { order, style: getComputedStyle( el ) } ) )
				.filter( ( layer ) => layer.style.display !== 'none' )
				.map( ( layer ) => ( {
					order: layer.order,
					opacity: Number( layer.style.opacity ),
					// `auto` paints as if it were 0 among siblings that have one.
					z: 'auto' === layer.style.zIndex ? 0 : Number( layer.style.zIndex ),
				} ) )
		);

		// Two slides on screen at once: that is what makes it a dissolve rather
		// than a cut.
		expect( layers ).toHaveLength( 2 );

		const fading = layers.filter( ( l ) => l.opacity > 0 && l.opacity < 1 );
		const solid = layers.filter( ( l ) => 1 === l.opacity );
		expect( fading ).toHaveLength( 1 );
		expect( solid ).toHaveLength( 1 );

		// 1. COVERAGE NEVER LEAVES 1.
		//
		// Fading both slides at once is the obvious way to write a cross-fade and
		// it is wrong: two half-transparent layers do not add up to an opaque one.
		// Measured mid-swap under that version, 0.49 over 0.51 covered 0.75 of the
		// box -- a quarter of the container showing through, which on a light
		// background reads as a FLASH OF LIGHT. Lengthening the fade made it worse,
		// because the flash lasted longer.
		const coverage = 1 - layers.reduce( ( rest, l ) => rest * ( 1 - l.opacity ), 1 );
		expect( coverage ).toBeCloseTo( 1, 5 );

		// 2. AND THE FADING LAYER IS THE ONE ON TOP.
		//
		// This second assertion exists because the first one alone is satisfied by
		// a carousel that does not fade at all. Take the z-index off the outgoing
		// slide and, going forward, the incoming one -- later in the DOM, fully
		// opaque -- simply covers it: coverage stays 1, one layer is still
		// somewhere between 0 and 1, every assertion above passes, and the visitor
		// sees a hard cut. Measured: that sabotage left this test green until this
		// assertion was added.
		//
		// Paint order among siblings is z-index first, then document order.
		expect(
			fading[ 0 ].z > solid[ 0 ].z ||
				( fading[ 0 ].z === solid[ 0 ].z && fading[ 0 ].order > solid[ 0 ].order )
		).toBe( true );

		// And none of it goes through startViewTransition. That call snapshots the
		// document element, so every swap cross-fades the entire viewport over
		// itself: measured on a real page, one swap, 597 604 pixels changed
		// OUTSIDE the carousel -- on a swap where the photograph did not change at
		// all. Decorative shapes elsewhere flickered on a five second beat.
		expect(
			await page.evaluate( () => ( window as unknown as Record< string, number > ).__vt )
		).toBe( 0 );
	} );

	test( 'it ships no controls, by design', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		// A deliberate choice, and it has a price worth naming: with nothing to
		// stop it, a carousel that runs on its own fails WCAG 2.2.2 (level A).
		// The readme says so plainly rather than letting a site find out.
		await expect(
			page.locator( '.wp-block-hcfd-carousel button, .wp-block-hcfd-carousel [role="button"]' )
		).toHaveCount( 0 );
	} );

	test( 'a single image never rotates and needs no shell', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.one.slug }/` );

		await expect( page.locator( '.hcfd-slide' ) ).toHaveCount( 1 );
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

		// Datastar signals are global to the document. These two carousels hold
		// three photographs and two, so one shared namespace would step them in
		// lockstep -- and would walk the second one onto a slide it does not have.
		// Waiting for the pair to DISAGREE is the assertion a shared namespace
		// could never satisfy.
		await page.waitForFunction(
			( pair: string[] ) => {
				const shown = ( id: string ) =>
					[ ...document.querySelectorAll( `#${ id } .hcfd-slide` ) ].findIndex(
						( slide ) => ! slide.hasAttribute( 'hidden' )
					);
				return shown( pair[ 0 ] ) !== shown( pair[ 1 ] );
			},
			ids,
			{ timeout: 25_000 }
		);
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
