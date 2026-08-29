# Changelog

All notable changes to **رستوران مستقیم** (Flavor theme + Flavor Core plugin) are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-08-30

### Added

- Smart AJAX menu search: live results, debounce, request cancellation, in-memory cache
- Persian text normalisation (Arabic yeh/kaf folding, digits, diacritics, ZWNJ) and light stemming
- Typo-tolerant fuzzy matching (UTF-8 Levenshtein) with weighted relevance ranking
- REST routes `GET flavor/v1/search`, `/search/suggest`, `/search/popular` plus an `admin-ajax` fallback for fully cached pages
- Cached per-branch search index with automatic invalidation on product / category / availability changes
- Did-you-mean suggestions, popular terms, recent searches, facet counts
- Keyboard navigation (arrows, Enter, Esc, Ctrl/Cmd+K) and an accessible combobox/listbox pattern
- Unit tests for Persian normalisation and search ranking; Persian docs at `docs/fa/06-jostojoo-hooshmand.md`

## [1.0.0] - 2026-08-25

### Added

- Conditional assets, font preload, hero LCP hint
- Restaurant / Menu / Breadcrumb JSON-LD and Open Graph
- REST rate limits, privacy export/erase, cache exclusion hints
- Persian manuals, video scripts, Raastichin listing copy
- Launch version lock for theme + plugin

## [0.4.0] - 2026-08-25

### Added

- Eight demo skins + Customizer branding
- One-click demo importer (pages, 22-item menus, branch, tables, hero)
- Front-page marketing hero/about
- Elementor widgets and Gutenberg dynamic blocks
- Bundled hero photography per demo

## [0.3.0] - 2026-08-25

### Added

- Jalali calendar helper and reservation booking (slots, capacity pool, walk-in, SMS confirm/reminder cron)
- Front-end reservation template with Shamsi month grid
- Menu time windows (breakfast/lunch/dinner/late night) with per-branch override
- Availability admin toggles (ناموجود لحظه‌ای)
- WooCommerce coupon extras: Jalali expiry, branch lock, first-order-only
- Cart coupon REST + drawer field
- Phone-order desk (customer lookup, recent orders, modifiers, send to kitchen)
- Loyalty points + stamp card, admin adjust, `/me` summary

## [0.2.0] - 2026-08-25

### Added

- QR PNG/SVG/PDF download, logo overlay, A6 print cards
- Three order modes in the cart drawer with official WooCommerce checkout
- Offline gateways: pay at counter, cash on delivery, card on delivery
- Kitchen kanban (items, audio, filters, fullscreen, 80mm receipts)
- Delivery zones (neighborhood + radius) with checkout enforcement
- Mobile OTP login and SMS provider adapters (Dev / Melipayamak / Faraz / Kavenegar)
- Cart and checkout REST (`/flavor/v1/cart`, `/checkout`, `/auth/otp/*`, `/zones/check`)
- Modifier bottom sheet with live extras

### Changed

- Plugin and theme version 0.2.0
- Rewrite flush on version bump (`/kitchen-receipt/`)

## [0.1.0] - 2026-08-25

### Added

- Monorepo bootstrap for the commercial product sold as two packages.
- `flavor-core` plugin architecture: singleton bootstrap, PSR-4 autoloader, activator, deactivator, uninstall.
- Complete custom table schema (kitchen tickets as source of truth, tables, reservations, zones, availability, loyalty, OTP, SMS log, schedules).
- Custom roles: Super-admin capabilities, Branch Manager, Kitchen Staff, Cashier.
- Branch custom post type with structured meta and REST fields.
- Dining-table custom table manager and QR token model (generation UI comes in Phase 2).
- WooCommerce bridge: Toman/Rial currency layer (admin-configurable), simple-product modifier data model.
- Kitchen ticket repository (indexed operational store, synced from WooCommerce orders).
- REST API namespace `flavor/v1` with public / cookie / capability route groups.
- Theme `flavor` skeleton: RTL-first, bundled Vazirmatn, page templates, plugin-missing notice.
- Developer documentation: database schema, architecture decisions, hooks map.

### Notes

- Phase 1 foundation only. Ordering UI, kitchen dashboard views, OTP SMS, reservations UX, and the 8 demos land in later phases.
