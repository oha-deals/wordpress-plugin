<?php
/**
 * Chooses the outgoing URL for an offer.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Affiliate;

use OhaDeals\Model\Coupon;
use OhaDeals\Model\Offer;
use OhaDeals\Model\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Own affiliate IDs first, then the product page on oha.deals, then the `oha_deals_offer_url` filter.
 *
 * A printed link is either a direct link to the affiliate service (Amazon, Awin) with the site owner's own ID, or an
 * ordinary page on oha.deals. Redirect URLs are never printed: WordPress.org forbids cloaked affiliate links, and
 * Amazon and Awin accept clicks only from registered sites.
 */
final class LinkResolver {

	/**
	 * Link kind: the site owner's own affiliate link (or any other link that does not lead to oha.deals).
	 */
	const KIND_OWN = 'own';

	/**
	 * Link kind: a page on oha.deals.
	 */
	const KIND_OHA_DEALS = 'oha.deals';

	/**
	 * Link kind: no link.
	 */
	const KIND_NONE = '';

	/**
	 * Anchor of the offers on an oha.deals product page.
	 */
	const OFFERS_ANCHOR = '#angebote';

	/**
	 * Providers, tried in order.
	 *
	 * @var Provider[]
	 */
	private array $providers;

	/**
	 * Constructor.
	 *
	 * @param Provider[] $providers Providers, tried in order. Anything that is not a Provider is ignored.
	 */
	public function __construct( array $providers ) {
		$this->providers = array_values(
			array_filter(
				$providers,
				static function ( $provider ): bool {
					return $provider instanceof Provider;
				}
			)
		);
	}

	/**
	 * The URL to print for an offer. Only https URLs are returned; an empty string means "no link".
	 *
	 * @param Offer   $offer   Offer.
	 * @param Product $product Product the offer belongs to.
	 * @return string
	 */
	public function url( Offer $offer, Product $product ): string {
		$url = null;
		foreach ( $this->providers as $provider ) {
			if ( $provider->supports( $offer ) ) {
				$url = $provider->url( $offer );
				if ( null !== $url && '' !== $url ) {
					break;
				}
			}
		}
		if ( null === $url || '' === $url ) {
			$url = self::offers_page( $product );
		}

		/**
		 * Filters the outgoing URL of an offer. Runs after the affiliate providers and the oha.deals fallback.
		 *
		 * A URL on oha.deals is treated as an ordinary link, any other URL as an affiliate link of this site (rel,
		 * disclosure and button label follow from that).
		 *
		 * @since 1.0.0
		 *
		 * @param string  $url     URL; only https URLs are printed.
		 * @param Offer   $offer   Offer.
		 * @param Product $product Product.
		 */
		$url = (string) apply_filters( 'oha_deals_offer_url', $url, $offer, $product );

		return self::is_https( $url ) ? $url : '';
	}

	/**
	 * The URL to print for a coupon: the shop's coupon page on oha.deals, else the coupon overview.
	 *
	 * @param Coupon $coupon Coupon.
	 * @return string https URL; an empty string means "no link".
	 */
	public function coupon_url( Coupon $coupon ): string {
		/**
		 * Filters the outgoing URL of a coupon.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url    URL; only https URLs are printed.
		 * @param Coupon $coupon Coupon.
		 */
		$url = (string) apply_filters( 'oha_deals_coupon_url', $coupon->url(), $coupon );

		return self::is_https( $url ) ? $url : '';
	}

	/**
	 * The offers section of a product's page on oha.deals, or an empty string when the API sent no product URL.
	 *
	 * @param Product $product Product.
	 * @return string
	 */
	public static function offers_page( Product $product ): string {
		$url = (string) $product->url();

		return self::is_https( $url ) ? explode( '#', $url, 2 )[0] . self::OFFERS_ANCHOR : '';
	}

	/**
	 * What kind of link a URL is.
	 *
	 * Decided from the host, so it also holds for URLs changed by a filter or built by a custom provider.
	 *
	 * @param string $url URL returned by this class.
	 * @return string One of the KIND_* constants.
	 */
	public static function kind( string $url ): string {
		if ( ! self::is_https( $url ) ) {
			return self::KIND_NONE;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		return 'oha.deals' === $host || '.oha.deals' === substr( $host, -10 ) ? self::KIND_OHA_DEALS : self::KIND_OWN;
	}

	/**
	 * Whether a URL is absolute https.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_https( string $url ): bool {
		if ( 0 !== stripos( $url, 'https://' ) ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) && '' !== $host;
	}
}
