import type { Page } from '@playwright/test';

/** The slide currently on screen, by its "n of m" label. */
export async function visibleSlide( page: Page, root = '.hcfd-carousel' ): Promise< string | null > {
	return page.evaluate( ( selector ) => {
		const carousel = document.querySelector( selector );
		const shown = [ ...( carousel?.querySelectorAll( '.hcfd-slide' ) ?? [] ) ].filter(
			( slide ) => ! slide.hasAttribute( 'hidden' )
		);
		return shown[ 0 ]?.getAttribute( 'aria-label' ) ?? null;
	}, root );
}

/** Waits until the burst has landed and the carousel holds every slide. */
export async function waitForBurst( page: Page, slides: number ): Promise< void > {
	await page.waitForFunction(
		( expected ) => document.querySelectorAll( '.hcfd-slide:not(noscript .hcfd-slide)' ).length >= expected,
		slides,
		{ timeout: 15_000 }
	);
}

/**
 * The stream URL the page asked for, taken from the rendered markup.
 *
 * Rebuilding it in the test would mean recomputing the signature, which is
 * exactly the thing under test.
 */
export async function streamUrl( page: Page ): Promise< string > {
	const expression = await page.getAttribute( '.hcfd-carousel', 'data-init__delay.500ms' );
	const match = expression?.match( /@get\('([^']+)'\)/ );

	if ( ! match ) {
		throw new Error( `No stream URL in: ${ expression }` );
	}

	return match[ 1 ];
}
