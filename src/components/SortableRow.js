import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const arrow = ( path ) => (
	<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
		<path d={ path } fill="currentColor" />
	</svg>
);

const UP = arrow( 'M6.5 12.4 12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2Z' );
const DOWN = arrow( 'm17.5 11.6-5.5 4.4-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2Z' );

/**
 * A list row with buttons that move it up and down.
 *
 * @param {Object}                   props
 * @param {string}                   props.name     Name for the button labels.
 * @param {boolean}                  props.isFirst  Whether the row cannot move up.
 * @param {boolean}                  props.isLast   Whether the row cannot move down.
 * @param {(offset: number) => void} props.onMove   Called with -1 or 1.
 * @param {unknown}                  props.children Row content.
 * @param {unknown}                  props.after    Content after the buttons.
 */
export default function SortableRow( {
	name,
	isFirst,
	isLast,
	onMove,
	children,
	after,
} ) {
	return (
		<li className="oha-deals-sortable__row">
			<div className="oha-deals-sortable__content">{ children }</div>
			<Button
				size="small"
				icon={ UP }
				label={ sprintf(
					/* translators: %s: list item. */
					__( 'Move %s up', 'oha-deals' ),
					name
				) }
				// Stays focusable at the end of the list; moving further does nothing.
				aria-disabled={ isFirst }
				onClick={ () => onMove( -1 ) }
			/>
			<Button
				size="small"
				icon={ DOWN }
				label={ sprintf(
					/* translators: %s: list item. */
					__( 'Move %s down', 'oha-deals' ),
					name
				) }
				aria-disabled={ isLast }
				onClick={ () => onMove( 1 ) }
			/>
			{ after }
		</li>
	);
}
