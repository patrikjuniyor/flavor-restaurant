# Changelog

All notable changes to **رستوران مستقیم** (Flavor theme + Flavor Core plugin) are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Demo design — 2026-10-01

- **Juice Bar / Limo** — bespoke botanical/citrus RTL landing page, 8 WooCommerce products, 4 illustrated categories, 6 new optimized local images, category filtering, brand story, native FAQ, service-specific contact/footer and accessible mobile navigation. Estedad + Vazirmatn stay local; the seven previously completed demo compositions are preserved.
- **Cloud Kitchen / Pack** — cobalt/lime ordering-first composition, 8 dishes, pickup/delivery only, operational neighborhood check and server-rendered branch-zone pricing. One-click import creates sample delivery rules in the configured storage currency and removes only the previous demo branch’s zones on re-import. Checkout/default mode and validated frontend branch context stay aligned; no fake courier tracking or guaranteed ETA.
- **Minimal Clean / Form** — paper-white/sage editorial masthead, wide food photograph, compact two-column live menu, grayscale kitchen narrative and restrained reservation/contact sections; 8 dishes, 6 real tables, synchronized opening hours, local optimized imagery and verified responsive/accessibility/cart/reservation flows.
- **Dark Luxe / Noir** — charcoal/copper evening-dining composition, 8 live WooCommerce dishes, architectural story, kitchen philosophy, native FAQ and real reservation links; imported 8 tables and seven-day 17:00–23:00 hours match the advertised service. Includes scoped skin and optimized local art.
- **Reservation integration** — normalize canonical branch/slot envelopes while preserving the legacy Jalali calendar, expose GET errors, label calendar controls and pressed states, discard stale capacity responses and clear obsolete time selections. Fix the Core table factory’s missing-section default so one-click dining packs create actual tables. Demo re-import cleans its own branch tables/hours only; frontend branch/service modes derive from live configuration.
- **Demo installation** — additive standalone packs, per-product image imports with asset reuse, WooCommerce CRUD synchronization, service-mode/table configuration, cleanup of stale demo overrides and anchor navigation, and no duplicate block hero on bespoke homepages.
- **Menu integration fix** — web menu now accepts both the current REST success envelope and legacy raw responses, loads canonical dish/modifier details, converts storage/display currency correctly and resolves bespoke product deep links without silently adding to the cart. API routes are unchanged. Added safe guest-cart token bridging, visible errors and cache-busted menu assets.
- **Verification** — standalone catalog validation, guarded disposable-WP repeated-import tests and Playwright/axe browser smoke tests; setup and results in `docs/DEMO-REDESIGN.md`.

### Security

