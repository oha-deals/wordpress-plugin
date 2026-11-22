import { withExample } from '../../components';
import { CouponsExample } from '../../components/examples';
import { coupons as icon } from '../../components/icons';
import { registerBlock } from '../../utils/register';
import { fromShortcode } from '../../utils/transforms';
import metadata from './block.json';
import Edit from './edit';

registerBlock( metadata, {
	icon,
	edit: withExample( Edit, CouponsExample, () => 'oha-deals-coupons' ),
	transforms: {
		from: fromShortcode(
			'oha_deals_coupons',
			metadata.name,
			metadata.attributes
		),
	},
} );
