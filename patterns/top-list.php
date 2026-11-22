<?php
/**
 * Title: Top 5 list with intro
 * Slug: oha-deals/top-list
 * Description: An introduction, a numbered list of products with live prices and a note on how they were chosen.
 * Categories: oha-deals
 * Keywords: top list, ranking, best of, comparison
 * Viewport Width: 800
 * Block Types: oha-deals/comparison
 *
 * @package OhaDeals
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Our top 5', 'Pattern placeholder text.', 'oha-deals' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Say in two or three sentences who this list is for and what you looked at. The prices below are updated automatically.', 'Pattern placeholder text.', 'oha-deals' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:oha-deals/comparison {"orientation":"rows","numbered":true,"fields":["image","brand","price","before","discount","shop","specs"]} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'How we chose', 'Pattern placeholder text.', 'oha-deals' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><?php echo esc_html_x( 'First criterion', 'Pattern placeholder text.', 'oha-deals' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php echo esc_html_x( 'Second criterion', 'Pattern placeholder text.', 'oha-deals' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
