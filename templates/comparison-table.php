<?php
/**
 * Comparison of several products: as columns (feature table) or as rows (ranked list).
 *
 * Override by copying to `<theme>/oha-deals/comparison-table.php`.
 *
 * @package OhaDeals
 *
 * @var array<string, mixed> $args {
 *     @type \OhaDeals\Model\Product[]                              $products           Products (at least one).
 *     @type string[]                                               $fields             Fields in display order: image, brand, price,
 *                                                                                      before, discount, shop, offers, lowest, change, specs.
 *     @type string                                                 $orientation        "columns" or "rows".
 *     @type int|null                                               $best_index         Index of the product with the best price, null to mark none.
 *     @type array<int, array{label: string, values: string[]}>     $spec_rows          Specifications, values aligned with the products.
 *     @type string                                                 $title              Title; empty uses a hidden default caption.
 *     @type bool                                                   $show_numbers       Whether the products are numbered.
 *     @type string                                                 $button_text        Button label; empty uses a label that says where the link leads.
 *     @type bool                                                   $amazon_prices      Whether Amazon prices are shown.
 *     @type bool                                                   $amazon_links       Whether Amazon is linked with the site's tag.
 *     @type \OhaDeals\Render\Helpers                               $helpers            Template helpers.
 *     @type string                                                 $wrapper_attributes Escaped wrapper attributes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$oha_deals_h        = $args['helpers'];
$oha_deals_products = array_values( $args['products'] );
$oha_deals_fields   = $args['fields'];
$oha_deals_rows     = 'rows' === $args['orientation'];
$oha_deals_images   = in_array( 'image', $oha_deals_fields, true ) && $oha_deals_h->shows_images();
$oha_deals_caption  = wp_unique_id( 'oha-deals-comparison-' );
$oha_deals_empty    = '<span aria-hidden="true">–</span><span class="oha-deals-visually-hidden">' . esc_html__( 'not available', 'oha-deals' ) . '</span>';

$oha_deals_labels = array(
	'price'    => __( 'Best price', 'oha-deals' ),
	'before'   => __( '30-day low', 'oha-deals' ),
	'discount' => __( 'Discount', 'oha-deals' ),
	'shop'     => __( 'Shop', 'oha-deals' ),
	'offers'   => __( 'Offers', 'oha-deals' ),
	'lowest'   => __( 'Lowest price so far', 'oha-deals' ),
	'change'   => __( 'Price change, 30 days', 'oha-deals' ),
);

// The reference price and the discount are printed with the price when it is shown.
$oha_deals_with_price = in_array( 'price', $oha_deals_fields, true );
$oha_deals_show       = array(
	'before'   => in_array( 'before', $oha_deals_fields, true ),
	'discount' => in_array( 'discount', $oha_deals_fields, true ),
);

/**
 * One value as escaped HTML; empty when the product has none.
 *
 * @param string                  $oha_deals_field   Field.
 * @param \OhaDeals\Model\Product $oha_deals_product Product.
 * @return string
 */
