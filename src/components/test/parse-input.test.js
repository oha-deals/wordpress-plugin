/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import parseInput from '../parse-input';

describe( 'parseInput', () => {
	it( 'reads the slug from an oha.deals product URL', () => {
		expect(
			parseInput( 'https://oha.deals/produkt/Synology-DS925-plus?x=1' )
		).toEqual( { type: 'url', value: 'synology-ds925-plus' } );
		expect( parseInput( 'http://www.oha.deals/produkt/usb-c' ) ).toEqual( {
			type: 'url',
			value: 'usb-c',
		} );
	} );

	it( 'treats 8 to 14 digits as an EAN', () => {
		expect( parseInput( ' 4711174725762 ' ) ).toEqual( {
			type: 'ean',
			value: '4711174725762',
		} );
		expect( parseInput( '12345678' ).type ).toBe( 'ean' );
		expect( parseInput( '1234567' ).type ).toBe( 'search' );
		expect( parseInput( '123456789012345' ).type ).toBe( 'search' );
	} );

	it( 'treats a full slug with three or more parts as a slug', () => {
		expect( parseInput( 'Synology-DS925-Plus' ) ).toEqual( {
			type: 'slug',
			value: 'synology-ds925-plus',
		} );
	} );

	it( 'sends short hyphenated words to the search', () => {
		expect( parseInput( 'usb-c' ) ).toEqual( {
			type: 'search',
			value: 'usb-c',
		} );
		expect( parseInput( 'wi-fi' ).type ).toBe( 'search' );
	} );

	it( 'sends free text and empty input to the search', () => {
		expect( parseInput( 'synology nas' ) ).toEqual( {
			type: 'search',
			value: 'synology nas',
		} );
		expect( parseInput( '' ) ).toEqual( { type: 'search', value: '' } );
		expect( parseInput( undefined ) ).toEqual( {
			type: 'search',
			value: '',
		} );
	} );
} );
