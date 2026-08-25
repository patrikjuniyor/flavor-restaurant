# Phase 2 — what landed

Shipped in `0.2.0`:

## QR

- PNG / SVG / PDF download per table
- Logo overlay from the custom logo
- Bulk A6 print cards (`چاپ کارت‌های A6`)
- Landing `/branch/{slug}/table/{n}/` already bound the dine-in session in Phase 1

## Three order modes

- Mode switcher in the cart drawer (سالن / بیرون‌بر / ارسال)
- Context persisted in WC session + cookie
- Checkout validates table (dine-in) and delivery zone (ارسال)
- Official WC checkout + `process_payment()` so ZarinPal / IDPay keep working
- Offline gateways: `flavor_pay_at_counter`, `flavor_cod`, `flavor_card_on_delivery`

## Kitchen

- Live kanban with items, modifiers, urgency colors
- One-tap status, item-level ready, audio beep, mode filter, fullscreen
- Kitchen / cashier 80mm receipts at `/kitchen-receipt/{id}/{kitchen|cashier}/`

## Tables

- Single + bulk create, toggle, delete
- QR actions on the same screen

## Delivery zones

- Admin UI: neighborhood list or radius
- `ZoneChecker` (haversine, neighborhood fold, optional polygon)
- REST `POST /flavor/v1/zones/check`
- Fee + minimum order enforced at checkout

## OTP + SMS

- `POST /flavor/v1/auth/otp/request` and `/verify`
- Rate limit 3 / 10 minutes, HMAC-stored codes
- Auto user `09xxxxxxxxx@otp.flavor.local`
- Provider interface: Dev, Melipayamak, Faraz, Kavenegar
- Templates with `{placeholders}`
- Kitchen status / new ticket fire SMS events

## Storefront

- Modifier bottom sheet + live extra
- Cart REST (`/cart`, `/cart/add`, `/cart/item`)
- One-page checkout inside the drawer

After update: visit **Settings → Permalinks → Save** once (or deactivate/reactivate the plugin) so `/kitchen-receipt/` and QR landing flush.
