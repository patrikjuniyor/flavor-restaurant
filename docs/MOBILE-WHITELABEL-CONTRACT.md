# Mobile White-Label Contract — `flavor-mobile-branding@1`

Canonical configuration schema shared by the WordPress mobile configuration API
(`flavor-core/includes/Mobile/MobileConfigManager.php`) and the Flutter brand
provisioning tool (`mobile/scripts/provision_brand.py`). One schema, both sides.

- **Producer:** `GET /wp-json/flavor/v1/mobile/config` →
  `MobileConfigManager::get_ci_provision_payload()` (`data` payload of the REST
  envelope). The payload carries `"$schema": "flavor-mobile-branding@1"`.
- **Consumers:** CI workflow `build-mobile.yml` runs
  `provision_brand.py --config-url <config endpoint>`; local canonical brand
  files live in `mobile/assets/branding/*.json`.
- **Unknown keys** are ignored by the provisioning tool (**ignored-with-warning**);
  invalid required values **reject the build** (exit code 2, no files written
  past the failure point).

## Canonical schema (v1)

```json
{
  "$schema": "flavor-mobile-branding@1",
  "app_name": "رستوران پدیده شاندیز",
  "app_identifier": "com.flavor.shandiz",
  "tenant_id": "shandiz_group",
  "version_name": "2.4.0",
  "version_code": 240,
  "api_base_url": "https://shandiz.flavor.restaurant/wp-json/flavor/v1",
  "default_branch_id": 1,
  "assets": {
    "logo_url": "https://…/logo.png",
    "icon_url": "https://…/icon.png",
    "splash_url": "https://…/splash.png"
  },
  "branding": {
    "primary_color": "#B71C1C",
    "secondary_color": "#D32F2F",
    "accent_color": "#FFA000",
    "font_family": "Vazirmatn",
    "border_radius": 12.0
  },
  "contact": {
    "phone": "021-22000001",
    "email": "support@shandiz.flavor.restaurant",
    "website": "https://shandiz.flavor.restaurant"
  },
  "legal": {
    "privacy_policy": "https://shandiz.flavor.restaurant/privacy",
    "terms": "https://shandiz.flavor.restaurant/terms"
  }
}
```

The server additionally emits `has_fcm` (bool) and `server_timestamp` (unix) —
informational, ignored by provisioning.

### Deep link configuration (App Links / Universal Links)

Optional canonical key `deep_links.domains` (array of explicit tenant
hostnames, no wildcards); when omitted, domains are derived from the
`api_base_url` host. The provisioning tool uses them to (re)write:

| Target | Result |
|---|---|
| `AndroidManifest.xml` autoverify intent-filter | `<data android:host="{domain}" .../>` per domain |
| `ios/Runner/Runner.entitlements` | `applinks:{domain}` Associated Domains |
| `assets/deeplinks/generated/assetlinks.json` | from `assetlinks.template.json`; SHA-256 cert fingerprint from env `FLAVOR_ANDROID_CERT_FINGERPRINT` (CI secret) |
| `assets/deeplinks/generated/apple-app-site-association` | from template; team id from env `FLAVOR_IOS_TEAM_ID` |

Release build requirements (signing, store artifacts, artifact gates) are
specified in `docs/MOBILE-RELEASE-CHECKLIST.md`.

