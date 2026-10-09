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

Flavor Core is the companion plugin for the Flavor restaurant theme (رستوران مستقیم). It owns every operational concern so merchants can switch themes without losing orders.

= Phase 1 (this release) =

* Custom tables (kitchen tickets as operational source of truth)
* Branch custom post type
* Dining-table registry + QR landing URL
* Toman / Rial currency layer
* Simple-product food modifiers (size, topping, cook, removal)
* REST API namespace `flavor/v1`
* Roles: branch manager, kitchen, cashier
* Kitchen dashboard skeleton (`/kitchen-dashboard/` and wp-admin)

WooCommerce 8.5+ with HPOS is required. When Flavor Core is activated, it attempts to download and activate WooCommerce from the official WordPress.org directory if WooCommerce is missing. If the host blocks automatic downloads or filesystem writes, install WooCommerce manually from Plugins → Add New and then activate it.

== Installation ==

1. Upload `flavor-core` to `wp-content/plugins/` and activate Flavor Core. WooCommerce is installed and activated automatically when it is not already available.
2. If automatic installation is blocked, install and activate WooCommerce 8.5+ from Plugins → Add New, then return to Flavor Core.
3. Activate the Flavor theme (optional but recommended).
4. Open رستوران مستقیم → Settings and edit the default branch.

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

= 0.1.0 =
First tagged release. Activate on a staging copy first.
