import { __ } from '@wordpress/i18n';
import { withExample } from '../../components';
import { DealsExample } from '../../components/examples';
import { deals as icon } from '../../components/icons';
import { registerBlock } from '../../utils/register';
import { fromShortcode } from '../../utils/transforms';
import metadata from './block.json';
import Edit from './edit';

registerBlock( metadata, {
	icon,
	edit: withExample( Edit, DealsExample, () => 'oha-deals-deals' ),
	variations: [
		{
			name: 'all',
			isDefault: true,
			title: __( 'oha.deals Deals', 'oha-deals' ),
			description: __(
				'Price drops and lowest prices in a grid.',
				'oha-deals'
			),
			attributes: { mode: 'all' },
			isActive: [ 'mode' ],
			scope: [ 'inserter', 'transform' ],
		},
		{
			name: 'drop',
			title: __( 'oha.deals Price drops', 'oha-deals' ),
			description: __(
				'Products whose price has just dropped.',
				'oha-deals'
			),
			attributes: { mode: 'drop' },
			isActive: [ 'mode' ],
			scope: [ 'inserter', 'transform' ],
		},
		{
			name: 'low',
			title: __( 'oha.deals Lowest prices', 'oha-deals' ),
			description: __(
				'Products at their lowest recorded price.',
				'oha-deals'
			),
			attributes: { mode: 'low' },
			isActive: [ 'mode' ],
			scope: [ 'inserter', 'transform' ],
		},
	],
	transforms: {
		from: fromShortcode(
			'oha_deals_list',
			metadata.name,
			metadata.attributes
		),
	},
} );
