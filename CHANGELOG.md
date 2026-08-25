# Changelog

All notable changes to **رستوران مستقیم** (Flavor theme + Flavor Core plugin) are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-08-25

### Added

- QR PNG/SVG/PDF download, logo overlay, A6 print cards
- Three order modes in the cart drawer with official WooCommerce checkout
- Offline gateways: pay at counter, cash on delivery, card on delivery
- Kitchen kanban (items, audio, filters, fullscreen, 80mm receipts)
- Delivery zones (neighborhood + radius) with checkout enforcement
- Mobile OTP login and SMS provider adapters (Dev / Melipayamak / Faraz / Kavenegar)
- Cart and checkout REST (`/flavor/v1/cart`, `/checkout`, `/auth/otp/*`, `/zones/check`)
- Modifier bottom sheet with live extras

### Changed

- Plugin and theme version 0.2.0
- Rewrite flush on version bump (`/kitchen-receipt/`)

## [0.1.0] - 2026-08-25

### Added

- Monorepo bootstrap for the commercial product sold as two packages.
- `flavor-core` plugin architecture: singleton bootstrap, PSR-4 autoloader, activator, deactivator, uninstall.
- Complete custom table schema (kitchen tickets as source of truth, tables, reservations, zones, availability, loyalty, OTP, SMS log, schedules).
- Custom roles: Super-admin capabilities, Branch Manager, Kitchen Staff, Cashier.
- Branch custom post type with structured meta and REST fields.
- Dining-table custom table manager and QR token model (generation UI comes in Phase 2).
- WooCommerce bridge: Toman/Rial currency layer (admin-configurable), simple-product modifier data model.
- Kitchen ticket repository (indexed operational store, synced from WooCommerce orders).
- REST API namespace `flavor/v1` with public / cookie / capability route groups.
- Theme `flavor` skeleton: RTL-first, bundled Vazirmatn, page templates, plugin-missing notice.
- Developer documentation: database schema, architecture decisions, hooks map.

### Notes

- Phase 1 foundation only. Ordering UI, kitchen dashboard views, OTP SMS, reservations UX, and the 8 demos land in later phases.
