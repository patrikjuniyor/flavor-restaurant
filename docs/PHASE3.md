# Phase 3 — what landed

Shipped in `0.3.0`.

## Reservations

- Jalali month grid on the «رزرو میز» page template
- Slots from branch hours + table capacity (pooled by section)
- Buffer / duration in settings
- Online booking starts as `pending`; walk-in / phone as `confirmed`
- Admin day calendar, status buttons, no-show counter
- SMS confirm + hourly reminder (~2 hours before)

REST: `GET /calendar`, `GET /reservations/slots`, `POST /reservations`

## Menu scheduling

- Default windows (صبحانه / نهار / شام / دیرهنگام)
- Per-branch override in **زمان‌بندی منو**
- Products with `_flavor_schedule` hide (or show “available at”) outside the window

## Availability

- Admin **موجودی لحظه‌ای** + kitchen REST already in Phase 2
- Bumps `menu_version`

## Discounts

- Extra fields on WooCommerce coupons: Jalali expiry, branch IDs, first-order
- `POST /flavor/v1/coupon` from the cart drawer

## Phone orders

- **سفارش تلفنی**: look up mobile, recent orders, add items + modifiers, COD/card, zone address
- Goes through official `CheckoutService` with `source=phone`

## Loyalty

- Points per N تومان, redeem rate, stamp card (N stamps → free item flag)
- Earned when kitchen marks `completed` (or WC completed)
- Admin search + manual adjust
- Exposed on `GET /me`

After update: visit Permalinks once if reservation pages 404 (no new rewrite required for booking; cron registers on `init`).
