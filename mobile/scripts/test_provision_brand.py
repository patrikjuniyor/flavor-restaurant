#!/usr/bin/env python3
"""
Contract tests for the WordPress <-> Flutter white-label provisioning tool.

Validates the canonical schema flavor-mobile-branding@1
(docs/MOBILE-WHITELABEL-CONTRACT.md):

* All committed brand files (default, shandiz, nayeb) provision successfully.
* The server-generated payload fixture (exported from the real PHP
  MobileConfigManager::get_ci_provision_payload()) provisions successfully.
* Legacy flat schemas still work via the deprecation shim.
* Invalid package names, URLs, colors and versions are REJECTED (exit 2).
* Malicious strings (quotes, newlines, XML/plist/Dart/Groovy injection) are
  escaped or rejected such that every generated file remains syntactically
  valid (XML/plist parse with stdlib parsers; Gradle/Dart structurally sane).
* Android keeps the stable native package (namespace + Kotlin files never
  change); only applicationId varies.

Run:  python3 mobile/scripts/test_provision_brand.py
Stdlib only — no third-party dependencies.
"""

import io
import json
import os
import plistlib
import re
import shutil
import sys
import tempfile
import unittest
import xml.etree.ElementTree as ET
from contextlib import redirect_stdout

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
REPO_ROOT = os.path.abspath(os.path.join(SCRIPT_DIR, os.pardir, os.pardir))
MOBILE_ROOT = os.path.join(REPO_ROOT, "mobile")
BRANDING_DIR = os.path.join(MOBILE_ROOT, "assets", "branding")
FIXTURE_PATH = os.path.join(BRANDING_DIR, "fixtures", "server_payload.json")

sys.path.insert(0, SCRIPT_DIR)
import provision_brand as pb  # noqa: E402


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def copy_mobile_project(dst: str) -> None:
    shutil.copytree(MOBILE_ROOT, dst)


def read(root: str, rel: str) -> str:
    with open(os.path.join(root, rel), encoding="utf-8") as f:
        return f.read()


def run_provision(mobile_dir: str, config_path: str) -> tuple:
    """Invoke the tool's main() and return (exit_code, combined stdout)."""
    buf = io.StringIO()
    with redirect_stdout(buf):
        code = pb.main(["--config-file", config_path, "--mobile-root", mobile_dir])
    return code, buf.getvalue()


