# Flavor Implementation Roadmap — Commercial V2 Platform

This roadmap provides a phased, actionable execution plan for transforming **Flavor** into a premium commercial restaurant platform supporting Web, REST API V2, Android, iOS, Kitchen Kiosks, and POS devices.

---

## Phase 1: Core Decoupling, Stateless Token Auth & Cart Engine

### 1. Objective
Decouple session management from PHP cookies to enable native mobile apps, introduce stateless JWT/Bearer token authentication with OTP verification, and abstract cart management so guest carts can persist via an `X-Cart-Token` header.

### 2. Files / Modules Affected
- `flavor-core/includes/Customer/OtpAuth.php`
- `flavor-core/includes/Customer/TokenService.php` *(New)*
- `flavor-core/includes/WooCommerce/CartSession.php`
- `flavor-core/includes/WooCommerce/CheckoutService.php`
- `flavor-core/includes/API/RestStore.php`
- `flavor-core/includes/Database/Schema.php`

### 3. Database Changes
- Add table `wp_flavor_auth_tokens` (columns: `id`, `user_id`, `token_hash`, `device_id`, `device_name`, `expires_at`, `revoked_at`, `created_at`).
- Add index on `wp_flavor_otp_codes (mobile, consumed_at, expires_at)`.

### 4. API Changes
- Introduce `POST /flavor/v2/auth/otp/request` and `POST /flavor/v2/auth/otp/verify`.
- Introduce `POST /flavor/v2/auth/token/refresh` and `POST /flavor/v2/auth/logout`.
- Upgrade cart endpoints to accept `X-Cart-Token` in addition to WordPress session cookies.

### 5. Frontend Changes
- Storefront JavaScript updated to store JWT access tokens in `localStorage` for headless capabilities while preserving standard cookie compatibility.

### 6. Mobile Changes
- Foundation network layer (Dio client) configured with token interceptors and secure storage.

### 7. Risks & Mitigations
- **Risk**: Session mismatch between WooCommerce cart and JWT tokens.
- **Mitigation**: Implement `CartTokenBridge` that binds WooCommerce `WC_Cart` data to token hashes.

### 8. Testing Requirements
- Unit tests for JWT generation, validation, expiration, and refresh.
- Integration tests for guest cart item additions across multiple concurrent cart tokens.

### 9. Migration Requirements
- Seamless upgrade: existing cookie-based users remain logged in; no manual user migration required.

---

## Phase 2: REST API V2 Formalization & Real-Time Streaming

### 2.1 Objective
Build the full suite of `/flavor/v2/*` REST endpoints conforming to modern RESTful standards, ISO dates, RFC 7807 error envelopes, and replace 15-second kitchen HTTP polling with Server-Sent Events (SSE).

### 2.2 Files / Modules Affected
- `flavor-core/includes/API/V2/` *(New directory with controllers)*
  - `AuthController.php`
  - `CatalogController.php`
  - `CartController.php`
  - `CheckoutController.php`
  - `ReservationController.php`
  - `KitchenController.php`
  - `CustomerController.php`
- `flavor-core/includes/Order/KitchenStreamService.php` *(New)*
- `flavor-core/includes/Support/Rewrites.php`

### 2.3 Database Changes
- Add `channel` and `device_token` columns to `wp_flavor_sms_log` / notification tables.

### 2.4 API Changes
- Complete release of all V2 endpoints detailed in `docs/API-ROADMAP.md`.
- New SSE endpoint `GET /flavor/v2/kitchen/stream?branch_id={id}`.

### 2.5 Frontend Changes
- Kitchen dashboard JS updated to connect via `EventSource` (SSE) with automatic fallback to polling if SSE is unsupported on older browsers.

### 2.6 Mobile Changes
- Mobile repository implementations for Catalog, Search, and Live Tracking.

### 2.7 Risks & Mitigations
- **Risk**: Shared hosting web servers closing long-lived HTTP connections prematurely.
- **Mitigation**: SSE heartbeat ping every 25 seconds; graceful client reconnection with exponential backoff.

### 2.8 Testing Requirements
- Load testing SSE stream with 100 concurrent kitchen/waiter clients.
- Schema validation tests for all V2 JSON payloads.

---

## Phase 3: Web Storefront Modernization & Design Polish

### 3.1 Objective
Refine the WordPress theme frontend, enhance mobile responsiveness, upgrade the modifier bottom sheet with instant optimistic price calculation, and optimize Core Web Vitals for the 8 pre-built demos.

### 3.2 Files / Modules Affected
- `flavor/assets/js/menu.js`, `flavor/assets/js/search.js`, `flavor/assets/js/reservation.js`
- `flavor/assets/css/main.css`, `flavor/assets/css/marketing.css`, `flavor/assets/css/rtl.css`
- `flavor/template-parts/menu/*`
- `flavor/elementor/*`
- `flavor/inc/class-customizer.php`

