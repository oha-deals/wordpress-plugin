import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	__experimentalNumberControl as NumberControl, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { BlockEdit, CategorySelect } from '../../components';
import { deals as icon } from '../../components/icons';
import metadata from './block.json';

/**
 * Convert a NumberControl value to a number, or undefined when empty.
 *
 * @param {string|number|undefined} value Raw value.
 * @return {number|undefined} Parsed value.
 */
const toPrice = ( value ) => {
	if ( value === '' || value === undefined || value === null ) {
		return undefined;
	}
	const number = Number( value );
	return Number.isFinite( number ) && number >= 0 ? number : undefined;
};

export default function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { mode, category, sort, limit, columns, minPrice, maxPrice } =
		attributes;

	return (
		<BlockEdit
			{ ...props }
			metadata={ metadata }
			icon={ icon }
			label={ __( 'oha.deals Deals', 'oha-deals' ) }
		>
			<InspectorControls>
				<PanelBody title={ __( 'Filter', 'oha-deals' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Show', 'oha-deals' ) }
						value={ mode }
						options={ [
							{
								label: __( 'All deals', 'oha-deals' ),
								value: 'all',
							},
							{
								label: __( 'Price drops', 'oha-deals' ),
								value: 'drop',
							},
							{
								label: __( 'Lowest prices', 'oha-deals' ),
								value: 'low',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { mode: value } )
						}
					/>
					<CategorySelect
						value={ category }
						onChange={ ( value ) =>
							setAttributes( { category: value } )
						}
					/>
					<NumberControl
						label={ __( 'Minimum price (EUR)', 'oha-deals' ) }
						min={ 0 }
						value={ minPrice ?? '' }
						onChange={ ( value ) =>
							setAttributes( { minPrice: toPrice( value ) } )
						}
					/>
					<NumberControl
						label={ __( 'Maximum price (EUR)', 'oha-deals' ) }
						min={ 0 }
						value={ maxPrice ?? '' }
						onChange={ ( value ) =>
							setAttributes( { maxPrice: toPrice( value ) } )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'Display', 'oha-deals' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Sort by', 'oha-deals' ) }
						value={ sort }
						options={ [
							{
								label: __( 'Discount in percent', 'oha-deals' ),
								value: 'percent',
							},
							{
								label: __( 'Discount in euros', 'oha-deals' ),
								value: 'amount',
							},
							{
								label: __( 'Lowest price', 'oha-deals' ),
								value: 'price',
							},
							{
								label: __( 'Newest', 'oha-deals' ),
								value: 'new',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { sort: value } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Number of deals', 'oha-deals' ) }
						value={ limit }
						min={ 1 }
						max={ 24 }
						onChange={ ( value ) =>
							setAttributes( { limit: value ?? 6 } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Columns', 'oha-deals' ) }
						value={ columns }
						min={ 1 }
						max={ 4 }
						onChange={ ( value ) =>
							setAttributes( { columns: value ?? 3 } )
						}
					/>
				</PanelBody>
			</InspectorControls>
		</BlockEdit>
	);
}
