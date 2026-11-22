<?php
/**
 * Price history chart.
 *
 * Override by copying to `<theme>/oha-deals/price-history.php`.
 *
 * @package OhaDeals
 *
 * @var array<string, mixed> $args {
 *     @type \OhaDeals\Model\Product  $product            Product.
 *     @type int                      $days               Range in days.
 *     @type string                   $chart              Escaped chart markup, empty without data.
 *     @type \OhaDeals\Render\Helpers $helpers            Template helpers.
 *     @type string                   $wrapper_attributes Escaped wrapper attributes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$oha_deals_product = $args['product'];
$oha_deals_h       = $args['helpers'];
$oha_deals_low     = $oha_deals_product->all_time_low();
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_block_wrapper_attributes() or esc_attr(). ?>>
	<div class="oha-deals-history__header">
		<p class="oha-deals-history__heading">
			<?php
			/* translators: %s: product name. */
			echo esc_html( sprintf( __( 'Price history of %s', 'oha-deals' ), $oha_deals_product->name() ) );
			?>
		</p>
		<p class="oha-deals-history__period">
			<?php
			/* translators: %d: number of days. */
			echo esc_html( sprintf( _n( 'Last %d day', 'Last %d days', (int) $args['days'], 'oha-deals' ), (int) $args['days'] ) );
			?>
		</p>
	</div>
	<?php if ( '' !== $args['chart'] ) : ?>
		<?php echo $args['chart']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts by HistoryChart::render(). ?>
	<?php else : ?>
		<p class="oha-deals-history__empty"><?php esc_html_e( 'No price history has been recorded for this period yet.', 'oha-deals' ); ?></p>
	<?php endif; ?>
	<?php if ( null !== $oha_deals_low ) : ?>
		<p class="oha-deals-history__low">
			<svg class="oha-deals-history__low-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="m4 7 6 6 4-4 6 7m0-5v5h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			<span>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: price, 2: date. */
						__( 'Lowest price so far: %1$s on %2$s', 'oha-deals' ),
						$oha_deals_h->format()->money( $oha_deals_low['price'], $oha_deals_product->currency() ),
						$oha_deals_h->format()->date( $oha_deals_low['date'] )
					)
				);
				?>
			</span>
		</p>
	<?php endif; ?>
	<?php $oha_deals_h->print_footer( false, false, false, false ); ?>
</div>
