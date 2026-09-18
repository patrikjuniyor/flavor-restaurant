# Flavor V2 Target Architecture — Commercial Omnichannel Restaurant Platform

## 1. Executive Summary & Architectural Vision

Flavor is evolving from a localized WordPress theme/plugin into an **enterprise-grade, omnichannel commercial restaurant platform**. The platform powers:
1. **High-converting, customizable restaurant web storefronts** (RTL-first, 8 brand skins, Elementor & Gutenberg integration).
2. **Unified REST API V2** serving Web, Android, iOS, Kitchen Kiosks, and Waiter POS tablets.
3. **Cross-Platform Customer Mobile Applications (Android & iOS)** built with Flutter, featuring deep linking, Iranian Shetab payment gateways, map address picker, and Jalali reservations.
4. **Dedicated Kitchen & Waiter Applications** with real-time ticket streaming, SLA coloring, and thermal Bluetooth/LAN printing (ESC/POS).
5. **Decoupled, modular domain core** that preserves backward compatibility with existing WooCommerce and custom operational database entities.

---

## 2. High-Level Architecture Diagram (C4 Container View)

```
                                  ┌─────────────────────────────────────────────────────────┐
                                  │                     CLIENT LAYER                        │
                                  └─────────────────────────────────────────────────────────┘
         ┌─────────────────────────┐      ┌─────────────────────────┐      ┌─────────────────────────┐
         │ Customer Web Storefront │      │  Customer Mobile Apps   │      │ Kitchen / POS Tablet    │
         │   (Theme / Vanilla JS)  │      │    (Flutter iOS/Android)│      │  (Web Kiosk & Flutter)  │
         └────────────┬────────────┘      └────────────┬────────────┘      └────────────┬────────────┘
                      │                                │                                │
                      │ HTTP (Cookies / Nonce)         │ HTTPS (Bearer JWT / Cart Token)│ HTTPS (Bearer JWT)
                      │                                │                                │
                      ▼                                ▼                                ▼
         ┌──────────────────────────────────────────────────────────────────────────────────────────┐
         │                             API GATEWAY & ROUTING LAYER                                  │
         │  - flavor/v1 (Legacy Compatibility Layer)                                                │
         │  - flavor/v2 (Omnichannel REST API: Auth, Catalog, Cart, Orders, Booking, Kitchen, POS)  │
         │  - SSE / WebSocket Stream Server (Real-time order events, kitchen updates)               │
         └─────────────────────────────────────────────┬────────────────────────────────────────────┘
                                                       │
                                                       ▼
         ┌──────────────────────────────────────────────────────────────────────────────────────────┐
         │                            FLAVOR CORE BUSINESS DOMAINS                                  │
         ├────────────────────┬────────────────────┬────────────────────┬───────────────────────────┤
         │  Catalog & Menu    │ Cart & Order State │ Table & QR Engine  │ Reservation & Jalali Cal  │
         ├────────────────────┼────────────────────┼────────────────────┼───────────────────────────┤
         │  Delivery & Zones  │ Loyalty & Ledger   │ SMS & OTP Gateway  │ Notification & Push Hub   │
         └────────────────────┴──────────┬─────────┴────────────────────┴───────────────────────────┘
                                         │
                 ┌───────────────────────┴───────────────────────┐
                 ▼                                               ▼
  ┌───────────────────────────────┐               ┌───────────────────────────────┐
  │      FINANCIAL TRUTH          │               │      OPERATIONAL TRUTH        │
  │  WooCommerce HPOS Tables      │               │  Custom InnoDB Tables (14+)   │
  │  (Orders, Coupons, Gateways)  │               │  (Tickets, Tables, Zones, etc)│
  └───────────────────────────────┘               └───────────────────────────────┘
```

---

## 3. Separation of Concerns: Financial Truth vs. Operational Truth

To ensure commercial stability and compatibility with existing WooCommerce plugins (e.g., ZarinPal, PayPing, Torob, accounting bridges), the architecture strictly decouples responsibilities:

| Domain | Source of Truth | Storage Mechanism | Responsibilities |
|---|---|---|---|
| **Orders & Invoices** | WooCommerce (HPOS) | `wp_wc_orders`, `wp_wc_order_items` | Financial integrity, total price, tax, discount calculations, gateway callbacks, refunds. |
| **Kitchen Tickets** | Flavor Core | `wp_flavor_kitchen_tickets`, `items` | Kitchen workflow (`new` -> `preparing` -> `ready` -> `completed`), table numbers, SLA timers, line-item kitchen status. |
| **Physical Tables & QR**| Flavor Core | `wp_flavor_tables` | Table tokens, capacity, indoor/outdoor sections, QR codes, dine-in bindings. |
| **Table Reservations** | Flavor Core | `wp_flavor_reservations` | Jalali booking dates, party size, section allocations, automated SMS reminders, no-show metrics. |
| **Delivery Logistics** | Flavor Core | `wp_flavor_delivery_zones` | Geo-fencing (Radius / Neighborhoods / Polygons), min orders, delivery fees. |
| **Customer Identity** | WordPress + Flavor | `wp_users` + `wp_flavor_otp_codes` | Mobile OTP authentication, customer address book (`wp_flavor_customer_addresses`), JWT sessions. |
| **Loyalty & Rewards** | Flavor Core | `wp_flavor_loyalty_ledger` | Points transactions, stamp card milestones, free item rewards. |

---

## 4. Unified Authentication Architecture (Web & Mobile)

Flavor V2 implements a **Dual-Mode Authentication Framework**:

