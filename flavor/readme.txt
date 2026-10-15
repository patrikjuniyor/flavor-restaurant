=== Flavor — رستوران مستقیم ===
Contributors: flavor
Tags: rtl-language-support, e-commerce, food-and-drink, custom-logo, custom-menu, featured-images, block-styles, block-patterns, custom-colors, editor-style, translation-ready
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
WC requires at least: 8.5
WC tested up to: 11.0
Stable tag: 1.6.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

قالب رستوران مستقیم. موبایل‌اول، RTL، تومان و منوی QR. منطقِ سفارش در افزونهٔ Flavor Core است و قالب فقط ظاهر را دارد.

== Description ==

Flavor is the presentation layer for رستوران مستقیم, an Iranian restaurant
platform. It is RTL-first, built around Toman, and designed for a guest who
arrives by scanning a QR code at a table.

The split is deliberate: everything operational — branches, tables, kitchen
tickets, Jalali reservations, OTP, delivery zones, loyalty — lives in the
companion plugin Flavor Core. The theme owns presentation only. A merchant can
change themes without losing a single order, and the theme never computes a
price, a stock level or a shipping cost on its own.

= Design system =

Twelve restaurant presets, each a complete palette rather than a colour swap:
modern, luxury, Persian traditional, café bistro, fast food, pizza, bakery,
juice bar, dark luxe, minimal, cloud kitchen and catering. Every preset is
reachable from the Customizer with a visual thumbnail, and every colour is
checked against WCAG AA before you publish.

A responsive type scale derives h2–h6 from the body size, so the page stays in
a single harmony instead of six unrelated numbers. Layout, spacing and radius
are set per breakpoint.

= Editing =

* Elementor: seventeen widgets in a branded `flavor` category, and three ready page templates (home, about, menu).
* Header and footer: Flavor's own chrome builder and the Customizer. No paid
  page-builder add-on is required, and deactivating Elementor leaves the layout —
  and the product page — standing.
* Block editor: eight core-block-only patterns, registered under «رستوران مستقیم»,
  plus a `theme.json` wired to the same `--flavor-*` tokens the Customizer writes.
* Per-page options: hide the title, transparent header, content width and a
  per-page background, on pages, posts and products.

= Commerce =

WooCommerce-compatible. Product cards, the menu grid, the cart widget and the
quick view all read prices, stock and totals from WooCommerce on the server.
Nothing about money is computed in the browser. The single-product page is
Flavor's own too: the gallery, price, the dish's modifier groups and the order
button are composed on WooCommerce's documented hooks — no WooCommerce template
is copied, so an update to the shop cannot silently orphan the layout — and the
extras a customer picks are priced by WooCommerce when the line lands in the
cart, not by a script in the page.

== Installation ==

1. Upload `flavor` to `wp-content/themes/` and activate it from Appearance → Themes.
2. Install and activate Flavor Core (`flavor-core.zip`) for restaurant operations: branches, tables, QR ordering, kitchen display, Jalali reservations, OTP login, and loyalty rewards. The theme operates standalone as a presentation layer, but operational dining features require the plugin.
3. Install and activate WooCommerce 8.5+ if it is not already present. When Flavor Core is activated, it attempts automatic installation if WooCommerce is missing.
4. Set up WordPress Permalinks: navigate to Settings → Permalinks (تنظیمات → پیوندهای یکتا) and select "Post name" (نام نوشته) so REST API endpoints and table QR landing URLs resolve smoothly.
5. Optional: install and activate the free Elementor Website Builder plugin to customize pages using Flavor's 17 restaurant widgets and 3 page templates. Elementor Pro is never required.
6. Choose your design preset: open Appearance → Customize → Ready Presets (نمایش → سفارشی‌سازی → پوسته‌های آماده) and select one of the 12 curated dining styles.

== Frequently Asked Questions ==

= Does the theme work without Flavor Core? =

Yes. You get the complete presentation design system, the 12 presets, the page templates, the 17 Elementor widgets and the block patterns. You do not get restaurant operational workflows (branches, dining tables, QR ordering, kitchen KDS display, Jalali reservations, SMS OTP auth or loyalty points) — those belong to the companion plugin Flavor Core.

= Why is order logic in a plugin and not the theme? =

Because a restaurant's operational data outlives its design. If branches, dining tables, customer orders and kitchen tickets lived in the theme, changing or updating themes would wipe out your data and order history. Encapsulating operations inside Flavor Core keeps your business data permanent and your presentation theme fully replaceable.

