# Building on oha.deals for WordPress

How to build your own tables, cards and templates from the plugin's data. Everything here is cached on your server
and needs the API key from Settings → oha.deals.

## Ways to build something

| You want | Use |
|---|---|
| A finished product card, offers table, deals grid, comparison, chart or coupon list | the blocks, the shortcodes or `oha_deals_render()` |
| The same element with your own markup | a template override in your theme |
| A starting point in the editor | the block patterns in the category oha.deals |
| Live values inside core blocks (paragraph, heading, button, image) | the block bindings source `oha-deals/product` |
| Raw data for your own code | `oha_deals_query()` |
| Data on the command line | `wp oha-deals product <slug> --format=json`, `wp oha-deals deals --format=json` |

There is no public REST route for the data: it would let anyone spend your API quota.

## Data API

```php
$data = oha_deals_query( $type, $args ); // array or WP_Error
```

| Type | Arguments | Returns |
|---|---|---|
| `product` | `slug` or `ean`; `include` (`array( 'history' )`), `days` (30, 90, 180, 365) | one product |
| `products` | `slugs`: array or comma list of slugs, EANs or oha.deals URLs (at most 20) | products in that order; unknown ones are left out |
| `search` | `q`, `limit` (1–50), `category` | products (summary) |
| `deals` | `mode` (all, drop, low), `sort` (percent, amount, price, new), `category`, `minPrice`, `maxPrice`, `limit` (1–100), `offset` | deals |
| `coupons` | `host`, `limit` (1–100) | coupons |
| `categories` | – | `slug`, `name`, `count` |

Each product is one cached API request, so prefer `search` or `deals` for long lists.

### Shapes

Keys are stable within a major version; new keys may be added. Every model also has `to_array()`
(`oha_deals_get_product( 'slug' )->to_array()`), which returns the same data without `link` and `link_is_own`.

```jsonc
// product
{
  "id": 34756, "slug": "example-ssd-1tb", "name": "Example SSD 1 TB", "brand": "Example",
  "category": { "slug": "ssd", "name": "SSD" },
  "ean": "4260000000000",
  "url": "https://oha.deals/produkt/example-ssd-1tb",
  "image": "https://…", "images": ["https://…"],     // images: detail only
  "spec": "SSD · 1 TB",
  "attrs": { "Typ": "SSD", "Kapazität": "1 TB" },    // label => value; [] when there are none
  "price": 79.9, "price_hidden": false, "price_at": null, "currency": "EUR", "shop": "Example Shop",
  "offer_count": 4,
  "before": 99.0, "discount_percent": 19,            // null unless a discount may be shown
  "change": { "d1": 0, "d7": -2.1, "d30": -4.2 },    // percent
  "msrp": 119.0, "low30": 79.9,
  "all_time_low": { "price": 74.9, "date": "2026-10-02" },
  "offers": [Offer], "used_offers": [Offer],         // detail only
  "history": [{ "date": "2026-11-19", "price": 79.9, "avg": 85.1 }], // with include=history
  "prices_checked_at": "2026-11-20T04:15:00.000Z",
  "omitted": [],                                     // e.g. ["history"] when the key lacks a capability
  "link": "https://…", "link_is_own": false
}

// offer
{
  "shop": { "id": "…", "name": "Example Shop", "host": "shop.example" },
  "price": 79.9, "price_hidden": false, "price_at": null,
  "shipping": 4.99, "total": 84.89, "currency": "EUR",   // shipping null = unknown, never "free"
  "availability": "in_stock",                            // in_stock, short, long, on_order
  "condition": "new",                                    // new, used, refurbished
  "is_amazon": false, "network": "awin", "asin": null,
  "awin_merchant_id": 1, "awin_product_id": "2", "merchant_url": null,
  "link": "https://…", "link_is_own": true
}

// deal
{ "kind": "drop", "is_all_time_low": false, "last_seen": "2026-11-20", "product": { … } }

// coupon
{
  "id": "c1", "shop": "Example Shop", "host": "shop.example", "title": "…", "description": "…",
  "code": "SAVE10", "code_hidden": false, "url": "https://oha.deals/gutscheine/…", "valid_until": "2026-12-31",
  "link": "https://…", "link_is_own": false
}
```

