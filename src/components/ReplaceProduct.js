import { Dropdown, ToolbarButton } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ProductPicker from './ProductPicker';

/**
 * Toolbar button with the product picker in a dropdown.
 *
 * @param {Object}                                       props
 * @param {string}                                       props.slug     Currently selected slug.
 * @param {string}                                       props.ean      Currently selected EAN.
 * @param {(value: {slug: string, ean: string}) => void} props.onSelect Called with the new product.
 */
export default function ReplaceProduct( { slug, ean, onSelect } ) {
	return (
		<Dropdown
			contentClassName="oha-deals-replace"
			popoverProps={ { placement: 'bottom-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<ToolbarButton
					aria-expanded={ isOpen }
					aria-haspopup="true"
					onClick={ onToggle }
				>
					{ __( 'Replace product', 'oha-deals' ) }
				</ToolbarButton>
			) }
			renderContent={ ( { onClose } ) => (
				<ProductPicker
					slug={ slug }
					ean={ ean }
					canRemove={ false }
					onSelect={ ( value ) => {
						onSelect( value );
						onClose();
					} }
				/>
			) }
		/>
	);
}
