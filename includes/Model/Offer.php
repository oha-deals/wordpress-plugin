<?php
/**
 * One shop offer for a product.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable offer built from a Partner API `Offer` object.
 */
final class Offer {

	/**
	 * Shop ID, e.g. `alternate`.
	 *
	 * @var string
	 */
	private string $shop_id;

	/**
	 * Shop display name.
	 *
	 * @var string
	 */
	private string $shop_name;

	/**
	 * Shop host, e.g. `alternate.de`.
	 *
	 * @var string|null
	 */
	private ?string $shop_host;

	/**
	 * Price in EUR, null when hidden or unknown.
	 *
	 * @var float|null
	 */
	private ?float $price;

	/**
	 * Whether the price is withheld for licence reasons.
	 *
	 * @var bool
	 */
	private bool $price_hidden;

	/**
	 * Shipping cost; null means unknown (never free).
	 *
	 * @var float|null
	 */
	private ?float $shipping;

	/**
	 * Price plus shipping, null when either is unknown.
	 *
	 * @var float|null
	 */
	private ?float $total;

	/**
	 * Currency code.
	 *
	 * @var string
	 */
	private string $currency;

	/**
	 * Availability: in_stock, short, long, on_order or empty.
	 *
	 * @var string
	 */
	private string $availability;

	/**
	 * Condition: new, used or refurbished.
	 *
	 * @var string
	 */
	private string $condition;

	/**
	 * ISO timestamp of an Amazon price, else null.
	 *
	 * @var string|null
	 */
	private ?string $price_at;

	/**
	 * The oha.deals redirect URL (https), or null when missing or unsafe. Never printed by the plugin itself.
	 *
	 * @var string|null
	 */
	private ?string $url;

	/**
	 * Merchant network: amazon, awin, other, or null without the ownLinks capability.
	 *
	 * @var string|null
	 */
	private ?string $network;

	/**
	 * Amazon ASIN.
	 *
	 * @var string|null
	 */
	private ?string $asin;

	/**
	 * Awin merchant (advertiser) ID.
	 *
	 * @var int|null
	 */
	private ?int $awin_merchant_id;

	/**
	 * Awin feed product ID (digits only), or null.
	 *
	 * @var string|null
	 */
	private ?string $awin_product_id;

	/**
	 * Clean merchant product URL without tracking (https), or null.
	 *
	 * @var string|null
	 */
	private ?string $merchant_url;

	/**
	 * Use from_array().
	 */
	private function __construct() {}

	/**
	 * Builds an offer from a decoded API object. Missing fields get safe defaults.
	 *
	 * @param array<string, mixed> $data Offer object.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$offer    = new self();
		$shop     = Cast::items( $data, 'shop' );
		$merchant = Cast::items( $data, 'merchant' );

		$offer->shop_id      = sanitize_key( Cast::text( $shop, 'id' ) );
		$offer->shop_name    = Cast::text( $shop, 'name' );
		$offer->shop_host    = Cast::nullable_text( $shop, 'host' );
		$offer->price_hidden = Cast::flag( $data, 'priceHidden' );
		$offer->price        = $offer->price_hidden ? null : Cast::nullable_float( $data, 'price' );
		$offer->shipping     = Cast::nullable_float( $data, 'shipping' );
		$offer->total        = $offer->price_hidden ? null : Cast::nullable_float( $data, 'total' );
		$offer->currency     = Cast::text( $data, 'currency', 'EUR' );
		$offer->availability = sanitize_key( Cast::text( $data, 'availability' ) );
		$offer->condition    = sanitize_key( Cast::text( $data, 'condition', 'new' ) );
		$offer->price_at     = Cast::nullable_text( $data, 'priceAt' );
		$offer->url          = Cast::https_url( $data, 'url' );

		$network                 = sanitize_key( Cast::text( $merchant, 'network' ) );
		$offer->network          = '' === $network ? null : $network;
		$asin                    = strtoupper( Cast::text( $merchant, 'asin' ) );
		$offer->asin             = preg_match( '/^[A-Z0-9]{10}$/', $asin ) ? $asin : null;
		$awin_id                 = Cast::integer( $merchant, 'awinMerchantId' );
		$offer->awin_merchant_id = $awin_id > 0 ? $awin_id : null;
		$awin_product            = Cast::text( $merchant, 'awinProductId' );
		$offer->awin_product_id  = preg_match( '/^[0-9]{1,20}$/', $awin_product ) ? $awin_product : null;
		$offer->merchant_url     = Cast::https_url( $merchant, 'url' );

		if ( '' === $offer->shop_name ) {
			$offer->shop_name = $offer->shop_host ?? $offer->shop_id;
		}

		return $offer;
	}

	/**
	 * Shop ID.
	 *
	 * @return string
	 */
	public function shop_id(): string {
		return $this->shop_id;
	}

