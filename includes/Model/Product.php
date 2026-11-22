<?php
/**
 * A product: summary or detail.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable product built from a Partner API `ProductSummary` or `Product` object.
 * Detail-only fields are empty for summaries.
 */
final class Product {

	/**
	 * Numeric ID.
	 *
	 * @var int
	 */
	private int $id = 0;

	/**
	 * Slug.
	 *
	 * @var string
	 */
	private string $slug = '';

	/**
	 * Name.
	 *
	 * @var string
	 */
	private string $name = '';

	/**
	 * Brand.
	 *
	 * @var string
	 */
	private string $brand = '';

	/**
	 * Category slug.
	 *
	 * @var string
	 */
	private string $category_slug = '';

	/**
	 * Category name.
	 *
	 * @var string
	 */
	private string $category_name = '';

	/**
	 * EAN / GTIN.
	 *
	 * @var string|null
	 */
	private ?string $ean = null;

	/**
	 * Main image (https), or null.
	 *
	 * @var string|null
	 */
	private ?string $image = null;

	/**
	 * Best price, or null when hidden or no current offer.
	 *
	 * @var float|null
	 */
	private ?float $price = null;

	/**
	 * Whether the best price is withheld for licence reasons.
	 *
	 * @var bool
	 */
	private bool $price_hidden = false;

	/**
	 * Currency code.
	 *
	 * @var string
	 */
	private string $currency = 'EUR';

	/**
	 * Shop of the best price.
	 *
	 * @var string|null
	 */
	private ?string $shop = null;

	/**
	 * Number of offers.
	 *
	 * @var int
	 */
	private int $offer_count = 0;

	/**
	 * Percent changes keyed d1, d7, d30 (null when unknown).
	 *
	 * @var array<string, float|null>
	 */
	private array $change = array();

	/**
	 * Reference ("before") price from the API, or null when no discount may be shown.
	 *
	 * @var float|null
	 */
	private ?float $before = null;

	/**
	 * ISO timestamp when the best price is Amazon's.
	 *
	 * @var string|null
	 */
	private ?string $price_at = null;

	/**
	 * Product page on oha.deals (https), or null.
	 *
	 * @var string|null
	 */
	private ?string $url = null;

	/**
	 * Short spec line.
	 *
	 * @var string
	 */
	private string $spec = '';

	/**
	 * Image URLs (https).
	 *
	 * @var string[]
	 */
	private array $images = array();

	/**
	 * Specifications: label => value.
	 *
	 * @var array<string, string>
	 */
	private array $attrs = array();

	/**
	 * Manufacturer's list price.
	 *
	 * @var float|null
	 */
	private ?float $msrp = null;

	/**
	 * Lowest price of the last 30 days.
	 *
	 * @var float|null
	 */
	private ?float $low30 = null;

	/**
	 * All-time low: `price` (float) and `date` (string), or null.
	 *
	 * @var array{price: float, date: string}|null
	 */
	private ?array $all_time_low = null;

	/**
	 * New-goods offers, cheapest first.
	 *
	 * @var Offer[]
	 */
	private array $offers = array();

	/**
	 * Used and refurbished offers.
	 *
	 * @var Offer[]
	 */
	private array $used_offers = array();

	/**
	 * ISO timestamp of the last price check.
	 *
	 * @var string|null
	 */
	private ?string $prices_checked_at = null;

	/**
	 * Price history points, oldest first: `date` (Y-m-d), `price` (float), `avg` (float|null).
	 *
	 * @var array<int, array{date: string, price: float, avg: float|null}>
	 */
	private array $history = array();

	/**
	 * Parts the API left out for lack of a capability (`meta.omitted`), e.g. `history`.
	 *
	 * @var string[]
	 */
	private array $omitted = array();

	/**
	 * Use from_array().
	 */
	private function __construct() {}

