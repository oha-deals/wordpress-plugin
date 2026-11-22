import { __ } from '@wordpress/i18n';
import { withExample } from '../../components';
import { ProductExample } from '../../components/examples';
import * as icons from '../../components/icons';
import registerBindings from '../../utils/bindings';
import { registerBlock } from '../../utils/register';
import { fromProductBlocks, fromShortcode } from '../../utils/transforms';
import metadata from './block.json';
import Edit from './edit';

registerBlock( metadata, {
	icon: icons.product,
	edit: withExample(
		Edit,
		ProductExample,
		( { layout } ) =>
			`oha-deals-product oha-deals-product--${ layout || 'card' }`
	),
	variations: [
		{
			name: 'card',
			isDefault: true,
			title: __( 'oha.deals Product', 'oha-deals' ),
			description: __(
				'A card with image, best price, shop offers and button.',
				'oha-deals'
			),
			icon: icons.layoutCard,
			attributes: { layout: 'card' },
			isActive: [ 'layout' ],
			scope: [ 'inserter', 'transform' ],
		},
		{
			name: 'compact',
			title: __( 'oha.deals Product, compact', 'oha-deals' ),
			description: __(
				'One row with image, best price and button.',
				'oha-deals'
			),
			icon: icons.layoutCompact,
			attributes: { layout: 'compact' },
			isActive: [ 'layout' ],
			scope: [ 'inserter', 'transform' ],
		},
		{
			name: 'inline',
			title: __( 'oha.deals Product, inline', 'oha-deals' ),
			description: __(
				'Name, best price and shop as one line of text.',
				'oha-deals'
			),
			icon: icons.layoutInline,
			attributes: { layout: 'inline' },
			isActive: [ 'layout' ],
			scope: [ 'inserter', 'transform' ],
		},
	],
	transforms: {
		from: [
			...fromShortcode(
				'oha_deals_product',
				metadata.name,
				metadata.attributes
			),
			fromProductBlocks( metadata.name, [
				'oha-deals/offers',
				'oha-deals/price-history',
			] ),
		],
	},
} );

registerBindings();
