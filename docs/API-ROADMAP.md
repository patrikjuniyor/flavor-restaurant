# Flavor REST API V2 — Omnichannel Specification & Roadmap

## 1. Design Principles & Standards

The Flavor REST API V2 is built from the ground up to provide a **production-ready, high-performance API** powering Web, Mobile (iOS/Android), Kitchen tablets, and POS devices.

### Key Architectural Standards:
1. **Namespace & Versioning**:
   - V1 (Legacy Web Theme): `/wp-json/flavor/v1/*` (maintains full backward compatibility).
   - V2 (Omnichannel Production): `/wp-json/flavor/v2/*`.
2. **Stateless Authentication**:
   - Supports both `Authorization: Bearer <jwt_access_token>` (Mobile/POS) and `X-WP-Nonce: <nonce>` (Web cookies).
3. **Cart Token Header (`X-Cart-Token`)**:
   - Decoupled from PHP sessions. Mobile apps send a UUIDv4 token allowing guest cart persistence across app restarts.
4. **Standard Envelope & Status Codes**:
   - Success responses return clean JSON payloads with HTTP 200/201.
   - Error responses follow **RFC 7807** problem details:
   ```json
   {
     "code": "flavor_invalid_param",
     "message": "شماره موبایل وارد شده معتبر نیست.",
     "data": { "status": 400, "field": "mobile" }
   }
   ```
5. **Currency Contract**:
   - Internal storage and API raw amounts: **Rials** (Integer `BIGINT`).
   - Formatted strings: **Toman** (Persian/Latin digits based on admin settings).
6. **Date & Time**:
   - System standard: ISO 8601 UTC (`2026-09-18T12:00:00Z`).
   - Jalali representations provided in structured metadata objects:
   ```json
   {
     "gregorian": "2026-09-18",
     "jalali": { "year": 1405, "month": 6, "day": 27, "formatted": "۲۷ شهریور ۱۴۰۵" }
   }
   ```

---

## 2. API Inventory & Endpoint Catalog (V2)

### 2.1 Authentication & Profile (`/flavor/v2/auth`, `/flavor/v2/me`)

| Method | Route | Access | Description |
|---|---|---|---|
| `POST` | `/flavor/v2/auth/otp/request` | Public (Rate-limited) | Requests a 5-digit OTP via SMS (or Dev log). |
| `POST` | `/flavor/v2/auth/otp/verify` | Public (Rate-limited) | Verifies OTP code; returns JWT access token, refresh token, and user profile. |
| `POST` | `/flavor/v2/auth/token/refresh`| Public | Exchanges valid refresh token for a new short-lived access token. |
| `POST` | `/flavor/v2/auth/logout` | Authenticated | Revokes current refresh token and clears session. |
| `GET` | `/flavor/v2/me` | Authenticated | Returns customer profile, loyalty summary, active order count. |
| `PUT` | `/flavor/v2/me` | Authenticated | Updates customer display name, email, birthday. |
| `GET` | `/flavor/v2/me/addresses` | Authenticated | List saved customer delivery addresses. |
| `POST` | `/flavor/v2/me/addresses` | Authenticated | Create a new delivery address with Lat/Lng and neighborhood. |
| `DELETE`| `/flavor/v2/me/addresses/{id}`| Authenticated | Delete saved delivery address. |
| `GET` | `/flavor/v2/me/orders` | Authenticated | List paginated historical orders for current user. |
| `GET` | `/flavor/v2/me/loyalty` | Authenticated | Detailed loyalty points ledger and stamp card status. |

---

### 2.2 Branches & Delivery Geo-Zones (`/flavor/v2/branches`, `/flavor/v2/zones`)

| Method | Route | Access | Description |
|---|---|---|---|
| `GET` | `/flavor/v2/branches` | Public (Cached) | Returns all active branches with GPS coords, address, phone, and opening status. |
| `GET` | `/flavor/v2/branches/{id}` | Public (Cached) | Full branch details, working hours, sections, and active delivery zones. |
| `POST` | `/flavor/v2/zones/check` | Public | Validates customer coordinate/neighborhood against branch zones; returns fee & ETA. |

---

### 2.3 Catalog & Smart Search (`/flavor/v2/menu`, `/flavor/v2/search`)

| Method | Route | Access | Description |
|---|---|---|---|
| `GET` | `/flavor/v2/menu` | Public (Cached 30s) | Paginated menu by `branch_id`. Includes modifiers, prep time, calories, dietary tags, schedules, availability. |
| `GET` | `/flavor/v2/categories` | Public (Cached) | List product categories with thumbnail and item counts. |
| `GET` | `/flavor/v2/products/{id}` | Public | Detailed product view with full modifier groups and nutrition info. |
| `GET` | `/flavor/v2/search` | Public (Rate-limited) | Typo-tolerant Persian smart search with highlighting and facets. |
| `GET` | `/flavor/v2/search/suggest` | Public | Fast prefix autocomplete for search bar. |
| `GET` | `/flavor/v2/search/popular` | Public | Trending search queries. |

---

### 2.4 Cart & Checkout (`/flavor/v2/cart`, `/flavor/v2/checkout`)

