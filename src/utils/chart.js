/**
 * Pure helpers for the price chart readout. A segment is a stretch of constant price, positioned in percent of the
 * plot: `{ x, end, y, date, price }`, sorted by `x`.
 */

/**
 * Limit a number to a range.
 *
 * @param {number} value Value.
 * @param {number} min   Lower bound.
 * @param {number} max   Upper bound.
 * @return {number} Clamped value; the lower bound when the value is not a number.
 */
export const clamp = ( value, min, max ) =>
	Math.min( max, Math.max( min, Number.isFinite( value ) ? value : min ) );

/**
 * Index of the segment whose price is valid at a horizontal position. Prices are steps, so the nearest change point
 * would be wrong in the middle of a long stretch. Positions before the first segment belong to the first one.
 *
 * @param {Array<{x: number}>} segments Segments.
 * @param {number}             x        Position in percent.
 * @return {number} Index, -1 without segments.
 */
export function segmentAt( segments, x ) {
	let low = 0;
	let high = segments.length - 1;
	if ( high < 0 ) {
		return -1;
	}
	while ( low < high ) {
		const middle = Math.ceil( ( low + high ) / 2 );
		if ( segments[ middle ].x <= x ) {
			low = middle;
		} else {
			high = middle - 1;
		}
	}
	return low;
}

/**
 * A pointer position as a percentage of the plot width.
 *
 * @param {number}                          clientX Pointer position.
 * @param {{ left: number, width: number }} rect    Bounding rectangle of the plot.
 * @return {number} 0 to 100.
 */
export const pointerPercent = ( clientX, rect ) =>
	rect.width > 0
		? clamp( ( ( clientX - rect.left ) / rect.width ) * 100, 0, 100 )
		: 0;

/**
 * The segment index after a key press, or null when the key is not handled. The time axis runs left to right in
 * every language, so left is always earlier.
 *
 * @param {string} key   `KeyboardEvent.key`.
 * @param {number} index Current index.
 * @param {number} count Number of segments.
 * @return {number|null} New index.
 */
export function indexAfterKey( key, index, count ) {
	switch ( key ) {
		case 'ArrowLeft':
		case 'ArrowDown':
			return clamp( index - 1, 0, count - 1 );
		case 'ArrowRight':
		case 'ArrowUp':
			return clamp( index + 1, 0, count - 1 );
		case 'PageDown':
			return clamp( index - 5, 0, count - 1 );
		case 'PageUp':
			return clamp( index + 5, 0, count - 1 );
		case 'Home':
			return 0;
		case 'End':
			return count - 1;
		default:
			return null;
	}
}
