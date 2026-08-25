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
| `flavor_core_sms_providers` | `ProviderInterface[]` | Register extra SMS drivers |
| `flavor_core_sms_sent` | `array $result, $mobile, $message, $event` | After send |
| `flavor_core_sms_melipayamak` | driver result or null | Glue to Melipayamak SDK |
| `flavor_core_sms_faraz` | driver result or null | Glue to Faraz |
| `flavor_core_sms_kavenegar` | driver result or null | Glue to Kavenegar |

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
| POST | `/kitchen/tickets/{id}/item` | `flavor_manage_kitchen` |
| POST | `/kitchen/availability` | `flavor_manage_kitchen` |
| GET | `/cart` | public (session) |
| POST | `/cart/add` | `X-WP-Nonce` |
| POST | `/cart/item` | `X-WP-Nonce` |
| GET | `/checkout/options` | public |
| POST | `/checkout` | `X-WP-Nonce` |
| POST | `/auth/otp/request` | public + rate limit |
| POST | `/auth/otp/verify` | public + rate limit |
| POST | `/zones/check` | public |
| GET | `/tables` | public |
| GET | `/me` | public |

## Capabilities

`flavor_manage_settings`, `flavor_manage_all_branches`, `flavor_manage_branch`, `flavor_manage_kitchen`, `flavor_manage_reservations`, `flavor_create_phone_order`, `flavor_confirm_payment`, `flavor_view_reports`, `flavor_manage_loyalty`, `flavor_manage_sms`.

## Template / theme contract

The theme may:

- enqueue a skin on `body.flavor-kitchen-kiosk`
- call `apply_filters( 'flavor_core_kitchen_view', $path )`
- consume `flavor/v1/menu`

The theme must not write to `wp_flavor_*` tables.