	/**
	 * Shop display name.
	 *
	 * @return string
	 */
	public function shop_name(): string {
		return $this->shop_name;
	}

	/**
	 * Shop host.
	 *
	 * @return string|null
	 */
	public function shop_host(): ?string {
		return $this->shop_host;
	}

	/**
	 * Price, null when hidden or unknown.
	 *
	 * @return float|null
	 */
	public function price(): ?float {
		return $this->price;
	}

	/**
	 * Whether the price is withheld (link-only offer).
	 *
	 * @return bool
	 */
	public function is_price_hidden(): bool {
		return $this->price_hidden || null === $this->price;
	}

	/**
	 * Shipping cost, null when unknown.
	 *
	 * @return float|null
	 */
	public function shipping(): ?float {
		return $this->shipping;
	}

	/**
	 * Total price, null when unknown.
	 *
	 * @return float|null
	 */
	public function total(): ?float {
		return $this->total;
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
	 * Availability key.
	 *
	 * @return string
	 */
	public function availability(): string {
		return $this->availability;
	}

	/**
	 * Condition key.
	 *
	 * @return string
	 */
	public function condition(): string {
		return $this->condition;
	}

	/**
	 * Amazon price timestamp (ISO 8601), or null.
	 *
	 * @return string|null
	 */
	public function price_at(): ?string {
		return $this->price_at;
	}

	/**
	 * The oha.deals redirect URL, or null.
	 *
	 * The plugin does not print it (see LinkResolver). It is available to the `oha_deals_offer_url` filter.
	 *
	 * @return string|null
	 */
	public function url(): ?string {
		return $this->url;
	}

	/**
	 * Merchant network, or null.
	 *
	 * @return string|null
	 */
	public function network(): ?string {
		return $this->network;
	}

	/**
	 * Amazon ASIN, or null.
	 *
	 * @return string|null
	 */
	public function asin(): ?string {
		return $this->asin;
	}

	/**
	 * Awin merchant ID, or null.
	 *
	 * @return int|null
	 */
	public function awin_merchant_id(): ?int {
		return $this->awin_merchant_id;
	}

	/**
	 * Awin feed product ID (digits only), or null.
	 *
	 * @return string|null
	 */
	public function awin_product_id(): ?string {
		return $this->awin_product_id;
	}

	/**
	 * Clean merchant product URL, or null.
	 *
	 * @return string|null
	 */
	public function merchant_url(): ?string {
		return $this->merchant_url;
	}

	/**
	 * The offer as a plain, JSON-ready array with stable snake_case keys.
	 *
	 * The oha.deals redirect URL is left out on purpose: link with LinkResolver (or the `link` that oha_deals_query()
	 * adds), never with a redirect.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'shop'             => array(
				'id'   => $this->shop_id,
				'name' => $this->shop_name,
				'host' => $this->shop_host,
			),
			'price'            => $this->price,
			'price_hidden'     => $this->is_price_hidden(),
			'price_at'         => $this->price_at,
			'shipping'         => $this->shipping,
			'total'            => $this->total,
			'currency'         => $this->currency,
			'availability'     => $this->availability,
			'condition'        => $this->condition,
			'is_amazon'        => $this->is_amazon(),
			'network'          => $this->network,
			'asin'             => $this->asin,
			'awin_merchant_id' => $this->awin_merchant_id,
			'awin_product_id'  => $this->awin_product_id,
			'merchant_url'     => $this->merchant_url,
		);
	}

	/**
	 * Whether this is an Amazon offer: it carries an Amazon price timestamp, or its shop id or merchant network is
	 * `amazon`. Amazon prices need the timestamp and disclaimer and must not be cached for longer than an hour.
	 *
	 * @return bool
	 */
	public function is_amazon(): bool {
		return null !== $this->price_at || 'amazon' === $this->shop_id || 'amazon' === $this->network;
	}
}
