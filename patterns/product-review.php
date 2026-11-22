<?php
/**
 * Title: Product card with price history
 * Slug: oha-deals/product-review
 * Description: A product card, the advantages and drawbacks side by side, and the price history for the question when to buy.
 * Categories: oha-deals
 * Keywords: review, test, product, price history
 * Viewport Width: 800
 * Block Types: oha-deals/product
 *
 * @package OhaDeals
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:oha-deals/product {"offersLimit":3} /-->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'What we like', 'Pattern placeholder text.', 'oha-deals' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><?php echo esc_html_x( 'An advantage', 'Pattern placeholder text.', 'oha-deals' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php echo esc_html_x( 'Another advantage', 'Pattern placeholder text.', 'oha-deals' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'What could be better', 'Pattern placeholder text.', 'oha-deals' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><?php echo esc_html_x( 'A drawback', 'Pattern placeholder text.', 'oha-deals' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'Is this a good price?', 'Pattern placeholder text.', 'oha-deals' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:oha-deals/price-history {"days":180} /-->
