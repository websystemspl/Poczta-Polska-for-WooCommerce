# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed
- The warning about an unpatched PHP version is shown only on the dashboard, the plugins list and the WooCommerce and plugin screens, not on every admin page. The error about a missing SOAP extension stays on every admin page.
- readme.txt: the external services section states when the map script is loaded; the 0.2.0 changelog lists all features of the release.

### Fixed
- The daily pickup point refresh is not scheduled twice when a refresh is already queued.

### Added
- Documentation in Polish (`docs/`): installation and configuration, shop manager guide, architecture and API, WordPress.org and updates, logs and errors, build and release.
- Unit tests for renumbering after a buffer date change, sub-parcel numbers, buffers deleted outside the plugin, sent envelope statuses, pickup point lookups and their limit, the audit log, privacy export and erasure, bulk actions and the tracking column (in-memory `$wpdb` and options for tests).

## [0.2.0] - 2026-11-04

### Added
- "Poczta Polska" admin menu with the settings screen (capability `manage_woocommerce`) and a "Settings" link on the Plugins screen.
- Elektroniczny Nadawca connection settings: environment switch (test INT / production) and WebAPI login and password per environment. Test is the default.
- Passwords encrypted with libsodium (key from `PPWC_ENCRYPTION_KEY` or the WordPress salts), never sent back to the browser.
- Shipping methods Pocztex Kurier, PUNKT, AUTOMAT and the eCommerce letter, with the Poczta Polska pickup point map for block and classic checkout and cash on delivery rules.
- Shipments in Elektroniczny Nadawca: creation from orders (also in bulk), labels, buffers, sending to the post office and courier orders.
- "Poczta Polska" column with tracking numbers in the orders list (HPOS and legacy).
- Privacy policy text, personal data export and erasure.
- `readme.txt` with the "External services" section, `.distignore` and `composer build` for the release ZIP.
- Shipment box: recipient landline next to the mobile, pickup point chosen on the Poczta Polska map (centred on the delivery address, also after switching from courier to PUNKT or AUTOMAT), checked on the server; the new point is saved in the order with a customer note.
- Posting office chosen for each shipment (default from the settings): the shipment goes to the buffer of that office, which is sent there; at the post office channel the shipment names the same office.
- "Sent envelopes" on the Buffers screen with the envelope status from EN (getEnvelopeStatus, refreshed every 15 minutes and on demand); the envelope and its status in the shipment box.
- Buffers deleted or sent outside the plugin are detected (getEnvelopeBuforList, confirmed with error 96001 or 20603): the plugin forgets them, marks their shipments and creates new shipments in a new buffer.
- End-to-end tests (Playwright) of the block and classic checkout and of shipments in EN, with instructions in Polish (`tests/e2e/README.md`, `npm run test:e2e`).

### Changed
- Shipments can be moved only between buffers with the same contract card and posting office.
- The shipment box lays out its fields in a grid with labels above the fields.
- The shipment box shows the values of the shipment that can still be changed, so saving keeps earlier changes.
- The "Connection" tab shows one card per environment with its credentials and connection test.

### Security
- Visitors (public REST route, checkout) can trigger at most 30 EN lookups of unknown pickup points per 10 minutes; PNI longer than 8 digits is rejected. Shop managers are not limited.
- Messages on the Buffers screen are kept per user instead of in the URL, so a link cannot display its own text there.

### Fixed
- Pickup point count in the settings shown with the shop thousands separator (the Polish WordPress translation has none).
- Bulk label printing in the legacy orders list.
- Uninstall removes the plugin shipping method instances from shipping zones.
- Changing the buffer date: shipments are found by the stored shop number also after a second renumbering, the label lock is cleared correctly, the buffer date is read from EN, sub-parcels get their new GUIDs.
- Sub-parcel tracking numbers after a date change are read from the sent envelope (`getEnvelopeContentShort`).
- The audit log no longer reports a new empty setting as a change.

## [0.1.0] - 2026-10-30

### Added
- Plugin skeleton with a PSR-4 autoloader and no runtime Composer dependency.
- PHP 8.2 minimum version guard.
- Admin notices for a missing SOAP extension and for PHP versions without the PHP2Shell fixes (CVE-2026-109618, CVE-2026-109619).
- Compatibility declarations for WooCommerce HPOS and Cart/Checkout Blocks.
- WooCommerce logger wrapper (source `poczta-polska`) that masks sensitive data.
- Activation, deactivation and uninstall routines.

[Unreleased]: https://github.com/websystemspl/Poczta-Polska-for-WooCommerce/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/websystemspl/Poczta-Polska-for-WooCommerce/releases/tag/v0.2.0
