/**
 * Pure attribute helpers: shortcode mapping, the example marker and list edits.
 */

/**
 * Value that marks the attributes of a block.json `example`. It is never a valid slug, category or host, so a real
 * block cannot carry it. The edit component then shows a static illustration instead of a live preview.
 */
export const EXAMPLE = '__example__';

/**
 * Whether the attributes come from a block.json `example`.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} True for the inserter preview.
 */
export const isExample = ( attributes ) =>
	[
		attributes.slug,
		attributes.category,
		attributes.host,
		attributes.slugs?.[ 0 ],
	].includes( EXAMPLE );

/**
 * Attribute name without case and separators, as the PHP side compares them.
 *
 * @param {string} name Attribute name.
 * @return {string} Comparable name.
 */
const comparable = ( name ) => name.toLowerCase().replace( /[_-]/g, '' );

/**
 * Coerces a shortcode value to the type of a block attribute.
 *
 * @param {string} raw    Raw value.
 * @param {Object} schema Attribute definition from block.json.
 * @return {string|number|boolean|string[]|undefined} Value, undefined when it does not fit.
 */
function coerce( raw, schema ) {
	const value = String( raw ).trim();
	const allowed = ( candidate ) =>
		! schema.enum || schema.enum.includes( candidate )
			? candidate
			: undefined;

	switch ( schema.type ) {
		case 'boolean':
			if ( /^(1|true|yes|on)$/i.test( value ) ) {
				return true;
			}
			return /^(0|false|no|off)$/i.test( value ) ? false : undefined;
		case 'number':
			return value !== '' && Number.isFinite( Number( value ) )
				? allowed( Number( value ) )
				: undefined;
		case 'array':
			return value
				.split( ',' )
				.map( ( item ) => item.trim() )
				.filter( Boolean );
		default:
			return allowed( schema.enum ? value.toLowerCase() : value );
	}
}

/**
 * Maps shortcode attributes (`show_image="false"`) onto block attributes (`showImage: false`). Unknown names and
 * values that do not fit are left out; ranges are enforced by the server.
 *
 * @param {Object<string, string>} named  Named shortcode attributes.
 * @param {Object<string, Object>} schema Attribute definitions from block.json.
 * @return {Object} Block attributes.
 */
export function shortcodeToAttributes( named, schema ) {
	const names = new Map(
		Object.keys( schema ).map( ( name ) => [ comparable( name ), name ] )
	);
	const attributes = {};
	Object.entries( named || {} ).forEach( ( [ key, raw ] ) => {
		const name = names.get( comparable( key ) );
		const value = name ? coerce( raw, schema[ name ] ) : undefined;
		if ( value !== undefined ) {
			attributes[ name ] = value;
		}
	} );
	return attributes;
}

/**
 * The attributes a block definition knows, without those added by block supports (colours, spacing, …). The preview
 * request sends only these: support styles belong to the block wrapper in the editor.
 *
 * @param {Object}                 attributes All attributes.
 * @param {Object<string, Object>} schema     Attribute definitions from block.json.
 * @return {Object} Own attributes; undefined values are left out.
 */
export const ownAttributes = ( attributes, schema ) =>
	Object.fromEntries(
		Object.keys( schema )
			.filter( ( name ) => attributes[ name ] !== undefined )
			.map( ( name ) => [ name, attributes[ name ] ] )
	);

/**
 * The slugs of several product blocks for a comparison: unique, in order, limited.
 *
 * @param {Array<{slug?: string}>} blocks Attributes of the product blocks.
 * @param {number}                 max    Most slugs.
 * @return {string[]} Slugs.
 */
export const collectSlugs = ( blocks, max ) =>
	[
		...new Set( blocks.map( ( { slug } ) => slug ).filter( Boolean ) ),
	].slice( 0, max );

/**
 * Moves a list item.
 *
 * @param {Array}  list   List.
 * @param {number} index  Index of the item.
 * @param {number} offset Positions to move, negative for up.
 * @return {Array} New list; the same list when the item cannot move there.
 */
export function moveItem( list, index, offset ) {
	const target = index + offset;
	if ( index < 0 || index >= list.length || target < 0 ) {
		return list;
	}
	if ( target >= list.length ) {
		return list;
	}
	const moved = [ ...list ];
	moved.splice( target, 0, ...moved.splice( index, 1 ) );
	return moved;
}
