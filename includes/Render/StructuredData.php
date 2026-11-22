<?php
/**
 * Schema.org JSON-LD for product elements.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Render;

use OhaDeals\Model\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Builds `Product` + `AggregateOffer` markup from the visible, non-Amazon offers.
 *
 * Amazon prices are left out because their mandatory timestamp cannot be expressed in the markup. Printed only for
 * the main post of a singular page, once per product.
 */
final class StructuredData {

	/**
	 * Slugs already printed on this page, so a product appears once even when embedded twice.
	 *
	 * @var array<string, bool>
	 */
	private array $printed = array();

	/**
	 * The `<script type="application/ld+json">` element for a product, or an empty string.
	 *
	 * @param Product $product Product.
	 * @return string
	 */
	public function script( Product $product ): string {
		if ( ! self::is_main_content() || isset( $this->printed[ $product->slug() ] ) ) {
			return '';
		}

		$data = $this->build( $product );

		/**
		 * Filters the JSON-LD data of a product element. Return an empty array to print nothing.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data    schema.org data.
		 * @param Product              $product Product.
		 */
		$data = (array) apply_filters( 'oha_deals_structured_data', $data, $product );
		if ( ! $data ) {
			return '';
		}

		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( false === $json ) {
			return '';
		}
		$this->printed[ $product->slug() ] = true;

		return '<script type="application/ld+json">' . $json . '</script>';
	}

	/**
	 * Whether the element is rendered as part of the main post on a singular page.
	 *
	 * JSON-LD is printed only there. Renders elsewhere (excerpts, widgets, REST previews, content that SEO plugins
	 * render and throw away) print none and do not use up the once-per-product slot.
	 *
	 * @return bool
	 */
	public static function is_main_content(): bool {
		return is_singular() && in_the_loop() && is_main_query();
	}

	/**
	 * The schema.org data, or an empty array when no offer has a visible non-Amazon price.
	 *
	 * @param Product $product Product.
	 * @return array<string, mixed>
	 */
	public function build( Product $product ): array {
		$prices = array();
		foreach ( $product->offers() as $offer ) {
			$price = $offer->price();
			if ( null !== $price && ! $offer->is_price_hidden() && ! $offer->is_amazon() ) {
				$prices[] = $price;
			}
		}
		if ( ! $prices || '' === $product->name() ) {
			return array();
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => $product->name(),
			'offers'   => array(
				'@type'         => 'AggregateOffer',
				'priceCurrency' => $product->currency(),
				'lowPrice'      => round( min( $prices ), 2 ),
				'highPrice'     => round( max( $prices ), 2 ),
				'offerCount'    => count( $prices ),
			),
		);
		if ( '' !== $product->brand() ) {
			$data['brand'] = array(
				'@type' => 'Brand',
				'name'  => $product->brand(),
			);
		}
		if ( null !== $product->ean() ) {
			$data['gtin'] = $product->ean();
		}
		if ( null !== $product->image() ) {
			$data['image'] = $product->image();
		}

		return $data;
	}
}