= Is WooCommerce required, and which versions are supported? =

Yes, WooCommerce is required for commercial cart, checkout, payments, and order persistence. Flavor supports WooCommerce 8.5 up to 11.0+, with full High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatibility. All pricing, discounts, shipping, and taxes are strictly calculated server-side by WooCommerce; client-side scripts never manipulate monetary totals.

= Is the theme translatable and multilingual-ready? =

Yes. All text strings are registered under the `flavor` text domain. The theme provides complete POT template files alongside compiled Persian (fa_IR) and Arabic (ar) catalogues. The codebase is RTL-first, with automated bidirectional parity testing. Standard multilingual plugins such as WPML or TranslatePress can translate all strings without conflicts, as no string or currency is hardcoded.

= Is there a native mobile app available for Flavor? =

Yes. The project includes a production-grade Flutter mobile application in the `mobile/` directory, featuring customer ordering, Jalali date reservations, offline menu caching with retry recovery, Iranian province/city selectors, deep linking, and push notification integrations. Merchants can build and publish their branded APK or release on Café Bazaar and Google Play.

= How do updates work for the theme and plugin? =

Flavor provides a secure, cryptographically signed update channel utilizing Ed25519 signatures (libsodium). When an update is published, the site verifies the digital signature of the release manifest against the bundled public key before downloading. Automatic atomic backups are preserved under `wp-content/flavor-backups/` prior to any theme update.

= Why is the theme package 1.3 MB when the demo preview is rich? =

High-resolution demo photography is distributed as a separate downloadable asset pack (`flavor-demo-pack.zip`, ~12 MB). The theme itself remains lightweight and agile (~1.3 MB) for instant shared hosting uploads. When demo images are not imported, the theme renders elegant, bundled SVG/CSS placeholders without generating broken URLs.

== Screenshots ==

1. screenshot.png — Storefront homepage preview showing hero banner, quick category navigation tabs, featured dishes, and modern Iranian restaurant typography.
2. storefront-menu.png — Digital interactive food menu grid with instant category filtering, dietary badges, and one-tap guest cart drawer.
3. single-dish-sheet.png — Single product detail sheet displaying food photography, calorie counts, ingredient tags, and real-time food modifiers (sizes, toppings, preparations).
4. checkout-drawer.png — Seamless slide-over cart drawer and checkout with delivery/dine-in order modes, guest ordering, and SMS OTP verification.
5. kitchen-kds-tablet.png — Dark-mode Kitchen Display System (KDS) optimized for kitchen touch tablets with large 48px touch targets and live polling.
6. customizer-presets.png — WordPress Customizer panel showing 12 curated restaurant presets with WCAG AA color contrast verification.

== Changelog ==

= 1.6.2 =
* Fixed Elementor widget category registration so the shared base is loaded before the category hook runs.
* Fixed Elementor page rendering on the front page when raw post content is blank.
* Added regression tests for Elementor boot order and page builder metadata.
* Clarified that Elementor is a separate optional plugin; the free version is sufficient.

= 1.6.1 =
* Demo photography moved to a separate pack; the theme itself is now 1.3 MB
  instead of 16 MB, and renders correctly with the pack absent.
* Verified against a real WordPress 7.1.3 with WooCommerce 11.0.1 in CI.
* Seventeen Elementor widgets in a branded `flavor` category, up from five; five new restaurant widgets (features, stats, process, FAQ, printed price list) and three page templates.
* Customizer: the preset picker, contrast report and both repeaters now render (they were empty).
* Responsive type scale with per-breakpoint weight, line height and letter spacing.
* Per-page options: hide title, transparent header, content width, background.
* Live preview for every Customizer setting that can support it, with the
  remainder documented as refresh-only rather than silently upgrading.
* Visual preset thumbnails, and non-destructive clearing of manual colour overrides.
* WCAG contrast report beside the colour pickers; unreadable pairs are now
  reported instead of being corrected in silence.
* Editable, reorderable gallery and testimonials with alt text per image.

== Upgrade Notice ==

= 1.6.2 =
Fixes Elementor widget loading order and front-page rendering. Install and activate Elementor separately, then edit a page with Elementor; the free plugin is enough.

= 1.6.1 =
The gallery and testimonials sections gained repeater controls. Existing
content is migrated automatically from the old six fixed gallery slots, and a
site that never opened them keeps showing the demo content. Nothing is lost on
upgrade.
