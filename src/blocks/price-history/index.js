import { withExample } from '../../components';
import { HistoryExample } from '../../components/examples';
import { history as icon } from '../../components/icons';
import { registerBlock } from '../../utils/register';
import { fromProductBlocks, fromShortcode } from '../../utils/transforms';
import metadata from './block.json';
import Edit from './edit';

registerBlock( metadata, {
	icon,
	edit: withExample( Edit, HistoryExample, () => 'oha-deals-price-history' ),
	transforms: {
		from: [
			...fromShortcode(
				'oha_deals_history',
				metadata.name,
				metadata.attributes
			),
			fromProductBlocks( metadata.name, [
				'oha-deals/product',
				'oha-deals/offers',
			] ),
		],
	},
} );
