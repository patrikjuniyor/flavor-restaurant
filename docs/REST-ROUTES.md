# REST API Routes — Authoritative Registration & Permission Matrix

> Source of truth: `flavor-core/includes/API/RestController.php::register()`.
> Route-map uniqueness is enforced by e2e suite 12 ("zero namespace+method+route duplicates").

## 1. Registration architecture

| Layer | Responsibility |
|---|---|
| Modular controllers (`*Controller.php`) | **Authoritative** route definitions. Each is registered once per namespace (`flavor/v1`, `flavor/v2`) by `RestController::register()`. v1 is a byte-identical alias of v2 kept for backward compatibility — every modular route exists in both namespaces exactly once. |
| `RestStore` (`/cart/add`, `/checkout`, …) | **Legacy v1-only** compatibility layer. Registers only routes that no modular controller handles. Registering a route that already exists elsewhere is forbidden (dead code — WordPress dispatches the first matching handler, so the later shadowed registration silently never runs). |
| `RestExperience` (`/calendar`, `/coupon`, `/staff/customer`) | Same legacy status. |

### Duplicates removed in this change (were dead/shadowed code)

All of the following were registered **twice** in `flavor/v1` — once by the modular controller (which served all live traffic) and once by the legacy sub-router (dead code). The legacy copies were deleted; clients see zero behavior change because the modular handler already answered these paths:

| Route | Authoritative handler |
|---|---|
| `GET flavor/v1/cart` | `CartController::get_cart` |
| `POST flavor/v1/auth/otp/request` | `AuthController::otp_request` |
| `POST flavor/v1/auth/otp/verify` | `AuthController::otp_verify` |
| `GET flavor/v1/reservations/slots` | `ReservationController::get_slots` |
| `POST flavor/v1/reservations` | `ReservationController::book_table` |
| `GET flavor/v1/reservations` | `ReservationController::list_reservations` |

### Legacy v1-only routes kept (no modular equivalent, still supported)

`POST /cart/add`, `POST /cart/item`, `POST /checkout`, `GET /checkout/options`, `POST /zones/check`, `GET /tables`, `POST /kitchen/tickets/{id}/item`, `POST /kitchen/availability`, `GET /me`, `GET /calendar`, `POST /coupon`, `GET /staff/customer` — all marked `@deprecated`; migrate callers to the modular v1/v2 endpoints.

## 2. Permission matrix — every mutating endpoint

Legend: **public** = `__return_true` (input is still sanitized + rate-limited); **bearer** = valid `Authorization: Bearer` token required (`require_authenticated`); **nonce** = valid `X-WP-Nonce` (`wp_rest`); **cap** = WP capability check.

| Endpoint | Namespace | Permission | Notes |
|---|---|---|---|
| `POST /auth/otp/request` | v1, v2 | public | Rate-limited (`RateLimit::guard`). |
| `POST /auth/otp/verify` | v1, v2 | public | Creates account + issues tokens. |
| `POST /auth/token/refresh` | v1, v2 | public | Rotates on a valid refresh token. |
| `POST /auth/token/revoke` | v1, v2 | bearer | Revokes caller's tokens (all devices). |
| `PUT /auth/me` | v1, v2 | bearer | |
| `POST /auth/device` / `DELETE /auth/device` | v1, v2 | public | Device token upsert/delete (unauthenticated, keyed by device token). |
| `POST /cart/items`, `PUT/DELETE /cart/items/{key}` | v1, v2 | public | Guest carts via `X-Cart-Token`/session. |
| `DELETE /cart`, `POST /cart/coupon`, `DELETE /cart/coupon` | v1, v2 | public | |
| `POST /cart/loyalty` | v1, v2 | bearer | Loyalty points are user-scoped. |
| `POST /orders` | v1, v2 | public | Guests need a valid `cart_token`; server revalidates branch/table/zone/products; idempotency key supported. |
| `POST /orders/{id}/cancel` | v1, v2 | public* | *Ownership enforced: bearer owner or order `guest_token` (IDOR guard in handler). |
| `POST /orders/{id}/reorder` | v1, v2 | public* | Same ownership rule. |
| `POST /reservations` | v1, v2 | public | Guest token issued for follow-up operations. |
| `POST /reservations/{id}/cancel` | v1, v2 | public* | *Ownership: bearer or reservation `guest_token`. |
| `POST /dishes/{id}/reviews` | v1, v2 | public | Rate-limited, content sanitized. |
| `POST /mobile/config`, `POST /mobile/builds/trigger` | v1, v2 | cap `manage_options` | Admin only. |
| `POST /mobile/builds/callback` | v1, v2 | public | **But** HMAC `X-Flavor-Signature` required (raw-body HMAC-SHA256 with `ci_webhook_secret`) + timestamp freshness + state machine. |
| `POST /webhooks` , `PUT/DELETE /webhooks/{id}`, `POST /webhooks/{id}/test` | v1, v2 | cap `flavor_manage_webhooks` or `manage_options` | Admin-only management. URL must pass SSRF validation; events must pass the explicit allowlist. |
| `POST /context` | v1, v2 | nonce | Storefront session context (branch/mode/table). |
| `POST /kitchen/tickets/{id}/status` | v1, v2 | cap `flavor_manage_kitchen` or `manage_options` + branch scope | Branch IDOR check in handler. |

**Read-only endpoints** of note: `GET /orders`, `GET /reservations`, `GET /reservations/my` require bearer; `GET /orders/{id}`, `GET /orders/{id}/track`, `GET /reservations/{id}` are public in the route but enforce the same guest-token/ownership guard in the handler; `GET /webhooks*` are cap-restricted; everything else under `/branches`, `/menu`, `/dishes`, `/categories`, `/settings/app-bootstrap`, `/mobile/config` (GET), `/system/health` is public read.

## 3. Webhook pipeline contract

- **Events** (allowlist, dot-names, never sanitized with `sanitize_key`): `order.created`, `order.updated`, `order.completed`, `order.cancelled`, `reservation.created`, `reservation.updated`, `customer.created`, `loyalty.points_awarded`. Subscription lists accept these plus `*`.
- **Hook alignment**: listeners subscribe to the hooks the application actually emits — `flavor_core_kitchen_ticket_created`, `flavor_core_kitchen_status_changed`, `flavor_core_reservation_created`, `flavor_core_reservation_status_changed`, `flavor_core_otp_verified` (fires `customer.created` only when `_flavor_just_created` user meta is set), `flavor_core_loyalty_points_awarded`.
- **Delivery is asynchronous**: `dispatch()` persists audit rows (`status = queued`) and schedules `flavor_core_webhook_process_queue`; no HTTP runs inside the checkout/reservation request. `process_queue()` drains with one attempt per row (write maps to `delivered` / `failed` / `skipped`).
- **Security**: target URLs must be public http(s), non-localhost, non-private/reserved IPs (literal and DNS-resolved), no URL credentials; redirects are never followed (3xx = failure); timeout 5s; `wp_safe_remote_post()` is used when available; every payload is signed with `X-Flavor-Signature` (HMAC-SHA256 over the exact raw body) and carries `X-Flavor-Event` + `X-Flavor-Delivery-ID`.
- **Secrets**: returned exactly once on `POST /webhooks` (create), never by any GET endpoint (`secret_configured: true` is exposed instead).
- **Admin test endpoint** (`POST /webhooks/{id}/test`, event `system.ping`) remains synchronous by design — it is an operator action, not the event pipeline.
