=== oha.deals – Price Comparison & Deals ===
Contributors: ohadeals
Tags: price comparison, comparison table, deals, coupons, price history
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Live prices, shop offers, comparison tables, deals, coupons and price history from oha.deals, as blocks and shortcodes.

== Description ==

This plugin connects your site to [oha.deals](https://oha.deals), a price comparison for shops in Germany. Place a product with its current best price, a table of shop offers, a comparison of several products, a grid of current deals, a price chart or a list of coupons in any post or page. Your server fetches the data, caches it and renders plain HTML.

You need an API key from oha.deals, see the FAQ. Without a key the blocks show nothing to visitors.

**Blocks**

* Product: one product as card, compact row or inline, with its best price and shop offers.
* Comparison: up to six products side by side with prices and technical data, or as a numbered top list.
* Offers: the shop offers for one product, cheapest first.
* Deals: current price drops and lowest prices, filterable by category and price.
* Price history: a chart of a product's price over 30 to 365 days.
* Coupons: current voucher codes with a copy button.

The editor has a product search, live previews, block variations and transforms. Blocks take over the colours, fonts and spacing of your theme.

**Build your own**

* Block bindings: show a live price, name, image or link inside core paragraph, heading, button and image blocks.
* Shortcodes: `[oha_deals_product]`, `[oha_deals_comparison]`, `[oha_deals_offers]`, `[oha_deals_list]`, `[oha_deals_history]`, `[oha_deals_coupons]` and `[oha_deals_price]` (only the price, for use in a sentence).
* For developers: `oha_deals_query()` returns the data as PHP arrays; templates can be overridden in your theme; filters, actions and WP-CLI commands are available.

**Good to know**

* A struck-through price appears only when the service confirms a real reduction. Unknown shipping is shown as unknown, never as free.
* Cached results are served while a refresh runs in the background, so a slow or unavailable service does not hold up your pages.
* No cookies, no tracking, no telemetry. The API key never reaches the visitor's browser.
* Optional and off by default: schema.org Product data and a "Prices by oha.deals" credit link.

**Links and commissions**

Without your own affiliate IDs, links lead to the product page on oha.deals. From there visitors can continue to the shops, and oha.deals may earn a commission. With your own Amazon Associates tag or Awin publisher ID, links go straight to Amazon or Awin with your ID and the commission is yours. A short disclosure under each element says where its links lead.

== External services ==

The plugin relies on the oha.deals Partner API (`https://oha.deals/api/partner/v1`), run by the authors of this plugin. It provides all product, price, deal, coupon and price history data. The plugin does not work without it.

What your server sends, and when:

* Every request: your API key, your server's IP address and a user agent that names the plugin and its version.
* When a page with an oha.deals element is rendered and the data is missing from the cache or expired: product identifiers (slug, EAN) or filter values (mode, sort, category, price range, number of results, shop domain).
* While an editor searches for a product: the search term.
* "Test connection", the settings page and Site Health: a request for the details of your key.

No visitor data is sent. Product images are the one exception to server-side loading: the visitor's browser loads them from the image hosts named in the data (oha.deals and the shops), which receive the visitor's IP address like with any embedded image. The "Product images" setting turns them off.

[Terms of use](https://oha.deals/nutzungsbedingungen) · [Privacy policy](https://oha.deals/datenschutz)

== Source code ==

The source code, including the uncompiled JavaScript and CSS of the blocks, is at [github.com/oha-deals/wordpress-plugin](https://github.com/oha-deals/wordpress-plugin). The files in `build/` are made from `src/` with `npm install && npm run build`. Bug reports and pull requests are welcome there.

== Installation ==

1. Install and activate the plugin.
2. Go to Settings → oha.deals, enter your API key and click "Test connection".
3. Add a block from the "oha.deals" category, or use a shortcode.

You can also define `OHA_DEALS_API_KEY` in `wp-config.php`.

== Frequently Asked Questions ==

= Where do I get an API key? =

Ask for one through the [contact form](https://oha.deals/kontakt) and name the website you want to use it on.

= Can I use my own affiliate links? =

Yes, for Amazon.de and for shops on Awin. Enter your IDs under Settings → oha.deals. Use only IDs of accounts in which your website is registered; on Awin you also need to be approved by each shop.

= Can I build comparison tables and top lists? =

Yes, with the Comparison block or `[oha_deals_comparison slugs="one, two, three"]`. For your own layouts use block bindings, a template override or `oha_deals_query()`.

= Why does an offer show no price, or a block nothing at all? =

Some prices and the price history are not part of every API key. Offers without a price still link on; editors see a hint when a block is empty.

= How long are prices cached? =

One hour by default, adjustable from 5 minutes to 24 hours. Amazon prices are kept for one hour at most and show the time they were retrieved.

= Can I change the look? =

Yes: with the block's colour, typography and spacing settings, with CSS custom properties such as `--oha-deals-accent`, or by copying a template to `your-theme/oha-deals/`.

= How do I remove all plugin data? =

Delete the plugin. Settings and cached data are removed unless you turned that off.

== Screenshots ==

1. Product card with best price, shop offers and price chart.
2. Comparison table with prices and technical data.
3. Deals grid with price drops and lowest prices.
4. Product search and live preview in the block editor.
5. Coupons with copy buttons.
6. Settings with connection status and your affiliate IDs.

== Changelog ==

= 1.0.0 =
* First release.
