# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-22

First release.

### Added

- Blocks and shortcodes for product, comparison, offers, deals, price history and coupons, plus `[oha_deals_price]`.
- Comparison tables and numbered top lists with prices and technical data.
- Block bindings source `oha-deals/product` for live values in core blocks.
- `oha_deals_query()` and `to_array()` for developers, template overrides, filters and actions.
- Editor product search through a REST proxy, so the API key never reaches the browser.
- Copy buttons for coupon codes and an interactive price chart (Interactivity API); both work without JavaScript.
- Settings page with connection status, connection test and cache clearing.
- Server-side API client with caching, background refresh and a circuit breaker for outages.
- Own Amazon Associates tag and Awin publisher ID; all other links lead to the product page on oha.deals.
- Optional schema.org Product markup and optional credit link (both off by default).
- Site Health test, privacy policy text, WP-CLI commands, German translation.

[Unreleased]: https://github.com/oha-deals/wordpress-plugin/compare/1.0.0...HEAD
[1.0.0]: https://github.com/oha-deals/wordpress-plugin/releases/tag/1.0.0
