# Unstoppable Product Addons

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress Plugin](https://img.shields.io/wordpress/plugin/v/unstoppable-product-addons)](https://wordpress.org/plugins/unstoppable-product-addons/)
[![WordPress](https://img.shields.io/wordpress/plugin/tested/unstoppable-product-addons)](https://wordpress.org/plugins/unstoppable-product-addons/)

Create custom product options for WooCommerce. Add text fields, dropdowns, checkboxes, file uploads, date pickers, and more to any product.

**[WordPress.org Plugin Page](https://wordpress.org/plugins/unstoppable-product-addons/)** | **[Documentation](https://unstoppablesolutions.app/plugins/unstoppable-product-addons/)**

## Features

- **10 Field Types** — Multiple choice (dropdown/radio/images), checkboxes, short text, long text, file upload, customer-defined price, quantity, heading, date picker, and color picker.
- **Global Add-ons** — Create add-on groups that apply to all products or specific categories.
- **Per-Product Add-ons** — Add custom options to individual products.
- **Flexible Pricing** — Flat fees, percentage-based, or quantity-multiplied pricing for each option.
- **File Upload Security** — Control allowed file types, sizes, and image dimensions with MIME validation.
- **Variable Product Support** — Apply add-ons to specific variations.
- **Inventory Tracking** — Track stock for add-on options.
- **Import/Export** — Easily migrate add-ons between products.
- **Migration Tool** — Import from other popular add-on plugins (WooCommerce Product Add-Ons, Orderable).

## Perfect For

- Personalized products (engraving, monograms)
- Gift options (gift wrapping, messages)
- Custom sizing or dimensions
- Appointment/delivery date selection
- File upload for print-on-demand
- Rush processing fees
- Donations and tips

## Requirements

- WordPress 6.5 or higher
- WooCommerce 7.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to `/wp-content/plugins/unstoppable-product-addons`, or install directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Options Studio → Product Addons** to configure global add-ons.
4. Or edit any product and look for the "Product Add-ons" panel.

## Development

### Repository Structure

```
unstoppable-product-addons/
├── assets/              # CSS and JavaScript files
│   ├── css/
│   └── js/
├── includes/            # PHP classes
│   ├── admin/          # Admin UI classes
│   │   └── views/      # Admin templates
│   └── api/            # REST API
├── languages/           # Translation files
├── templates/           # Frontend templates
├── .github/workflows/   # CI/CD workflows
├── readme.txt           # WordPress.org listing
├── ROADMAP.md           # Development roadmap
└── CHANGELOG.md         # Version history
```

### Release Workflow

Releases are deployed to WordPress.org SVN automatically by GitHub Actions:

1. Finish feature work on `main`
2. Bump the version in:
   - `unstoppable-product-addons.php` (Version header + `UPAD_VERSION` constant)
   - `readme.txt` (Stable tag + Changelog entry)
3. Commit and push to `main`
4. On GitHub, create a new **Release** tagged `vX.Y.Z`
5. The `deploy-to-wporg.yml` workflow fires and pushes to WordPress.org SVN

### CI

Every push and pull request runs the official WordPress Plugin Check against the codebase via `.github/workflows/plugin-check.yml`.

### Coding Standards

This plugin follows:
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WordPress Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)

## Roadmap

See [ROADMAP.md](ROADMAP.md) for planned features and development priorities.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Support

- [WordPress.org Support Forum](https://wordpress.org/support/plugin/unstoppable-product-addons/)
- [Unstoppable Solutions Support](https://unstoppablesolutions.app/support)

## License

GPL-2.0-or-later. See the `License` header in `unstoppable-product-addons.php` and `license.txt`.

## Author

Developed and maintained by [Unstoppable Solutions](https://unstoppablesolutions.app).
