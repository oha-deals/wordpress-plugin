<?php
/**
 * Public helper functions for themes and other plugins.
 *
 * @package OhaDeals
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads a product from oha.deals (cached).
 *
 * @since 1.0.0
 *
 * @param string $slug_or_ean Product slug, EAN or oha.deals product URL.
 * @return \OhaDeals\Model\Product|\WP_Error
 */
function oha_deals_get_product( $slug_or_ean ) {
	return \OhaDeals\Plugin::instance()->repository()->product( (string) $slug_or_ean );
}

/**
 * Loads data from oha.deals (cached) as plain arrays, for custom tables, templates and shortcodes.
 *
 * Example: `$products = oha_deals_query( 'products', array( 'slugs' => array( 'product-one', 'product-two' ) ) );`
 *
 * Types and arguments:
 *
 * - `product`: `slug` or `ean`; `include` (array of `history`), `days` (30, 90, 180 or 365).
 * - `products`: `slugs` (array or comma list of slugs, EANs or oha.deals URLs; at most 20).
 * - `search`: `q`, `limit` (1–50), `category`.
 * - `deals`: `mode`, `sort`, `category`, `minPrice`, `maxPrice`, `limit` (1–100), `offset`.
 * - `coupons`: `host`, `limit` (1–100).
 * - `categories`: none.
 *
 * Products, offers and coupons carry `link` (the URL to print) and `link_is_own` (whether it is this site's affiliate
 * link, which needs `rel="sponsored nofollow"` and a disclosure). Shapes: docs/DEVELOPERS.md.
 *
 * @since 1.0.0
 *
 * @param string               $type Type of data.
 * @param array<string, mixed> $args Arguments.
 * @return array<int|string, mixed>|\WP_Error One object for `product`, else a list.
 */
function oha_deals_query( $type, $args = array() ) {
	return \OhaDeals\Plugin::instance()->query()->run( (string) $type, is_array( $args ) ? $args : array() );
}

/**
 * Renders an element as HTML, exactly like the matching shortcode.
 *
 * Example: `echo oha_deals_render( 'product', array( 'slug' => 'synology-ds925-plus', 'layout' => 'compact' ) );`
 *
 * @since 1.0.0
 *
 * @param string               $view Element: `product`, `offers`, `deals`, `comparison`, `price-history`, `coupons` or
 *                                   `price`.
 * @param array<string, mixed> $args Attributes, camelCase (`offersLimit`) or snake_case (`offers_limit`).
 * @return string Escaped HTML; empty when there is nothing to show.
 */
function oha_deals_render( $view, $args = array() ) {
	return \OhaDeals\Plugin::instance()->elements()->render( (string) $view, is_array( $args ) ? $args : array(), false );
}