| Field | Type | Required | Validation (tool rejects on violation) |
|---|---|---|---|
| `app_name` | string | yes | non-empty, ≤100 chars, no control characters |
| `app_identifier` | string | yes | dot-separated segments, each segment matches `[a-zA-Z][a-zA-Z0-9_]*`, ≥2 segments, ≤255 chars |
| `tenant_id` | string | yes | `^[a-z0-9][a-z0-9_\-]{0,63}$` |
| `version_name` | string | yes | semantic `MAJOR.MINOR.PATCH` (+ optional `-prerelease`) |
| `version_code` | int | yes | 1 … 2_100_000_000 (Google Play max) |
| `api_base_url` | string | yes | `http(s)` URL, host required, no whitespace/quotes |
| `default_branch_id` | int | yes | ≥ 0 |
| `assets.logo_url` / `icon_url` / `splash_url` | string | no (default `""`) | same URL rule (or empty) |
| `branding.primary_color` / `secondary_color` / `accent_color` | string | no (defaults) | `#[0-9a-fA-F]{6}` |
| `branding.font_family` | string | no (default `Vazirmatn`) | `[\w\- ]{1,64}` |
| `branding.border_radius` | number | no (default 12) | 0 … 200 |
| `contact.phone` / `contact.email` | string | no (defaults) | text ≤200 chars; email must look like an email when present |
| `contact.website` / `legal.privacy_policy` / `legal.terms` | string | no (default `""`) | URL rule (or empty) |

## Legacy → canonical mapping

The provisioning tool still accepts the deprecated flat shape and migrates it
with explicit deprecation warnings (canonical keys always win):

| Legacy (flat) | Canonical (nested) |
|---|---|
| `package_name` | `app_identifier` |
| `restaurant_id` | `tenant_id` |
| `primary_color`, `secondary_color`, `accent_color`, `font_family`, `border_radius` | `branding.*` |
| `support_phone`, `support_email`, `website_url` | `contact.phone`, `contact.email`, `contact.website` |
| `privacy_policy_url`, `terms_url` | `legal.privacy_policy`, `legal.terms` |
| `logo_url`, `icon_url`, `splash_url` | `assets.*` |

## Android: stable native package architecture

- `applicationId` in `mobile/android/app/build.gradle` **is** provisioned per
  brand (from `app_identifier`).
- The Gradle `namespace` and the Kotlin source tree are an **architectural
  constant**: `com.flavor.restaurant`, matching
  `android/app/src/main/kotlin/com/flavor/restaurant/MainActivity.kt`.
  The tool *never* rewrites them and **fails the build** if they have drifted
  from `com.flavor.restaurant`. This avoids the anti-pattern of per-brand
  Kotlin package renames (moving source files at build time makes builds
  non-reproducible and breaks tooling that assumes a fixed entry point).
- `android:label` in `AndroidManifest.xml` is set to `app_name` (XML-escaped).
- `versionCode` / `versionName` are pinned from `version_code` /
  `version_name`.

## iOS

- **Bundle identifier:** `mobile/ios/Runner.xcodeproj/project.pbxproj` is
  generated by `flutter create` inside CI and is not committed. When present,
  the tool rewrites `PRODUCT_BUNDLE_IDENTIFIER` to `app_identifier`; when
  absent it logs a notice and the identifier must be passed by CI
  (e.g. `flutter create --org ... --project-name ...` / build settings).
  `CFBundleIdentifier` in `Info.plist` stays `$(PRODUCT_BUNDLE_IDENTIFIER)`.
- **Display name:** `CFBundleDisplayName` is provisioned to `app_name`
  (plist-escaped) and `CFBundleURLName` to `app_identifier`.

## Escaping rules (applied before writing any file)

| Target | Rule |
|---|---|
| Dart string literals (`brand_tokens.g.dart`) | single-quoted literal; escape `\`, `'`, `$`; strip control chars |
| Android XML attributes (`AndroidManifest.xml`) | XML attribute escaping (`&quot; &apos; &amp; &lt; &gt;`); strip control chars |
| plist strings (`Info.plist`) | plist = XML; same escaping, then verified parseable |
| Gradle strings (`build.gradle`) | escape `\`, `"`, neutralize `$` GString interpolation |
| Kotlin | **never written** (stable native package) |

## Testing & regeneration

- Contract tests: `python3 mobile/scripts/test_provision_brand.py`
  (stdlib only — covers all committed brand files, the server-generated
  payload fixture, legacy aliases, malicious strings, and syntactic validity
  of every generated file).
- Server-payload fixture regeneration (real PHP code path, mock WP env):
  `php flavor-core/tests/export-mobile-config.php > mobile/assets/branding/fixtures/server_payload.json`
