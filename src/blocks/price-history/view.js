/**
 * Price chart readout: shows the period and price under the pointer, or of the stretch chosen with the arrow keys.
 * The chart itself is complete without this module.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';
import { indexAfterKey, pointerPercent, segmentAt } from '../../utils/chart';

/**
 * Moves the readout to a segment.
 *
 * @param {Object} context Chart context.
 * @param {number} index   Segment index.
 * @param {number} x       Horizontal position in percent.
 */
const moveTo = ( context, index, x ) => {
	const segment = context.segments[ index ];
	if ( ! segment ) {
		return;
	}
	context.index = index;
	context.date = segment.date;
	context.price = segment.price;
	context.text = `${ segment.date }, ${ segment.price }`;
	context.style = `--x:${ x }%;--y:${ segment.y }%`;
};

store( 'oha-deals/price-history', {
	actions: {
		point( event ) {
			const context = getContext();
			// There is no line before the first recorded price.
			const x = Math.max(
				context.segments[ 0 ].x,
				pointerPercent(
					event.clientX,
					getElement().ref.getBoundingClientRect()
				)
			);
			moveTo( context, segmentAt( context.segments, x ), x );
			context.active = true;
		},
		leave() {
			const { ref } = getElement();
			if ( ref.ownerDocument.activeElement !== ref ) {
				getContext().active = false;
			}
		},
		show() {
			getContext().active = true;
		},
		hide() {
			getContext().active = false;
		},
	},
	callbacks: {
		init() {
			const context = getContext();
			const { ref } = getElement();

			// A plain listener: preventDefault() needs a synchronous handler, and the API's withSyncEvent() does not
			// exist before WordPress 6.8 (a missing import would stop the whole module there).
			const onKeyDown = ( event ) => {
				const index = indexAfterKey(
					event.key,
					context.index,
					context.segments.length
				);
				if ( index === null ) {
					return;
				}
				event.preventDefault();
				const { x, end } = context.segments[ index ];
				moveTo( context, index, ( x + end ) / 2 );
				context.active = true;
			};
			ref.addEventListener( 'keydown', onKeyDown );
			context.ready = true;

			return () => ref.removeEventListener( 'keydown', onKeyDown );
		},
	},
} );
