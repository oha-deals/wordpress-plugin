import apiFetch from '@wordpress/api-fetch';
import * as blocks from '@wordpress/blocks';
import {
	createReduxStore,
	register,
	resolveSelect,
	select as globalSelect,
} from '@wordpress/data';
import { addAction } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { bindingValue } from './binding-value';
import { PRODUCTS_CHANGED } from './events';

const SOURCE = 'oha-deals/product';
const STORE = 'oha-deals/products';
const REFRESH_MS = 500;

/**
 * Products of the Block Bindings source, loaded on first use.
 */
const store = createReduxStore( STORE, {
	reducer( state = {}, action ) {
		return action.type === 'RECEIVE'
			? { ...state, [ action.id ]: action.product }
			: state;
	},
	actions: {
		receive: ( id, product ) => ( { type: 'RECEIVE', id, product } ),
	},
	selectors: {
		getProduct: ( state, id ) => state[ id ],
	},
	resolvers: {
		getProduct:
			( id ) =>
			async ( { dispatch } ) => {
				const args = /^\d{8,14}$/.test( id )
					? { ean: id }
					: { slug: id };
				try {
					const response = await apiFetch( {
						path: addQueryArgs( '/oha-deals/v1/product', args ),
					} );
					dispatch.receive( id, response?.data ?? response );
				} catch {
					dispatch.receive( id, null );
				}
			},
	},
} );

/**
 * Fields offered in the editor.
 *
 * @return {Array<[string, string]>} Field and label.
 */
const fields = () => [
	[ 'name', __( 'Name', 'oha-deals' ) ],
	[ 'brand', __( 'Brand', 'oha-deals' ) ],
	[ 'spec', __( 'Short description', 'oha-deals' ) ],
	[ 'category', __( 'Category', 'oha-deals' ) ],
	[ 'price', __( 'Best price', 'oha-deals' ) ],
	[ 'before_price', __( '30-day low', 'oha-deals' ) ],
	[ 'discount', __( 'Discount', 'oha-deals' ) ],
	[ 'shop', __( 'Shop', 'oha-deals' ) ],
	[ 'offer_count', __( 'Number of offers', 'oha-deals' ) ],
	[ 'lowest_price', __( 'Lowest price so far', 'oha-deals' ) ],
	[ 'lowest_date', __( 'Date of the lowest price', 'oha-deals' ) ],
	[ 'image', __( 'Image', 'oha-deals' ) ],
	[ 'image_alt', __( 'Image description', 'oha-deals' ) ],
	[ 'url', __( 'Link', 'oha-deals' ) ],
	[ 'rel', __( 'Link relation', 'oha-deals' ) ],
	[ 'link_label', __( 'Link text', 'oha-deals' ) ],
	[ 'disclosure', __( 'Disclosure', 'oha-deals' ) ],
	[ 'checked_at', __( 'Time of the price check', 'oha-deals' ) ],
];

/**
 * The slugs of the products that the open post already shows; the field list offers their values.
 *
 * @return {string[]} Unique slugs.
 */
function usedSlugs() {
	const { getClientIdsWithDescendants, getBlockAttributes, getBlockName } =
		globalSelect( 'core/block-editor' );
	const slugs = getClientIdsWithDescendants()
		.filter( ( id ) => getBlockName( id )?.startsWith( 'oha-deals/' ) )
		.flatMap( ( id ) => {
			const { slug, slugs: list } = getBlockAttributes( id ) || {};
			return list || [ slug ];
		} );
	return [ ...new Set( slugs.filter( Boolean ) ) ];
}

const getValues = ( { select, bindings } ) =>
	Object.fromEntries(
		Object.entries( bindings ).map( ( [ attribute, { args } ] ) => {
			const id = args?.slug || args?.ean;
			return [
				attribute,
				bindingValue(
					id ? select( STORE ).getProduct( id ) : null,
					args?.field
				),
			];
		} )
	);

/**
 * The field list for the products in use.
 *
 * @param {string[]} slugs Product slugs.
 * @return {Promise<Object[]>} Fields with label, type and source arguments.
 */
async function fieldsList( slugs ) {
	const products = await Promise.all(
		slugs.map( ( slug ) => resolveSelect( STORE ).getProduct( slug ) )
	);
	return slugs.flatMap( ( slug, index ) =>
		fields().map( ( [ field, label ] ) => ( {
			label: sprintf(
				/* translators: 1: product name, 2: field, e.g. "Best price". */
				__( '%1$s: %2$s', 'oha-deals' ),
				products[ index ]?.name || slug,
				label
			),
			type: 'string',
			args: { slug, field },
		} ) )
	);
}

/**
 * Returns a function that registers the source again with the fields of the products in use. Core keeps the list of a
 * registered source, so a changed list needs a new registration.
 *
 * @param {string} label Label from the server.
 * @return {() => Promise<void>} Refresh function.
 */
function fieldsRefresher( label ) {
	const { registerBlockBindingsSource, unregisterBlockBindingsSource } =
		blocks;
	let lastKey = '';

	return async () => {
		const slugs = usedSlugs();
		const key = slugs.join();
		if ( key === lastKey ) {
			return;
		}
		lastKey = key;
		const list = await fieldsList( slugs );
		if ( key === lastKey ) {
			unregisterBlockBindingsSource( SOURCE );
			registerBlockBindingsSource( {
				name: SOURCE,
				label,
				getValues,
				getFieldsList: () => list,
			} );
		}
	};
}

/**
 * Shows the values of the server's Block Bindings source in the editor. The client API exists since WordPress 6.7;
 * before, the editor shows the source name and the values appear on the site. Since WordPress 6.9, the Attributes
 * panel also offers the values of the products in the post.
 */
export default function registerBindings() {
	const { registerBlockBindingsSource, getBlockBindingsSource } = blocks;
	if ( ! registerBlockBindingsSource || ! getBlockBindingsSource ) {
		return;
	}
	// Bootstrapped from the server with its label.
	const server = getBlockBindingsSource( SOURCE );
	if ( ! server || server.getValues ) {
		return;
	}

	register( store );
	registerBlockBindingsSource( { name: SOURCE, getValues } );

	const refresh = fieldsRefresher( server.label );
	let timer;
	addAction( PRODUCTS_CHANGED, 'oha-deals/bindings', () => {
		// The setting that came with field lists for every source; earlier versions expect another format.
		const { __experimentalBlockBindingsSupportedAttributes: supported } =
			globalSelect( 'core/block-editor' )?.getSettings() || {};
		if ( supported ) {
			clearTimeout( timer );
			timer = setTimeout( refresh, REFRESH_MS );
		}
	} );
}
