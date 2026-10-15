=== Flavor Core ===
Contributors: flavor
Tags: woocommerce, restaurant, cafe, qr-menu, iran, jalali, toman
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
WC requires at least: 8.5
WC tested up to: 11.0
Stable tag: 1.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

منطق کسب‌وکار قالب Flavor / رستوران مستقیم: شعبه، میز، سفارش سالن و ارسال، آشپزخانه، رزرو شمسی، OTP.

== Description ==

Flavor Core is the operational business logic engine for the Flavor restaurant theme (رستوران مستقیم). It owns every operational concern — branches, dining tables, kitchen tickets, Jalali calendar reservations, SMS OTP authentication, delivery zones, and customer loyalty — so merchants can switch themes without losing operational data.

= Core Systems =

* Custom database tables: Kitchen tickets as operational source of truth (`wp_flavor_kitchen_tickets`), dining tables, reservations, zones, loyalty points, and SMS audit logs.
* Multi-branch operations: Custom Post Type (`flavor_branch`) with working hours, coordinates, delivery radius, and managers.
* Dining-table registry: Per-branch tables with unique token generation and QR code landing URLs for contactless ordering.
* Currency & Pricing: Iranian Toman and Rial currency formatting, automatic Rial storage to Toman display conversions, and food product modifiers.
* Kitchen Display System (KDS): Live web dashboard at `/kitchen-dashboard/` with audio order chime, dark UI, glanceable typography, and touch-friendly actions.
* Jalali Calendar: Accurate Gregorian to Jalali conversions, leap-year calculations, and table reservation booking management.
* Secure Authentication: Phone number SMS OTP verification with rate limiting, brute-force throttling, and single-use token rotation.
* REST API: Secure endpoints under `/wp-json/flavor/v1/` and `/flavor/v2/` supporting web storefronts and the Flutter mobile app.

== Installation ==

1. Upload the `flavor-core` folder to your WordPress `wp-content/plugins/` directory, or upload `flavor-core.zip` via Plugins → Add New → Upload Plugin.
2. Activate Flavor Core in WordPress admin. The plugin will verify database schemas and create required custom tables automatically.
3. Ensure WooCommerce 8.5+ is installed and active. If missing, Flavor Core attempts automatic installation from WordPress.org.
4. Ensure WordPress Permalinks are configured to "Post name" under Settings → Permalinks to enable REST API routes and QR endpoints.
5. Activate the companion Flavor theme (`flavor.zip`) for the presentation layer.
6. Open رستوران مستقیم → Settings in the admin sidebar to set up your primary restaurant branch, hours, and payment preferences.

== Frequently Asked Questions ==

= Does Flavor Core require the Flavor theme? =

No. Flavor Core is architected as an independent, decoupled business engine. While pairing it with the companion Flavor theme provides an optimized end-to-end presentation experience, Flavor Core can power any custom WordPress theme or serve purely as a headless REST API backend for mobile apps.

= Why does Flavor Core use custom database tables instead of standard post meta? =

High-volume restaurants receive hundreds of order items, kitchen status updates, and table turnover events daily. Storing active kitchen tickets, reservations, and SMS audit logs in custom, indexed tables (`wp_flavor_kitchen_tickets`, `wp_flavor_reservations`, etc.) guarantees sub-millisecond query performance and eliminates database bloat from `wp_postmeta`.

= How does contactless QR table ordering work? =

Each dining table registered under a branch receives a secure, unique token. Scanning the table's QR code directs the diner's smartphone browser to the digital menu with the table pre-selected. Diners add dishes, customize options, and submit their orders directly to the kitchen display without requiring app installation or staff intervention.

= Is WooCommerce required for Flavor Core to operate? =

Yes. WooCommerce provides the battle-tested transactional infrastructure for order persistence, customer cart management, tax rules, and local payment gateway integrations. Flavor Core bridges seamlessly into WooCommerce via HPOS and Cart/Checkout Blocks compatibility.

= Can I use Iranian SMS gateways for OTP login and order notifications? =

Yes. Flavor Core includes a flexible SMS gateway abstraction supporting leading Iranian SMS service providers (Kavenegar, FarazSMS/IPPanel, Melipayamak, Ghasedak). In local development or staging environments, an integrated debug log mode records OTP codes without consuming gateway credits.

= Does Flavor Core support the companion Flutter mobile app? =

Yes. Flavor Core provides dedicated REST API v2 endpoints tailored for the Flutter mobile application, including mobile cart token bridging (`X-Cart-Token`), idempotency replay protection, FCM push notification dispatch, and Iranian province/city selectors.

== Screenshots ==

1. kitchen-kds.png — Real-time Kitchen Display System (KDS) showing active orders, ticket age, item modifiers, and status transition buttons.
2. admin-settings.png — Flavor Core administrative settings panel showing branch management, ordering modes, and SMS gateway configuration.
3. table-qr-management.png — Dining table registry with instant QR code generation, branch assignment, and printable table cards.
4. jalali-reservations.png — Table reservation calendar interface with Jalali dates, time slot capacities, and guest management.
5. loyalty-points.png — Customer loyalty rewards dashboard displaying points accumulation, digital stamps, and discount rules.

== Changelog ==

= 1.5.1 =
* CRITICAL FIX: the Gregorian-to-Jalali conversion produced garbage years, so
  every displayed Jalali date was wrong (2026-10-08 rendered as 24 آبان 7717
  instead of 16 مهر 1405). This leaked into reservation slot labels, the
  reservation panel, the settings API and time-boxed discounts.
  from_gregorian() is now the exact inverse of to_gregorian(), verified against
  a 73,414-day round trip over 1900–2100 and an 18,628-day comparison against
  the ICU Persian calendar.
* Fixed test-suite rot: reservation fixtures used a hard-coded date that made
  five tests fail forever once it passed. Fixtures are now relative.

= 1.5.0 =
* Premium admin design system: tokens, KPI cards, tables, charts, tabs, badges.
* Kitchen dashboard rebuilt as a dark KDS with lane counts, empty states, live
  clock, connection indicator and elapsed-time urgency chips; reduced-motion safe.
* Storefront micro-interactions: focus rings, button feedback, toast/sheet/cart
  entrances, dish-image zoom and busy-grid shimmer.
* Mobile: fixed a compile error in the token rotation path and hardened deep-link
  resolution against control-character injection.

= 1.4.0 =
* WooCommerce is now installed and activated automatically on plugin activation.
* REST API v2, security hardening and a runtime verification suite.

= 1.1.0 =
* Smart AJAX menu search: Persian normalisation, typo tolerance, ranking, facets, did-you-mean.
* New REST routes flavor/v1/search, /search/suggest, /search/popular with an admin-ajax fallback.

= 0.3.0 =
* Reservations (Jalali), menu schedules, coupons, phone orders, loyalty.

= 0.2.0 =
* QR codes, order modes, kitchen dashboard, delivery zones, OTP, SMS adapters.

= 0.1.0 =
* Initial public foundation for Phase 1.

== Upgrade Notice ==

= 1.5.1 =
Critical Jalali calendar calculation fix for leap years and reservation time slots. Recommended for all installations.

= 1.5.0 =
Upgraded admin interface and real-time kitchen display.

= 0.1.0 =
First tagged release. Activate on a staging copy first.
