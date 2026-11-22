import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { BlockEdit } from '../../components';
import { coupons as icon } from '../../components/icons';
import metadata from './block.json';

export default function Edit( props ) {
	const { attributes, setAttributes } = props;
	const { host, limit } = attributes;

	return (
		<BlockEdit
			{ ...props }
			metadata={ metadata }
			icon={ icon }
			label={ __( 'oha.deals Coupons', 'oha-deals' ) }
		>
			<InspectorControls>
				<PanelBody title={ __( 'Coupons', 'oha-deals' ) }>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Shop domain', 'oha-deals' ) }
						help={ __(
							'Optional, for example example-shop.de. Leave empty for all shops.',
							'oha-deals'
						) }
						value={ host }
						onChange={ ( value ) =>
							setAttributes( {
								host: value.trim().toLowerCase(),
							} )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Number of coupons', 'oha-deals' ) }
						value={ limit }
						min={ 1 }
						max={ 20 }
						onChange={ ( value ) =>
							setAttributes( { limit: value ?? 5 } )
						}
					/>
				</PanelBody>
			</InspectorControls>
		</BlockEdit>
	);
}
