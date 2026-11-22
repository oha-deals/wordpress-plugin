<?php
/**
 * Affiliate link provider contract.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Affiliate;

use OhaDeals\Model\Offer;

defined( 'ABSPATH' ) || exit;

/**
 * Builds an outgoing link with the site owner's own affiliate ID.
 *
 * Add providers through the `oha_deals_affiliate_providers` filter.
 */
interface Provider {

	/**
	 * Whether this provider can build a link for the offer.
	 *
	 * @param Offer $offer Offer.
	 * @return bool
	 */
	public function supports( Offer $offer ): bool;

	/**
	 * The affiliate URL (https), or null to let the next provider try.
	 *
	 * @param Offer $offer Offer.
	 * @return string|null
	 */
	public function url( Offer $offer ): ?string;
}
