<?php
/**
 * Title: Comparison with verdict
 * Slug: oha-deals/comparison-verdict
 * Description: A comparison table of two to six products, followed by a short verdict.
 * Categories: oha-deals
 * Keywords: comparison, versus, table, verdict
 * Viewport Width: 1000
 * Block Types: oha-deals/comparison
 *
 * @package OhaDeals
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'The models side by side', 'Pattern placeholder text.', 'oha-deals' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:oha-deals/comparison {"align":"wide","fields":["image","brand","price","before","discount","shop","offers","lowest","specs"]} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem","left":"1.5rem","right":"1.5rem"}},"border":{"radius":"12px","width":"1px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="border-width:1px;border-radius:12px;padding-top:1.5rem;padding-right:1.5rem;padding-bottom:1.5rem;padding-left:1.5rem"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'Our verdict', 'Pattern placeholder text.', 'oha-deals' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Say which model suits whom, and why.', 'Pattern placeholder text.', 'oha-deals' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
