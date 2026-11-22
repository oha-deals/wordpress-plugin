<?php
/**
 * Offers table: one row per shop.
 *
 * Override by copying to `<theme>/oha-deals/offers-table.php`.
 *
 * @package OhaDeals
 *
 * @var array<string, mixed> $args {
 *     @type \OhaDeals\Model\Product  $product            Product.
 *     @type \OhaDeals\Model\Offer[]  $offers             Offers (at least one).
 *     @type bool                     $show_shipping      Whether to show shipping costs under the price.
 *     @type bool                     $show_title         Whether the caption is visible (it is always present for screen readers).
 *     @type bool                     $amazon_prices      Whether Amazon prices are shown.
 *     @type bool                     $amazon_links       Whether Amazon is linked with the site's tag.
 *     @type \OhaDeals\Render\Helpers $helpers            Template helpers.
 *     @type string                   $wrapper_attributes Escaped wrapper attributes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$oha_deals_product = $args['product'];
$oha_deals_h       = $args['helpers'];
$oha_deals_format  = $oha_deals_h->format();

// The row that carries the product's best price: new goods with a visible price only.
$oha_deals_best = $oha_deals_product->best_offer();
if ( null !== $oha_deals_best && '' === $oha_deals_h->offer_price( $oha_deals_best ) ) {
	$oha_deals_best = null;
}
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_block_wrapper_attributes() or esc_attr(). ?>>
	<?php // The explicit roles keep the table semantics when narrow containers lay the rows out as grids. ?>
	<table class="oha-deals-table oha-deals-offers-table" role="table">
		<caption class="<?php echo esc_attr( $args['show_title'] ? 'oha-deals-table__caption' : 'oha-deals-visually-hidden' ); ?>">
			<?php
			/* translators: %s: product name. */
			echo esc_html( sprintf( __( 'Prices for %s', 'oha-deals' ), $oha_deals_product->name() ) );
			?>
		</caption>
		<thead role="rowgroup">
			<tr role="row">
				<th scope="col" role="columnheader" class="oha-deals-offers-table__shop"><?php esc_html_e( 'Shop', 'oha-deals' ); ?></th>
				<th scope="col" role="columnheader" class="oha-deals-offers-table__price"><?php esc_html_e( 'Price', 'oha-deals' ); ?></th>
				<th scope="col" role="columnheader" class="oha-deals-offers-table__action"><span class="oha-deals-visually-hidden"><?php esc_html_e( 'Link', 'oha-deals' ); ?></span></th>
			</tr>
		</thead>
		<tbody role="rowgroup">
			<?php foreach ( $args['offers'] as $oha_deals_offer ) : ?>
				<?php
				$oha_deals_url       = $oha_deals_h->offer_url( $oha_deals_offer, $oha_deals_product );
				$oha_deals_label     = $oha_deals_h->offer_link_label( $oha_deals_offer, $oha_deals_product );
				$oha_deals_price     = $oha_deals_h->offer_price( $oha_deals_offer );
				$oha_deals_condition = $oha_deals_format->condition( $oha_deals_offer->condition() );
				$oha_deals_as_of     = $oha_deals_h->offer_as_of( $oha_deals_offer, $oha_deals_product );
				$oha_deals_stock     = $oha_deals_format->availability( $oha_deals_offer->availability() );
				$oha_deals_is_best   = $oha_deals_offer === $oha_deals_best;
				?>
				<tr role="row" class="oha-deals-offers-table__row<?php echo $oha_deals_is_best ? ' oha-deals-offers-table__row--best' : ''; ?>">
					<th scope="row" role="rowheader" class="oha-deals-offers-table__shop">
						<span class="oha-deals-offers-table__name"><?php echo esc_html( $oha_deals_offer->shop_name() ); ?></span>
						<?php if ( $oha_deals_is_best ) : ?>
							<span class="oha-deals-badge oha-deals-badge--best"><?php esc_html_e( 'Best price', 'oha-deals' ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $oha_deals_condition ) : ?>
							<span class="oha-deals-badge oha-deals-badge--condition"><?php echo esc_html( $oha_deals_condition ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $oha_deals_stock ) : ?>
							<span class="<?php echo esc_attr( 'oha-deals-stock oha-deals-stock--' . sanitize_html_class( $oha_deals_offer->availability() ) ); ?>"><?php echo esc_html( $oha_deals_stock ); ?></span>
						<?php endif; ?>
					</th>
					<td role="cell" class="oha-deals-offers-table__price">
						<?php if ( '' !== $oha_deals_price ) : ?>
							<strong class="oha-deals-amount"><?php echo esc_html( $oha_deals_price ); ?></strong>
							<?php if ( $args['show_shipping'] ) : ?>
								<small class="oha-deals-offers-table__shipping"><?php echo esc_html( $oha_deals_format->shipping( $oha_deals_offer->shipping(), $oha_deals_offer->currency() ) ); ?></small>
							<?php endif; ?>
							<?php if ( '' !== $oha_deals_as_of ) : ?>
								<small class="oha-deals-as-of"><?php echo esc_html( $oha_deals_as_of ); ?></small>
							<?php endif; ?>
						<?php else : ?>
							<span class="oha-deals-offers-table__hidden"><?php esc_html_e( 'Price at the shop', 'oha-deals' ); ?></span>
						<?php endif; ?>
					</td>
					<td role="cell" class="oha-deals-offers-table__action">
						<?php if ( '' !== $oha_deals_url ) : ?>
							<a class="oha-deals-button oha-deals-button--small <?php echo $oha_deals_is_best ? 'oha-deals-button--primary' : 'oha-deals-button--secondary'; ?>"<?php $oha_deals_h->print_link_attributes( $oha_deals_url ); ?>>
								<?php
								echo esc_html( $oha_deals_label );
								if ( '' !== $oha_deals_offer->shop_name() && false === strpos( $oha_deals_label, $oha_deals_offer->shop_name() ) ) {
									echo '<span class="oha-deals-visually-hidden">: ' . esc_html( $oha_deals_offer->shop_name() ) . '</span>';
								}
								$oha_deals_h->print_new_tab_hint();
								?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php $oha_deals_h->print_footer( (bool) $args['amazon_prices'], (bool) $args['amazon_links'] ); ?>
</div>