$oha_deals_value = static function ( string $oha_deals_field, \OhaDeals\Model\Product $oha_deals_product ) use ( $oha_deals_h, $oha_deals_show ): string {
	$oha_deals_format = $oha_deals_h->format();
	$oha_deals_price  = $oha_deals_h->product_price( $oha_deals_product );
	$oha_deals_best   = $oha_deals_product->best_offer();
	$oha_deals_before = $oha_deals_h->before_price( $oha_deals_product );
	$oha_deals_off    = $oha_deals_h->discount( $oha_deals_product );

	switch ( $oha_deals_field ) {
		case 'price':
			if ( '' === $oha_deals_price ) {
				return $oha_deals_product->offer_count() > 0 ? '<span class="oha-deals-comparison__hidden">' . esc_html__( 'Price at the shop', 'oha-deals' ) . '</span>' : '';
			}
			$oha_deals_html = '<strong class="oha-deals-amount">' . esc_html( $oha_deals_price ) . '</strong>';
			if ( $oha_deals_show['discount'] && '' !== $oha_deals_off ) {
				$oha_deals_html .= ' <span class="oha-deals-badge oha-deals-badge--discount">' . esc_html( $oha_deals_off ) . '</span>';
			}
			if ( $oha_deals_show['before'] && '' !== $oha_deals_before ) {
				$oha_deals_html .= ' <span class="oha-deals-before"><span class="oha-deals-visually-hidden">' . esc_html__( '30-day low:', 'oha-deals' ) . '</span><s>' . esc_html( $oha_deals_before ) . '</s></span>';
			}
			// Amazon prices carry their time; without one of their own, the product's last price check.
			$oha_deals_as_of = $oha_deals_h->product_as_of( $oha_deals_product );
			if ( '' !== $oha_deals_as_of ) {
				$oha_deals_html .= '<small class="oha-deals-as-of">' . esc_html( $oha_deals_as_of ) . '</small>';
			}
			return $oha_deals_html;

		case 'before':
			return '' !== $oha_deals_before ? '<s>' . esc_html( $oha_deals_before ) . '</s>' : '';

		case 'discount':
			return '' !== $oha_deals_off ? '<span class="oha-deals-badge oha-deals-badge--discount">' . esc_html( $oha_deals_off ) . '</span>' : '';

		case 'shop':
			$oha_deals_shop = (string) $oha_deals_product->shop();
			if ( '' === $oha_deals_shop && null !== $oha_deals_best ) {
				$oha_deals_shop = $oha_deals_best->shop_name();
			}
			if ( '' === $oha_deals_shop ) {
				return '';
			}
			$oha_deals_html = esc_html( $oha_deals_shop );
			if ( '' !== $oha_deals_price && null !== $oha_deals_best ) {
				$oha_deals_html .= '<small class="oha-deals-comparison__note">' . esc_html( $oha_deals_format->shipping( $oha_deals_best->shipping(), $oha_deals_best->currency() ) ) . '</small>';
			}
			return $oha_deals_html;

		case 'offers':
			$oha_deals_count = $oha_deals_product->offer_count();
			if ( $oha_deals_count < 1 ) {
				return '';
			}
			/* translators: %s: number of offers. */
			return esc_html( sprintf( _n( '%s offer', '%s offers', $oha_deals_count, 'oha-deals' ), number_format_i18n( $oha_deals_count ) ) );

		case 'lowest':
			$oha_deals_low = $oha_deals_product->all_time_low();
			if ( null === $oha_deals_low ) {
				return '';
			}
			return esc_html( $oha_deals_format->money( $oha_deals_low['price'], $oha_deals_product->currency() ) )
				. '<small class="oha-deals-comparison__note">' . esc_html( $oha_deals_format->date( $oha_deals_low['date'] ) ) . '</small>';

		case 'change':
			$oha_deals_change = $oha_deals_product->change( 'd30' );
			if ( null === $oha_deals_change ) {
				return '';
			}
			$oha_deals_kind = 'same';
			if ( round( $oha_deals_change, 1 ) < 0 ) {
				$oha_deals_kind = 'down';
			} elseif ( round( $oha_deals_change, 1 ) > 0 ) {
				$oha_deals_kind = 'up';
			}
			return '<span class="' . esc_attr( 'oha-deals-change oha-deals-change--' . $oha_deals_kind ) . '">' . esc_html( $oha_deals_format->percent( $oha_deals_change, 1 ) ) . '</span>';

		default:
			return '';
	}
};

// The rows (or columns) to show: fields without a value for any product are left out. A list has no room for a column
// per specification, so the first few are printed under the product name there.
$oha_deals_lines = array();
$oha_deals_specs = $oha_deals_rows && in_array( 'specs', $oha_deals_fields, true ) ? array_slice( $args['spec_rows'], 0, 4 ) : array();
foreach ( $oha_deals_fields as $oha_deals_field ) {
	if ( 'specs' === $oha_deals_field ) {
		if ( $oha_deals_rows ) {
			continue;
		}
		foreach ( $args['spec_rows'] as $oha_deals_spec ) {
			$oha_deals_lines[] = array(
				'field'  => 'specs',
				'label'  => $oha_deals_spec['label'],
				'values' => array_map( 'esc_html', array_values( $oha_deals_spec['values'] ) ),
			);
		}
		continue;
	}
	if ( ! isset( $oha_deals_labels[ $oha_deals_field ] ) || ( $oha_deals_with_price && isset( $oha_deals_show[ $oha_deals_field ] ) ) ) {
		continue;
	}
	$oha_deals_values = array();
	foreach ( $oha_deals_products as $oha_deals_product ) {
		$oha_deals_values[] = $oha_deals_value( $oha_deals_field, $oha_deals_product );
	}
	if ( '' !== implode( '', $oha_deals_values ) ) {
		$oha_deals_lines[] = array(
			'field'  => $oha_deals_field,
			'label'  => $oha_deals_labels[ $oha_deals_field ],
			'values' => $oha_deals_values,
		);
	}
}

/**
 * The product head: number, image, brand, name and the best-price label.
 *
 * @param int $oha_deals_index Product index.
 * @return void
 */
