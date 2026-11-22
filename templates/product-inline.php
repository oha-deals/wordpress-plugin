<?php
/**
 * Product mention for running text (layout "inline"): name, best price, shop and link.
 *
 * Override by copying to `<theme>/oha-deals/product-inline.php`.
 *
 * @package OhaDeals
 *
 * @var array<string, mixed> $args {
 *     @type \OhaDeals\Model\Product  $product            Product.
 *     @type string                   $button_url         Link URL (https or empty).
 *     @type bool                     $amazon_prices      Whether an Amazon price is shown.
 *     @type bool                     $amazon_links       Whether Amazon is linked with the site's tag.
 *     @type string                   $tag                "span" (shortcode) or "div" (block).
 *     @type \OhaDeals\Render\Helpers $helpers            Template helpers.
 *     @type string                   $wrapper_attributes Escaped wrapper attributes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$oha_deals_product = $args['product'];
$oha_deals_h       = $args['helpers'];
$oha_deals_tag     = 'div' === $args['tag'] ? 'div' : 'span';
$oha_deals_price   = $oha_deals_h->product_price( $oha_deals_product );
$oha_deals_as_of   = $oha_deals_h->product_as_of( $oha_deals_product );
?>
<<?php echo tag_escape( $oha_deals_tag ); ?> <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_block_wrapper_attributes() or esc_attr(). ?>>
	<?php if ( '' !== $args['button_url'] ) : ?>
		<a class="oha-deals-inline__link"<?php $oha_deals_h->print_link_attributes( $args['button_url'] ); ?>><?php echo esc_html( $oha_deals_product->name() ); ?><?php $oha_deals_h->print_new_tab_hint(); ?></a>
	<?php else : ?>
		<span class="oha-deals-inline__name"><?php echo esc_html( $oha_deals_product->name() ); ?></span>
	<?php endif; ?>
	<?php if ( '' !== $oha_deals_price ) : ?>
		<span class="oha-deals-inline__price">
			<?php
			if ( null !== $oha_deals_product->shop() ) {
				/* translators: 1: price, 2: shop name. */
				echo esc_html( sprintf( __( 'from %1$s at %2$s', 'oha-deals' ), $oha_deals_price, $oha_deals_product->shop() ) );
			} else {
				/* translators: %s: price. */
				echo esc_html( sprintf( __( 'from %s', 'oha-deals' ), $oha_deals_price ) );
			}
			?>
		</span>
		<?php if ( '' !== $oha_deals_as_of ) : ?>
			<small class="oha-deals-as-of">(<?php echo esc_html( $oha_deals_as_of ); ?>)</small>
		<?php endif; ?>
	<?php endif; ?>
	<?php $oha_deals_h->print_footer( (bool) $args['amazon_prices'], (bool) $args['amazon_links'], true ); ?>
</<?php echo tag_escape( $oha_deals_tag ); ?>>
