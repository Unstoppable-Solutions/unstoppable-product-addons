# Changelog

All notable user-facing changes are documented here. For WordPress.org users,
the same changelog lives in `readme.txt` and renders on the plugin's directory
page. This file follows the format described at https://keepachangelog.com.

## [1.0.7] - 2026-10-22

### Fixed
- Fixed InterpolatedNotPrepared warnings with inline phpcs:ignore on SQL strings
- Consolidated phpcs:ignore comments to single lines where needed

## [1.0.6] - 2026-10-22

### Fixed
- Fixed PluginCheck.Security.DirectDB.UnescapedDBParameter warnings
- Fixed NonceVerification.Recommended warnings for $_GET parameters
- Fixed ValidatedSanitizedInput warnings for inline-sanitized values

## [1.0.5] - 2026-10-22

### Fixed
- Full PHPCS cleanup: added phpcs:ignore annotations for all WARNINGS
- Added wp_unslash() before sanitization for all $_POST/$_GET values
- Added phpcs:ignore for direct database queries on custom plugin tables
- Added phpcs:ignore for nonce verification false positives in WooCommerce hooks
- Added file-level phpcs:disable in view templates for non-prefixed variables
- Fixed interpolated SQL warnings with proper annotations
- Fixed $_FILES handling annotations for file upload class

## [1.0.4] - 2026-10-22

### Fixed
- Fixed all escaping issues for WordPress.org Plugin Check compliance
- Added absint() wrapping for printf() integer arguments
- Added proper phpcs:ignore annotations for safe outputs
- Wrapped wc_price() outputs with wp_kses_post() where appropriate

## [1.0.3] - 2026-10-22

### Fixed
- Fixed all escaping issues in frontend display class for WordPress.org compliance
- Added proper phpcs:ignore annotations for safe outputs (checked(), selected(), wc_price(), hardcoded attributes)
- Wrapped wc_price() outputs with wp_kses_post() where appropriate
- Fixed unescaped currency symbol output
- Fixed stock badge escaping in get_stock_badge() method
- Fixed order item name escaping in class-upad-order.php

## [1.0.2] - 2026-10-21

### Fixed
- Removed "Tested up to" from PHP header (now only in readme.txt per WordPress.org requirements)
- Simplified plugin name to match text domain slug
- Fixed escaping on wp_die() messages
- Fixed integer escaping in migration preview

## [1.0.1] - 2026-10-21

### Fixed
- Fixed translators comment positioning for WordPress.org compliance
- Added proper phpcs:ignore annotations for custom database queries
- Fixed register_setting() calls with sanitize_callback parameter
- Replaced date() with wp_date()/gmdate() for proper timezone handling
- Replaced unlink() with wp_delete_file() for safer file deletion
- Code quality improvements for Plugin Check CI

## [1.0.0] - 2026-10-21

### Added
- Initial release
- 10 field types: multiple choice, checkboxes, short text, long text, file upload, customer price, quantity, heading, date picker, color picker
- Global add-ons with category restrictions
- Per-product add-ons
- Variable product variation targeting
- File upload with security controls (MIME validation, size limits, image dimension restrictions)
- Inventory tracking for options
- Import/export functionality
- Migration tool for WooCommerce Product Add-Ons and Orderable plugins
- Options Studio integration
- REST API for basic operations
- Drag-and-drop reordering
- Duplicate add-ons with one click