$oha_deals_head = static function ( int $oha_deals_index ) use ( $args, $oha_deals_h, $oha_deals_products, $oha_deals_fields, $oha_deals_images, $oha_deals_specs ): void {
	$oha_deals_product = $oha_deals_products[ $oha_deals_index ];
	?>
	<span class="oha-deals-comparison__product">
		<?php if ( $oha_deals_images ) : ?>
			<span class="oha-deals-comparison__media">
				<?php $oha_deals_h->print_image( $oha_deals_product->image(), $oha_deals_h->image_alt( $oha_deals_product ), 160 ); ?>
			</span>
		<?php endif; ?>
		<span class="oha-deals-comparison__text">
			<?php if ( in_array( 'brand', $oha_deals_fields, true ) && '' !== $oha_deals_product->brand() && 0 !== stripos( $oha_deals_product->name(), $oha_deals_product->brand() ) ) : ?>
				<span class="oha-deals-comparison__brand"><?php echo esc_html( $oha_deals_product->brand() ); ?></span>
			<?php endif; ?>
			<span class="oha-deals-comparison__name"><?php echo esc_html( $oha_deals_product->name() ); ?></span>
			<?php if ( $args['best_index'] === $oha_deals_index ) : ?>
				<span class="oha-deals-badge oha-deals-badge--best"><?php esc_html_e( 'Best price', 'oha-deals' ); ?></span>
			<?php endif; ?>
			<?php if ( $oha_deals_specs ) : ?>
				<span class="oha-deals-comparison__specs">
					<?php foreach ( $oha_deals_specs as $oha_deals_spec ) : ?>
						<?php $oha_deals_text = (string) ( array_values( $oha_deals_spec['values'] )[ $oha_deals_index ] ?? '' ); ?>
						<?php if ( '' !== $oha_deals_text ) : ?>
							<span class="oha-deals-comparison__spec"><?php echo esc_html( $oha_deals_spec['label'] . ': ' . $oha_deals_text ); ?></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>
		</span>
	</span>
	<?php
};

/**
 * The link button of a product.
 *
 * @param int $oha_deals_index Product index.
 * @return void
 */
$oha_deals_link = static function ( int $oha_deals_index ) use ( $args, $oha_deals_h, $oha_deals_products ): void {
	$oha_deals_product = $oha_deals_products[ $oha_deals_index ];
	$oha_deals_url     = $oha_deals_h->best_url( $oha_deals_product );
	if ( '' === $oha_deals_url ) {
		return;
	}
	$oha_deals_text  = '' !== $args['button_text'] ? (string) $args['button_text'] : $oha_deals_h->best_link_label( $oha_deals_product );
	$oha_deals_class = null === $args['best_index'] || $args['best_index'] === $oha_deals_index ? 'primary' : 'secondary';
	?>
	<a class="<?php echo esc_attr( 'oha-deals-button oha-deals-button--small oha-deals-button--' . $oha_deals_class ); ?>"<?php $oha_deals_h->print_link_attributes( $oha_deals_url ); ?>>
		<?php echo esc_html( $oha_deals_text ); ?>
		<span class="oha-deals-visually-hidden"><?php echo esc_html( ': ' . $oha_deals_product->name() ); ?></span>
		<?php $oha_deals_h->print_new_tab_hint(); ?>
	</a>
	<?php
};

