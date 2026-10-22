# Unstoppable Product Addons

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

Add optional extras to WooCommerce products at purchase — gift wrap, engraving, personalization, custom text fields. One admin page, no theme edits, no per-product configuration slog.

> **Status:** pre-release (v0.1.0). Not yet published to the WordPress Plugin Directory — the deploy workflow is set up and will fire the moment this plugin is approved into the directory and a tagged release is cut.

## Planned features

- **Global add-on sets** — define a set once (e.g. "Gift options"), attach to any number of products
- **Per-product overrides** — a specific product can tweak or replace the global set
- **Field types** — text input, textarea, checkbox, dropdown, radio, file upload, date, time
- **Pricing hooks** — each add-on can be free, flat-fee, percentage of product price, or per-character
- **Cart + checkout display** — add-on choices visible all the way through to the order
- **Order emails + admin** — add-on values printed in the order detail and admin order screen
- **No theme edits** — hooks on standard WooCommerce templates

## Why not just use the existing WooCommerce Product Add-Ons plugin?

- Not using the WooCommerce brand name in the title (per WordPress.org trademark rules)
- Open source, no "upgrade to Pro to unlock the useful stuff" pattern on the fundamentals
- Clean admin UI, no 10-year-old UX
- Managed as part of the Unstoppable Solutions suite — consistent conventions, release cadence, support

## Development

### Release workflow

Releases are deployed to WordPress.org SVN automatically by GitHub Actions, once the plugin is accepted into the directory:

1. Finish feature work on `main`
2. Bump the version in `unstoppable-product-addons.php` (header + `UPAD_VERSION` constant), `readme.txt` (Stable tag), and add a Changelog entry
3. Commit and push `main`
4. On GitHub, create a new **Release** tagged `vX.Y.Z` — the deploy workflow fires

### CI

Every push and pull request runs the official WordPress Plugin Check against the codebase.

## License

GPL-2.0-or-later. See the `License` header in `unstoppable-product-addons.php`.

## Author

Developed and maintained by [Unstoppable Solutions](https://unstoppablesolutions.app).