### Rules when you print the data yourself

- Escape everything (`esc_html()`, `esc_url()`).
- Link with `link`. When `link_is_own` is true it is your affiliate link: add `rel="sponsored nofollow noopener"` and
  disclose it. Never print oha.deals redirect URLs.
- A price with `price_at` is Amazon's. Show the time next to it together with the Amazon disclaimer, or do not show it.
- Respect `price_hidden`; show a struck price only when `before` is set; do not call unknown shipping free.
- Images are loaded from other hosts by the visitor's browser. The "Product images" setting does not apply to your code.

## Custom shortcode: a small price table

```php
add_shortcode( 'my_ssd_table', function () {
	$products = oha_deals_query( 'products', array( 'slugs' => array( 'ssd-one', 'ssd-two', 'ssd-three' ) ) );
	if ( is_wp_error( $products ) || ! $products ) {
		return '';
	}

	$rows = '';
	$own  = false;
	foreach ( $products as $product ) {
		// Amazon prices need their timestamp, so this table leaves them out.
		$price = null === $product['price'] || null !== $product['price_at']
			? '–'
			: number_format_i18n( $product['price'], 2 ) . ' €';
		$own   = $own || $product['link_is_own'];
		$rows .= sprintf(
			'<tr><th scope="row"><a href="%1$s" rel="%2$s">%3$s</a></th><td>%4$s</td><td>%5$s</td></tr>',
			esc_url( $product['link'] ),
			esc_attr( $product['link_is_own'] ? 'sponsored nofollow noopener' : 'nofollow noopener' ),
			esc_html( $product['name'] ),
			esc_html( $product['attrs']['Kapazität'] ?? '–' ),
			esc_html( $price )
		);
	}

	$note = $own ? 'Links to shops are affiliate links.' : 'Links lead to the price comparison on oha.deals.';

	return '<table><thead><tr><th>Product</th><th>Capacity</th><th>Price</th></tr></thead><tbody>' . $rows . '</tbody></table>'
		. '<p><small>' . esc_html( $note ) . '</small></p>';
} );
```

## Template overrides

Copy a file from `templates/` to `<theme>/oha-deals/` (child theme first). Each file documents its `$args` at the top.
`$args['helpers']` (`OhaDeals\Render\Helpers`) formats prices and prints links; its `print_*` methods echo escaped markup,
all others return plain text that you escape.

A comparison as a plain table, `<theme>/oha-deals/comparison-table.php`:

```php
<?php
defined( 'ABSPATH' ) || exit;

$helpers = $args['helpers'];
?>
<div <?php echo $args['wrapper_attributes']; // Already escaped. ?>>
	<table>
		<thead>
			<tr>
				<th scope="col">Product</th>
				<?php foreach ( $args['spec_rows'] as $row ) : ?>
					<th scope="col"><?php echo esc_html( $row['label'] ); ?></th>
				<?php endforeach; ?>
				<th scope="col">Price</th>
				<th scope="col">Link</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $args['products'] as $index => $product ) : ?>
				<?php $url = $helpers->best_url( $product ); ?>
				<tr<?php echo $index === $args['best_index'] ? ' class="is-best"' : ''; ?>>
					<th scope="row"><?php echo esc_html( $product->name() ); ?></th>
					<?php foreach ( $args['spec_rows'] as $row ) : ?>
						<td><?php echo esc_html( $row['values'][ $index ] ); ?></td>
					<?php endforeach; ?>
					<td>
						<?php echo esc_html( $helpers->product_price( $product ) ); ?>
						<small><?php echo esc_html( $helpers->product_as_of( $product ) ); ?></small>
					</td>
					<td>
						<?php if ( '' !== $url ) : ?>
							<a<?php $helpers->print_link_attributes( $url ); ?>><?php echo esc_html( $helpers->best_link_label( $product ) ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php $helpers->print_footer( $args['amazon_prices'], $args['amazon_links'] ); // Disclosure, Amazon notes, credit. ?>
</div>
```

