# Phase 4 — what landed

Shipped in theme `0.4.0`.

## Design system

- Eight skins as CSS tokens (`Design::tokens`)
- Customizer: skin, header layout, accent/bg/surface/ink, hero copy, about
- Body classes: `flavor-skin-{slug}` and `flavor-header-{layout}`
- Marketing CSS for hero / about / gallery / quotes
- `front-page.php` uses the hero + about from Customizer (menu stays on its own template for performance)

## Eight demos

| Slug | عنوان | فضا |
|---|---|---|
| `modern-cafe` | کافه مدرن | کرم / قهوه‌ای / زیتونی |
| `fast-food` | فست‌فود | قرمز / زرد |
| `traditional` | رستوران سنتی | زرشکی / طلایی |
| `fine-dining` | رستوران لوکس | مشکی / طلا |
| `pastry` | شیرینی‌فروشی | پاستل |
| `juice-bar` | آبمیوه | سبز / نارنجی |
| `catering` | کترینگ | سرمه‌ای سازمانی |
| `cloud-kitchen` | آشپزخانه ابری | مینیمال ارسال |

Each pack: 22 Persian menu items, categories, branch, 8 tables, pages (خانه / منو / رزرو / شعبه‌ها / درباره / تماس), hero photo, testimonials.

## Importer

Appearance → **دموهای Flavor** → one-click.

- Cleans previous `_flavor_demo` posts
- Sideloads the bundled hero
- Sets Customizer + site title
- Builds the primary menu
- If Flavor Core + WooCommerce are active: simple products, modifiers, default branch, tables 1–8

Assumes store catalog currency is **تومان (IRT)**. Modifier extras are stored in Rials (default storage unit).

## Elementor

Five widgets (register only when Elementor is present): هیرو، درباره، گالری، نظرات، اطلاعات شعبه.

## Gutenberg

Dynamic blocks `flavor/hero`, `flavor/about`, `flavor/gallery`, `flavor/testimonial` — no webpack.

Hero photos live in `flavor/demos/{slug}/hero.jpg` (~1.5 MB total) so the theme zip stays host-friendly.
