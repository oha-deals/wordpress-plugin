<?php
/**
 * Coupon list.
 *
 * Override by copying to `<theme>/oha-deals/coupons-list.php`.
 *
 * @package OhaDeals
 *
 * @var array<string, mixed> $args {
 *     @type \OhaDeals\Model\Coupon[] $coupons            Coupons (at least one).
 *     @type \OhaDeals\Render\Helpers $helpers            Template helpers.
 *     @type string                   $wrapper_attributes Escaped wrapper attributes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$oha_deals_h = $args['helpers'];
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_block_wrapper_attributes() or esc_attr(). ?>>
	<ul class="oha-deals-coupons__list">
		<?php foreach ( $args['coupons'] as $oha_deals_coupon ) : ?>
			<?php
			$oha_deals_url   = $oha_deals_h->coupon_url( $oha_deals_coupon );
			$oha_deals_until = null !== $oha_deals_coupon->valid_until() ? $oha_deals_h->format()->date( $oha_deals_coupon->valid_until() ) : '';
			?>
			<li class="oha-deals-coupon">
				<div class="oha-deals-coupon__body">
					<p class="oha-deals-coupon__shop"><?php echo esc_html( $oha_deals_coupon->shop() ); ?></p>
					<?php if ( '' !== $oha_deals_coupon->title() ) : ?>
						<h3 class="oha-deals-coupon__title"><?php echo esc_html( $oha_deals_coupon->title() ); ?></h3>
					<?php endif; ?>
					<?php if ( '' !== $oha_deals_coupon->description() ) : ?>
						<p class="oha-deals-coupon__description"><?php echo esc_html( $oha_deals_coupon->description() ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $oha_deals_until ) : ?>
						<p class="oha-deals-coupon__until">
							<?php
							/* translators: %s: date. */
							echo esc_html( sprintf( __( 'Valid until %s', 'oha-deals' ), $oha_deals_until ) );
							?>
						</p>
					<?php endif; ?>
				</div>
				<div class="oha-deals-coupon__stub">
					<?php if ( null !== $oha_deals_coupon->code() ) : ?>
						<?php
						// The copy button needs JavaScript and stays invisible without it; the code itself is selectable text.
						$oha_deals_context = wp_interactivity_data_wp_context(
							array(
								'ready'  => false,
								'copied' => false,
								'status' => '',
								'done'   => __( 'Copied', 'oha-deals' ),
								'manual' => __( 'Selected – copy it with your keyboard.', 'oha-deals' ),
							)
						);
						?>
						<div
							class="oha-deals-coupon__chip"
							data-wp-interactive="oha-deals/coupon"
							<?php echo $oha_deals_context; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_* flags by core. ?>
							data-wp-init="callbacks.init"
							data-wp-class--is-ready="context.ready"
							data-wp-class--is-copied="context.copied"
						>
							<span class="oha-deals-visually-hidden"><?php esc_html_e( 'Code:', 'oha-deals' ); ?></span>
							<code class="oha-deals-coupon__code"><?php echo esc_html( $oha_deals_coupon->code() ); ?></code>
							<button type="button" class="oha-deals-coupon__copy" data-wp-on--click="actions.copy">
								<svg class="oha-deals-coupon__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path class="oha-deals-coupon__icon-copy" d="M9 9V5.5A1.5 1.5 0 0 1 10.5 4h8A1.5 1.5 0 0 1 20 5.5v8a1.5 1.5 0 0 1-1.5 1.5H15M5.5 9h8a1.5 1.5 0 0 1 1.5 1.5v8a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 4 18.5v-8A1.5 1.5 0 0 1 5.5 9Z" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path class="oha-deals-coupon__icon-done" d="m5 12.5 4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/></svg>
								<span class="oha-deals-visually-hidden">
									<?php
									/* translators: %s: coupon code. */
									echo esc_html( sprintf( __( 'Copy code %s', 'oha-deals' ), $oha_deals_coupon->code() ) );
									?>
								</span>
							</button>
							<span class="oha-deals-coupon__status" role="status" data-wp-text="context.status"></span>
						</div>
					<?php elseif ( $oha_deals_coupon->is_code_hidden() ) : ?>
						<p class="oha-deals-coupon__hint"><?php esc_html_e( 'The code is shown at the shop.', 'oha-deals' ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $oha_deals_url ) : ?>
						<p class="oha-deals-coupon__action">
							<a class="oha-deals-button oha-deals-button--small oha-deals-button--primary"<?php $oha_deals_h->print_link_attributes( $oha_deals_url ); ?>>
								<?php
								echo esc_html( $oha_deals_h->coupon_link_label( $oha_deals_coupon ) );
								if ( ! $oha_deals_h->is_own_link( $oha_deals_url ) ) {
									echo '<span class="oha-deals-visually-hidden">: ' . esc_html( $oha_deals_coupon->shop() ) . '</span>';
								}
								$oha_deals_h->print_new_tab_hint();
								?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php $oha_deals_h->print_footer( false, false ); ?>
</div>
