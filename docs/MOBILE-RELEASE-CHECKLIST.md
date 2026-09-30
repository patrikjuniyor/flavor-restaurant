# Mobile Release Checklist — Android & iOS

Run through this list before every white-label store submission.
CI (`build-mobile.yml`) enforces the hard requirements and FAILS LOUDLY
when they are missing.

## 1. Version code & version name

- [ ] `version_code` in the brand config is **monotonically increasing**
  per store track (Google Play rejects lower/equal codes; range 1..2100000000).
- [ ] `version_name` is **semver** (`MAJOR.MINOR.PATCH[-prerelease]`).
- [ ] Server config API and `provision_brand.py` agree on values (provisioning
  pins `pubspec.yaml` and Gradle `versionCode`/`versionName` from the same
  canonical config — `flavor-mobile-branding@1`).
- [ ] iOS build number derived from the same `version_code` (via pubspec).

## 2. Package ID (Android) / Bundle ID (iOS)

- [ ] Android `applicationId` == brand's `app_identifier` (provisioned).
- [ ] Gradle `namespace` stays at `com.flavor.restaurant` (architectural
  constant; drift fails provisioning).
- [ ] iOS `CFBundleURLName` == `app_identifier`; `PRODUCT_BUNDLE_IDENTIFIER`
  provisioned into `project.pbxproj` during CI (generated, not committed).

## 3. Signing

### Android
- [ ] Upload keystore kept OUTSIDE the repo (`FLAVOR_UPLOAD_KEYSTORE_BASE64`
  GitHub secret; `.gitignore` bans `*.keystore`, `*.jks`,
  `keystore.properties`).
- [ ] `FLAVOR_UPLOAD_STORE_PASSWORD` / `FLAVOR_UPLOAD_KEY_ALIAS` /
  `FLAVOR_UPLOAD_KEY_PASSWORD` set as secrets (masked twice in CI).
- [ ] Release APK/AAB are NEVER debug-signed (`build.gradle` throws
  `GradleException` for any `:app:*Release*` task without credentials).
- [ ] Verification: `jarsigner -verify -verbose -certs app-release.apk`
  shows the upload certificate, CI logs the signer.
- [ ] Gradle files contain no passwords; hard-coded secrets trigger review.

### iOS
- [ ] Distribution certificate (`.p12`) + provisioning profile provided as
  base64 secrets (`FLAVOR_IOS_DIST_CERT_P12_BASE64`,
  `FLAVOR_IOS_DIST_CERT_PASSWORD`, `FLAVOR_IOS_PROVISIONING_PROFILE_BASE64`,
  `FLAVOR_IOS_TEAM_ID`); missing secrets FAIL the release build.
- [ ] Temporary keychain in CI is wiped by GitHub firewalling the runner;
  no cert material reaches the repo.
- [ ] **Never assume `--no-codesign` is store-ready** — that flag was
  removed from the release path and is only acceptable for local smoke
  builds outside delivery workflows.
- [ ] `export_options.plist` (CI-generated) uses `method: app-store` and the
  provisioned Team ID; final IPA is uploaded from `build/ios/ipa/`.

## 4. Deep links

- [ ] Android App Links: `AndroidManifest.xml` intent-filter(s) contain the
  **tenant's real domain(s)** (provisioned from canonical `api_base_url`
  host or explicit `deep_links.domains`) — no `*.restaurant.com` placeholder.
- [ ] `https://<domain>/.well-known/assetlinks.json` renders from
  `mobile/assets/deeplinks/generated/assetlinks.json` with the
  release-certificate SHA-256 (`FLAVOR_ANDROID_CERT_FINGERPRINT`).
- [ ] iOS Universal Links: `Runner.entitlements` lists `applinks:<domain>`
  (provisioned); `https://<domain>/.well-known/apple-app-site-association`
  renders with `<TEAM_ID>.<bundle id>` (`FLAVOR_IOS_TEAM_ID`).
- [ ] Custom scheme `flavor://` routing covered by
  `mobile/test/unit/deep_link_test.dart`; push-payload links covered by
  `notification_payload_test.dart`.
- [ ] Real-device verification: `adb shell am start -a android.intent.action.VIEW \
  -d https://<domain>/product/1` and iOS Notes-app link tapping.

## 5. Privacy permissions

- [ ] Camera (`NSCameraUsageDescription`) — QR table codes.
- [ ] Location (`NSLocationWhenInUseUsageDescription`) — nearest branch,
  courier fee.
- [ ] Android: `ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION`,
  `POST_NOTIFICATIONS` declared in manifest.
- [ ] Push entitlements: `aps-environment` flips to `production` via the
  App Store provisioning profile in CI (dev default committed).
- [ ] Play Data Safety + App Privacy answers match the manifest/plist.

## 6. Artifact acceptance gates (CI)

- [ ] `flutter test` green before either build job proceeds.
- [ ] Provisioning suite green: `python3 mobile/scripts/test_provision_brand.py`.
- [ ] Both APK + AAB + IPA artifacts carry brand-specific ids/versions.
- [ ] Build callback to WordPress reports artifacts with SHA-256 checksums.
