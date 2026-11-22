import { createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { withExample } from '../../components';
import { ComparisonExample } from '../../components/examples';
import * as icons from '../../components/icons';
import { collectSlugs } from '../../utils/attributes';
import { registerBlock } from '../../utils/register';
import { fromShortcode } from '../../utils/transforms';
import metadata from './block.json';
import Edit, { MAX_PRODUCTS } from './edit';

registerBlock( metadata, {
	icon: icons.comparison,
	edit: withExample(
		Edit,
		ComparisonExample,
		( { orientation } ) =>
			`oha-deals-comparison oha-deals-comparison--${ orientation }`
	),
	variations: [
		{
			name: 'columns',
			isDefault: true,
			title: __( 'oha.deals Comparison table', 'oha-deals' ),
			description: __(
				'Products side by side, one column each.',
				'oha-deals'
			),
			icon: icons.comparison,
			attributes: { orientation: 'columns', numbered: false },
			isActive: [ 'orientation' ],
			scope: [ 'inserter', 'transform' ],
		},
		{
			name: 'rows',
			title: __( 'oha.deals Top list', 'oha-deals' ),
			description: __(
				'A numbered list of products, one row each.',
				'oha-deals'
			),
			icon: icons.topList,
			attributes: { orientation: 'rows', numbered: true },
			isActive: [ 'orientation' ],
			scope: [ 'inserter', 'transform' ],
		},
	],
	transforms: {
		from: [
			...fromShortcode(
				'oha_deals_comparison',
				metadata.name,
				metadata.attributes
			),
			{
				type: 'block',
				isMultiBlock: true,
				blocks: [ 'oha-deals/product' ],
				isMatch: ( blocks ) =>
					collectSlugs( [ blocks ].flat(), MAX_PRODUCTS ).length > 0,
				transform: ( blocks ) =>
					createBlock( metadata.name, {
						slugs: collectSlugs( [ blocks ].flat(), MAX_PRODUCTS ),
					} ),
			},
		],
	},
} );