- **Android: release builds could no longer be debug-signed** — `build.gradle` previously attached `signingConfigs.debug` to the release build type, making Play uploads either fail or (worse) ship the shared debug key; release now requires `FLAVOR_UPLOAD_*` environment variables (CI secrets) or a git-ignored `keystore.properties`, enforced at task-graph execution time with a loud `GradleException`, and store credentials (`*.keystore`, `*.jks`, `.p12`, `.mobileprovision`, `keystore.properties`) are banned via `.gitignore`
- **CI: iOS releases no longer ship an unsigned artifact** — `flutter build ios --no-codesign` was the release path, producing a binary that App Store Connect would reject; the workflow now fails loudly without `FLAVOR_IOS_DIST_CERT_P12_BASE64` / password / provisioning profile / team id, installs them into an isolated temporary keychain, and builds a fully signed `app-store` IPA with CI-generated export options
- **Deep links: tenant domains are provisioned and validated** — the manifest app-link filter previously pointed at a placeholder host (`*.restaurant.com`); provisioning now derives validated tenant hosts from the canonical config (or explicit `deep_links.domains`), rewrites Android intent-filters and iOS Associated Domains, generates `assetlinks.json` (release SHA-256 via `FLAVOR_ANDROID_CERT_FINGERPRINT`) and `apple-app-site-association` (`FLAVOR_IOS_TEAM_ID`), rejects wildcards/malformed domains, and never silently emits valid well-known files without the proper credentials
- **Push: device registration now requires authentication** — `POST/DELETE /flavor/v*/auth/device` previously accepted anonymous callers and bound device tokens to whoever first claimed them; both endpoints now require an authenticated session, registration binds tokens to the authenticated user (with automatic re-binding when a device changes accounts), and unregistration is ownership-checked (IDOR) returning 404 for foreign tokens
- **Push: transport credentials are env/path-only secrets** — FCM now authenticates with an OAuth2 service-account token (RS256 JWT, HTTP v1 API) and APNs with an AuthKey `.p8` (ES256 JWT); key material is resolved from `FLAVOR_FCM_SERVICE_ACCOUNT_JSON` / `*_PATH` and `FLAVOR_APNS_*` env vars (or path-only options), is cached solely in `*_oauth_*` transients, and is never stored in the DB, returned by APIs, or logged. The decommissioned legacy FCM server-key API was removed
- **Mobile provisioning: injection into generated native/Dart sources eliminated** — the white-label provisioning tool previously interpolated raw config values into `AndroidManifest.xml` (`android:label`), `Info.plist` strings, Gradle string literals and single-quoted Dart string literals with no escaping, so a server/staging config value containing quotes, control characters, XML markup or `$` could corrupt generated projects or inject code. All outbound values now go through format-specific escapers (XML attribute, plist, Gradle GString-neutralizing, Dart string) with control characters stripped
- **Mobile provisioning: strict validation with hard build rejection** — application identifiers, semantic version names, version codes (1..2100000000), hex colors (6-digit only), http(s) URLs, tenant slugs, font families and border radii are validated before any file is written; invalid values abort CI with a dedicated exit code instead of producing a broken app
- **Webhooks: event subscriptions were silently dead** — `WebhookController` ran `sanitize_key()` over event names, corrupting dotted names (`order.created` → `ordercreated`) so subscribed webhooks never matched dispatched events. Events are now validated against an explicit allowlist (`WebhookManager::validate_events`, dotted names never sanitized) with 400 + details for unsupported names, and `dispatch()` itself ignores unknown events
- **Webhooks: listeners subscribed to ghost hook names** — `WebhookManager` listened on `flavor_order_placed`, `flavor_reservation_created`, `flavor_customer_registered`, … which no code ever emits; nothing ever fired. Listeners are now wired to the actual `do_action()` calls the application emits (`flavor_core_kitchen_ticket_created`, `flavor_core_kitchen_status_changed`, `flavor_core_reservation_created`, `flavor_core_reservation_status_changed`, `flavor_core_otp_verified`, `flavor_core_loyalty_points_awarded` — the latter newly emitted by `PointsManager` when points are actually awarded; `customer.created` fires only for accounts created in the current OTP flow)
- **Webhooks: SSRF protection on target URLs** — create/update/validate-delivery all enforce `WebhookManager::is_safe_target_url()`: only http/https, no localhost/`.localhost`/`.local`/`.internal`, no private/reserved IP literals (IPv4+IPv6, incl. cloud metadata 169.254.169.254), no DNS name resolving to a private IP, no URL credentials; redirects are never followed (3xx = failure), timeout capped at 5s, and `wp_safe_remote_post()` is preferred when available
- **Webhooks: secrets are write-only** — `GET /webhooks/{id}` previously returned the full HMAC secret via `SELECT *`; it is now excluded from every GET response (a `secret_configured` flag is exposed instead). The secret is returned exactly once, on create
- **Webhooks: delivery moved out of the synchronous checkout path** — `dispatch()` now persists queued audit rows and schedules a `flavor_core_webhook_process_queue` cron task instead of doing inline `wp_remote_post()` during order placement; `process_queue()` drains rows (delivered/failed/skipped) with attempt accounting. The admin test endpoint stays synchronous on purpose
- **REST: duplicate route registrations removed** — six `flavor/v1` routes were registered twice (modular controller + legacy sub-router): `GET /cart`, `POST /auth/otp/request`, `POST /auth/otp/verify`, `GET /reservations/slots`, `POST /reservations`, `GET /reservations`. WordPress dispatches the first handler, so the legacy copies were dead/shadowed code with drift risk; they (and their unused handlers) are deleted with zero client-visible change. Legacy sub-routers are now `@deprecated` v1-only compatibility layers whose remaining routes are unique
- **Checkout: idempotent order creation** — `POST /flavor/v2/orders` accepts a client `idempotency_key` (stored as HPOS-safe `_flavor_idempotency_key` order meta); replaying the same key returns the original order instead of creating a duplicate, and requests without a key are auto-deduplicated within a 120-second double-submit window via a cart/user/mode fingerprint — including orders whose first payment attempt failed (no duplicate rows on payment retry)
- **Checkout: replay responses are ownership-bound** — a replay is only answered to the same authenticated customer, or to the request carrying the same guest cart token (bound at order creation via a SHA-256 hash; the token itself is never persisted), otherwise it is rejected with 409 — idempotency keys alone are not an order-reading capability
- **Checkout: dine-in can no longer attach arbitrary tables** — dine-in orders now require a table that exists, is active and belongs to the selected branch; `table_id` from another branch and unresolvable `table_number` values are rejected with 400 instead of being silently persisted to order meta
- **Checkout: branch and delivery inputs are server-validated** — the branch must exist and be published (mandatory for delivery), and delivery eligibility, minimum order and fee continue to derive exclusively from server-side zone data
- **Checkout: cart revalidated against the live catalog before payment** — every line is re-checked for product existence/purchasability, `AvailabilityManager` branch availability, `MenuScheduler` schedule visibility, price sanity (including drift detection on plain lines) and modifier validity against the product catalog, so stale or tampered carts can never become orders
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