```
 ┌────────────────┐         ┌────────────────────────┐         ┌─────────────────────────┐
 │ Client Device  ├────────►│ POST /flavor/v2/auth/  ├────────►│ Generates & Signs:      │
 │ (Web / Mobile) │ Mobile  │      otp/request       │         │ 1. Short-lived Access   │
 └────────────────┘ Number  └───────────┬────────────┘         │    Token (JWT, 15 mins) │
                                        │                      │ 2. Long-lived Refresh   │
                                        ▼                      │    Token (DB, 60 days)  │
                            ┌────────────────────────┐         │ 3. Cart Token           │
                            │ POST /flavor/v2/auth/  ├────────►│ 4. WP Session Cookie    │
                            │      otp/verify        │         │    (Web only)           │
                            └────────────────────────┘         └─────────────────────────┘
```

### Key Auth Rules:
1. **Mobile Clients**: Send `Authorization: Bearer <jwt_access_token>`. When expired, exchange the refresh token at `POST /flavor/v2/auth/token/refresh`.
2. **Web Clients**: Continue receiving WordPress auth cookies (`wp_set_auth_cookie`) and REST nonces (`X-WP-Nonce`), while also supporting JWT tokens for headless/SPA components.
3. **Guest Cart Tokens**: Unauthenticated mobile users receive a deterministic `X-Cart-Token` (UUIDv4) stored in device secure storage, allowing multi-day offline-first cart persistence without a forced login.
4. **Staff Roles**: Capability-based checks (`flavor_manage_kitchen`, `flavor_manage_branch`, `flavor_create_phone_order`) work seamlessly across both Cookie sessions and JWT claims.

---

## 5. Real-Time Event & Notification Hub

### The Polling Bottleneck in V1
Flavor V1 used a 15-second client-side `setInterval` polling `GET /flavor/v1/kitchen/tickets`. In busy restaurants, this causes:
- Database overload with repeated SELECT queries.
- Latency in order alerts (up to 15 seconds delay for sound/receipts).

### V2 Real-Time Streaming Architecture
1. **Server-Sent Events (SSE) / WebSocket Fallback**:
   - Primary endpoint: `GET /flavor/v2/kitchen/stream?branch_id=X` (SSE stream over HTTP/2).
   - Hooked to `flavor_core_kitchen_ticket_created` and `flavor_core_kitchen_status_changed`.
   - Heartbeat ping every 25 seconds; automatic reconnect with backoff.
2. **Customer Push Notifications (Mobile Apps)**:
   - Device registration: `POST /flavor/v2/notifications/device` (stores FCM/APNs token, platform, user_id, branch_id).
   - Triggered on order status transitions (`preparing`, `ready`, `dispatched`, `completed`).
   - Iran-resilient fallback: Integrated with Iranian push providers (Chabok / Najva) or automated fallback to transactional SMS.

---

## 6. Multi-Branch Isolation & Data Architecture

```
                       ┌─────────────────────────────────┐
                       │        Super Administrator      │
                       │   (Access to all branches)      │
                       └────────────────┬────────────────┘
                                        │
           ┌────────────────────────────┴────────────────────────────┐
           ▼                                                         ▼
┌─────────────────────────────────┐       ┌─────────────────────────────────┐
│       Tehran Branch (#101)      │       │      Shiraz Branch (#102)       │
├─────────────────────────────────┤       ├─────────────────────────────────┤
│ - Staff: Cashier_1, Chef_1      │       │ - Staff: Cashier_2, Chef_2      │
│ - Tables: T1..T20 (Tokens)      │       │ - Tables: T1..T15 (Tokens)      │
│ - Delivery: 5 Zones (Polygon)   │       │ - Delivery: 3 Zones (Radius)    │
│ - Menus: Custom Schedule        │       │ - Menus: Custom Schedule        │
│ - Instant Availability Overrides│       │ - Instant Availability Overrides│
└─────────────────────────────────┘       └─────────────────────────────────┘
```

1. **Context Enforcement**: Every REST request validates the `branch_id` against user capabilities (`Roles::can_access_branch()`).
2. **Catalog Scope**: Product pricing and categories are shared globally, but **instant availability**, **meal schedules**, and **branch-specific modifiers** are resolved per branch.
3. **Cache Invalidation**: Each branch maintains an independent `menu_version_{branch_id}` option. Updating an item's availability in Branch A bumps only Branch A's cache version, keeping other branches cached.

---

## 7. Media & CDN Architecture

1. **Local & High-Performance Media Pipeline**:
   - Iranian hosting environments often face international CDN throttling.
   - All product images are processed on upload into optimized WebP variants:
     - `flavor_thumb_square` (180x180 WebP) - for cart drawer & search results.
     - `flavor_card_medium` (600x400 WebP) - for menu cards.
     - `flavor_hero_large` (1200x800 WebP) - for modals and marketing heroes.
2. **Mobile Vector Assets**:
   - All app icons, UI glyphs, dietary badges, and placeholder illustrations are bundled locally within the Flutter client binary (zero network request overhead).

---

## 8. Scalability & Future Multi-Tenant SaaS Evolution

To expand from single-restaurant installations into a **SaaS Restaurant Cloud Platform**:
- **Phase 1 (Single Tenant / Multi-Branch)**: Current architecture with strict `branch_id` foreign keys and role isolation.
- **Phase 2 (WordPress Multisite / Subdomains)**: Each restaurant brand runs on its own blog ID with dedicated tables and branding, while sharing common plugin binaries and API handlers.
- **Phase 3 (Enterprise Multi-Tenant SaaS)**: Tenant isolation at API gateway level with `tenant_id` database routing, Redis cluster caching, and separate billing/subscription microservices.
