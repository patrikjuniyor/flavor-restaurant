# Push Notifications — Production Setup (FCM & APNs)

Payload contract `flavor-mobile-push@1` and delivery architecture for
`flavor-core` (server) + `flavor_mobile` (Flutter).

## Architecture

```
Server event ──► NotificationHub.send() ──► dispatch_push()
                                                │ platform-partition, REAL delivery
                                   ┌────────────┴────────────┐
                        PushNotificationService::send_fcm()      send_apns()
                        (HTTP v1 API, OAuth2 service              (HTTP/2, AuthKey .p8,
                         account, RS256 JWT)                       ES256 JWT)
                                                │
                            stale tokens (UNREGISTERED / 410) ──► deactivated (rotation cleanup)
flutter main() ─► Firebase.initializeApp() ─► NotificationService
      ├── requestPermission()           ├── onForegroundMessage ─► flutter_local_notifications
      ├── getToken() (+ getAPNSToken)   ├── onMessageOpenedApp ─┐
      ├── onTokenRefresh → re-register  ├── getInitialMessage ──┤ DeepLinkService
      └── register → POST /auth/device  └── background handler ─┘ (flavor:// deep links)
```

Registration is **authenticated only** (`POST/DELETE /flavor/v*/auth/device`,
401 for anonymous callers); tokens are bound to the authenticated account,
unregistration is ownership-checked (IDOR), and `logout(all_devices=true)`
deactivates every push device of the user.

## Server configuration (never commit real values)

### FCM — HTTP v1 service account (recommended, env or option)

| Source | Meaning |
|---|---|
| `FLAVOR_FCM_SERVICE_ACCOUNT_JSON` (env) | Raw service-account JSON |
| `FLAVOR_FCM_SERVICE_ACCOUNT_PATH` (env) **or** option `flavor_fcm_service_account_path` | Absolute path to the JSON file, OUTSIDE the web root |

The file is a Google **service account key** with role *Firebase Cloud Messaging API Admin*,
created in *Firebase console → Project settings → Service accounts → Generate new private key*.

> ⚠️ The legacy `FLAVOR_FCM_SERVER_KEY` (`/fcm/send`) endpoint was
> decommissioned by Google on 2024-06-21 and is no longer supported.

### APNs — token-based (separate from FCM, env or options)

| Source | Meaning |
|---|---|
| `FLAVOR_APNS_AUTH_KEY_PATH` / option `flavor_apns_auth_key_path` | Path to `AuthKey_<KEY_ID>.p8`, OUTSIDE the web root |
| `FLAVOR_APNS_KEY_ID` | 10-char key id from Apple Developer → Keys |
| `FLAVOR_APNS_TEAM_ID` | 10-char Apple team id |
| `FLAVOR_APNS_BUNDLE_ID` | e.g. `com.flavor.restaurant` (per brand) |
| `FLAVOR_APNS_ENV` | `production` (default) or `sandbox` |

Create the key in *Apple Developer → Certificates, Identifiers & Profiles →
Keys → Apple Push Notifications service*.

Only **paths and identifiers** may live in the database options; key material
never does. If no provider is configured, delivery degrades gracefully to
`missing_credentials` with zero HTTP attempts (verified by tests).

## Flutter configuration per brand (never commit real files)

| File | Location | Purpose |
|---|---|---|
| `google-services.json` | `mobile/android/app/` | FCM client config (Android) |
| `GoogleService-Info.plist` | `mobile/ios/Runner/` | FCM client config (iOS); added via Xcode / generated project |
| `Runner.entitlements` | Xcode capabilities | Add **Push Notifications** capability (`aps-environment`) |
| Background Modes | Xcode capabilities | Enable `Background fetch` + `Remote notifications` |

These files are brand-specific artifacts produced during white-label
provisioning; they are git-ignored (see `.gitignore`). `Firebase.initializeApp()`
auto-loads them natively. When absent, the app starts normally and push is
a no-op (guard in `main.dart`).

The app version sent during registration comes from
`--dart-define=FLAVOR_APP_VERSION=x.y.z` (set by CI; default `1.0.0`).

## Payload contract — `flavor-mobile-push@1`

Server → client data fields:

| `type` | Fields | Client deep link |
|---|---|---|
| `order_status` | `order_id`, `status`, `click_action=flavor://order/<id>` | `/order-tracking` |
| `reservation_status` | `reservation_id`, `status`, `click_action` | `/reservation-history` |
| `promo` | `dish_id` | `/dish-detail` |

Parsing rules in `NotificationPayloadParser`: a `flavor://` `click_action`
wins; otherwise the link is synthesized from typed fields; non-`flavor`
schemes are rejected. Navigation goes through `DeepLinkService` with the
existing auth-guard (`AuthBridge` provides the current auth state).

## Token rotation & logout cleanup

- Registration is an **upsert** (`REPLACE`) keyed by `(device_token, platform)`;
  identical token re-registration is idempotent and refreshes `last_seen_at`,
  `app_version`, `is_active=1`.
- Rotation: the client listens to `onTokenRefresh`, registers the NEW token,
  THEN unregisters the superseded one (server deactivates the stale row on the
  next provider error too — so both paths converge).
- A token registered by account A that later registers under account B is
  **re-bound** automatically (device ownership change).
- Logout: client `onLogout()` (DELETE device) + server-side revoke of all
  devices on `token/revoke(all_devices=true)`.

## Tests

- PHP: `php flavor-core/tests/run-e2e-integration.php` — suite 13 covers
  auth enforcement, re-binding, IDOR, real FCM/APNs delivery paths with
  ephemeral test credentials, stale-token deactivation, missing-credential
  degradation, logout cleanup, and the observability hook contract.
- Flutter: `flutter test mobile/test/unit/notification_payload_test.dart`
  — payload parsing (incl. hostile strings), order/reservation navigation
  destinations, token rotation, logout idempotence, failure tolerance.
