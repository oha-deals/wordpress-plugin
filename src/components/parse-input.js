/**
 * Classify what an editor typed or pasted into the product picker.
 *
 * @param {string} raw Raw input.
 * @return {{type: string, value: string}} Type is one of url, ean, slug, search.
 */
export default function parseInput( raw ) {
	const value = ( raw || '' ).trim();

	const url = value.match(
		/^https?:\/\/(?:www\.)?oha\.deals\/produkt\/([a-z0-9][a-z0-9-]*)/i
	);
	if ( url ) {
		return { type: 'url', value: url[ 1 ].toLowerCase() };
	}

	if ( /^\d{8,14}$/.test( value ) ) {
		return { type: 'ean', value };
	}

	// A full slug has at least three hyphen-separated parts (brand-model-variant). Shorter tokens such as
	// "usb-c" or "wi-fi" are search terms.
	if ( /^[a-z0-9]+(?:-[a-z0-9]+){2,}$/i.test( value ) ) {
		return { type: 'slug', value: value.toLowerCase() };
	}

	return { type: 'search', value };
}
