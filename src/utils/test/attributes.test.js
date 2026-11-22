/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	EXAMPLE,
	collectSlugs,
	isExample,
	moveItem,
	ownAttributes,
	shortcodeToAttributes,
} from '../attributes';
import product from '../../blocks/product/block.json';
import deals from '../../blocks/deals/block.json';
import history from '../../blocks/price-history/block.json';
import comparison from '../../blocks/comparison/block.json';
import offers from '../../blocks/offers/block.json';
import coupons from '../../blocks/coupons/block.json';

describe( 'shortcodeToAttributes', () => {
	it( 'matches names without case, underscores and hyphens', () => {
		expect(
			shortcodeToAttributes(
				{
					slug: 'synology-ds925-plus',
					show_image: 'false',
					showhistory: 'yes',
					'button-text': 'Buy',
					offers_limit: '5',
				},
				product.attributes
			)
		).toEqual( {
			slug: 'synology-ds925-plus',
			showImage: false,
			showHistory: true,
			buttonText: 'Buy',
			offersLimit: 5,
		} );
	} );

	it( 'leaves out unknown names and values that do not fit', () => {
		expect(
			shortcodeToAttributes(
				{
					mode: 'everything',
					limit: 'many',
					columns: '',
					unknown: 'x',
					sort: 'PRICE',
				},
				deals.attributes
			)
		).toEqual( { sort: 'price' } );
		expect(
			shortcodeToAttributes( { days: '45' }, history.attributes )
		).toEqual( {} );
		expect(
			shortcodeToAttributes( { days: '365' }, history.attributes )
		).toEqual( { days: 365 } );
		expect(
			shortcodeToAttributes( { show_image: 'maybe' }, product.attributes )
		).toEqual( {} );
	} );

	it( 'splits lists', () => {
		expect(
			shortcodeToAttributes(
				{ slugs: ' a-b-c, d-e-f ,, ', fields: 'price,shop' },
				comparison.attributes
			)
		).toEqual( {
			slugs: [ 'a-b-c', 'd-e-f' ],
			fields: [ 'price', 'shop' ],
		} );
	} );

	it( 'handles missing attributes', () => {
		expect( shortcodeToAttributes( undefined, deals.attributes ) ).toEqual(
			{}
		);
	} );
} );

describe( 'isExample', () => {
	it( 'is true for every block.json example and false for real blocks', () => {
		[ product, deals, history, comparison, offers, coupons ].forEach(
			( { name, example } ) =>
				expect( [ name, isExample( example.attributes ) ] ).toEqual( [
					name,
					true,
				] )
		);
		expect( isExample( {} ) ).toBe( false );
		expect( isExample( { slug: 'synology-ds925-plus' } ) ).toBe( false );
		expect( isExample( { slugs: [] } ) ).toBe( false );
	} );

	it( 'uses a marker that cannot be a slug or a host', () => {
		expect( EXAMPLE ).not.toMatch( /^[a-z0-9][a-z0-9.-]*$/ );
	} );
} );

describe( 'ownAttributes', () => {
	it( 'drops support attributes and undefined values', () => {
		expect(
			ownAttributes(
				{
					mode: 'low',
					minPrice: undefined,
					backgroundColor: 'base',
					style: { spacing: {} },
					align: 'wide',
				},
				deals.attributes
			)
		).toEqual( { mode: 'low' } );
	} );
} );

describe( 'collectSlugs', () => {
	it( 'keeps order, drops duplicates and blocks without a slug, and limits', () => {
		expect(
			collectSlugs(
				[
					{ slug: 'a' },
					{ ean: '4711174725762' },
					{ slug: 'b' },
					{ slug: 'a' },
					{ slug: 'c' },
				],
				2
			)
		).toEqual( [ 'a', 'b' ] );
	} );
} );

describe( 'moveItem', () => {
	it( 'moves items within the bounds', () => {
		const list = [ 'a', 'b', 'c' ];
		expect( moveItem( list, 2, -1 ) ).toEqual( [ 'a', 'c', 'b' ] );
		expect( moveItem( list, 0, 1 ) ).toEqual( [ 'b', 'a', 'c' ] );
		expect( moveItem( list, 0, -1 ) ).toBe( list );
		expect( moveItem( list, 2, 1 ) ).toBe( list );
		expect( moveItem( list, 5, -1 ) ).toBe( list );
		expect( list ).toEqual( [ 'a', 'b', 'c' ] );
	} );
} );
