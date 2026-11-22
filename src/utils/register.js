import { registerBlockType } from '@wordpress/blocks';

/**
 * Registers a server-rendered block.
 *
 * @param {Object} metadata Contents of block.json; name, title and the rest come from the server.
 * @param {Object} settings Client settings (edit, icon, transforms, variations).
 */
export function registerBlock( metadata, settings ) {
	registerBlockType( metadata.name, {
		...settings,
		// Blocks without saved markup keep the anchor in the block comment. Core declares the attribute like this
		// since WordPress 7.0; before, it would be read from markup that does not exist.
		attributes: { ...metadata.attributes, anchor: { type: 'string' } },
		save: () => null,
	} );
}
