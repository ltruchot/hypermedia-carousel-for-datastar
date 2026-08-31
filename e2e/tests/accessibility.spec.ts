import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { FIXTURES } from '../fixtures';
import { controlName, visibleSlide, waitForBurst } from '../helpers';

/**
 * A carousel that starts on its own is the textbook way to fail WCAG 2.2.2.
 * None of what follows is a nicety.
 */
test.describe( 'accessibility', () => {
	test( 'axe finds nothing on the carousel', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		const results = await new AxeBuilder( { page } )
			.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
			.include( '.wp-block-hcfd-carousel' )
			.analyze();

		expect( results.violations.map( ( v ) => `${ v.id }: ${ v.help }` ) ).toEqual( [] );
	} );

	test( 'axe would notice if the labels went away', async ( { page } ) => {
		// A green run only means something if the tool is looking. Take the
		// accessible names off and it must go red -- otherwise the assertion
		// above is decoration.
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );
		await page.evaluate( () => document.querySelectorAll( '.hcfd-sr' ).forEach( ( n ) => n.remove() ) );

		const results = await new AxeBuilder( { page } )
			.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
			.include( '.wp-block-hcfd-carousel' )
			.analyze();

		expect( results.violations.map( ( v ) => v.id ) ).toContain( 'button-name' );
	} );

	test( 'the stop button comes before the movement', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );
		await page.evaluate( () => ( document.activeElement as HTMLElement )?.blur() );

		const order: string[] = [];
		for ( let i = 0; i < 40 && order.length < 3; i++ ) {
			await page.keyboard.press( 'Tab' );
			const cls = await page.evaluate( () => document.activeElement?.className ?? '' );
			if ( cls.includes( 'hcfd-button' ) ) {
				order.push( cls.replace( 'hcfd-button ', '' ) );
			}
		}

		// Making someone tab through the movement to reach what stops the
		// movement is not providing a way to stop it.
		expect( order[ 0 ] ).toBe( 'hcfd-button--toggle' );
	} );

	test( 'pausing works, and it never restarts on its own', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		// The name is asserted as a behaviour, not as a string: the site under
		// test may run in any language, and a suite that hard-codes English
		// fails on a translated site while nothing is actually wrong.
		const playing = await controlName( page, '.hcfd-button--toggle' );
		expect( playing ).not.toBe( '' );

		await page.locator( '.hcfd-button--toggle' ).click();
		const paused = await controlName( page, '.hcfd-button--toggle' );

		expect( paused ).not.toBe( '' );
		expect( paused ).not.toBe( playing );

		const stopped = await visibleSlide( page );
		await page.waitForTimeout( 12_000 );
		expect( await visibleSlide( page ) ).toBe( stopped );
	} );

	test( 'it announces nothing while it moves on its own, and politely once it does not', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		// Reading a photograph out every five seconds is hostile, not helpful.
		await expect( page.locator( '.hcfd-track' ) ).toHaveAttribute( 'aria-live', 'off' );
		await page.locator( '.hcfd-button--toggle' ).click();
		await expect( page.locator( '.hcfd-track' ) ).toHaveAttribute( 'aria-live', 'polite' );
	} );

	test( 'slides off screen are out of the tab order and out of the tree', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		const reachable = await page.evaluate(
			() =>
				[ ...document.querySelectorAll( '.hcfd-slide[hidden]' ) ].flatMap( ( slide ) => [
					...slide.querySelectorAll( 'a, button, input, [tabindex]' ),
				] ).length
		);

		// An opacity-0 slide would stay focusable and stay readable.
		expect( reachable ).toBe( 0 );
		await expect( page.locator( '.hcfd-slide[hidden]' ).first() ).toBeHidden();
	} );

	test( 'reduced motion stops the rotation outright', async ( { browser } ) => {
		const context = await browser.newContext( { reducedMotion: 'reduce' } );
		const page = await context.newPage();

		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		const before = await visibleSlide( page );
		await page.waitForTimeout( 12_000 );

		// No stylesheet can stop a timer, so this is checked inside the
		// expression that drives it, on every tick.
		expect( await visibleSlide( page ) ).toBe( before );
		await context.close();
	} );

	test( 'every slide is named for a screen reader', async ( { page } ) => {
		await page.goto( `/${ FIXTURES.many.slug }/` );
		await waitForBurst( page, FIXTURES.many.slides );

		const labels: string[] = await page.evaluate( () =>
			[ ...document.querySelectorAll( '.hcfd-track > .hcfd-slide' ) ].map( ( s ) =>
				s.getAttribute( 'aria-label' )
			)
		);

		// Every slide names its position and the total, in whatever language the
		// site runs. Asserting the wording would test the translation instead.
		expect( labels ).toHaveLength( FIXTURES.many.slides );
		labels.forEach( ( label, index ) => {
			expect( label ).toContain( String( index + 1 ) );
			expect( label ).toContain( String( FIXTURES.many.slides ) );
		} );
		expect( new Set( labels ).size ).toBe( FIXTURES.many.slides );
	} );
} );
