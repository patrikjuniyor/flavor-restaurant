=== Flavor — رستوران مستقیم ===
Contributors: flavor
Tags: rtl-language-support, e-commerce, food-and-drink, custom-logo, custom-menu, featured-images, block-styles, block-patterns, custom-colors, editor-style, translation-ready
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
WC requires at least: 8.5
WC tested up to: 11.0
Stable tag: 1.6.1
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
* Block editor: eight core-block-only patterns, registered under «رستوران مستقیم»,
  plus a `theme.json` wired to the same `--flavor-*` tokens the Customizer writes.
* Per-page options: hide the title, transparent header, content width and a
  per-page background, on pages, posts and products.

= Commerce =

WooCommerce-compatible. Product cards, the menu grid, the cart widget and the
quick view all read prices, stock and totals from WooCommerce on the server.
Nothing about money is computed in the browser.

== Installation ==

1. Upload `flavor` to `wp-content/themes/` and activate it.
2. Install and activate Flavor Core for branches, tables, QR ordering, kitchen
   display and reservations. The theme works without it, but the restaurant
   features need it.
3. Install and activate WooCommerce 8.5+ if it is not already present.
4. Open نمایش → سفارشی‌سازی → پوسته‌های آماده and choose a preset.

== Frequently Asked Questions ==

= Does the theme work without Flavor Core? =

Yes. You get the design system, the presets, the page templates, the Elementor
widgets and the block patterns. You do not get branches, tables, QR ordering,
the kitchen display, Jalali reservations, OTP or loyalty — those are Flavor Core.

= Why is order logic in a plugin and not the theme? =

Because a restaurant's data outlives its design. If branches and orders lived
in the theme, changing themes would mean re-entering every table and losing
order history. Keeping them in the plugin makes the theme replaceable.

= Is it WooCommerce compatible? =

Yes, 8.5 and above, including HPOS. Prices, stock and totals always come from
WooCommerce server-side; the theme never recalculates them in the browser.

= Can I use a child theme? =

Yes, and it is the recommended way to hold custom CSS. `flavor-child` ships with
the parent and loads its stylesheet last, so an override never loses a
specificity race.

= Is it translatable? =

Yes. All strings are in the `flavor` text domain and the theme ships a POT file
plus compiled Persian and Arabic catalogues. It is RTL-first, and the type
scale carries an explicit warning that letter-spacing breaks Persian joining.

= Is it compatible with WPML or TranslatePress? =

The theme is declared Persian-first rather than claiming official multilingual
integration. It does not register its own translation layer, so a multilingual
plugin is free to own the strings: nothing in the theme hardcodes a locale,
a currency, or a direction, and every user-facing string goes through the
`flavor` text domain.

What this means in practice: the theme will not fight such a plugin, but it is
not tested against one either. Claiming official integration would mean
maintaining a compatibility matrix against two paid plugins for a market the
theme is built for, and a half-kept promise is worse than an honest one. If you
run a multilingual site, test your specific setup before you rely on it.

= Why is the theme 1.3 MB when the repository is larger? =

The demo photography is a separate download (`flavor-demo-pack.zip`, about
12 MB). A customer who supplies their own photographs never loads it, and the
theme renders correctly without it: sections that would point at missing
images fall back to a bundled placeholder instead of emitting broken URLs.

== Changelog ==

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

= 1.6.1 =
The gallery and testimonials sections gained repeater controls. Existing
content is migrated automatically from the old six fixed gallery slots, and a
site that never opened them keeps showing the demo content. Nothing is lost on
upgrade.
