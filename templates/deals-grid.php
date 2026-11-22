<?php
/**
 * Deals grid.
 *
 * Override by copying to `<theme>/oha-deals/deals-grid.php`.
 *
 * @package OhaDeals
 *
 * @var array<string, mixed> $args {
 *     @type \OhaDeals\Model\Deal[]   $deals              Deals (at least one).
 *     @type int                      $columns            Columns in wide containers (1–4).
 *     @type bool                     $amazon_prices      Whether Amazon prices are shown.
 *     @type \OhaDeals\Render\Helpers $helpers            Template helpers.
 *     @type string                   $wrapper_attributes Escaped wrapper attributes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$oha_deals_h      = $args['helpers'];
$oha_deals_images = $oha_deals_h->shows_images();
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_block_wrapper_attributes() or esc_attr(). ?>>
	<ul class="oha-deals-grid" style="<?php echo esc_attr( '--oha-deals-columns: ' . (int) $args['columns'] ); ?>">
		<?php foreach ( $args['deals'] as $oha_deals_deal ) : ?>
			<?php
			$oha_deals_product = $oha_deals_deal->product();
			$oha_deals_url     = $oha_deals_h->product_url( $oha_deals_product );
			$oha_deals_price   = $oha_deals_h->product_price( $oha_deals_product );
			$oha_deals_before  = $oha_deals_h->before_price( $oha_deals_product );
			$oha_deals_off     = $oha_deals_h->discount( $oha_deals_product );
			$oha_deals_as_of   = $oha_deals_h->product_as_of( $oha_deals_product );
			$oha_deals_low     = $oha_deals_deal->is_all_time_low() || 'low' === $oha_deals_deal->kind();
			?>
			<li class="oha-deals-grid__item oha-deals-deal">
				<?php if ( $oha_deals_images ) : ?>
					<div class="oha-deals-deal__media">
						<?php if ( null !== $oha_deals_product->image() ) : ?>
							<?php $oha_deals_h->print_image( $oha_deals_product->image(), $oha_deals_h->image_alt( $oha_deals_product ), 160 ); ?>
						<?php else : ?>
							<svg class="oha-deals-deal__no-image" viewBox="0 0 24 24" width="48" height="48" aria-hidden="true" focusable="false"><path d="M3.5 12.2V5A1.5 1.5 0 0 1 5 3.5h7.2a1.5 1.5 0 0 1 1 .4l7 7a1.5 1.5 0 0 1 0 2.2l-7 7a1.5 1.5 0 0 1-2.200 0l-7-7a1.5 1.5 0 0 1-.5-.9ZM8 8h.01" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
						<?php endif; ?>
						<?php if ( '' !== $oha_deals_off ) : ?>
							<span class="oha-deals-badge oha-deals-badge--discount oha-deals-deal__discount"><?php echo esc_html( $oha_deals_off ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<div class="oha-deals-deal__body">
					<p class="oha-deals-deal__kind<?php echo $oha_deals_low ? ' oha-deals-deal__kind--low' : ''; ?>">
						<?php
						if ( $oha_deals_deal->is_all_time_low() ) {
							esc_html_e( 'Lowest price ever', 'oha-deals' );
						} elseif ( $oha_deals_low ) {
							esc_html_e( 'Low price', 'oha-deals' );
						} else {
							esc_html_e( 'Price drop', 'oha-deals' );
						}
						?>
					</p>
					<h3 class="oha-deals-deal__title">
						<?php if ( '' !== $oha_deals_url ) : ?>
							<a class="oha-deals-deal__link"<?php $oha_deals_h->print_link_attributes( $oha_deals_url ); ?>><?php echo esc_html( $oha_deals_product->name() ); ?><?php $oha_deals_h->print_new_tab_hint(); ?></a>
						<?php else : ?>
							<?php echo esc_html( $oha_deals_product->name() ); ?>
						<?php endif; ?>
					</h3>
					<?php if ( '' !== $oha_deals_price ) : ?>
						<div class="oha-deals-deal__buy">
							<p class="oha-deals-deal__price">
								<strong class="oha-deals-amount"><?php echo esc_html( $oha_deals_price ); ?></strong>
								<?php if ( '' !== $oha_deals_before ) : ?>
									<span class="oha-deals-before">
										<span class="oha-deals-visually-hidden"><?php esc_html_e( '30-day low:', 'oha-deals' ); ?></span>
										<s><?php echo esc_html( $oha_deals_before ); ?></s>
									</span>
								<?php endif; ?>
								<?php if ( ! $oha_deals_images && '' !== $oha_deals_off ) : ?>
									<span class="oha-deals-badge oha-deals-badge--discount"><?php echo esc_html( $oha_deals_off ); ?></span>
								<?php endif; ?>
							</p>
							<?php if ( null !== $oha_deals_product->shop() ) : ?>
								<p class="oha-deals-deal__shop">
									<?php
									/* translators: %s: shop name. */
									echo esc_html( sprintf( __( 'at %s', 'oha-deals' ), $oha_deals_product->shop() ) );
									?>
								</p>
							<?php endif; ?>
							<?php if ( '' !== $oha_deals_as_of ) : ?>
								<p class="oha-deals-as-of"><?php echo esc_html( $oha_deals_as_of ); ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php $oha_deals_h->print_footer( (bool) $args['amazon_prices'], false ); ?>
</div>
