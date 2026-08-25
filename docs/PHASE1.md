# Phase 1 — what landed

Shipped in `0.1.0`:

- Plugin singleton, PSR-4 autoloader (no Composer required on the host), activator / deactivator / uninstall
- 14 custom tables, documented in `DATABASE.md`
- Roles and capabilities
- Branch CPT + default branch seeder
- Table registry, bulk create, QR landing rewrite (`/branch/{slug}/table/{n}/`)
- Currency layer (storage Rial / display Toman, admin-switchable)
- Simple product food tab (modifiers, prep time, calories, dietary, schedule)
- Cart item meta + server-side extra price
- Kitchen ticket repository (SoT) + WooCommerce sync hooks
- REST `flavor/v1` (branches, menu, context, kitchen)
- Admin menu, settings, kitchen skeleton, `/kitchen-dashboard/` kiosk
- Theme skeleton: RTL-first, bundled Vazirmatn, menu / reservation / branches / tracking templates, Schema.org
- Iran provinces dataset and mobile normalizer

Intentionally not in 0.1.0 (later phases):

- Full modifier bottom sheet + live price
- Custom checkout that still calls WC gateways
- OTP + SMS adapters
- Reservation calendar (Jalali picker)
- QR PNG/SVG/PDF and A6 cards
- Delivery zone checker UI
- Receipt print window
- Phone-order UI
- 8 demos and the importer
- Loyalty