| Method | Route | Access | Description |
|---|---|---|---|
| `GET` | `/flavor/v2/cart` | Public (Session/Token)| Get current cart items, modifiers breakdown, totals, and fees. |
| `POST` | `/flavor/v2/cart/items` | Public (Session/Token)| Add product to cart with selected modifier IDs and special instructions. |
| `PUT` | `/flavor/v2/cart/items/{key}` | Public (Session/Token)| Update line item quantity (qty=0 removes item). |
| `DELETE`| `/flavor/v2/cart` | Public (Session/Token)| Clear entire cart. |
| `POST` | `/flavor/v2/cart/coupon` | Public (Session/Token)| Apply discount coupon with Jalali & branch validation. |
| `DELETE`| `/flavor/v2/cart/coupon` | Public (Session/Token)| Remove applied coupon. |
| `GET` | `/flavor/v2/checkout/options` | Public | Returns available payment gateways for current order mode (`dine_in`, `takeaway`, `delivery`). |
| `POST` | `/flavor/v2/checkout` | Public (Rate-limited) | Creates WooCommerce order, initiates payment gateway (or offline mode), creates kitchen ticket. |

---

### 2.5 Order Tracking & Lifecycle (`/flavor/v2/orders`)

| Method | Route | Access | Description |
|---|---|---|---|
| `GET` | `/flavor/v2/orders/{id}/track` | Public (Phone token) | Live status tracker for customer (shows stepper: `received` -> `preparing` -> `ready` -> `dispatched` -> `delivered`). |
| `POST` | `/flavor/v2/orders/{id}/cancel`| Authenticated / Staff | Request order cancellation before preparation starts. |

---

### 2.6 Dine-In QR Tables (`/flavor/v2/tables`)

| Method | Route | Access | Description |
|---|---|---|---|
| `GET` | `/flavor/v2/tables/bind/{token}`| Public | Resolves QR token to branch & table number, binds session. |
| `GET` | `/flavor/v2/tables` | Public | List active tables for a branch (used for manual table selection). |
| `POST` | `/flavor/v2/tables/call-waiter` | Public (Bound table) | Sends instant push notification / alert to waiter dashboard. |
| `POST` | `/flavor/v2/tables/request-bill`| Public (Bound table) | Requests bill payment at table. |

---

### 2.7 Table Reservations (`/flavor/v2/reservations`)

| Method | Route | Access | Description |
|---|---|---|---|
| `GET` | `/flavor/v2/reservations/calendar` | Public | Returns Jalali month calendar grid with open/closed days. |
| `GET` | `/flavor/v2/reservations/slots` | Public | Calculates available time slots for a specific date, party size, and section. |
| `POST` | `/flavor/v2/reservations` | Public (Rate-limited) | Books a table reservation (fires confirmation SMS). |
| `GET` | `/flavor/v2/reservations` | Staff (`manage_res`)| Admin list of reservations filtered by branch and date. |
| `POST` | `/flavor/v2/reservations/{id}/status` | Staff (`manage_res`)| Updates reservation status (`confirmed`, `seated`, `completed`, `cancelled`, `no_show`). |

---

### 2.8 Kitchen Display System (KDS) & Waiter POS (`/flavor/v2/kitchen`, `/flavor/v2/pos`)

| Method | Route | Access | Description |
|---|---|---|---|
| `GET` | `/flavor/v2/kitchen/tickets` | Staff (`kitchen`) | Returns live tickets grouped by lane (`new`, `preparing`, `ready`). |
| `GET` | `/flavor/v2/kitchen/stream` | Staff (`kitchen`) | Server-Sent Events (SSE) stream for real-time ticket creation & updates. |
| `POST` | `/flavor/v2/kitchen/tickets/{id}/status` | Staff (`kitchen`)| Transitions ticket state with audit timestamp. |
| `POST` | `/flavor/v2/kitchen/tickets/{id}/item` | Staff (`kitchen`)| Marks individual food item ready. |
| `POST` | `/flavor/v2/kitchen/availability` | Staff (`kitchen`)| Instantly 86 / restock menu item for a branch. |
| `POST` | `/flavor/v2/pos/phone-order` | Staff (`phone_order`)| Cashier creates phone/walk-in order with customer auto-lookup. |

---

### 2.9 Mobile Push Device Registration (`/flavor/v2/notifications`)

| Method | Route | Access | Description |
|---|---|---|---|
| `POST` | `/flavor/v2/notifications/device` | Public / Auth | Registers FCM/APNs push token linked to device and user. |
| `DELETE`| `/flavor/v2/notifications/device` | Authenticated | Unregisters device token on logout. |

---

## 3. Backward Compatibility & Adapter Architecture

To guarantee that existing theme components, Elementor widgets, and external webhooks continue working without interruption:
1. `RestController.php` (`flavor/v1`) delegates internal business operations directly to the new V2 Service layer (`OrderService`, `CatalogService`, `ReservationService`).
2. Legacy endpoints preserve exact JSON response keys and status codes.
3. Upgrading to V2 does not require updating old frontend views immediately; both V1 and V2 coexist simultaneously.
