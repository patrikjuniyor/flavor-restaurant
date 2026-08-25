# Architecture — Flavor / رستوران مستقیم

## Packages

```
                    ┌─────────────────────────────┐
   Browser / Tablet │  flavor (theme)             │
                    │  templates, CSS, vanilla JS │
                    └─────────────┬───────────────┘
                                  │ REST flavor/v1
                    ┌─────────────▼───────────────┐
                    │  flavor-core (plugin)       │
                    │  business logic + schema    │
                    └───────┬─────────────┬───────┘
                            │             │
                     ┌──────▼──────┐ ┌────▼────────────┐
                     │ WooCommerce │ │ Custom tables   │
                     │ products,   │ │ kitchen tickets │
                     │ orders,     │ │ tables, zones,  │
                     │ coupons,    │ │ reservations,   │
                     │ gateways    │ │ OTP, loyalty    │
                     └─────────────┘ └─────────────────┘
```

The theme never writes operational data. If the merchant switches themes, orders, reservations, QR bindings and branch settings survive.

## Namespaces

| Package | Namespace | Text domain |
|---|---|---|
| Plugin | `FlavorCore\` | `flavor-core` |
| Theme | `Flavor\` | `flavor` |

Autoloading is PSR-4, implemented in `flavor-core/includes/Autoloader.php` so the plugin runs **without Composer on production hosts**.

## Request classes

### Public (rate-limited, no login)

- `GET /flavor/v1/menu`
- `GET /flavor/v1/branches`
- `GET /flavor/v1/branches/{id}`
- `GET /flavor/v1/reservations/slots`

### Cookie / checkout nonce

- Cart read/write
- Checkout (creates a WC order, then a kitchen ticket)
- OTP request / verify

### Capability-gated

- Kitchen ticket transitions — `flavor_manage_kitchen`
- Availability toggles — `flavor_manage_kitchen` or `flavor_manage_branch`
- Phone order — `flavor_create_phone_order`
- Reports — `flavor_view_reports`

Public menu endpoints intentionally do **not** require a logged-in nonce. A blanket “all REST needs nonce” rule would break first paint of the menu page.

## Currency

`FlavorCore\WooCommerce\Currency` is the only place that formats money.

- Storage default: **Rial** (integer, no decimals) in custom tables.
- WooCommerce order totals stay in whatever WC currency the store uses.
- Display default: **Toman**, Persian or Latin digits (admin setting).
- Gateways (ZarinPal, IDPay, …) receive the amount WooCommerce already computed. We do not reimplement gateway math.
- Admin can switch storage/display units. Conversion is `×10` / `÷10` and happens only in this layer.

## Catalog: simple product + modifiers

There is no custom WC product type in V1. Food items are `simple` products. Modifier groups live in product meta:

```
_flavor_modifiers = [
  { type: size|topping|cook|removal, name, price_modifier_rial, is_default, sort }
]
_flavor_prep_time
_flavor_calories
_flavor_dietary   // vegetarian, vegan, spicy, gluten_free, dairy_free, nut_free
_flavor_schedule  // breakfast, lunch, dinner, late_night
```

Cart item meta stores the selected modifiers. Server recalculates price; the client total is never trusted.

## Orders vs kitchen tickets

WooCommerce is the financial source of truth (items, totals, payment, refunds, HPOS).

`{$wpdb->prefix}flavor_kitchen_tickets` is the **operational** source of truth:

- branch, table, order mode
- kitchen status (`new → preparing → ready → completed`)
- customer mobile snapshot
- delivery zone snapshot
- timestamps for SLA coloring

A ticket is inserted **once**, idempotently, after:

- `woocommerce_payment_complete` for online payments
- `woocommerce_checkout_order_processed` when the method is pay-at-counter / COD / card-on-delivery

Never query tickets with `get_post_meta( $order_id )`. Always use `FlavorCore\Order\KitchenTicketRepository` and `wc_get_order()`.

## Kitchen surfaces

Both views call the same repository and REST controller:

1. `wp-admin` page `flavor-kitchen` (cashiers / managers)
2. Front rewrite `/kitchen-dashboard/` (kiosk / tablet)

The theme may enqueue a skin stylesheet. It must not register the rewrite or the capability check.

## Caching contract

| Resource | Cache |
|---|---|
| Public menu JSON | Public, short TTL, key includes `branch_id + menu_version` |
| Branch list | Public, 5 minutes |
| Cart, OTP, checkout | `private, no-store` |
| Kitchen poll | `private, no-store` |
| Availability mutation | Bumps `menu_version` for that branch |

On activation we store suggested LiteSpeed / WP Rocket exclusion paths in an option so the setup guide can print them.

## Multi-branch tenancy

Every operational row has `branch_id`.

- Super Admin: all branches
- `flavor_branch_manager`: only assigned branch IDs (`_flavor_managed_branches` user meta)
- Kitchen / Cashier: same assignment, narrower caps

A default branch is created on first activation so single-location restaurants never see an empty selector.

## SMS and OTP

`FlavorCore\SMS\ProviderInterface` + concrete drivers (Melipayamak, Faraz, Kavenegar) in Phase 2.

If no provider is configured, OTP codes are written to `flavor_sms_log` with status `dev` and shown to shop managers — so local/demo installs work without an SMS contract.

## What the theme is allowed to do

- Render markup and enqueue CSS/JS
- Call REST
- Print Schema.org JSON-LD from plugin helpers
- Style `/kitchen-dashboard/`

Forbidden: custom tables, order status changes, payment, OTP send, QR generation.

## PHP / WP constraints

- PHP 8.0+ (typed properties, union types, `str_starts_with`)
- No front-end jQuery requirement
- WooCommerce HPOS compatible (order CRUD only)
- All SQL via `$wpdb->prepare`
- All output escaped; all input sanitized
