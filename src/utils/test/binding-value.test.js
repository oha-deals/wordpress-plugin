/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { bindingValue } from '../binding-value';

const item = {
	slug: 'a-b-c',
	name: 'Product',
	brand: '',
	priceFormatted: '€ 10.00',
	shop: null,
};

describe( 'bindingValue', () => {
	it( 'prefers the values of the server', () => {
		expect(
			bindingValue(
				{ ...item, bindings: { price: '10,00 €', name: null } },
				'price'
			)
		).toBe( '10,00 €' );
		expect(
			bindingValue( { ...item, bindings: { name: null } }, 'name' )
		).toBeUndefined();
	} );

	it( 'falls back to the picker item', () => {
		expect( bindingValue( item, 'name' ) ).toBe( 'Product' );
		expect( bindingValue( item, 'price' ) ).toBe( '€ 10.00' );
		expect( bindingValue( item, 'lowest_price' ) ).toBeUndefined();
	} );

	it( 'has no value for empty fields, unknown products and missing arguments', () => {
		expect( bindingValue( item, 'brand' ) ).toBeUndefined();
		expect( bindingValue( item, 'shop' ) ).toBeUndefined();
		expect( bindingValue( null, 'name' ) ).toBeUndefined();
		expect( bindingValue( undefined, 'name' ) ).toBeUndefined();
		expect( bindingValue( item, undefined ) ).toBeUndefined();
	} );
} );