`comparison-table` receives `products`, `fields`, `orientation`, `best_index` (or null), `spec_rows` (`label`, `values` in
product order), `title`, `show_numbers`, `button_text` (may be empty: use `best_link_label()`), `amazon_prices`,
`amazon_links`, `attributes`, `helpers` and `wrapper_attributes`.

Link helpers: `offer_url()`, `best_url()`, `product_url()`, `coupon_url()`, `is_own_link()`, `offer_link_label()`,
`best_link_label()`, `coupon_link_label()`, `print_link_attributes()`. Prices: `product_price()`, `before_price()`,
`discount()`, `product_as_of()` (the time an Amazon price needs next to it), `offer_price()`, `offer_as_of()`.

## Block bindings

Core paragraph, heading, button and image blocks can show live values. Switch to the code editor, or use these blocks in a
pattern or block template:

```html
<!-- wp:heading {"metadata":{"bindings":{"content":{"source":"oha-deals/product","args":{"slug":"example-ssd-1tb","field":"name"}}}}} -->
<h2 class="wp-block-heading">Product name</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"oha-deals/product","args":{"slug":"example-ssd-1tb","field":"price"}}}}} -->
<p>See the current price</p>
<!-- /wp:paragraph -->

<!-- wp:button {"metadata":{"bindings":{"url":{"source":"oha-deals/product","args":{"slug":"example-ssd-1tb","field":"url"}},"rel":{"source":"oha-deals/product","args":{"slug":"example-ssd-1tb","field":"rel"}},"text":{"source":"oha-deals/product","args":{"slug":"example-ssd-1tb","field":"link_label"}}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://oha.deals" rel="nofollow">Compare</a></div>
<!-- /wp:button -->
```

`args`: `slug` (or an oha.deals URL) or `ean`, and `field`:

`name`, `brand`, `spec`, `category`, `price`, `before_price`, `discount`, `shop`, `offer_count`, `lowest_price`,
`lowest_date`, `image`, `image_alt`, `url`, `rel`, `link_label`, `disclosure`, `checked_at`.

- Values are text in the site's language and formats. When a value is not available, the block keeps its own content.
- When the best price is Amazon's, `price`, `before_price`, `discount`, `lowest_price` and `lowest_date` are not available:
  a single value cannot carry the timestamp Amazon requires. Use the product block there.
- Bind `rel` together with `url`, and show `disclosure` once per page that uses `url`.
- `image` and `image_alt` respect the "Product images" setting.

## Hooks

| Filter | Arguments |
|---|---|
| `oha_deals_api_base` | `string $base` (https only) |
| `oha_deals_request_args` | `array $args, string $url` |
| `oha_deals_cache_ttl` | `int $ttl, string $path, array $query` |
| `oha_deals_affiliate_providers` | `Provider[] $providers` |
| `oha_deals_offer_url` | `string $url, Offer $offer, Product $product` |
| `oha_deals_coupon_url` | `string $url, Coupon $coupon` |
| `oha_deals_disclosure_text` | `string $text, bool $own` |
| `oha_deals_template_path` | `string $path, string $view` |
| `oha_deals_template_args` | `array $args, string $view` |
| `oha_deals_structured_data` | `array $data, Product $product` |

| Action | Arguments |
|---|---|
| `oha_deals_loaded` | `Plugin $plugin` |
| `oha_deals_before_render` | `string $view, array $args` |
| `oha_deals_after_render` | `string $view, array $args` |

Functions: `oha_deals_query( $type, $args )`, `oha_deals_get_product( $slug_or_ean )`, `oha_deals_render( $view, $args )`.
