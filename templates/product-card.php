<?php
/**
 * Product card (layouts "card" and "compact").
 *
 * Override by copying to `<theme>/oha-deals/product-card.php`.
 *
 * @package OhaDeals
 *
 * @var array<string, mixed> $args {
 *     @type \OhaDeals\Model\Product        $product            Product.
 *     @type \OhaDeals\Model\Offer[]        $offers             Offers to list (empty for "compact").
 *     @type string                         $layout             "card" or "compact".
 *     @type string                         $button_text        Button label.
 *     @type string                         $button_url         Button URL (https or empty).
 *     @type string                         $chart              Escaped chart markup or empty.
 *     @type bool                           $amazon_prices      Whether Amazon prices are shown.
 *     @type bool                           $amazon_links       Whether Amazon is linked with the site's tag.
 *     @type string                         $structured_data    JSON-LD script or empty.
 *     @type array<string, mixed>           $attributes         Normalised attributes.
 *     @type \OhaDeals\Render\Helpers       $helpers            Template helpers.
 *     @type string                         $wrapper_attributes Escaped wrapper attributes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$oha_deals_product = $args['product'];
$oha_deals_h       = $args['helpers'];
$oha_deals_format  = $oha_deals_h->format();
$oha_deals_price   = $oha_deals_h->product_price( $oha_deals_product );
$oha_deals_before  = $oha_deals_h->before_price( $oha_deals_product );
$oha_deals_off     = $oha_deals_h->discount( $oha_deals_product );
$oha_deals_as_of   = $oha_deals_h->product_as_of( $oha_deals_product );
$oha_deals_size    = 'compact' === $args['layout'] ? 96 : 160;
$oha_deals_button  = 'oha-deals-button oha-deals-button--primary' . ( 'compact' === $args['layout'] ? ' oha-deals-button--small' : '' );
// Many product names start with the brand; it is not repeated above the title then.
$oha_deals_brand = '' !== $oha_deals_product->brand() && 0 !== stripos( $oha_deals_product->name(), $oha_deals_product->brand() ) ? $oha_deals_product->brand() : '';
$oha_deals_media = ! empty( $args['attributes']['showImage'] ) && $oha_deals_h->shows_images() && null !== $oha_deals_product->image();
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_block_wrapper_attributes() or esc_attr(). ?>>
	<div class="oha-deals-product__body<?php echo $oha_deals_media ? '' : ' oha-deals-product__body--no-media'; ?>">
		<?php if ( $oha_deals_media ) : ?>
			<div class="oha-deals-product__media">
				<?php $oha_deals_h->print_image( $oha_deals_product->image(), $oha_deals_h->image_alt( $oha_deals_product ), $oha_deals_size ); ?>
			</div>
		<?php endif; ?>

		<div class="oha-deals-product__main">
			<div class="oha-deals-product__head">
				<?php if ( '' !== $oha_deals_brand ) : ?>
					<p class="oha-deals-product__brand"><?php echo esc_html( $oha_deals_brand ); ?></p>
				<?php endif; ?>
				<h3 class="oha-deals-product__title"><?php echo esc_html( $oha_deals_product->name() ); ?></h3>
				<?php if ( 'card' === $args['layout'] && '' !== $oha_deals_product->spec() ) : ?>
					<p class="oha-deals-product__spec"><?php echo esc_html( $oha_deals_product->spec() ); ?></p>
				<?php endif; ?>
			</div>

			<div class="oha-deals-product__buy">
				<div class="oha-deals-price-block">
					<?php if ( '' !== $oha_deals_price ) : ?>
						<p class="oha-deals-price-block__label"><?php esc_html_e( 'Best price', 'oha-deals' ); ?></p>
						<p class="oha-deals-price-block__line">
							<strong class="oha-deals-amount"><?php echo esc_html( $oha_deals_price ); ?></strong>
							<?php if ( '' !== $oha_deals_off ) : ?>
								<span class="oha-deals-badge oha-deals-badge--discount"><?php echo esc_html( $oha_deals_off ); ?></span>
							<?php endif; ?>
						</p>
						<?php if ( '' !== $oha_deals_before ) : ?>
							<p class="oha-deals-price-block__before">
								<span class="oha-deals-price-block__before-label"><?php esc_html_e( '30-day low:', 'oha-deals' ); ?></span>
								<s><?php echo esc_html( $oha_deals_before ); ?></s>
							</p>
						<?php endif; ?>
						<?php if ( null !== $oha_deals_product->shop() ) : ?>
							<p class="oha-deals-price-block__shop">
								<?php
								/* translators: %s: shop name. */
								echo esc_html( sprintf( __( 'at %s', 'oha-deals' ), $oha_deals_product->shop() ) );
								?>
							</p>
						<?php endif; ?>
						<?php if ( '' !== $oha_deals_as_of ) : ?>
							<p class="oha-deals-as-of"><?php echo esc_html( $oha_deals_as_of ); ?></p>
						<?php endif; ?>
					<?php elseif ( $oha_deals_product->offer_count() > 0 ) : ?>
						<p class="oha-deals-price-block__none"><?php esc_html_e( 'See the shops for the current price.', 'oha-deals' ); ?></p>
					<?php else : ?>
						<p class="oha-deals-price-block__none"><?php esc_html_e( 'Currently not available.', 'oha-deals' ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( '' !== $args['button_url'] ) : ?>
					<p class="oha-deals-product__action">
						<a class="<?php echo esc_attr( $oha_deals_button ); ?>"<?php $oha_deals_h->print_link_attributes( $args['button_url'] ); ?>>
							<?php echo esc_html( $args['button_text'] ); ?>
							<span class="oha-deals-visually-hidden"><?php echo esc_html( ': ' . $oha_deals_product->name() ); ?></span>
							<?php $oha_deals_h->print_new_tab_hint(); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( $args['offers'] ) : ?>
		<ul class="oha-deals-offer-list">
			<?php foreach ( $args['offers'] as $oha_deals_offer ) : ?>
				<?php
				$oha_deals_url        = $oha_deals_h->offer_url( $oha_deals_offer, $oha_deals_product );
				$oha_deals_label      = $oha_deals_h->offer_link_label( $oha_deals_offer, $oha_deals_product );
				$oha_deals_offer_text = $oha_deals_h->offer_price( $oha_deals_offer );
				$oha_deals_offer_asof = $oha_deals_h->offer_as_of( $oha_deals_offer, $oha_deals_product );
				?>
				<li class="oha-deals-offer-list__item">
					<span class="oha-deals-offer-list__shop"><?php echo esc_html( $oha_deals_offer->shop_name() ); ?></span>
					<span class="oha-deals-offer-list__price">
						<?php if ( '' !== $oha_deals_offer_text ) : ?>
							<span class="oha-deals-amount"><?php echo esc_html( $oha_deals_offer_text ); ?></span>
							<small class="oha-deals-offer-list__shipping"><?php echo esc_html( $oha_deals_format->shipping( $oha_deals_offer->shipping(), $oha_deals_offer->currency() ) ); ?></small>
							<?php if ( '' !== $oha_deals_offer_asof ) : ?>
								<small class="oha-deals-as-of"><?php echo esc_html( $oha_deals_offer_asof ); ?></small>
							<?php endif; ?>
						<?php else : ?>
							<span class="oha-deals-offer-list__hidden"><?php esc_html_e( 'Price at the shop', 'oha-deals' ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( '' !== $oha_deals_url ) : ?>
						<a class="oha-deals-offer-list__link"<?php $oha_deals_h->print_link_attributes( $oha_deals_url ); ?>>
							<?php
							echo esc_html( $oha_deals_label );
							if ( '' !== $oha_deals_offer->shop_name() && false === strpos( $oha_deals_label, $oha_deals_offer->shop_name() ) ) {
								echo '<span class="oha-deals-visually-hidden">: ' . esc_html( $oha_deals_offer->shop_name() ) . '</span>';
							}
							$oha_deals_h->print_new_tab_hint();
							?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php
	if ( '' !== $args['chart'] ) {
		echo $args['chart']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts by HistoryChart::render().
	}
	$oha_deals_h->print_footer( (bool) $args['amazon_prices'], (bool) $args['amazon_links'] );
	if ( '' !== $args['structured_data'] ) {
		echo $args['structured_data']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG by StructuredData::script().
	}
	?>
</div>
