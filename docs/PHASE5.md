# Phase 5 — launch polish

Shipped as **1.0.0**.

## Performance

- `menu.js` only on the menu template; `reservation.js` only on reservation
- `marketing.css` only on the front page
- Preload Vazirmatn Regular (`font-display: swap` already)
- Hero `fetchpriority="high"`
- REST menu remains public `max-age=30` + `menu_version`

## Security

- IP rate limiter on OTP, reservation, coupon
- Privacy exporters/erasers for mobile + loyalty meta
- Cache exclusion hints (WP Rocket filter + admin notice)
- `SECURITY.md`

## SEO

- JSON-LD graph: Restaurant + FoodEstablishment + Menu/MenuItem + BreadcrumbList
- Open Graph / Twitter tags

## Tests

Existing PHPUnit suites plus rate-limit guard coverage in docs. Run with `WP_TESTS_DIR`.

## Documentation (Persian)

- `docs/fa/01-nasb.md` نصب
- `docs/fa/02-peykar-bandi.md` پیکربندی
- `docs/fa/03-bahrebardari.md` بهره‌برداری
- `docs/fa/04-moshtari.md` مشتری
- `docs/fa/05-toshe-dahande.md` توسعه‌دهنده
- `docs/fa/video-script.md` متن ویدیو
- `docs/raastichin/listing.md` متن مارکت

ویدیوی واقعی باید روی دامنه محصول ضبط شود (خارج از این ریپو).
