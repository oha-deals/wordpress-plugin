<?php
/**
 * Awin product and deep links.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Affiliate;

use OhaDeals\Model\Offer;

defined( 'ABSPATH' ) || exit;

/**
 * Builds Awin links with the site owner's publisher ID.
 *
 * Prefers the product link (`pclick.php`, needs the feed product ID, available for most feed shops) and falls back to
 * the deep link (`cread.php`, needs a clean merchant URL). Without either, it declines and the product page on
 * oha.deals is linked.
 */
final class AwinProvider implements Provider {

	/**
	 * Awin publisher (affiliate) ID.
	 *
	 * @var string
	 */
	private string $publisher_id;

	/**
	 * Constructor.
	 *
	 * @param string $publisher_id Publisher ID (digits); empty disables the provider.
	 */
	public function __construct( string $publisher_id ) {
		$this->publisher_id = $publisher_id;
	}

	/**
	 * Supported when a publisher ID is set and the offer carries an Awin merchant ID plus a product ID or a clean
	 * merchant URL.
	 *
	 * @param Offer $offer Offer.
	 * @return bool
	 */
	public function supports( Offer $offer ): bool {
		return '' !== $this->publisher_id
			&& null !== $offer->awin_merchant_id()
			&& ( null !== $offer->awin_product_id() || null !== $offer->merchant_url() );
	}

	/**
	 * `https://www.awin1.com/pclick.php?p=<product>&a=<publisher>&m=<merchant>` when the product ID is known,
	 * else `https://www.awin1.com/cread.php?awinmid=<merchant>&awinaffid=<publisher>&ued=<url>`.
	 *
	 * @param Offer $offer Offer.
	 * @return string|null
	 */
	public function url( Offer $offer ): ?string {
		if ( ! $this->supports( $offer ) ) {
			return null;
		}

		$merchant_id = (int) $offer->awin_merchant_id();
		$product_id  = $offer->awin_product_id();
		if ( null !== $product_id ) {
			return 'https://www.awin1.com/pclick.php?p=' . rawurlencode( $product_id )
				. '&a=' . rawurlencode( $this->publisher_id )
				. '&m=' . $merchant_id;
		}

		return 'https://www.awin1.com/cread.php?awinmid=' . $merchant_id
			. '&awinaffid=' . rawurlencode( $this->publisher_id )
			. '&ued=' . rawurlencode( (string) $offer->merchant_url() );
	}
}
