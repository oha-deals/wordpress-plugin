/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { clamp, indexAfterKey, pointerPercent, segmentAt } from '../chart';

const segments = [ { x: 0 }, { x: 40 }, { x: 40 }, { x: 90 } ];

describe( 'segmentAt', () => {
	it( 'returns the segment whose price is valid at the position', () => {
		expect( segmentAt( segments, 0 ) ).toBe( 0 );
		// Nearer to the change at 40, but the old price still holds.
		expect( segmentAt( segments, 39.9 ) ).toBe( 0 );
		expect( segmentAt( segments, 89.9 ) ).toBe( 2 );
		expect( segmentAt( segments, 90 ) ).toBe( 3 );
		expect( segmentAt( segments, 100 ) ).toBe( 3 );
	} );

	it( 'takes the last of several changes at the same position', () => {
		expect( segmentAt( segments, 40 ) ).toBe( 2 );
	} );

	it( 'uses the first segment before the recorded history', () => {
		expect( segmentAt( [ { x: 30 }, { x: 60 } ], 5 ) ).toBe( 0 );
	} );

	it( 'handles one and no segments', () => {
		expect( segmentAt( [ { x: 0 } ], 50 ) ).toBe( 0 );
		expect( segmentAt( [], 50 ) ).toBe( -1 );
	} );
} );

describe( 'pointerPercent', () => {
	const rect = { left: 100, width: 400 };

	it( 'maps the pointer to the plot and stays inside it', () => {
		expect( pointerPercent( 100, rect ) ).toBe( 0 );
		expect( pointerPercent( 200, rect ) ).toBe( 25 );
		expect( pointerPercent( 900, rect ) ).toBe( 100 );
		expect( pointerPercent( 0, rect ) ).toBe( 0 );
	} );

	it( 'does not divide by zero', () => {
		expect( pointerPercent( 100, { left: 0, width: 0 } ) ).toBe( 0 );
	} );
} );

describe( 'indexAfterKey', () => {
	it( 'moves with the arrow keys and stops at the ends', () => {
		expect( indexAfterKey( 'ArrowLeft', 3, 4 ) ).toBe( 2 );
		expect( indexAfterKey( 'ArrowLeft', 0, 4 ) ).toBe( 0 );
		expect( indexAfterKey( 'ArrowRight', 3, 4 ) ).toBe( 3 );
		expect( indexAfterKey( 'ArrowUp', 1, 4 ) ).toBe( 2 );
		expect( indexAfterKey( 'ArrowDown', 1, 4 ) ).toBe( 0 );
	} );

	it( 'jumps with Home, End and the page keys', () => {
		expect( indexAfterKey( 'Home', 3, 20 ) ).toBe( 0 );
		expect( indexAfterKey( 'End', 3, 20 ) ).toBe( 19 );
		expect( indexAfterKey( 'PageUp', 3, 20 ) ).toBe( 8 );
		expect( indexAfterKey( 'PageDown', 3, 20 ) ).toBe( 0 );
	} );

	it( 'ignores other keys', () => {
		expect( indexAfterKey( 'Tab', 1, 4 ) ).toBeNull();
		expect( indexAfterKey( 'a', 1, 4 ) ).toBeNull();
	} );
} );

describe( 'clamp', () => {
	it( 'handles values that are not numbers', () => {
		expect( clamp( NaN, 0, 5 ) ).toBe( 0 );
		expect( clamp( 9, 0, 5 ) ).toBe( 5 );
	} );
} );