- **Push: the server delivery "implementation" was a hook with zero listeners** — `NotificationHub::dispatch_push()` called `do_action('flavor_dispatch_push_tokens', ...)` and reported success without ever delivering anything. Delivery now platform-partitions tokens and calls real `send_fcm()` / `send_apns()` implementations (OAuth + HTTP v1, ES256 + APNs/2), the action still fires afterwards with delivery results purely as an observability extension point, provider-reported stale tokens (`UNREGISTERED`/410/`BadDeviceToken`) are deactivated as rotation cleanup, and a missing-credentials configuration degrades gracefully with zero HTTP attempts
- **Mobile: mock FCM tokens in production** — `NotificationService.init()` fabricated `fcm_mock_token_*` tokens and registered them. `main.dart` now initializes Firebase from per-brand native config (`google-services.json` / `GoogleService-Info.plist`, git-ignored), requests notification permissions, registers REAL FCM tokens (awaiting the APNs token on iOS), and follows `onTokenRefresh` rotation (register new → unregister superseded), all through a gateway abstraction so behavior stays testable; a brand without Firebase config still boots with push as a no-op
- **Mobile: notification taps were not connected to navigation** — foreground/background/terminated messages now flow through `NotificationPayloadParser` (contract `flavor-mobile-push@1`, hostile click_actions rejected) into the existing `DeepLinkService`, including the Android default-notification-channel manifest metadata, an in-isolate background handler, and `AuthBridge` for auth-guarded deep links from notification taps
- **Mobile: broken contract between the WordPress config API and the Flutter provisioning tool** — the server emitted the canonical nested payload (`app_identifier`, `branding.*`, `contact.*`, `legal.*`, `assets.*`) while `provision_brand.py` read legacy flat keys (`package_name`, `primary_color`, `support_email`, `privacy_policy_url`), so branded CI builds silently skipped the applicationId, contact, legal and several branding values entirely. Both sides now implement the single documented schema `flavor-mobile-branding@1`, the tool normalizes legacy flat keys with deprecation warnings, and the brand asset files were migrated to the canonical shape. The tool also stopped rewriting the Gradle `namespace`/Kotlin package per brand (source-tree moves made builds non-reproducible): the native package is a fixed architectural constant (`com.flavor.restaurant` — enforced, drift fails the build) and only `applicationId` varies
- **Checkout: guest cart was destroyed on failed payments** — `CartTokenService::delete_cart()` ran right after order creation, *before* the gateway check and `process_payment()`; a declined gateway left the customer with neither order confirmation nor cart. The stored guest cart is now deleted only after the gateway confirmed success and the order is fully persisted, and failed payments return a recoverable `flavor_pay_failed` (with `order_id`) while the cart stays intact for a retry
- **Checkout: kitchen ticket race around `woocommerce_checkout_order_processed`** — flavor metadata (`_flavor_branch_id`, `_flavor_table_id`, `_flavor_order_mode`, mobile, source, zone, guest token) was attached to the order only after `WC_Checkout::create_order()` returned, but that method fires the hook *internally*, so the ticket snapshot was created with empty metadata (default branch, dine-in, no table). `KitchenTicketSync` now supports a suspend flag around programmatic order creation, and `CheckoutService` materializes the offline ticket explicitly once meta and payment are persisted; online methods still create their ticket on `woocommerce_payment_complete` (metadata is saved before the redirect happens)
- **Checkout: missing-gateway check ran after the order row existed** — gateway availability is now verified before `create_order()`, so a nonexistent `payment_method` returns `flavor_gateway` 400 without creating orphan rows (mode-allowlist violations still return `flavor_pay`)
- **Settings: undefined `clean_option_cache()` fatal** — `Settings::bump_menu_version()` called a function that exists neither in WordPress nor the plugin; it now uses `wp_cache_delete( $key, 'options' )` (latent production fatal surfaced by the new `AvailabilityManager::set()` checkout tests)
- **API: recoverable checkout errors lost their context** — `OrderController` now forwards structured `WP_Error` data (`order_id`, `recoverable`, …) as response `details` instead of dropping everything but the status code
- **Auth: fatal error on logout-all-devices** — `AuthController::token_revoke()` called the non-existent `TokenService::revoke_all_for_user()`; it now calls the actual `TokenService::revoke_all_user_tokens()`
- **Auth: OTP login wrote device name into the device_id column** — `TokenService::issue( $user_id, '', $device_name )` argument order corrected in `otp_verify()`
- **Auth: refresh tokens never expired** — the refresh flow only checked `revoked_at`; refresh TTL is now enforced via a new `refresh_expires_at` column (schema 1.5.0), with a `created_at + REFRESH_TTL` fallback for legacy rows and a best-effort revoke of dead rows
- **DB: `flavor_device_tokens` production schema missing `app_version` and `updated_at`** while registration wrote them (and skipped the NOT NULL `last_seen_at`) — columns added via dbDelta migration (DB version 1.4.0 → 1.5.0)
- **DB: token purge could delete rows whose refresh token was still valid** — purge is now refresh-aware and only removes revoked rows older than 30 days or rows whose refresh credential is definitively expired
- **Observability: silent database-write failures** — `issue()`, `refresh()`, `revoke()`, `revoke_all_user_tokens()` and `purge_expired()` now check write results, log via `StructuredLogger` (channel `auth`) and surface `WP_Error` (500) instead of pretending success; `POST /auth/device` and `DELETE /auth/device` return 500 on write failure while keeping their success payloads unchanged
- **Tests: mock wpdb divergence from real wpdb** — mock `update()`/`delete()` now translate NULL where-values to `IS NULL` (real wpdb semantics) and the mock `flavor_device_tokens` table uses the production composite `UNIQUE (device_token, platform)`

