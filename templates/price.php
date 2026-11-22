<?php
/**
 * The best price as inline text ([oha_deals_price]).
 *
 * Override by copying to `<theme>/oha-deals/price.php`.
 *
 * @package OhaDeals
 *
 * @var array<string, mixed> $args {
 *     @type \OhaDeals\Model\Product  $product            Product with a visible price.
 *     @type bool                     $amazon_prices      Whether the price is Amazon's.
 *     @type \OhaDeals\Render\Helpers $helpers            Template helpers.
 *     @type string                   $wrapper_attributes Escaped wrapper attributes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$oha_deals_h     = $args['helpers'];
$oha_deals_as_of = $oha_deals_h->product_as_of( $args['product'] );

// Printed without whitespace between the tags, so the price fits into running text.
echo '<span ' . $args['wrapper_attributes'] . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_attr().
echo '<span class="oha-deals-amount">' . esc_html( $oha_deals_h->product_price( $args['product'] ) ) . '</span>';
if ( '' !== $oha_deals_as_of ) {
	echo ' <small class="oha-deals-as-of">(' . esc_html( $oha_deals_as_of ) . ')</small>';
}
$oha_deals_h->print_footer( (bool) $args['amazon_prices'], false, true, false );
echo '</span>';
