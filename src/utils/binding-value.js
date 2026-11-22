/**
 * Picker item properties that match fields of the Block Bindings source.
 */
const ITEM_FIELDS = {
	name: 'name',
	brand: 'brand',
	price: 'priceFormatted',
	shop: 'shop',
	image: 'image',
	url: 'url',
};

/**
 * One value of the Block Bindings source for the editor. The REST answer carries the values of the front end in
 * `bindings`; without it, the fields that the picker item has are used.
 *
 * @param {Object|null|undefined} product Answer of `GET oha-deals/v1/product`.
 * @param {string|undefined}      field   Field name.
 * @return {string|undefined} Plain text; undefined when there is no value.
 */
export function bindingValue( product, field ) {
	if ( ! product || ! field ) {
		return undefined;
	}
	const value = product.bindings
		? product.bindings[ field ]
		: product[ ITEM_FIELDS[ field ] ];
	return value === null || value === undefined || value === ''
		? undefined
		: String( value );
}