$oha_deals_count = count( $oha_deals_products );
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_block_wrapper_attributes() or esc_attr(). ?>>
	<?php // The explicit roles keep the table semantics when narrow containers lay the rows out as cards. ?>
	<div class="oha-deals-comparison__scroll" role="region" aria-labelledby="<?php echo esc_attr( $oha_deals_caption ); ?>" tabindex="0">
		<table class="<?php echo esc_attr( 'oha-deals-table oha-deals-comparison__table oha-deals-comparison__table--' . ( $oha_deals_rows ? 'rows' : 'columns' ) ); ?>" role="table" style="<?php echo esc_attr( '--oha-deals-products: ' . $oha_deals_count ); ?>">
			<caption id="<?php echo esc_attr( $oha_deals_caption ); ?>" class="<?php echo esc_attr( '' !== $args['title'] ? 'oha-deals-table__caption' : 'oha-deals-visually-hidden' ); ?>">
				<span class="oha-deals-comparison__title"><?php echo esc_html( '' !== $args['title'] ? $args['title'] : __( 'Product comparison', 'oha-deals' ) ); ?></span>
			</caption>
			<?php if ( $oha_deals_rows ) : ?>
				<thead role="rowgroup">
					<tr role="row">
						<?php if ( $args['show_numbers'] ) : ?>
							<th scope="col" role="columnheader" class="oha-deals-comparison__rank"><span class="oha-deals-visually-hidden"><?php esc_html_e( 'Rank', 'oha-deals' ); ?></span></th>
						<?php endif; ?>
						<th scope="col" role="columnheader"><?php esc_html_e( 'Product', 'oha-deals' ); ?></th>
						<?php foreach ( $oha_deals_lines as $oha_deals_line ) : ?>
							<th scope="col" role="columnheader"><?php echo esc_html( $oha_deals_line['label'] ); ?></th>
						<?php endforeach; ?>
						<th scope="col" role="columnheader"><span class="oha-deals-visually-hidden"><?php esc_html_e( 'Link', 'oha-deals' ); ?></span></th>
					</tr>
				</thead>
				<tbody role="rowgroup">
					<?php foreach ( $oha_deals_products as $oha_deals_index => $oha_deals_product ) : ?>
						<tr role="row" class="oha-deals-comparison__row<?php echo $args['best_index'] === $oha_deals_index ? ' oha-deals-comparison__row--best' : ''; ?>">
							<?php if ( $args['show_numbers'] ) : ?>
								<td role="cell" class="oha-deals-comparison__rank"><span class="oha-deals-comparison__number"><?php echo esc_html( number_format_i18n( $oha_deals_index + 1 ) ); ?></span></td>
							<?php endif; ?>
							<th scope="row" role="rowheader" class="oha-deals-comparison__head"><?php $oha_deals_head( $oha_deals_index ); ?></th>
							<?php foreach ( $oha_deals_lines as $oha_deals_line ) : ?>
								<?php $oha_deals_cell = (string) ( $oha_deals_line['values'][ $oha_deals_index ] ?? '' ); ?>
								<td role="cell" class="<?php echo esc_attr( 'oha-deals-comparison__cell oha-deals-comparison__cell--' . $oha_deals_line['field'] . ( '' === $oha_deals_cell ? ' is-empty' : '' ) ); ?>">
									<span class="oha-deals-comparison__label" aria-hidden="true"><?php echo esc_html( $oha_deals_line['label'] ); ?></span>
									<span class="oha-deals-comparison__value"><?php echo '' !== $oha_deals_cell ? $oha_deals_cell : $oha_deals_empty; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $oha_deals_value and above. ?></span>
								</td>
							<?php endforeach; ?>
							<td role="cell" class="oha-deals-comparison__action"><?php $oha_deals_link( $oha_deals_index ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			<?php else : ?>
				<thead role="rowgroup">
					<tr role="row">
						<td role="cell" class="oha-deals-comparison__corner"></td>
						<?php foreach ( $oha_deals_products as $oha_deals_index => $oha_deals_product ) : ?>
							<th scope="col" role="columnheader" class="oha-deals-comparison__head<?php echo $args['best_index'] === $oha_deals_index ? ' is-best' : ''; ?>">
								<?php if ( $args['show_numbers'] ) : ?>
									<span class="oha-deals-comparison__number"><?php echo esc_html( number_format_i18n( $oha_deals_index + 1 ) ); ?></span>
								<?php endif; ?>
								<?php $oha_deals_head( $oha_deals_index ); ?>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody role="rowgroup">
					<?php foreach ( $oha_deals_lines as $oha_deals_line ) : ?>
						<tr role="row">
							<th scope="row" role="rowheader" class="oha-deals-comparison__attribute"><?php echo esc_html( $oha_deals_line['label'] ); ?></th>
							<?php foreach ( $oha_deals_products as $oha_deals_index => $oha_deals_product ) : ?>
								<?php $oha_deals_cell = (string) ( $oha_deals_line['values'][ $oha_deals_index ] ?? '' ); ?>
								<td role="cell" class="<?php echo esc_attr( 'oha-deals-comparison__cell oha-deals-comparison__cell--' . $oha_deals_line['field'] . ( $args['best_index'] === $oha_deals_index ? ' is-best' : '' ) ); ?>">
									<?php echo '' !== $oha_deals_cell ? $oha_deals_cell : $oha_deals_empty; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $oha_deals_value and above. ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					<tr role="row">
						<th scope="row" role="rowheader" class="oha-deals-comparison__attribute"><span class="oha-deals-visually-hidden"><?php esc_html_e( 'Link', 'oha-deals' ); ?></span></th>
						<?php foreach ( $oha_deals_products as $oha_deals_index => $oha_deals_product ) : ?>
							<td role="cell" class="oha-deals-comparison__action<?php echo $args['best_index'] === $oha_deals_index ? ' is-best' : ''; ?>"><?php $oha_deals_link( $oha_deals_index ); ?></td>
						<?php endforeach; ?>
					</tr>
				</tbody>
			<?php endif; ?>
		</table>
	</div>
	<?php $oha_deals_h->print_footer( (bool) $args['amazon_prices'], (bool) $args['amazon_links'] ); ?>
</div>
