import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { BlockEdit, ProductPicker } from '../../components';
import { offers as icon } from '../../components/icons';
import metadata from './block.json';

export default function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { slug, ean, limit, showShipping, includeUsed, showTitle } =
		attributes;

	return (
		<BlockEdit
			{ ...props }
			metadata={ metadata }
			needsProduct
			icon={ icon }
			label={ __( 'oha.deals Offers', 'oha-deals' ) }
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
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Number of offers', 'oha-deals' ) }
						value={ limit }
						min={ 1 }
						max={ 30 }
						onChange={ ( value ) =>
							setAttributes( { limit: value ?? 10 } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show shipping costs', 'oha-deals' ) }
						checked={ !! showShipping }
						onChange={ ( value ) =>
							setAttributes( { showShipping: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Include used and refurbished offers',
							'oha-deals'
						) }
						checked={ !! includeUsed }
						onChange={ ( value ) =>
							setAttributes( { includeUsed: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show title', 'oha-deals' ) }
						checked={ !! showTitle }
						onChange={ ( value ) =>
							setAttributes( { showTitle: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
		</BlockEdit>
	);
}
