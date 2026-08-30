/**
 * Editor code for the Hypermedia Carousel block.
 *
 * Written against wp.element.createElement rather than JSX, so that the plugin
 * ships with no build step at all: what is published is what runs, which is
 * both easier to review and one fewer thing to keep in sync.
 *
 * The preview is a ServerSideRender of render.php, so the editor and the front
 * end can never disagree about what a slide looks like.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;

	var blockEditor = wp.blockEditor;
	var components = wp.components;
	var ServerSideRender = wp.serverSideRender;

	var SIZES = [
		{ label: __( 'Thumbnail', 'hypermedia-carousel-for-datastar' ), value: 'thumbnail' },
		{ label: __( 'Medium', 'hypermedia-carousel-for-datastar' ), value: 'medium' },
		{ label: __( 'Large', 'hypermedia-carousel-for-datastar' ), value: 'large' },
		{ label: __( 'Full size', 'hypermedia-carousel-for-datastar' ), value: 'full' }
	];

	/**
	 * Panel shown in the sidebar whatever the state of the block.
	 *
	 * @param {Object} props Block props.
	 * @return {Object} Element.
	 */
	function Sidebar( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;

		return el(
			blockEditor.InspectorControls,
			null,
			el(
				components.PanelBody,
				{ title: __( 'Carousel', 'hypermedia-carousel-for-datastar' ) },
				el( components.SelectControl, {
					label: __( 'Image size', 'hypermedia-carousel-for-datastar' ),
					value: attributes.sizeSlug,
					options: SIZES,
					__nextHasNoMarginBottom: true,
					onChange: function ( value ) {
						setAttributes( { sizeSlug: value } );
					}
				} ),
				el( components.TextControl, {
					label: __( 'Accessible name', 'hypermedia-carousel-for-datastar' ),
					help: __( 'Announced to screen readers, for example “Life at the school”. Leave empty for a generic name.', 'hypermedia-carousel-for-datastar' ),
					value: attributes.ariaLabel,
					__nextHasNoMarginBottom: true,
					onChange: function ( value ) {
						setAttributes( { ariaLabel: value } );
					}
				} ),
				el(
					blockEditor.MediaUploadCheck,
					null,
					el( blockEditor.MediaUpload, {
						multiple: 'add',
						gallery: true,
						allowedTypes: [ 'image' ],
						value: attributes.ids,
						onSelect: function ( media ) {
							setAttributes( {
								ids: media.map( function ( item ) {
									return item.id;
								} )
							} );
						},
						render: function ( open ) {
							return el(
								components.Button,
								{ variant: 'secondary', onClick: open.open },
								attributes.ids.length
									? __( 'Edit images', 'hypermedia-carousel-for-datastar' )
									: __( 'Add images', 'hypermedia-carousel-for-datastar' )
							);
						}
					} )
				),
				el(
					'p',
					{ style: { marginTop: '1em', fontStyle: 'italic' } },
					__( 'The seconds each slide stays on screen are set once for the whole site, under Settings → Hypermedia Carousel.', 'hypermedia-carousel-for-datastar' )
				)
			)
		);
	}

	wp.blocks.registerBlockType( 'hcfd/carousel', {
		edit: function ( props ) {
			var blockProps = blockEditor.useBlockProps();
			var attributes = props.attributes;

			var body = attributes.ids.length
				? el( ServerSideRender, {
					block: 'hcfd/carousel',
					attributes: attributes
				} )
				: el( blockEditor.MediaPlaceholder, {
					icon: 'images-alt2',
					labels: {
						title: __( 'Hypermedia Carousel', 'hypermedia-carousel-for-datastar' ),
						instructions: __( 'Pick the images this carousel rotates through. One image never rotates; two or more start the loop.', 'hypermedia-carousel-for-datastar' )
					},
					multiple: true,
					gallery: true,
					allowedTypes: [ 'image' ],
					onSelect: function ( media ) {
						props.setAttributes( {
							ids: media.map( function ( item ) {
								return item.id;
							} )
						} );
					}
				} );

			return el(
				Fragment,
				null,
				el( Sidebar, props ),
				el( 'div', blockProps, body )
			);
		},

		// A dynamic block keeps nothing in post content but its own comment, so
		// deactivating the plugin leaves no broken markup behind -- only the
		// absence of a carousel.
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
