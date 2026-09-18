# Flavor Mobile Roadmap — Cross-Platform Strategy & Engineering Specs

## 1. Technology Selection & Rationale

To deliver premium, commercial-grade mobile applications for both **iOS and Android** with shared business logic and minimal maintenance overhead, **Flutter (Dart 3.x)** is selected as the core mobile framework.

### Why Flutter over React Native / Native?
1. **Pixel-Perfect RTL & Persian Typography**:
   - Flutter renders its own UI via Impeller/Skia, eliminating Android font clipping, baseline mismatch, and BiDi layout glitches that frequently affect React Native.
   - Built-in seamless support for Persian typography (**Vazirmatn**, **Dana**, **IRANSans**).
2. **High-Performance Animations**:
   - 60/120 FPS fluid animations for modifier bottom sheets, floating cart bars, and category sticky tab transitions.
3. **Hardware & POS Compatibility**:
   - Direct integration with Sunmi, Pax, and standard ESC/POS Bluetooth/LAN thermal receipt printers for the Kitchen/Waiter Tablet app.
4. **Resilience to Network Fluctuations**:
   - Advanced offline-first caching with **Hive/Isar**, ensuring customers can browse the menu even during unstable mobile internet conditions.

---

## 2. Mobile Architecture Blueprint (Clean Architecture + BLoC)

```
lib/
├── app/
│   ├── config/              # App themes, API constants, brand skin tokens
│   ├── routes/              # GoRouter configuration & deep link handlers
│   └── di/                  # Dependency Injection (get_it + injectable)
├── core/
│   ├── error/               # Failure models & Exception handlers
│   ├── network/             # Dio HTTP client, JWT Refresh Interceptor, SSL Pinning
│   ├── storage/             # SecureStorage (JWT) & Hive (Cart/Cache)
│   ├── utils/               # Jalali calendar converter, Persian number formatter, currency
│   └── widgets/             # Core UI components (Buttons, Inputs, Shimmers, Dialogs)
└── features/
    ├── auth/                # OTP request, OTP verify, token lifecycle
    ├── branch/              # Branch list, GPS distance resolver, active branch selector
    ├── menu/                # Menu catalog, categories, product details, smart search
    ├── modifier/            # Interactive item customizer bottom sheet
    ├── cart/                # Cart state management, calculations, coupon engine
    ├── checkout/            # Order mode toggle, address picker, payment gateway bridge
    ├── order_tracking/      # Live order tracking stepper, map view, push handler
    ├── reservation/         # Jalali date picker, slot calculation, booking flow
    ├── loyalty/             # Points balance, stamp card visual milestone
    └── kitchen_pos/         # (KDS App) Kanban board, ESC/POS thermal printing
```

---

## 3. Iran-Specific Mobile Solutions

### 3.1 Payment Gateway Bridge & Deep Linking
- **Challenge**: Iranian payment gateways (ZarinPal, IDPay, Asan Pardakht, Mellat) operate via Web redirects (Shetap Shaparak network).
- **Solution**:
  1. The app invokes `POST /flavor/v2/checkout` with `payment_method`.
  2. The server creates the order and returns a secure gateway payment URL.
  3. The mobile app opens an in-app browser session via `flutter_custom_tabs` with a deep-link callback schema: `flavor://checkout/verify?order_id=X&status=success`.
  4. The app intercepts the redirect, verifies payment status via REST API, and displays the animated receipt and live order tracking screen.

### 3.2 Map & Delivery Address Selection
- Seamless map picker using **flutter_map** with Iranian vector tile providers (**Neshan / CedarMaps / OpenStreetMap**).
- Reverse geocoding resolves pinned GPS coordinates to province, city, and neighborhood names automatically.

### 3.3 Sanction-Resilient Push Notifications
- Dual-provider notification service:
  - Primary: **Firebase Cloud Messaging (FCM)** for standard global delivery.
  - Resilient Iranian Fallback: **Chabok / Najva** integration for domestic network environments.
  - Critical Fallback: Automatic fallback to **Transactional SMS** via Flavor Core SMS facade.

---

## 4. Customer Mobile Application Feature Scope

### Screen 1: Splash & Branch Selector
- Automatically detects user GPS location and highlights the nearest active branch.
- Displays branch opening hours, delivery zones, and dine-in availability.

### Screen 2: Interactive Menu & Smart Search
- Sticky category navigation bar with smooth anchor scrolling.
- Real-time typo-tolerant smart search with highlighted Persian terms.
- Live out-of-stock badges (synced instantly with kitchen 86-ing).
- Meal schedule indicators (e.g. "Breakfast menu available until 11:30").

### Screen 3: Food Customizer (Bottom Sheet)
- Interactive size options (Small / Medium / Large).
- Multi-select toppings and sides with real-time price summation.
- Special instructions input box for the chef.

### Screen 4: Persistent Cart & Mode Switcher
- Floating bottom cart bar visible across the entire application.
- Three order modes: **سالن (Dine-in)**, **بیرون‌بر (Takeaway)**, **ارسال (Delivery)**.
- Delivery zone fee calculator and minimum order validation.

### Screen 5: Live Order Tracker
- Visual step-by-step progress: `دریافت شد (Received)` -> `در حال آماده‌سازی (Preparing)` -> `آماده تحویل (Ready)` -> `پیک در راه (Dispatched)` -> `تحویل شد (Delivered)`.
- Live timer and estimated arrival countdown.

### Screen 6: Jalali Table Reservation
- Native Jalali month calendar picker (فروردین to اسفند).
- Dynamic party size selector and section preference (indoor, outdoor, window).
- Time slot capacity calculator with instant confirmation SMS.

### Screen 7: Profile, Saved Addresses & Loyalty
- One-tap OTP login.
- Address book with custom tags (Home, Work, Other) and pinned GPS coordinates.
- Visual Stamp Card with progress animations towards free reward items.

---

## 5. Kitchen Display System (KDS) & Waiter POS Tablet App

A dedicated tablet-optimized application designed for Android tablets, iPads, and commercial POS devices (Sunmi V2/T2, Pax):

1. **Kanban Order Columns**:
   - `New Orders (سفارش‌های جدید)`: Plays distinct audio alarm, flashes yellow/red based on SLA.
   - `In Preparation (در حال پخت)`: Item-by-item checkbox completion.
   - `Ready for Pickup (آماده تحویل)`: Alerts waiters / delivery couriers.
2. **Thermal Receipt Printing (ESC/POS)**:
   - Direct Bluetooth, USB, and LAN thermal receipt printing without needing a PC.
   - Separate print formats: 80mm Kitchen Prep Slip (with modifiers & notes) and 80mm Cashier Bill.
3. **Instant 86-ing (Out of Stock Toggle)**:
   - Chefs can tap any menu item to mark it unavailable for the day, immediately invalidating cache across web and customer mobile apps.

---

## 6. Build, CI/CD & Distribution Strategy

| Platform / Market | Target Artifact | Distribution Channel |
|---|---|---|
| **Android (Global)** | Android App Bundle (AAB) | Google Play Store |
| **Android (Iran)** | Signed Universal APK | Cafe Bazaar, Myket, Direct Download |
| **iOS (Global)** | IPA (Signed via Apple Developer) | Apple App Store / TestFlight |
| **iOS (Iran)** | Ad-Hoc / Enterprise / Web-Clip PWA | SibApp, Bazarche, Direct PWA |
| **POS Devices** | Direct APK / Sunmi Store | Commercial hardware deployment |