	/**
	 * Builds a product from a decoded API object.
	 *
	 * @param array<string, mixed> $data Product or ProductSummary object.
	 * @param array<string, mixed> $meta Response meta (for `omitted`).
	 * @return self
	 */
	public static function from_array( array $data, array $meta = array() ): self {
		$product  = new self();
		$category = Cast::items( $data, 'category' );

		$product->id            = Cast::integer( $data, 'id' );
		$product->slug          = sanitize_title( Cast::text( $data, 'slug' ) );
		$product->name          = Cast::text( $data, 'name' );
		$product->brand         = Cast::text( $data, 'brand' );
		$product->category_slug = sanitize_title( Cast::text( $category, 'slug' ) );
		$product->category_name = Cast::text( $category, 'name' );
		$ean                    = Cast::text( $data, 'ean' );
		$product->ean           = preg_match( '/^[0-9]{8,14}$/', $ean ) ? $ean : null;
		$product->image         = Cast::https_url( $data, 'image' );
		$product->price_hidden  = Cast::flag( $data, 'priceHidden' );
		$product->price         = $product->price_hidden ? null : Cast::nullable_float( $data, 'price' );
		$product->currency      = Cast::text( $data, 'currency', 'EUR' );
		$product->shop          = Cast::nullable_text( $data, 'shop' );
		$product->offer_count   = max( 0, Cast::integer( $data, 'offerCount' ) );
		$product->before        = Cast::nullable_float( $data, 'before' );
		$product->price_at      = Cast::nullable_text( $data, 'priceAt' );
		$product->url           = Cast::https_url( $data, 'url' );
		$product->spec          = Cast::text( $data, 'spec' );
		$product->msrp          = Cast::nullable_float( $data, 'msrp' );
		$product->low30         = Cast::nullable_float( $data, 'low30' );

		$change = Cast::items( $data, 'change' );
		foreach ( array( 'd1', 'd7', 'd30' ) as $period ) {
			$product->change[ $period ] = Cast::nullable_float( $change, $period );
		}

		foreach ( Cast::items( $data, 'images' ) as $image ) {
			if ( is_string( $image ) && 0 === stripos( $image, 'https://' ) ) {
				$product->images[] = $image;
			}
		}
		if ( null === $product->image && $product->images ) {
			$product->image = $product->images[0];
		}

		foreach ( Cast::items( $data, 'attrs' ) as $label => $value ) {
			$label = trim( (string) $label );
			if ( '' !== $label && is_scalar( $value ) && ! is_bool( $value ) && '' !== trim( (string) $value ) ) {
				$product->attrs[ $label ] = trim( (string) $value );
			}
		}

		$low = Cast::items( $data, 'allTimeLow' );
		if ( null !== Cast::nullable_float( $low, 'price' ) ) {
			$product->all_time_low = array(
				'price' => (float) Cast::nullable_float( $low, 'price' ),
				'date'  => Cast::text( $low, 'date' ),
			);
		}

		$product->offers      = self::offers_from( Cast::items( $data, 'offers' ) );
		$product->used_offers = self::offers_from( Cast::items( $data, 'usedOffers' ) );

		$product->prices_checked_at = Cast::nullable_text( $data, 'pricesCheckedAt' );

		foreach ( Cast::items( $data, 'history' ) as $point ) {
			if ( ! is_array( $point ) ) {
				continue;
			}
			$price = Cast::nullable_float( $point, 'price' );
			$date  = Cast::text( $point, 'date' );
			if ( null === $price || $price < 0 || ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $date ) ) {
				continue;
			}
			$product->history[] = array(
				'date'  => substr( $date, 0, 10 ),
				'price' => $price,
				'avg'   => Cast::nullable_float( $point, 'avg' ),
			);
		}
		usort(
			$product->history,
			static function ( array $a, array $b ): int {
				return strcmp( $a['date'], $b['date'] );
			}
		);

