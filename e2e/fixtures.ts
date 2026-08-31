/**
 * The pages the suite needs, and the block markup that makes each one.
 *
 * Kept in one place so global-setup can create them and the specs can name
 * them without repeating a slug in two files.
 */
export type Fixture = {
	slug: string;
	title: string;
	/** How many images the carousel should end up showing. */
	slides: number;
	content: ( ids: number[] ) => string;
};

const block = ( ids: number[], label = 'Test carousel' ) =>
	`<!-- wp:hcfd/carousel {"ids":[${ ids.join( ',' ) }],"ariaLabel":"${ label }"} /-->`;

export const FIXTURES: Record< string, Fixture > = {
	many: {
		slug: 'hcfd-e2e-many',
		title: 'HCFD e2e — several images',
		slides: 5,
		content: ( ids ) => block( ids.slice( 0, 5 ) ),
	},
	one: {
		slug: 'hcfd-e2e-one',
		title: 'HCFD e2e — a single image',
		slides: 1,
		content: ( ids ) => block( ids.slice( 0, 1 ) ),
	},
	none: {
		slug: 'hcfd-e2e-none',
		title: 'HCFD e2e — no image',
		slides: 0,
		content: () => block( [] ),
	},
	twice: {
		slug: 'hcfd-e2e-twice',
		title: 'HCFD e2e — two carousels',
		slides: 5,
		content: ( ids ) =>
			`${ block( ids.slice( 0, 3 ), 'First' ) }${ block( ids.slice( 3, 5 ), 'Second' ) }`,
	},
	hostile: {
		slug: 'hcfd-e2e-hostile',
		title: 'HCFD e2e — a hostile accessible name',
		slides: 2,
		content: ( ids ) =>
			`<!-- wp:hcfd/carousel {"ids":[${ ids
				.slice( 0, 2 )
				.join( ',' ) }],"ariaLabel":"\\u0022 onload=\\u0022alert(1)"} /-->`,
	},
};
