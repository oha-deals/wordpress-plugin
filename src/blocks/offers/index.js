import { withExample } from '../../components';
import { OffersExample } from '../../components/examples';
import { offers as icon } from '../../components/icons';
import { registerBlock } from '../../utils/register';
import { fromProductBlocks, fromShortcode } from '../../utils/transforms';
import metadata from './block.json';
import Edit from './edit';

registerBlock( metadata, {
	icon,
	edit: withExample( Edit, OffersExample, () => 'oha-deals-offers' ),
	transforms: {
		from: [
			...fromShortcode(
				'oha_deals_offers',
				metadata.name,
				metadata.attributes
			),
			fromProductBlocks( metadata.name, [
				'oha-deals/product',
				'oha-deals/price-history',
			] ),
		],
	},
} );