		foreach ( Cast::items( $meta, 'omitted' ) as $part ) {
			if ( is_string( $part ) ) {
				$product->omitted[] = sanitize_key( $part );
			}
		}

		return $product;
	}

	/**
	 * Builds offers from a list, skipping malformed entries.
	 *
	 * @param array<mixed> $items Raw offers.
	 * @return Offer[]
	 */
	private static function offers_from( array $items ): array {
		$offers = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$offers[] = Offer::from_array( $item );
			}
		}

		return $offers;
	}

	/**
	 * Numeric ID.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return $this->slug;
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Brand.
	 *
	 * @return string
	 */
	public function brand(): string {
		return $this->brand;
	}

	/**
	 * Category slug.
	 *
	 * @return string
	 */
	public function category_slug(): string {
		return $this->category_slug;
	}

	/**
	 * Category name.
	 *
	 * @return string
	 */
	public function category_name(): string {
		return $this->category_name;
	}

	/**
	 * EAN, or null.
	 *
	 * @return string|null
	 */
	public function ean(): ?string {
		return $this->ean;
	}

	/**
	 * Main image URL, or null.
	 *
	 * @return string|null
	 */
	public function image(): ?string {
		return $this->image;
	}

	/**
	 * Best price, or null.
	 *
	 * @return float|null
	 */
	public function price(): ?float {
		return $this->price;
	}

	/**
	 * Whether the best price is withheld or unknown.
	 *
	 * @return bool
	 */
	public function is_price_hidden(): bool {
		return $this->price_hidden || null === $this->price;
	}

	/**
	 * Currency code.
	 *
	 * @return string
	 */
	public function currency(): string {
		return $this->currency;
	}

	/**
	 * Shop of the best price, or null.
	 *
	 * @return string|null
	 */
	public function shop(): ?string {
		return $this->shop;
	}

	/**
	 * Number of offers.
	 *
	 * @return int
	 */
	public function offer_count(): int {
		return $this->offer_count;
	}

	/**
	 * Percent change over a period (`d1`, `d7`, `d30`), or null.
	 *
	 * @param string $period Period key.
	 * @return float|null
	 */
	public function change( string $period ): ?float {
		return $this->change[ $period ] ?? null;
	}

	/**
	 * Reference price for a struck "before" price, only when the API sent one and it is above the price.
	 *
	 * @return float|null
	 */
	public function before(): ?float {
		if ( null === $this->before || null === $this->price || $this->before <= $this->price ) {
			return null;
		}

		return $this->before;
	}

	/**
	 * Discount against the API's reference price, in whole percent, or null when there is no reference price.
	 *
	 * @return int|null
	 */
	public function discount_percent(): ?int {
		$before = $this->before();
		if ( null === $before || null === $this->price ) {
			return null;
		}
		$percent = (int) round( ( 1 - $this->price / $before ) * 100 );

		return $percent > 0 ? $percent : null;
	}

	/**
	 * Amazon price timestamp, or null.
	 *
	 * @return string|null
	 */
	public function price_at(): ?string {
		return $this->price_at;
	}

	/**
	 * Product page on oha.deals, or null.
	 *
	 * @return string|null
	 */
	public function url(): ?string {
		return $this->url;
	}

	/**
	 * Spec line.
	 *
	 * @return string
	 */
	public function spec(): string {
		return $this->spec;
	}

	/**
	 * Image URLs.
	 *
	 * @return string[]
	 */
	public function images(): array {
		return $this->images;
	}

	/**
	 * Specifications: label => value.
	 *
	 * @return array<string, string>
	 */
	public function attrs(): array {
		return $this->attrs;
	}

	/**
	 * Manufacturer's list price, or null.
	 *
	 * @return float|null
	 */
	public function msrp(): ?float {
		return $this->msrp;
	}

	/**
	 * 30-day low, or null.
	 *
	 * @return float|null
	 */
	public function low30(): ?float {
		return $this->low30;
	}

	/**
	 * All-time low with `price` and `date`, or null.
	 *
	 * @return array{price: float, date: string}|null
	 */
	public function all_time_low(): ?array {
		return $this->all_time_low;
	}

	/**
	 * New-goods offers, cheapest first.
	 *
	 * @return Offer[]
	 */
	public function offers(): array {
		return $this->offers;
	}

	/**
	 * Used and refurbished offers.
	 *
	 * @return Offer[]
	 */
	public function used_offers(): array {
		return $this->used_offers;
	}

	/**
	 * The offer that carries the best price: the first offer with a visible price, else the first offer.
	 *
	 * @return Offer|null
	 */
	public function best_offer(): ?Offer {
		foreach ( $this->offers as $offer ) {
			if ( ! $offer->is_price_hidden() ) {
				return $offer;
			}
		}

		return $this->offers[0] ?? null;
	}

	/**
	 * Timestamp of the last price check, or null.
	 *
	 * @return string|null
	 */
	public function prices_checked_at(): ?string {
		return $this->prices_checked_at;
	}

	/**
	 * Price history, oldest first.
	 *
	 * @return array<int, array{date: string, price: float, avg: float|null}>
	 */
	public function history(): array {
		return $this->history;
	}

	/**
	 * Whether the API left out a part for lack of a capability.
	 *
	 * @param string $part Part name, e.g. `history`.
	 * @return bool
	 */
	public function is_omitted( string $part ): bool {
		return in_array( $part, $this->omitted, true );
	}

	/**
	 * Whether the best price itself is Amazon's. Such a price may only be shown with its timestamp and the disclaimer.
	 *
	 * @return bool
	 */
	public function is_best_price_amazon(): bool {
		if ( $this->is_price_hidden() ) {
			return false;
		}
		$best = $this->best_offer();

		return null !== $this->price_at || ( null !== $best && $best->is_amazon() );
	}

	/**
	 * The product as a plain, JSON-ready array with stable snake_case keys.
	 *
	 * Detail-only parts (`images`, `attrs`, `offers`, `history`, …) are empty for search and deal results.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$offer_to_array = static function ( Offer $offer ): array {
			return $offer->to_array();
		};

		return array(
			'id'                => $this->id,
			'slug'              => $this->slug,
			'name'              => $this->name,
			'brand'             => $this->brand,
			'category'          => array(
				'slug' => $this->category_slug,
				'name' => $this->category_name,
			),
			'ean'               => $this->ean,
			'url'               => $this->url,
			'image'             => $this->image,
			'images'            => $this->images,
			'spec'              => $this->spec,
			'attrs'             => $this->attrs,
			'price'             => $this->price,
			'price_hidden'      => $this->is_price_hidden(),
			'price_at'          => $this->price_at,
			'currency'          => $this->currency,
			'shop'              => $this->shop,
			'offer_count'       => $this->offer_count,
			'before'            => $this->before(),
			'discount_percent'  => $this->discount_percent(),
			'change'            => $this->change,
			'msrp'              => $this->msrp,
			'low30'             => $this->low30,
			'all_time_low'      => $this->all_time_low,
			'offers'            => array_map( $offer_to_array, $this->offers ),
			'used_offers'       => array_map( $offer_to_array, $this->used_offers ),
			'history'           => $this->history,
			'prices_checked_at' => $this->prices_checked_at,
			'omitted'           => $this->omitted,
		);
	}

	/**
	 * Whether any price shown for this product is Amazon's (needs the timestamp and the Amazon disclaimer).
	 *
	 * @return bool
	 */
	public function has_amazon_price(): bool {
		if ( null !== $this->price_at && null !== $this->price ) {
			return true;
		}
		foreach ( array_merge( $this->offers, $this->used_offers ) as $offer ) {
			if ( $offer->is_amazon() && ! $offer->is_price_hidden() ) {
				return true;
			}
		}

		return false;
	}
}
