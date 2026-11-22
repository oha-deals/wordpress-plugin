<?php
/**
 * Amazon Associates links.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Affiliate;

use OhaDeals\Model\Offer;

defined( 'ABSPATH' ) || exit;

/**
 * Links Amazon offers to amazon.de with the site owner's Associates tag.
 */
final class AmazonProvider implements Provider {

	/**
	 * Associates tag, e.g. `example-21`.
	 *
	 * @var string
	 */
	private string $tag;

	/**
	 * Constructor.
	 *
	 * @param string $tag Associates tag; empty disables the provider.
	 */
	public function __construct( string $tag ) {
		$this->tag = $tag;
	}

	/**
	 * Supported when a tag is set and the offer carries an ASIN.
	 *
	 * @param Offer $offer Offer.
	 * @return bool
	 */
	public function supports( Offer $offer ): bool {
		return '' !== $this->tag && null !== $offer->asin();
	}

	/**
	 * `https://www.amazon.de/dp/<asin>?tag=<tag>`.
	 *
	 * @param Offer $offer Offer.
	 * @return string|null
	 */
	public function url( Offer $offer ): ?string {
		if ( ! $this->supports( $offer ) ) {
			return null;
		}

		return 'https://www.amazon.de/dp/' . rawurlencode( (string) $offer->asin() ) . '?tag=' . rawurlencode( $this->tag );
	}
}
