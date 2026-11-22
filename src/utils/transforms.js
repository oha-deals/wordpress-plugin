import { createBlock } from '@wordpress/blocks';
import { next } from '@wordpress/shortcode';
import { shortcodeToAttributes } from './attributes';

/**
 * The shortcode when it is the whole text, else null.
 *
 * @param {string} tag  Shortcode tag.
 * @param {string} text Text of a Shortcode block.
 * @return {Object|null} Parsed shortcode.
 */
const soleShortcode = ( tag, text ) => {
	const trimmed = ( text || '' ).trim();
	const match = next( tag, trimmed );
	return match && match.content === trimmed ? match.shortcode : null;
};

/**
 * Transforms from a shortcode: pasted or converted text, and the core Shortcode block.
 *
 * @param {string}                 tag       Shortcode tag.
 * @param {string}                 blockName Block name.
 * @param {Object<string, Object>} schema    Attribute definitions from block.json.
 * @return {Object[]} `from` transforms.
 */
export function fromShortcode( tag, blockName, schema ) {
	const attributes = {};
	Object.keys( schema ).forEach( ( name ) => {
		attributes[ name ] = {
			type: schema[ name ].type,
			shortcode: ( { named } ) =>
				shortcodeToAttributes( named, schema )[ name ],
		};
	} );

	return [
		{ type: 'shortcode', tag, attributes },
		{
			type: 'block',
			blocks: [ 'core/shortcode' ],
			isMatch: ( { text } ) => !! soleShortcode( tag, text ),
			transform: ( { text } ) =>
				createBlock(
					blockName,
					shortcodeToAttributes(
						soleShortcode( tag, text ).attrs.named,
						schema
					)
				),
		},
	];
}

/**
 * Transform from other blocks about one product: the product is kept, everything else uses the defaults.
 *
 * @param {string}   blockName Target block.
 * @param {string[]} blocks    Source blocks.
 * @return {Object} `from` transform.
 */
export const fromProductBlocks = ( blockName, blocks ) => ( {
	type: 'block',
	blocks,
	transform: ( { slug, ean } ) => createBlock( blockName, { slug, ean } ),
} );
