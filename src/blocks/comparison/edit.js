import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { BlockEdit, FieldChooser, ProductList } from '../../components';
import { comparison as icon } from '../../components/icons';
import metadata from './block.json';

/**
 * Most products in one comparison; the server enforces the same limit.
 */
export const MAX_PRODUCTS = 6;

export default function Edit( props ) {
	const { attributes, setAttributes } = props;
	const {
		slugs,
		fields,
		orientation,
		highlightBest,
		title,
		buttonText,
		numbered,
	} = attributes;
	const label = __( 'oha.deals Comparison', 'oha-deals' );

	const list = (
		<ProductList
			slugs={ slugs }
			max={ MAX_PRODUCTS }
			onChange={ ( value ) => setAttributes( { slugs: value } ) }
		/>
	);

	return (
		<BlockEdit
			{ ...props }
			metadata={ metadata }
			icon={ icon }
			label={ label }
			placeholder={
				// One product is nothing to compare; keep the picker until there are two.
				slugs.length < 2 && (
					<Placeholder
						icon={ icon.src }
						label={ label }
						instructions={ __(
							'Add at least two products to compare.',
							'oha-deals'
						) }
						className="oha-deals-placeholder"
					>
						{ list }
					</Placeholder>
				)
			}
		>
			<InspectorControls>
				<PanelBody title={ __( 'Products', 'oha-deals' ) }>
					{ list }
				</PanelBody>
				<PanelBody title={ __( 'Display', 'oha-deals' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Orientation', 'oha-deals' ) }
						value={ orientation }
						options={ [
							{
								label: __( 'Products as columns', 'oha-deals' ),
								value: 'columns',
							},
							{
								label: __( 'Products as rows', 'oha-deals' ),
								value: 'rows',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { orientation: value } )
						}
					/>
					{ orientation === 'rows' && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Number the products', 'oha-deals' ) }
							checked={ !! numbered }
							onChange={ ( value ) =>
								setAttributes( { numbered: value } )
							}
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Highlight the best price', 'oha-deals' ) }
						checked={ !! highlightBest }
						onChange={ ( value ) =>
							setAttributes( { highlightBest: value } )
						}
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Title', 'oha-deals' ) }
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Button text', 'oha-deals' ) }
						help={ __(
							'Leave empty for the default.',
							'oha-deals'
						) }
						value={ buttonText }
						onChange={ ( value ) =>
							setAttributes( { buttonText: value } )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'Fields', 'oha-deals' ) }>
					<FieldChooser
						options={ [
							{
								value: 'image',
								label: __( 'Image', 'oha-deals' ),
							},
							{
								value: 'brand',
								label: __( 'Brand', 'oha-deals' ),
							},
							{
								value: 'price',
								label: __( 'Best price', 'oha-deals' ),
							},
							{
								value: 'before',
								label: __( '30-day low', 'oha-deals' ),
							},
							{
								value: 'discount',
								label: __( 'Discount', 'oha-deals' ),
							},
							{ value: 'shop', label: __( 'Shop', 'oha-deals' ) },
							{
								value: 'offers',
								label: __( 'Number of offers', 'oha-deals' ),
							},
							{
								value: 'lowest',
								label: __( 'Lowest price so far', 'oha-deals' ),
							},
							{
								value: 'change',
								label: __(
									'Price change, 30 days',
									'oha-deals'
								),
							},
							{
								value: 'specs',
								label: __( 'Specifications', 'oha-deals' ),
							},
						] }
						value={ fields }
						onChange={ ( value ) =>
							setAttributes( { fields: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
		</BlockEdit>
	);
}
