import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { BlockEdit, ProductPicker } from '../../components';
import { history as icon } from '../../components/icons';
import metadata from './block.json';

export default function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { slug, ean, days, height } = attributes;

	return (
		<BlockEdit
			{ ...props }
			metadata={ metadata }
			needsProduct
			icon={ icon }
			label={ __( 'oha.deals Price History', 'oha-deals' ) }
		>
			<InspectorControls>
				<PanelBody title={ __( 'Product', 'oha-deals' ) }>
					<ProductPicker
						slug={ slug }
						ean={ ean }
						onSelect={ ( value ) => setAttributes( value ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Chart', 'oha-deals' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Period', 'oha-deals' ) }
						help={ __(
							'Needs an API key with the history capability.',
							'oha-deals'
						) }
						value={ String( days ) }
						options={ [
							{
								label: __( '30 days', 'oha-deals' ),
								value: '30',
							},
							{
								label: __( '90 days', 'oha-deals' ),
								value: '90',
							},
							{
								label: __( '180 days', 'oha-deals' ),
								value: '180',
							},
							{
								label: __( '365 days', 'oha-deals' ),
								value: '365',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { days: parseInt( value, 10 ) } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Height (px)', 'oha-deals' ) }
						value={ height }
						min={ 120 }
						max={ 600 }
						step={ 10 }
						onChange={ ( value ) =>
							setAttributes( { height: value ?? 220 } )
						}
					/>
				</PanelBody>
			</InspectorControls>
		</BlockEdit>
	);
}