### 3.3 Database Changes
- No schema changes; Customizer options enriched with micro-typography settings.

### 3.4 API Changes
- None (consumes `/flavor/v2/*`).

### 3.5 Frontend Changes
- Sticky category pills with smooth `IntersectionObserver`.
- Zero-CLS image containers with blur-up placeholder effects.
- Enhanced Jalali date picker with swipeable months on mobile.

### 3.6 Mobile Changes
- Sync visual tokens (colors, border radii, typography) between CSS variables and Flutter theme files.

### 3.7 Risks & Mitigations
- **Risk**: Cache invalidation issues with third-party caching plugins (LiteSpeed / WP Rocket).
- **Mitigation**: Automated cache purge hooks on menu/branch save + strict `Cache-Control` response headers.

### 3.8 Testing Requirements
- Cross-browser compatibility (Chrome, Safari iOS, Firefox, Samsung Internet).
- Lighthouse score audit: Performance >= 92, Accessibility >= 95, SEO = 100.

---

## Phase 4: Customer Flutter Mobile App (Android & iOS)

### 4.1 Objective
Deliver a production-ready, white-label Flutter application for restaurant customers supporting online ordering, branch selection, food customization, address picker on Iranian maps, online Shetab payments, live order tracking, and Jalali reservations.

### 4.2 Files / Modules Affected
- `mobile/customer_app/` *(New Flutter Project)*
  - `lib/features/auth/`
  - `lib/features/branch/`
  - `lib/features/menu/`
  - `lib/features/cart/`
  - `lib/features/checkout/`
  - `lib/features/order_tracking/`
  - `lib/features/reservation/`
  - `lib/features/loyalty/`

### 4.3 Database Changes
- None on server. Local SQLite/Hive tables on client for offline caching.

### 4.4 API Changes
- Ensure payment redirect returns custom scheme `flavor://payment-callback`.

### 4.5 Frontend / Web Changes
- Add banner / smart app banner meta tag to web theme inviting mobile visitors to download the app.

### 4.6 Mobile Changes
- Full application build, BLoC state management, Iranian Shetab payment bridge, Neshan/OpenStreetMap integration, FCM/Chabok push notifications.

### 4.7 Risks & Mitigations
- **Risk**: Apple App Store review delays or rejections for Iranian regional payment schemes.
- **Mitigation**: Support standard guest mode, in-app webviews, and alternative iOS distribution (SibApp / Direct Web-Clip PWA).

### 4.8 Testing Requirements
- End-to-end purchasing test with real Iranian sandbox payment gateways (ZarinPal sandbox).
- UI automated testing on various screen sizes (iPhone 13 mini to Galaxy S24 Ultra).

---

## Phase 5: Kitchen Display System (KDS) & Waiter POS Tablet App

### 5.1 Objective
Develop a dedicated tablet application for kitchen staff and waiters with real-time audio alerts, SLA monitoring, and direct ESC/POS Bluetooth/LAN receipt printing.

### 5.2 Files / Modules Affected
- `mobile/kds_pos_app/` *(New Flutter Tablet Project)*
- `flavor-core/includes/Receipt/EscPosPrinterService.php` *(New)*

### 5.3 Database Changes
- Add `printed_at` and `assigned_waiter_id` to `wp_flavor_kitchen_tickets`.

### 5.4 API Changes
- Introduce `/flavor/v2/pos/orders` and `/flavor/v2/tables/call-waiter` listener.

### 5.5 Mobile / Tablet Changes
- Fullscreen KDS Kanban interface, color-coded SLA timers, ESC/POS 80mm thermal receipt generator, audio chimes.

### 5.6 Risks & Mitigations
- **Risk**: Bluetooth printer disconnects in humid/hot kitchen environments.
- **Mitigation**: Auto-reconnect watchdog service + print retry queue.

### 5.7 Testing Requirements
- Hardware testing with Sunmi POS terminals, Xprinter Bluetooth/LAN 80mm thermal printers.

---

## Phase 6: Multi-Tenant SaaS Foundations & Commercial Packaging

### 6.1 Objective
Prepare Flavor for commercial distribution as a standalone theme/plugin package on markets (e.g. راست‌چین / Raastchin) and provide an architectural path toward multi-tenant Cloud SaaS.

### 6.2 Files / Modules Affected
- `flavor-core/includes/Licensing/` *(New)*
- `flavor/inc/class-demo-importer.php`
- `docs/*`

### 6.3 Database Changes
- Automated migration runner with rollback capabilities.

### 6.4 API Changes
- SaaS tenant header handling (`X-Tenant-ID`) for future multi-tenant routing.

### 6.5 Testing Requirements
- Clean install testing on shared cPanel / DirectAdmin / Ubuntu LAMP hosting.
- Demo import verification for all 8 business models.
