# Contributing

Thanks for taking the time to help. Bug reports, documentation fixes and pull requests are welcome.

## Before you start

- For larger changes, open an issue first so we can agree on the approach.
- Read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md). It is the design spec; update it when behaviour changes.
- By contributing you agree that your work is released under GPL-2.0-or-later.

## Setup

```bash
npm install
composer install
npm run env:start
```

## Standards

- PHP: WordPress-Extra and WordPress-Docs through PHPCS (`composer lint`, `composer lint:fix`), PHP 7.4 compatible,
  PHPStan level 6 (`composer analyse`).
- JavaScript and CSS: `npm run lint:js`, `npm run lint:css`, `npm run format`.
- Prefix everything global with `oha_deals_`, `OhaDeals` or `OHA_DEALS_`.
- Sanitise input early, escape output late. Write actions need a nonce and a capability check.
- Every user-facing string goes through the i18n functions with the `oha-deals` text domain.
- Keep the plugin free of tracking, cookies and remote-loaded code, as WordPress.org requires.
- Add tests for PHP changes (`tests/phpunit`). Mock HTTP with the `pre_http_request` filter.

## Pull requests

1. Branch from `main`.
2. Keep each pull request focused on one change.
3. Run the linters and tests listed above.
4. Add an entry under "Unreleased" in `CHANGELOG.md`.
5. Fill in the pull request template.

## Reporting security problems

Do not use public issues. See [SECURITY.md](SECURITY.md).

## Conduct

Participation is covered by the [Code of Conduct](CODE_OF_CONDUCT.md).
