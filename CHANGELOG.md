# Changelog

All notable changes to **رستوران مستقیم** (Flavor theme + Flavor Core plugin) are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Security

- **CI build callback: HMAC authentication is now mandatory** — missing signature, missing server secret, malformed signature and invalid signature are all rejected with HTTP 403 (previously a callback without any signature was accepted and could rewrite build status, artifact URLs and logs)
- **Signature verified against the exact raw HTTP body** — the payload is never decoded/re-encoded before HMAC verification, matching the GitHub Actions signing protocol byte-for-byte
- **Replay protection** — signed payloads must carry a `timestamp` inside a 300-second freshness window, and a strict build state machine (`queued → building → success/failed/cancelled`, terminal states locked) rejects replays and illegal transitions with 409
- **Artifact URLs / checksums are only writable on a verified `success` transition**
- **Removed the insecure `dev_secret` fallback from `build-mobile.yml`** — the workflow now fails early and clearly when `FLAVOR_CI_WEBHOOK_SECRET` is not configured
- **`ci_webhook_secret` is generated once and persisted** (`MobileConfigManager::get_webhook_secret()`) instead of being regenerated on every `get_all()` call — which previously made valid HMACs unverifiable
- **Secrets never leave the API in plain text** — `github_token`, `ci_webhook_secret` and `fcm_service_key` are masked in config responses with `*_configured` metadata flags, and submitting the mask placeholder can never overwrite a stored secret
- **Workflow/Server protocol alignment** — the callback now understands the workflow's actual payload contract (`timestamp`, `logs`, structured `artifacts[]` with apk/aab/ipa mapping), and a deterministic test replicates the workflow's byte-exact payload format
- CI callback security events (bad signatures, replays, illegal transitions, DB failures) are logged via `StructuredLogger` (channel `mobile_build`)

### Fixed

- **Auth: fatal error on logout-all-devices** — `AuthController::token_revoke()` called the non-existent `TokenService::revoke_all_for_user()`; it now calls the actual `TokenService::revoke_all_user_tokens()`
- **Auth: OTP login wrote device name into the device_id column** — `TokenService::issue( $user_id, '', $device_name )` argument order corrected in `otp_verify()`
- **Auth: refresh tokens never expired** — the refresh flow only checked `revoked_at`; refresh TTL is now enforced via a new `refresh_expires_at` column (schema 1.5.0), with a `created_at + REFRESH_TTL` fallback for legacy rows and a best-effort revoke of dead rows
- **DB: `flavor_device_tokens` production schema missing `app_version` and `updated_at`** while registration wrote them (and skipped the NOT NULL `last_seen_at`) — columns added via dbDelta migration (DB version 1.4.0 → 1.5.0)
- **DB: token purge could delete rows whose refresh token was still valid** — purge is now refresh-aware and only removes revoked rows older than 30 days or rows whose refresh credential is definitively expired
- **Observability: silent database-write failures** — `issue()`, `refresh()`, `revoke()`, `revoke_all_user_tokens()` and `purge_expired()` now check write results, log via `StructuredLogger` (channel `auth`) and surface `WP_Error` (500) instead of pretending success; `POST /auth/device` and `DELETE /auth/device` return 500 on write failure while keeping their success payloads unchanged
- **Tests: mock wpdb divergence from real wpdb** — mock `update()`/`delete()` now translate NULL where-values to `IS NULL` (real wpdb semantics) and the mock `flavor_device_tokens` table uses the production composite `UNIQUE (device_token, platform)`

### Added

- Flavor Builder («فلیور ساز») — the theme's built-in drag-and-drop page builder, ported from Rasta Commerce "Rasta Builder" (`inc/builder.php`)
  - Meta box on pages, posts and `flavor_branch` with palette → canvas drag-and-drop, block reorder, inline field editing and enable toggle
  - Layout stored in `_flavor_builder_data` post meta and rendered server-side (no JS required for visitors)
  - Content elements: heading, text, button, image · Layout: divider, spacer · Sections: CTA, features list, testimonials
  - Restaurant elements replacing the shop ones: menu grid (WooCommerce food items), menu categories (`product_cat`), table reservation CTA (auto-resolves the `reservation` page)
  - `[flavor_builder id="123"]` shortcode to embed a saved layout anywhere
  - Frontend styles load only on pages using the builder (`flavor_builder_enqueue_styles` filter to override)
  - Extensibility filters: `flavor_builder_elements`, `flavor_builder_post_types`, `flavor_builder_reservation_url`
  - Assets: `assets/css/builder.css`, `assets/css/builder-admin.css`, `assets/js/builder-admin.js`

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
