# oha.deals for WordPress — architecture

Status: 1.0.0 (2026-11-22). This file is the build spec; keep it current when the code changes. How to build on the plugin
(templates, data API, bindings, hooks): [DEVELOPERS.md](DEVELOPERS.md). Source: https://github.com/oha-deals/wordpress-plugin

## Goal

A WordPress plugin that shows live price comparisons, deals and price history from the
[oha.deals](https://oha.deals) Partner API on any WordPress site. Site owners connect with their own API key
and can use their own affiliate IDs (Amazon tag, Awin publisher ID). It is published on WordPress.org and on GitHub.

API contract: `https://oha.deals/api/partner/v1/openapi.json`.

## Identity

| | |
|---|---|
| Plugin name | oha.deals – Price Comparison & Deals |
| Slug / text domain | `oha-deals` |
| Main file | `oha-deals.php` (repo root = plugin root) |
| PHP namespace | `OhaDeals\` → `includes/` (PSR-4, own autoloader, no Composer packages at runtime) |
| Prefixes | functions/hooks/options `oha_deals_`, constants `OHA_DEALS_`, CSS `.oha-deals-`, REST `oha-deals/v1`, blocks `oha-deals/*` |
| Requires | WordPress 6.6+ (the built blocks depend on the `react-jsx-runtime` script, added in 6.6), PHP 7.4+ (tested up to PHP 8.4) |
| Licence | GPL-2.0-or-later |

## Rules that come from WordPress.org

- No runtime dependency loaded from a remote host; no minified code without source in the repo.
- The plugin calls an external service (oha.deals). `readme.txt` has an "External services" section saying what is
  sent (API key, product identifiers, search terms typed by editors), when, and links to terms and privacy policy.
- Every API call is server-side; no cookies, no tracking, no telemetry. Product images are the exception that
  `readme.txt` must state: the visitor's browser loads them from the image hosts the API names (oha.deals' image
  host, shop CDNs). The `show_images` setting turns them off.
- Guideline 12: affiliate links are disclosed and lead directly to the affiliate service. The plugin never prints a
  redirect (`Offer.url`, a coupon's `url`). Without the site owner's own IDs, links go to ordinary pages on oha.deals,
  where visitors can continue to the shops and oha.deals may earn a commission; with own IDs the link goes straight to
  Amazon or Awin and the commission is the site owner's. `readme.txt`, the settings page and the privacy text say so.
- No notices outside the Dashboard and the Plugins screen; the onboarding notice is dismissible per user.
- The "Prices by oha.deals" credit link is **off by default** (opt-in).
- Everything escaped late, sanitised early, nonces + capability checks on every write, no direct file access.
- `uninstall.php` removes options, transients, the user option and scheduled events on every site (only when the "delete
  data" setting is on, default on).

## Settings

One option `oha_deals_settings` (array, autoload off), page under Settings → oha.deals, built with the Settings API.

The API base URL is **not** a setting: it is `https://oha.deals/api/partner/v1`, the `OHA_DEALS_API_BASE` constant or
the `oha_deals_api_base` filter, and only https is accepted. (On multisite a site administrator could otherwise point
a network-wide key at a host of their own.) Client and cache read the settings at request time, so a theme's filter
also applies in wp-admin and WP-CLI. Changing the API key bumps the cache version.

| Key | Default | Notes |
|---|---|---|
| `api_key` | `''` | constant `OHA_DEALS_API_KEY` wins and locks the field; shown masked |
| `cache_ttl` | `3600` | seconds, 300–86400; responses with Amazon prices are capped at 3600 |
| `amazon_tag` | `''` | `^[a-z0-9-]{1,32}-2[0-9]$`-like validation, lenient |
| `awin_publisher_id` | `''` | digits |
| `show_images` | `true` | product images (loaded by the visitor's browser from the API's image hosts) |
| `link_rel_sponsored` | `true` | own affiliate links `rel="sponsored nofollow noopener"`, links to oha.deals `rel="nofollow noopener"` |
| `open_new_tab` | `true` | |
| `show_disclosure` | `true` | small text under each element |
| `disclosure_text` | `''` | empty = "Links to shops are affiliate links." when the element contains own affiliate links, else "Links lead to the price comparison on oha.deals."; a custom text replaces both |
| `show_attribution` | `false` | opt-in credit link |
| `structured_data` | `false` | schema.org `Product` + `AggregateOffer` JSON-LD for product blocks |
| `delete_data_on_uninstall` | `true` | |

The settings page (`Admin/SettingsPage.php`, `assets/admin/settings.{css,js}`, hand-written, loaded on this page only, no
inline script) starts with a status card: without a key it explains how to get one; with a key it shows plan,
capabilities in words, rate limit, last price check and the time of the last connection test. Loading the page never
calls the API: the card is rendered from the cached `/me` answer (`Repository::cached_me()` → `Cache::peek()`); without
one, the script calls `POST oha-deals/v1/connection` after load. "Test connection" uses the same route, "Clear cache"
`POST oha-deals/v1/cache/flush`; results go to a live region. The last price check is the newest `pricesCheckedAt` seen
in any response (transient `oha_deals_last_price_check`). Readable plan and capability names come from `Admin/KeyInfo.php`
(settings page, REST, WP-CLI). The cache lifetime is a select (5 min – 24 h); a stored custom value stays selectable.
The key field is a password field that never prints the stored key: empty keeps it, a "Remove the saved key" checkbox
clears it. The Site Health test reads `/me` through the cache.

`Admin/OnboardingNotice.php`: while no key is set, administrators see one notice on the Dashboard and the Plugins screen
only. Dismissal is a nonce'd `admin-post.php` link (works without JavaScript) stored as user option
`oha_deals_welcome_dismissed`, removed by `uninstall.php`.

## PHP layout

```
oha-deals.php                 header, constants, requirement checks, boots Plugin
uninstall.php
includes/
  Autoloader.php
  functions.php               public helpers oha_deals_get_product(), oha_deals_query(), oha_deals_render()
  Plugin.php                  wires everything on plugins_loaded / init; builds services lazily, reset on option change
  Settings/Options.php        typed getters, defaults, sanitize callback
  Admin/SettingsPage.php      settings page with status card and guide
  Admin/OnboardingNotice.php  "add your API key" notice (Dashboard and Plugins screen)
  Admin/KeyInfo.php           plan, capabilities and rate limit as text
  Admin/SiteHealth.php        Site Health test: key present, API reachable
  Api/Client.php              wp_remote_get, auth headers (Authorization + X-Oha-Key), no redirects; page views: 4 s,
                              no retry; cron/admin/REST/CLI: 8 s, one retry on 5xx/timeout, honours Retry-After
  Api/Cache.php               transients, stale-while-revalidate, circuit breaker, group flush by version salt
  Api/Repository.php          product( slug|EAN|URL ), products(), lookup_ean(), search(), deals(), coupons(), categories(),
                              me(), cached_me()
                              → models (snake_case per WPCS; an EAN resolves via /products/lookup, then /products/{slug})
  Api/Query.php               oha_deals_query(): API data as arrays, with `link` and `link_is_own`
  Model/Product.php  Model/Offer.php  Model/Deal.php  Model/Coupon.php    immutable, built from arrays, tolerant of missing
                              fields; to_array() with stable snake_case keys
  Model/Cast.php              tolerant readers for decoded JSON (https-only URLs, finite numbers; numeric ids become text)
  Affiliate/LinkResolver.php  picks the outgoing URL for an offer or coupon; kind(): own affiliate link or oha.deals
  Affiliate/Provider.php      interface: supports(Offer): bool, url(Offer): ?string
  Affiliate/AmazonProvider.php  Affiliate/AwinProvider.php
  Render/Renderer.php         render( string $view, array $args ): string
  Render/TemplateLoader.php   theme override: <theme>/oha-deals/<view>.php, filter oha_deals_template_path
  Render/Formatter.php        money (number_format_i18n, € placement per locale), percent, dates
  Render/HistoryChart.php     step chart: SVG lines that stretch, HTML labels, markers and stats in percent, round axis
                              values, data table in <details>; complete without JavaScript, plus the readout (see below)
  Render/StructuredData.php   Product + AggregateOffer from visible non-Amazon prices; main post of a singular page
                              only (in the loop), once per product
  Render/Elements.php         attribute schema + normalisation (camelCase or snake_case), loads data, picks the template,
                              editor-only notices; the single render path for blocks, shortcodes and oha_deals_render()
  Render/Helpers.php          passed to templates as $args['helpers']: price texts, links, link labels, images, footer notes
  Blocks/Registry.php         registers build/blocks/*/block.json with PHP render callbacks, the block category
                              `oha-deals` and the editor translations; from WordPress 6.7 on through
                              wp_register_block_metadata_collection( build/blocks, build/blocks-manifest.php )
  Blocks/Bindings.php         Block Bindings source `oha-deals/product`
  Blocks/Patterns.php         registers patterns/*.php (only with the blocks) in the pattern category `oha-deals`
  Shortcodes/Registry.php
  Rest/EditorController.php   editor search and settings page routes, so the key never reaches the browser
  Cli/Command.php             wp oha-deals status | cache flush | product <slug> | deals
  Privacy/Policy.php          wp_add_privacy_policy_content
templates/                    product-card.php (card + compact), product-inline.php, offers-table.php, deals-grid.php,
                              comparison-table.php, price-history.php, coupons-list.php, price.php ([oha_deals_price]), notice.php
assets/css/frontend.css       hand-written, no build; style handle oha-deals-frontend
assets/admin/                 settings.css, settings.js (hand-written, no build), mark.svg
patterns/                     comparison.php, deals-page.php, product-review.php, top-list.php: block markup with the
                              metadata in the file header, as theme patterns have it
src/blocks/<name>/            block.json, index.js, edit.js, view.js (coupons, price-history)  → build/blocks/<name>/
src/components/               BlockEdit (placeholder, preview, toolbar), ProductPicker, ProductList, FieldChooser,
                              CategorySelect, ReplaceProduct, SettingsLink, SortableRow, icons, example illustrations
src/utils/                    register (registerBlockType wrapper), use-block-preview (server preview hook), bindings +
                              binding-value (client side of the bindings source), transforms, attributes, chart, events
tests/phpstan-bootstrap.php   defines the plugin constants for PHPStan
languages/                    oha-deals.pot; bundled translations (PHP and editor JSON) are loaded from here
tests/phpunit/
demo/                         mu-plugin with invented API answers for screenshots and the live preview; not released
```

## Caching

- Key: `oha_deals_` + md5(cache version + path + sorted query).
  Value: `{ data, meta, fetched_at, soft_ttl, expires_at, retry_at? }`.
- Soft TTL = `cache_ttl`; hard TTL = soft × 6 (min 1 day), except Amazon-priced payloads (hard = soft ≤ 3600, never
  served stale) and `/products/search` (hard = soft ≤ 600, never served stale). A payload is Amazon-priced when any
  object has a non-empty `priceAt`, or a visible price with `shop.id` or `merchant.network` `amazon` (defensive).
- Read: fresh → return. Stale but within hard TTL → return stale and schedule one single-event cron refresh
  (`oha_deals_refresh`, deduplicated; not before `retry_at`). Missing → fetch inline (page views: 4 s, no retry;
  elsewhere 8 s, one retry); on failure cache the error for 60 s
  (or the server's `Retry-After`, if longer) so a down API never slows every page view. A `not_found` is cached for
  the soft TTL, at most 1 h. A missing API key is never cached.
- Circuit breaker: a transport error or 5xx answer sets the transient `oha_deals_circuit_open` for 45 s; while it is
  set, no inline fetch is made (missing entries fail at once, stale entries are still served, editors see the notice).
  "Test connection" (`fresh`) bypasses it.
- Background refresh: at most 20 per cron request (the rest are rescheduled a minute later). A passing failure keeps
  the stale entry and sets `retry_at` = now + 5 min so stale reads do not reschedule at once; a definitive one
  (`not_found`, `unauthorized`, `forbidden`, `invalid_request`) replaces it.
- Client retry (not on page views): one retry on transport errors and 5xx; a 429 `Retry-After` of at most 2 s is
  waited for, anything longer is returned at once (and backs the negative cache).
- "Clear cache" bumps the cache version. Works with a persistent object cache (transients API).
- Front-end output never shows an error to visitors: on failure the element renders nothing (editors and
  `current_user_can( 'edit_posts' )` see a short notice).

## Outgoing links

`LinkResolver::url( Offer, Product )`:

1. Own IDs: first provider that `supports()` the offer and returns a URL wins.
   Amazon: `amazon_tag` set and `merchant.asin` → `https://www.amazon.de/dp/<asin>?tag=<tag>`.
   Awin: `awin_publisher_id` set and `merchant.awinMerchantId`, then
   `merchant.awinProductId` (98 % of Awin offers) → `https://www.awin1.com/pclick.php?p=<awinProductId>&a=<publisher id>&m=<awinMerchantId>`,
   else `merchant.url` → `https://www.awin1.com/cread.php?awinmid=…&awinaffid=…&ued=<rawurlencode url>`, else no own link.
2. Otherwise the product page on oha.deals: `Product.url` + `#angebote`. Never `Offer.url` (a redirect).
3. Filter `oha_deals_offer_url` ( string $url, Offer $offer, Product $product ) runs last; `Offer::url()` is available to
   it. Providers are filterable through `oha_deals_affiliate_providers`.

Coupons (`LinkResolver::coupon_url( Coupon )`): the API's `pageUrl` (the shop's coupon page on oha.deals), else
`https://oha.deals/gutscheine`, then the filter `oha_deals_coupon_url`. The API's `url` (a redirect) is not read.

`LinkResolver::kind( $url )` decides from the host: `oha.deals` and its subdomains are oha.deals links, everything else is
an own affiliate link. From that follow

- `rel`: `sponsored nofollow noopener` for own links, `nofollow noopener` for oha.deals links (`Helpers::print_link_attributes()`);
- the labels: "To the shop" / "View price at <shop>" / "View best price" versus "Compare on oha.deals"
  (`Helpers::offer_link_label()`, `best_link_label()`), "Redeem at <shop>" versus "View on oha.deals" (`coupon_link_label()`);
- the disclosure: `Elements` announces the URLs of an element (`Helpers::start_element()`) before rendering it, so the
  text is right wherever the template prints it; links a template adds through `print_link_attributes()` count as well.

Only `https` URLs are ever printed (`esc_url` with an explicit protocol list).

## Blocks and shortcodes

All blocks are dynamic (server-rendered, `apiVersion` 3). Supports: `anchor`, `align` wide/full, colours (text, background,
link, gradients), font size and line height, margin and padding, border, shadow, client navigation; `html: false`.
WordPress 7.0+ prints the anchor itself; on 6.6–6.9 `Elements` passes it as `id` (the attribute lives in the block comment,
see `src/utils/register.js`). Each shortcode calls the same render function as its block.

Editor: `BlockEdit` shows a placeholder until the block has what it needs, then the server-rendered preview.
`use-block-preview` calls the core route `wp/v2/block-renderer/<block>` with the block's own attributes only, and puts the
server's wrapper classes on the editor's block wrapper, so support styles do not apply twice. A server notice
(`.oha-deals-notice`) is shown as placeholder text; `.oha-deals-notice--key` (no key, or the key was rejected) adds the
link to the settings. Examples in the inserter are static illustrations (`slug: "__example__"`), without API calls.

Shortcode tags use the full `oha_deals_` prefix (WordPress.org asks for prefixes of four characters or more); there
are no aliases for shorter names.

| Block | Shortcode | Attributes (block camelCase / shortcode snake_case) |
|---|---|---|
| `oha-deals/product` | `[oha_deals_product]` | `slug` or `ean`; `layout` card\|compact\|inline (card); `showImage` (true); `offersLimit` 0–10 (3); `showHistory` (false); `buttonText` |
| `oha-deals/offers` | `[oha_deals_offers]` | `slug` or `ean`; `limit` 1–30 (10, applies to new goods); `showShipping` (true); `includeUsed` (false; appends up to 5 used/refurbished offers); `showTitle` (true) |
| `oha-deals/deals` | `[oha_deals_list]` | `mode` all\|drop\|low; `category`; `sort` percent\|amount\|price\|new; `limit` 1–24 (6); `columns` 1–4 (3); `minPrice`; `maxPrice` |
| `oha-deals/comparison` | `[oha_deals_comparison]` | `slugs` (array / comma list, at most 6; slug, EAN or oha.deals URL); `fields` (ordered list of image, brand, price, before, discount, shop, offers, lowest, change, specs; default image, price, shop, offers, lowest, specs); `orientation` columns\|rows (columns); `highlightBest` (true); `title`; `buttonText`; `numbered` (false; rows only) |
| `oha-deals/price-history` | `[oha_deals_history]` | `slug` or `ean`; `days` 30\|90\|180\|365 (90); `height` (220) |
| `oha-deals/coupons` | `[oha_deals_coupons]` | `host` (a domain; an invalid value is an editor notice, never "all shops"); `limit` 1–20 (5) |
| – | `[oha_deals_price]` | `slug` or `ean`; prints just the best price as text, for sentences |

Display rules shared with oha.deals itself:

- A struck "before" price / percent is shown only when the API sends `before`. Never computed locally.
- `shipping: null` prints "shipping unknown", never "free".
- `priceHidden` offers print the shop with a "View price at <shop>" link and no price.
- Amazon offers (a `priceAt`, or `shop.id` / `merchant.network` `amazon`) print the timestamp next to the price
  (`priceAt`, falling back to the product's `pricesCheckedAt`), and the element carries the Amazon disclaimer line.
- The deals request always sends `priced=1` (the server drops rows without a price for this key before paging), and
  rows that still arrive without a price are dropped at render time: no price-less deal cards.
- Deals requests send prices as `number_format( round( $v, 2 ), 2, '.', '' )`, clamped to 0–9,999,999; an inverted
  min/max pair is dropped; a category that is not a lowercase hyphenated slug is omitted.
- Coupons past `validUntil` are not shown.
- Price history: the API sends change points only, plus one point from before the window. The chart uses a time axis,
  clamps that first point to the window start, extends the last price to today and draws steps (a price holds until
  it changes).
- Price history needs the `history` capability; without it the block renders nothing for visitors and a hint for editors.
- A `priceAt` that is a bare date (`2026-11-20`) prints the date only; dates are pinned to noon UTC so they do not
  shift with the site time zone.
- The disclosure is printed only on elements with outgoing links (not on price history or `[oha_deals_price]`). When
  an element links to Amazon with the site's own tag, the Associates sentence is appended to it.
- Comparison: unknown and repeated products are dropped, the order is kept. The best price is marked only when at least two
  products have a visible price and there is no tie. Specification rows are labels that at least two products share (at
  most 12).
- Shortcode attributes match case- and underscore-insensitively (`show_image`, `showimage`, `showImage`); invalid values
  fall back to the defaults in the table, numbers are clamped to the ranges. `height` is clamped to 120–600.
- The content filter runs shortcodes after blocks. `Elements` encodes the bracket of anything in block output (and in bound
  values) that looks like a registered shortcode, so text from the API never runs as one.
- Wrappers: `oha-deals oha-deals-<element>` (+ `oha-deals-product--<layout>`, `oha-deals-deals--columns-<n>`, `oha-deals-comparison--<orientation>`), through
  `get_block_wrapper_attributes()` for blocks. The inline layout and `[oha_deals_price]` render a `<span>` in shortcodes.

### Front-end scripts and styles

- Two script modules on the Interactivity API, both optional: `oha-deals/price-history` (readout on the chart: pointer, touch,
  arrow keys) and `oha-deals/coupon` (copy button). Without them the chart and the code are complete. They are the
  `viewScriptModule` of their blocks; `HistoryChart::render()` and `Elements::coupons()` also enqueue them by their core
  ids, so shortcodes, product cards and theme overrides get them. The printed state equals the initial context
  (`wp_interactivity_data_wp_context()`), so no server-side directive processing is needed.
- `assets/css/frontend.css`: theme fonts and colours, container queries, logical properties. Public tokens (set anywhere above
  the elements): `--oha-deals-accent`, `-on-accent`, `-positive`, `-radius`, `-gap`, `-border`, `-muted`, `-surface`,
  `-image-bg`, `-background`. `--ohad-*` are internal.

## Editor REST proxy (`oha-deals/v1`)

| Route | Capability | Purpose |
|---|---|---|
| `GET /search?q=` | `edit_posts` | product picker results (name, brand, image, price, slug, ean) |
| `GET /product?slug=` or `?ean=` | `edit_posts` | resolve a pasted slug / EAN / oha.deals URL |
| `GET /products?slugs=a,b` | `edit_posts` | several picker items in the given order (at most 6; unknown ones left out) |
| `GET /categories` | `edit_posts` | category select for the deals block |
| `POST /connection` | `manage_options` | test the key (`/me`); `summary` holds the texts of the status card |
| `POST /cache/flush` | `manage_options` | bump cache version |

There is no public data route: it would let anyone spend the site owner's API quota.

### Editor REST response shapes (what `src/components` expects)

- `GET /search?q=` → JSON array (a `{ "data": [...] }` envelope is also accepted) of
  `{ slug, ean, name, brand, image, price, priceFormatted, shop, url }`; `ean`, `brand`, `image`, `price`,
  `priceFormatted` and `shop` may be `null`.
- `GET /product?slug=` or `?ean=` → one object of the same shape (or the envelope) plus `bindings` (every field of the Block
  Bindings source: text or `null`). A non-2xx answer means "not found".
- `GET /products?slugs=` → array of picker items.
- `GET /categories` → array of `{ slug, name, count }` (envelope accepted).
- The picker sends a pasted `https://oha.deals/produkt/<slug>` URL as `slug`, a string of 8–14 digits as `ean`, and a
  full slug (at least three hyphen-separated parts, e.g. `synology-ds925-plus`) as `slug` first; if that finds nothing
  it falls back to `/search`. Shorter hyphenated words (`usb-c`) go straight to the search.

## Block build

- `npm run build` empties `build/`, runs `wp-scripts build --experimental-modules --source-path=src/blocks
  --output-path=build/blocks` and writes `build/blocks-manifest.php`. Every `src/blocks/<name>/block.json` becomes
  `build/blocks/<name>/{block.json,index.js,index.asset.php,index.css,index-rtl.css}` (+ `view.js`, `view.asset.php`).
  PHP registers `build/blocks/<name>/` (the directory or its `block.json`) and supplies the `render_callback`.
- `block.json` uses `"editorScript": "file:./index.js"`, `"editorStyle": "file:./index.css"` and
  `"style": "oha-deals-frontend"`. PHP must register the style handle `oha-deals-frontend`
  (`assets/css/frontend.css`) before blocks are registered. Category is `oha-deals` (registered in PHP, listed last).
- `build/blocks-manifest.php` (block directory name => metadata) is optional; without it, or before WordPress 6.7, core
  reads each `block.json`.
- Editor scripts get `wp_set_script_translations( …, 'oha-deals', languages/ )`. Core looks in `languages/` first and falls
  back to WordPress.org language packs. The JSON file names carry the md5 of the built path (`build/blocks/…/index.js`), so
  `oha-deals.pot` is made from `build/` (after `npm run build`), not from `src/`.
- Editor dependencies come from WordPress (`wp-api-fetch`, `wp-block-editor`, `wp-core-data`, `wp-url`, …) through the
  generated `index.asset.php`; nothing is bundled from a remote host.
- `build/` is not committed; CI and the release workflow build it. Number attributes without a default
  (`minPrice`, `maxPrice` of the deals block) are omitted from the attributes sent to PHP when empty.

## Extension points

Filters: `oha_deals_api_base`, `oha_deals_request_args`, `oha_deals_cache_ttl`, `oha_deals_offer_url`,
`oha_deals_coupon_url`, `oha_deals_affiliate_providers`, `oha_deals_template_path`, `oha_deals_template_args`, `oha_deals_disclosure_text`,
`oha_deals_structured_data`. Actions: `oha_deals_loaded`, `oha_deals_before_render`, `oha_deals_after_render`.
Public helper functions: `oha_deals_get_product( $slug_or_ean )` (Product or WP_Error),
`oha_deals_query( $type, $args )` (arrays or WP_Error; every model has a `to_array()` with stable snake_case keys) and
`oha_deals_render( $view, $args )`, where `$view` is an element (`product`, `offers`, `deals`, `comparison`,
`price-history`, `coupons`, `price`) and `$args` its attributes; it renders exactly like the shortcode.
Templates receive one `$args` array (documented at the top of each template) including `$args['helpers']`
(`OhaDeals\Render\Helpers`); its `print_*` methods echo escaped markup, all others return plain text.
Block Bindings source `oha-deals/product` (`Blocks/Bindings.php`, client side `src/utils/bindings.js`): `slug` or `ean` plus
`field`; values are plain text, null when unavailable (the block keeps its content). The editor shows the values since
WordPress 6.7 and offers the fields of the products in the post since 6.9. When the best price is Amazon's, the price and everything derived from it
is null, because a bound value cannot carry the mandatory timestamp.
Templates can be overridden from the theme (`<theme>/oha-deals/…`). Pattern titles and descriptions are translated in
`Blocks/Patterns.php` (make-pot reads pattern headers of themes only).

## Quality gates

- PHPCS: `WordPress-Extra`, `WordPress-Docs`, `PHPCompatibilityWP` (7.4+). PHPStan level 8 with `szepeviktor/phpstan-wordpress`.
- PHPUnit 9.6 (WP test suite through `wp-env`, plugin mapped to `wp-content/plugins/oha-deals`): Options sanitising,
  Client (mocked with `pre_http_request`), Cache stale/refresh/breaker logic, LinkResolver, Formatter, HistoryChart,
  every template with fixture payloads (escaping with hostile strings, null handling), admin screens, bindings, query API,
  patterns, REST, uninstall, and the real `build/blocks` metadata: every attribute matches the schema and every script
  handle in `index.asset.php` is registered on the WordPress version under test. Also run with `WP_MULTISITE=1`.
- PHPCS: test methods need no docblock (`Squiz.Commenting.FunctionComment.Missing` excluded for `*Test.php`).
- JS: `@wordpress/scripts` lint + build; `npm run test:js` runs the unit tests (`wp-scripts test-unit-js`, Vitest).
  WordPress Plugin Check in CI.
- GitHub Actions: lint + tests on PHP 7.4 / 8.1 / 8.4 × WP 6.6 / latest; the release workflow lints, tests, builds the
  zip outside the workspace and deploys to WordPress.org SVN on tag (10up action pinned to a release tag).
