import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { FIXTURES } from '../fixtures';
import { visibleSlide, waitForBurst } from '../helpers';

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

		await expect( page.locator( '.hcfd-button--toggle' ) ).toHaveAccessibleName( 'Pause slideshow' );
		await page.locator( '.hcfd-button--toggle' ).click();
		await expect( page.locator( '.hcfd-button--toggle' ) ).toHaveAccessibleName( 'Play slideshow' );

		const paused = await visibleSlide( page );
		await page.waitForTimeout( 12_000 );
		expect( await visibleSlide( page ) ).toBe( paused );
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

		const labels = await page.evaluate( () =>
			[ ...document.querySelectorAll( '.hcfd-track > .hcfd-slide' ) ].map( ( s ) =>
				s.getAttribute( 'aria-label' )
			)
		);

		expect( labels ).toEqual( [ '1 of 5', '2 of 5', '3 of 5', '4 of 5', '5 of 5' ] );
	} );
} );
