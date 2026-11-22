import { BaseControl, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { moveItem } from '../utils/attributes';
import SortableRow from './SortableRow';

/**
 * Checkbox list of fields; chosen fields can be reordered, the others follow.
 *
 * @param {Object}                                  props
 * @param {Array<{ value: string, label: string }>} props.options  All fields.
 * @param {string[]}                                props.value    Chosen fields in order.
 * @param {(fields: string[]) => void}              props.onChange Called with the new fields.
 */
export default function FieldChooser( { options, value, onChange } ) {
	const labels = Object.fromEntries(
		options.map( ( option ) => [ option.value, option.label ] )
	);
	const chosen = value.filter( ( field ) => labels[ field ] );
	const others = options
		.map( ( option ) => option.value )
		.filter( ( field ) => ! chosen.includes( field ) );

	return (
		<BaseControl
			__nextHasNoMarginBottom
			help={ __(
				'The order applies to the rows or columns.',
				'oha-deals'
			) }
		>
			<BaseControl.VisualLabel as="legend">
				{ __( 'Fields', 'oha-deals' ) }
			</BaseControl.VisualLabel>
			<ul className="oha-deals-sortable">
				{ chosen.map( ( field, index ) => (
					<SortableRow
						key={ field }
						name={ labels[ field ] }
						isFirst={ index === 0 }
						isLast={ index === chosen.length - 1 }
						onMove={ ( offset ) =>
							onChange( moveItem( chosen, index, offset ) )
						}
					>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ labels[ field ] }
							checked
							// The server falls back to all default fields for an empty list, so one field stays.
							disabled={ chosen.length === 1 }
							onChange={ () =>
								onChange(
									chosen.filter(
										( other ) => other !== field
									)
								)
							}
						/>
					</SortableRow>
				) ) }
				{ others.map( ( field ) => (
					<li key={ field } className="oha-deals-sortable__row">
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ labels[ field ] }
							checked={ false }
							onChange={ () => onChange( [ ...chosen, field ] ) }
						/>
					</li>
				) ) }
			</ul>
		</BaseControl>
	);
}