def write_config(tmp: str, payload: dict) -> str:
    path = os.path.join(tmp, "cfg.json")
    with open(path, "w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False)
    return path


def load_fixture(name: str) -> dict:
    path = name if os.path.isabs(name) else os.path.join(BRANDING_DIR, name)
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def assert_xml_valid(testcase, content: str, what: str):
    try:
        ET.fromstring(content)
    except ET.ParseError as e:
        testcase.fail(f"{what}: not well-formed XML after provisioning: {e}")


def assert_plist_valid(testcase, root: str) -> dict:
    path = os.path.join(root, "ios", "Runner", "Info.plist")
    with open(path, "rb") as f:
        try:
            return plistlib.load(f)
        except Exception as e:
            testcase.fail(f"Info.plist: not a valid plist after provisioning: {e}")


def assert_dart_sane(testcase, content: str):
    balances = {"{": "}", "(": ")", "[": "]"}
    depth = {k: 0 for k in balances}
    in_str = False
    i = 0
    while i < len(content):
        ch = content[i]
        if in_str:
            if ch == "\\":
                i += 2
                continue
            if ch == "'":
                in_str = False
        else:
            if ch == "'":
                in_str = True
            elif ch in depth:
                depth[ch] += 1
            elif ch in balances.values():
                for o, c in balances.items():
                    if ch == c:
                        depth[o] -= 1
                        testcase.assertGreaterEqual(depth[o], 0, "Duplicate/unbalanced closing brace in Dart")
        i += 1
    testcase.assertFalse(in_str, "Unterminated single-quoted string in generated Dart")
    for o in balances:
        testcase.assertEqual(depth[o], 0, f"Unbalanced '{o}' in generated Dart")
    # No unescaped interpolation leak: every '$' inside a single-quoted
    # literal must be backslash-escaped. (No double-quoted strings generated.)
    testcase.assertNotRegex(
        content,
        r"'.(?<![\\'])\$[a-zA-Z\{]",
        "Unescaped '$' interpolation leaked into a Dart string literal",
    )


def assert_gradle_sane(testcase, content: str, app_id: str, v_name: str, v_code: int):
    testcase.assertEqual(content.count("{"), content.count("}"), "Unbalanced braces in build.gradle")
    m = re.search(r'applicationId\s+"([^"]+)"', content)
    testcase.assertIsNotNone(m, "applicationId missing in build.gradle")
    testcase.assertEqual(m.group(1), app_id)
    m = re.search(r'namespace\s+"([^"]+)"', content)
    testcase.assertEqual(m.group(1), pb.STABLE_NATIVE_PACKAGE, "namespace must stay the stable package")
    m = re.search(r'versionCode\s+(\d+)', content)
    testcase.assertEqual(int(m.group(1)), v_code)
    m = re.search(r'versionName\s+"([^"]+)"', content)
    testcase.assertEqual(m.group(1), v_name)


def baseline_config() -> dict:
    return {
        "app_name": "Test Restaurant",
        "app_identifier": "com.acme.test",
        "tenant_id": "acme_test",
        "version_name": "1.2.3",
        "version_code": 42,
        "api_base_url": "https://api.acme.example/wp-json/flavor/v1",
        "default_branch_id": 0,
    }


# ---------------------------------------------------------------------------
# Contract: good payloads (canonical)
# ---------------------------------------------------------------------------

class GoodPayloadTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp(prefix="flavor-provision-test-")
        self.addCleanup(shutil.rmtree, self.tmp, True)
        self.project = os.path.join(self.tmp, "mobile")
        copy_mobile_project(self.project)
        # Snapshot the Kotlin entry point — it must never be touched.
        self.ktrel = os.path.join(
            "android", "app", "src", "main", "kotlin",
            *pb.STABLE_NATIVE_PACKAGE.split("."), "MainActivity.kt")
        self.kotlin_before = read(self.project, self.ktrel)

    def _check_project(self, app_id, app_name, v_name, v_code, tenant, api_url):
        gradle = read(self.project, os.path.join("android", "app", "build.gradle"))
        assert_gradle_sane(self, gradle, app_id, v_name, v_code)

        manifest_rel = os.path.join("android", "app", "src", "main", "AndroidManifest.xml")
        manifest = read(self.project, manifest_rel)
        assert_xml_valid(self, manifest, "AndroidManifest.xml")
        root_el = ET.fromstring(manifest)
        app_el = root_el.find("application")
        label = app_el.get("{http://schemas.android.com/apk/res/android}label")
        self.assertEqual(label, app_name)

        plist = assert_plist_valid(self, self.project)
        self.assertEqual(plist["CFBundleDisplayName"], app_name)
        url_types = plist.get("CFBundleURLTypes") or []
        self.assertTrue(url_types, "CFBundleURLTypes missing from Info.plist")
        self.assertEqual(url_types[0]["CFBundleURLName"], app_id)

        # Deep links: tenant host must be provisioned everywhere, no leftovers.
        expected_host = api_url.split("/")[2]
        self.assertNotIn("*.restaurant.com", manifest)
        self.assertIn(f'android:host="{expected_host}"', manifest)
        ent_path = os.path.join(self.project, "ios", "Runner", "Runner.entitlements")
        with open(ent_path, "rb") as f:
            ent = plistlib.load(f)
        domains = ent.get("com.apple.developer.associated-domains") or []
        self.assertIn(f"applinks:{expected_host}", domains)

        assetlinks = read(self.project, os.path.join("assets", "deeplinks", "generated", "assetlinks.json"))
        al = json.loads(assetlinks)
        self.assertEqual(al[0]["target"]["package_name"], app_id)
        aasa = read(self.project, os.path.join("assets", "deeplinks", "generated", "apple-app-site-association"))
        self.assertIn(f'"appID": "000000TEAM.{app_id}"', aasa)

        dart = read(self.project, os.path.join("lib", "core", "constants", "brand_tokens.g.dart"))
        assert_dart_sane(self, dart)
        self.assertIn(f"tenantId = '{tenant}'", dart)
        self.assertIn(f"apiBaseUrl = '{api_url}'", dart)

        self.assertIn(f"version: {v_name}+{v_code}", read(self.project, "pubspec.yaml"))
        self.assertEqual(self.kotlin_before, read(self.project, self.ktrel),
                         "MainActivity.kt must never be modified by provisioning")

    def test_default_branding(self):
        cfg = os.path.join(BRANDING_DIR, "default_branding.json")
        code, out = run_provision(self.project, cfg)
        self.assertEqual(code, 0, out)
        self._check_project("com.flavor.restaurant", "Flavor Restaurant", "1.0.0", 1,
                            "default_tenant", "https://restaurant.example.com/wp-json/flavor/v2")

    def test_brand_shandiz(self):
        cfg = os.path.join(BRANDING_DIR, "brand_shandiz.json")
        code, out = run_provision(self.project, cfg)
        self.assertEqual(code, 0, out)
        self._check_project("com.flavor.shandiz", "رستوران پدیده شاندیز", "2.4.0", 240,
                            "shandiz_group", "https://shandiz.flavor.restaurant/wp-json/flavor/v2")
        # Non-default applicationId but the stable namespace must be untouched.
        gradle = read(self.project, os.path.join("android", "app", "build.gradle"))
        self.assertIn('applicationId "com.flavor.shandiz"', gradle)
        self.assertIn(f'namespace "{pb.STABLE_NATIVE_PACKAGE}"', gradle)

    def test_brand_nayeb(self):
        cfg = os.path.join(BRANDING_DIR, "brand_nayeb.json")
        code, out = run_provision(self.project, cfg)
        self.assertEqual(code, 0, out)
        self._check_project("com.flavor.nayeb", "رستوران نائب ساعی", "3.1.0", 310,
                            "nayeb_grand", "https://nayeb.flavor.restaurant/wp-json/flavor/v2")

    def test_server_generated_payload(self):
        """Payload exported from the real PHP
        MobileConfigManager::get_ci_provision_payload() must provision cleanly."""
        payload = load_fixture(FIXTURE_PATH)
        nested = payload["data"]["data"] if "data" in payload and "data" in payload["data"] else payload.get("data", payload)
        cfg_path = write_config(self.tmp, payload)
        code, out = run_provision(self.project, cfg_path)
        self.assertEqual(code, 0, out)
        self._check_project(
            nested["app_identifier"], nested["app_name"],
            nested["version_name"], nested["version_code"],
            nested["tenant_id"], nested["api_base_url"])
        dart = read(self.project, os.path.join("lib", "core", "constants", "brand_tokens.g.dart"))
        self.assertIn(f"privacyPolicyUrl = '{nested['legal']['privacy_policy']}'", dart)
        self.assertIn(f"termsUrl = '{nested['legal']['terms']}'", dart)


# ---------------------------------------------------------------------------
# Contract: legacy alias compatibility
# ---------------------------------------------------------------------------

class LegacyAliasTests(unittest.TestCase):
    def test_legacy_flat_schema_normalizes(self):
        legacy = {
            "app_name": "Legacy Restaurant",
            "package_name": "com.acme.legacy",
            "restaurant_id": "acme_legacy",
            "version_name": "0.9.0",
            "version_code": 9,
            "api_base_url": "https://legacy.acme.example/wp-json/flavor/v2",
            "primary_color": "#112233",
            "secondary_color": "#445566",
            "accent_color": "#778899",
            "support_phone": "021-123456",
            "support_email": "hey@acme.example",
            "privacy_policy_url": "https://legacy.acme.example/privacy",
            "terms_url": "https://legacy.acme.example/terms",
            "default_branch_id": 3,
        }
        normalized = pb.normalize_config(dict(legacy))
        self.assertEqual(normalized["app_identifier"], "com.acme.legacy")
        self.assertEqual(normalized["tenant_id"], "acme_legacy")
        self.assertEqual(normalized["branding"]["primary_color"], "#112233")
        self.assertEqual(normalized["contact"]["phone"], "021-123456")
        self.assertEqual(normalized["contact"]["email"], "hey@acme.example")
        self.assertEqual(normalized["legal"]["privacy_policy"], "https://legacy.acme.example/privacy")
        self.assertEqual(normalized["legal"]["terms"], "https://legacy.acme.example/terms")

    def test_canonical_wins_over_legacy(self):
        cfg = baseline_config()
        cfg["package_name"] = "com.acme.should_be_ignored"
        cfg["branding"] = {"primary_color": "#C62828"}
        cfg["primary_color"] = "#000000"
        normalized = pb.normalize_config(cfg)
        self.assertEqual(normalized["app_identifier"], "com.acme.test")
        self.assertEqual(normalized["branding"]["primary_color"], "#C62828")


# ---------------------------------------------------------------------------
# Contract: rejection of invalid values
# ---------------------------------------------------------------------------

class RejectionTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp(prefix="flavor-reject-test-")
        self.addCleanup(shutil.rmtree, self.tmp, True)
        self.project = os.path.join(self.tmp, "mobile")
        copy_mobile_project(self.project)

    def assert_rejected(self, mutation, needle: str = ""):
        cfg = baseline_config()
        mutation(cfg)
        cfg_path = write_config(self.tmp, cfg)
        code, out = run_provision(self.project, cfg_path)
        self.assertEqual(code, 2, f"expected rejection, got exit {code}: {out}")
        self.assertIn("Provisioning rejected", out)
        if needle:
            self.assertIn(needle, out)

    def test_reject_invalid_package_names(self):
        for bad in ["1com.acme.app", "com..app", "com.acme .app", "com.acme-$evil",
                    "com.acme.$(whoami)", "X" * 300, "", "com,acme", "com.acme\n.app"]:
            self.assert_rejected(lambda c, b=bad: c.update({"app_identifier": b}), "app_identifier")

    def test_reject_invalid_colors(self):
        for bad in ["red", "#12345", "#GG0000", "#12GG00", "#1234567", "0xFF123456", "#"]:
            self.assert_rejected(
                lambda c, b=bad: c.setdefault("branding", {}).update({"primary_color": b}),
                "primary_color")

    def test_reject_invalid_version_names(self):
        for bad in ["1.0", "abc", "1..2..3", "1.0.0\"; rm -rf /", "v2", "1.2.3 $(x)"]:
            self.assert_rejected(lambda c, b=bad: c.update({"version_name": b}), "version_name")

    def test_reject_invalid_version_codes(self):
        for bad in [0, -1, 2_100_000_001, "x", None, 1.5]:
            self.assert_rejected(lambda c, b=bad: c.update({"version_code": b}), "version_code")

    def test_reject_invalid_urls(self):
        for bad in ["javascript:alert(1)", "not a url", "https://bad\"quote.com/x",
                    "ftp://no.com/x", "http://", "https://sp ace.com/"]:
            self.assert_rejected(lambda c, b=bad: c.update({"api_base_url": b}), "api_base_url")
            self.assert_rejected(
                lambda c, b=bad: c.setdefault("legal", {}).update({"privacy_policy": b}),
                "privacy_policy")

    def test_reject_invalid_tenant(self):
        for bad in ["No Caps", "with space", "ünicode", "a/b", "$(id)"]:
            self.assert_rejected(lambda c, b=bad: c.update({"tenant_id": b}), "tenant_id")

    def test_reject_invalid_branch_and_email(self):
        self.assert_rejected(lambda c: c.update({"default_branch_id": -3}), "default_branch_id")
        self.assert_rejected(lambda c: c.setdefault("contact", {}).update({"email": "not-an-email"}), "email")


# ---------------------------------------------------------------------------
# Contract: injection resistance & syntactic validity under hostile input
# ---------------------------------------------------------------------------

class MaliciousInputTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp(prefix="flavor-evil-test-")
        self.addCleanup(shutil.rmtree, self.tmp, True)
        self.project = os.path.join(self.tmp, "mobile")
        copy_mobile_project(self.project)

    def provision_with(self, mutation) -> tuple:
        cfg = baseline_config()
        mutation(cfg)
        cfg_path = write_config(self.tmp, cfg)
        return run_provision(self.project, cfg_path)

    def test_xml_specials_in_app_name_are_escaped(self):
        evil = 'Evil <&> "Restaurant" \'$(whoami)\' ${HOME}'
        code, out = self.provision_with(lambda c: c.update({"app_name": evil}))
        self.assertEqual(code, 0, out)

        manifest = read(self.project, os.path.join("android", "app", "src", "main", "AndroidManifest.xml"))
        assert_xml_valid(self, manifest, "AndroidManifest.xml")
        root_el = ET.fromstring(manifest)
        label = root_el.find("application").get("{http://schemas.android.com/apk/res/android}label")
        self.assertIn('Evil <&> "Restaurant"', label)  # round-trips through XML escapes

        plist = assert_plist_valid(self, self.project)
        self.assertIn('Evil <&> "Restaurant"', plist["CFBundleDisplayName"])

        dart = read(self.project, os.path.join("lib", "core", "constants", "brand_tokens.g.dart"))
        assert_dart_sane(self, dart)

    def test_newlines_and_control_chars_are_stripped(self):
        evil = "Line1\nLine2\r\n\x00Tab\tEnd"
        code, out = self.provision_with(lambda c: c.update({"app_name": evil}))
        self.assertEqual(code, 0, out)
        dart = read(self.project, os.path.join("lib", "core", "constants", "brand_tokens.g.dart"))
        self.assertNotIn("\x00", dart)
        self.assertNotIn("\r", dart)
        app_name_line = next(l for l in dart.splitlines() if "appName" in l)
        self.assertNotIn("\n", app_name_line.rstrip("\n").rstrip())
        self.assertIn("Line1Line2", app_name_line, "newlines collapsed into single line")
        assert_xml_valid(self, read(self.project, os.path.join("android", "app", "src", "main", "AndroidManifest.xml")), "AndroidManifest.xml")

    def test_dart_injection_in_strings(self):
        evil = "x'; inject(); //$"
        cfg = baseline_config()
        cfg["app_name"] = evil
        cfg_path = write_config(self.tmp, cfg)
        code, out = run_provision(self.project, cfg_path)
        self.assertEqual(code, 0, out)
        dart = read(self.project, os.path.join("lib", "core", "constants", "brand_tokens.g.dart"))
        assert_dart_sane(self, dart)
        self.assertIn("\\'", dart)

    def test_gradle_gstring_neutralized(self):
        """A ${...} sequence must never survive into a double-quoted Gradle string."""
        code, out = self.provision_with(lambda c: c.update({"version_name": "1.2.3-beta1"}))
        self.assertEqual(code, 0, out)
        gradle = read(self.project, os.path.join("android", "app", "build.gradle"))
        self.assertNotRegex(gradle, r'versionName\s+"[^"]*\$\{')

    def test_namespace_drift_fails_build(self):
        gradle_path = os.path.join(self.project, "android", "app", "build.gradle")
        content = read(self.project, os.path.join("android", "app", "build.gradle"))
        content = content.replace(f'namespace "{pb.STABLE_NATIVE_PACKAGE}"', 'namespace "com.evil.moved"')
        with open(gradle_path, "w", encoding="utf-8") as f:
            f.write(content)
        code, out = run_provision(self.project, write_config(self.tmp, baseline_config()))
        self.assertEqual(code, 2, out)
        self.assertIn("namespace", out)

    def test_unicode_persian_and_emoji(self):
        code, out = self.provision_with(lambda c: c.update({"app_name": "رستوران طعم 🍽️ — شعبه سعادت‌آباد"}))
        self.assertEqual(code, 0, out)
        plist = assert_plist_valid(self, self.project)
        self.assertEqual(plist["CFBundleDisplayName"], "رستوران طعم 🍽️ — شعبه سعادت‌آباد")
        assert_xml_valid(self, read(self.project, os.path.join("android", "app", "src", "main", "AndroidManifest.xml")), "AndroidManifest.xml")
        assert_dart_sane(self, read(self.project, os.path.join("lib", "core", "constants", "brand_tokens.g.dart")))


# ---------------------------------------------------------------------------
# Contract: release deep-link provisioning (App Links / Universal Links)
# ---------------------------------------------------------------------------

class DeepLinkProvisioningTests(unittest.TestCase):
    ENV_KEYS = ("FLAVOR_ANDROID_CERT_FINGERPRINT", "FLAVOR_IOS_TEAM_ID")

    def setUp(self):
        self.tmp = tempfile.mkdtemp(prefix="flavor-link-test-")
        self.addCleanup(shutil.rmtree, self.tmp, True)
        self.project = os.path.join(self.tmp, "mobile")
        copy_mobile_project(self.project)
        # Snapshot env so test mutations never leak between cases.
        self.env_snapshot = {k: os.environ.get(k) for k in self.ENV_KEYS}
        self.addCleanup(self._restore_env)

    def _restore_env(self):
        for k, v in self.env_snapshot.items():
            if v is None:
                os.environ.pop(k, None)
            else:
                os.environ[k] = v

    def provision(self, cfg: dict) -> tuple:
        return run_provision(self.project, write_config(self.tmp, cfg))

    def test_explicit_domain_list_overrides_api_host(self):
        cfg = baseline_config()
        cfg["deep_links"] = {"domains": ["order.acme.example", "go.acme.example"]}
        code, out = self.provision(cfg)
        self.assertEqual(code, 0, out)
        manifest = read(self.project, os.path.join("android", "app", "src", "main", "AndroidManifest.xml"))
        self.assertIn('android:host="order.acme.example"', manifest)
        self.assertIn('android:host="go.acme.example"', manifest)
        self.assertNotIn("api.acme.example\"", manifest.replace('android:host="api.acme.example"', "api.acme.example\""))

    def test_invalid_explicit_domain_is_rejected(self):
        for bad in ["*.acme.example", "not a domain", "acme..example", "under_score.example", ""]: 
            cfg = baseline_config()
            cfg["deep_links"] = {"domains": [bad]}
            code, out = self.provision(cfg)
            self.assertEqual(code, 2, f"expected rejection for {bad!r}, got exit {code}")
            self.assertIn("Provisioning rejected", out)

    def test_cert_fingerprint_from_env_renders_assetlinks(self):
        fp = ":".join(["AB"] * 32)
        os.environ["FLAVOR_ANDROID_CERT_FINGERPRINT"] = fp
        code, out = self.provision(baseline_config())
        self.assertEqual(code, 0, out)
        asset = json.loads(read(self.project, os.path.join("assets", "deeplinks", "generated", "assetlinks.json")))
        self.assertEqual(asset[0]["target"]["sha256_cert_fingerprints"], [fp])

    def test_invalid_cert_fingerprint_is_rejected(self):
        os.environ["FLAVOR_ANDROID_CERT_FINGERPRINT"] = "AB:CD:EF"
        code, out = self.provision(baseline_config())
        self.assertEqual(code, 2)
        self.assertIn("FLAVOR_ANDROID_CERT_FINGERPRINT", out)

    def test_team_id_from_env_renders_aasa(self):
        os.environ["FLAVOR_IOS_TEAM_ID"] = "ABCDEFG123"
        code, out = self.provision(baseline_config())
        self.assertEqual(code, 0, out)
        aasa = read(self.project, os.path.join("assets", "deeplinks", "generated", "apple-app-site-association"))
        self.assertIn('"appID": "ABCDEFG123.com.acme.test"', aasa)

    def test_invalid_team_id_is_rejected(self):
        os.environ["FLAVOR_IOS_TEAM_ID"] = "team"
        code, out = self.provision(baseline_config())
        self.assertEqual(code, 2)
        self.assertIn("FLAVOR_IOS_TEAM_ID", out)

    def test_entitlements_are_valid_plist_and_brand_specific(self):
        cfg = os.path.join(BRANDING_DIR, "brand_shandiz.json")
        code, out = run_provision(self.project, cfg)
        self.assertEqual(code, 0, out)
        with open(os.path.join(self.project, "ios", "Runner", "Runner.entitlements"), "rb") as f:
            ent = plistlib.load(f)
        self.assertIn("applinks:shandiz.flavor.restaurant",
                      ent["com.apple.developer.associated-domains"])
        manifest = read(self.project, os.path.join("android", "app", "src", "main", "AndroidManifest.xml"))
        assert_xml_valid(self, manifest, "AndroidManifest.xml")
        self.assertIn('android:host="shandiz.flavor.restaurant"', manifest)
        self.assertNotIn("restaurant.com\" android:pathPrefix=\"/\"", manifest.replace("shandiz.", ""))


if __name__ == "__main__":
    unittest.main(verbosity=2)