### Added

- **Mobile release tooling documentation** — `docs/MOBILE-RELEASE-CHECKLIST.md` (version codes/names, IDs, Android/iOS signing, deep links, privacy permissions, CI acceptance gates); `mobile/android/app/proguard-rules.pro` (standard Flutter keeps for future shrinking); contract doc `MOBILE-WHITELABEL-CONTRACT.md` gained the `deep_links.domains` schema section
- **Push documentation & test coverage** — `docs/PUSH-NOTIFICATIONS.md` (payload contract, FCM/APNs setup, rotation and logout flows, per-brand native files that must never be committed; matching `.gitignore` entries); e2e suite 13 covers auth enforcement, token re-binding, IDOR-protected unregistration, end-to-end FCM/APNs delivery with ephemeral test credentials, stale-token deactivation, missing-credential degradation, logout device cleanup and the observability-hook contract; `mobile/test/unit/notification_payload_test.dart` covers payload parsing (incl. hostile strings), order/reservation navigation destinations, token rotation, logout idempotence and failure tolerance
- **Mobile white-label contract documentation & tests** — `docs/MOBILE-WHITELABEL-CONTRACT.md` defines the canonical schema (fields, types, validation rules, legacy→canonical mapping, escaping rules, stable-native-package architecture); the server payload carries a `"$schema": "flavor-mobile-branding@1"` marker; `mobile/scripts/test_provision_brand.py` provides a 19-test stdlib suite covering every committed brand file, a server-generated payload fixture (exported by `flavor-core/tests/export-mobile-config.php` from the real `MobileConfigManager`), legacy aliases, rejection matrices and hostile-string escaping with syntactic verification of all generated Android/iOS/Dart artifacts; `project.pbxproj` (when present) gets its `PRODUCT_BUNDLE_IDENTIFIER` provisioned, otherwise CI is instructed to supply the bundle identifier
- **REST route registry observability** — the mock environment now records every `register_rest_route()` call and provides a functional WP action registry (`add_action`/`do_action`), enabling deterministic route-duplication and webhook-dispatch tests; `wp_generate_uuid4`, `wp_schedule_single_event`, `wp_verify_nonce` and `wp_parse_url` stubs and the webhook tables were added
- **`docs/REST-ROUTES.md`** — authoritative registration architecture, removed-duplicate list, full permission matrix for every mutating endpoint, and the webhook pipeline contract (events, hooks, queue, SSRF, signing, redaction)
- **Route & webhook e2e suite (9 scenarios)** — zero-duplicate route map + legacy-surface retention, hook-name alignment proof, all 8 supported events dispatched from their real application hooks (queued, async, cron-scheduled), unsupported events rejected at three layers, secret redaction on GET endpoints, webhook admin authorization (guest 403 / customer 403 / admin ok), SSRF matrix at validator + API level, async queue consumption with outcome accounting + skipped/deactivated webhooks, and the synchronous admin test endpoint
- **Checkout reliability e2e suite (10 scenarios)** — explicit-key idempotency replay, auto-dedup of rapid double-submits, failed gateway payment (cart preserved + recoverable error + no duplicate on retry), missing gateway rejected pre-creation, nonexistent/unpublished branch, arbitrary/cross-branch/inactive/missing dine-in table, branch-level product unavailability with restock recovery, offline-payment kitchen ticket created synchronously with full metadata, online payment deferring the ticket until `payment_complete`, and cross-cart replay hijack protection (409)
- **Test environment** — mock WP now provides `wc_get_orders()` (customer/meta/pagination filters), `get_post_status()` with per-post override seams, gateway catalog overrides plus failing/online gateway fixtures, and `flavor_availability`/`flavor_availability_log` tables
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
