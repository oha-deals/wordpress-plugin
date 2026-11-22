import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { BlockEdit, ProductPicker } from '../../components';
import { product as icon } from '../../components/icons';
import metadata from './block.json';

export default function Edit( props ) {
	const { attributes, setAttributes } = props;
	const {
		slug,
		ean,
		layout,
		showImage,
		offersLimit,
		showHistory,
		buttonText,
	} = attributes;

	return (
		<BlockEdit
			{ ...props }
			metadata={ metadata }
			needsProduct
			icon={ icon }
			label={ __( 'oha.deals Product', 'oha-deals' ) }
		>
			<InspectorControls>
				<PanelBody title={ __( 'Product', 'oha-deals' ) }>
					<ProductPicker
						slug={ slug }
						ean={ ean }
						onSelect={ ( value ) => setAttributes( value ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Display', 'oha-deals' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Layout', 'oha-deals' ) }
						value={ layout }
						options={ [
							{ label: __( 'Card', 'oha-deals' ), value: 'card' },
							{
								label: __( 'Compact', 'oha-deals' ),
								value: 'compact',
							},
							{
								label: __( 'Inline', 'oha-deals' ),
								value: 'inline',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show image', 'oha-deals' ) }
						checked={ !! showImage }
						onChange={ ( value ) =>
							setAttributes( { showImage: value } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Shop offers to list', 'oha-deals' ) }
						value={ offersLimit }
						min={ 0 }
						max={ 10 }
						onChange={ ( value ) =>
							setAttributes( { offersLimit: value ?? 3 } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show price history', 'oha-deals' ) }
						help={ __(
							'Needs an API key with the history capability.',
							'oha-deals'
						) }
						checked={ !! showHistory }
						onChange={ ( value ) =>
							setAttributes( { showHistory: value } )
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
			</InspectorControls>
		</BlockEdit>
	);
}
