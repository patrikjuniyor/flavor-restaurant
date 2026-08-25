# Hooks reference — flavor-core

Text domain: `flavor-core`.

## Actions

| Hook | Args | When |
|---|---|---|
| `flavor_core_booted` | `Plugin $plugin` | After all Phase-1 modules registered |
| `flavor_core_kitchen_ticket_created` | `int $ticket_id, array $row` | First insert of a kitchen ticket |
| `flavor_core_kitchen_status_changed` | `int $ticket_id, string $from, string $to` | Legal status transition |

## Filters

| Hook | Value | Notes |
|---|---|---|
| `flavor_core_setting` | mixed, `$key` | Override a single setting at read time |
| `flavor_core_get_setting` | mixed, `$key` | Alternate read path used internally |
| `flavor_core_kitchen_view` | `string $path` | Theme can swap kitchen PHP markup |

## REST

Namespace: `flavor/v1`

| Method | Route | Auth |
|---|---|---|
| GET | `/branches` | public |
| GET | `/branches/{id}` | public |
| GET | `/menu` | public, `Cache-Control: public, max-age=30` |
| GET | `/context` | public (cookie) |
| POST | `/context` | `X-WP-Nonce: wp_rest` |
| GET | `/kitchen/tickets` | `flavor_manage_kitchen` |
| POST | `/kitchen/tickets/{id}/status` | `flavor_manage_kitchen` |

## Capabilities

`flavor_manage_settings`, `flavor_manage_all_branches`, `flavor_manage_branch`, `flavor_manage_kitchen`, `flavor_manage_reservations`, `flavor_create_phone_order`, `flavor_confirm_payment`, `flavor_view_reports`, `flavor_manage_loyalty`, `flavor_manage_sms`.

## Template / theme contract

The theme may:

- enqueue a skin on `body.flavor-kitchen-kiosk`
- call `apply_filters( 'flavor_core_kitchen_view', $path )`
- consume `flavor/v1/menu`

The theme must not write to `wp_flavor_*` tables.
