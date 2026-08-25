=== Flavor Core ===
Contributors: flavor
Tags: woocommerce, restaurant, cafe, qr-menu, iran, jalali, toman
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.0
WC requires at least: 8.5
Stable tag: 0.3.0
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

WooCommerce 8.5+ with HPOS is required.

== Installation ==

1. Install and activate WooCommerce.
2. Upload `flavor-core` to `wp-content/plugins/`.
3. Activate Flavor Core.
4. Activate the Flavor theme (optional but recommended).
5. Open رستوران مستقیم → Settings and edit the default branch.

== Changelog ==

= 0.3.0 =
* Reservations (Jalali), menu schedules, coupons, phone orders, loyalty.

= 0.2.0 =
* QR codes, order modes, kitchen dashboard, delivery zones, OTP, SMS adapters.

= 0.1.0 =
* Initial public foundation for Phase 1.

== Upgrade Notice ==

= 0.1.0 =
First tagged release. Activate on a staging copy first.
