<?php
/**
 * Title: Deals page with coupons
 * Slug: oha-deals/deals-page
 * Description: Price drops, lowest prices and coupon codes, each with a heading.
 * Categories: oha-deals
 * Keywords: deals, offers, coupons, sale
 * Viewport Width: 1200
 *
 * @package OhaDeals
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph -->
<p><?php echo esc_html_x( 'The best price drops of the day, updated automatically.', 'Pattern placeholder text.', 'oha-deals' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Price drops', 'Pattern placeholder text.', 'oha-deals' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:oha-deals/deals {"mode":"drop","limit":8,"columns":4,"align":"wide"} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'At their lowest price', 'Pattern placeholder text.', 'oha-deals' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:oha-deals/deals {"mode":"low","limit":4,"columns":4,"align":"wide"} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Coupon codes', 'Pattern placeholder text.', 'oha-deals' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:oha-deals/coupons {"limit":5} /--></div>
<!-- /wp:group -->
