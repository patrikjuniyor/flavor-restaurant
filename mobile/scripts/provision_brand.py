#!/usr/bin/env python3
"""
Flavor Mobile Brand Provisioning Tool.
Applies White-Label branding configuration from WordPress API or JSON file
to Flutter, Android, and iOS projects automatically during CI/CD.

Usage:
  python3 provision_brand.py --config-url https://restaurant.com/wp-json/flavor/v1/mobile/config
  python3 provision_brand.py --config-file path/to/brand_config.json
"""

import argparse
import json
import os
import re
import sys
import urllib.request
import hashlib


def fetch_config_from_url(url: str) -> dict:
    print(f"[*] Fetching brand config from API: {url}")
    req = urllib.request.Request(
        url,
        headers={"User-Agent": "Flavor-CI-Provisioner/1.0", "Accept": "application/json"}
    )
    with urllib.request.urlopen(req, timeout=30) as resp:
        if resp.status != 200:
            raise RuntimeError(f"Failed to fetch config, HTTP status: {resp.status}")
        data = json.loads(resp.read().decode("utf-8"))
        if "data" in data:
            return data["data"]
        return data


def load_config_from_file(path: str) -> dict:
    print(f"[*] Loading brand config from file: {path}")
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)


def update_pubspec_yaml(mobile_dir: str, config: dict):
    pubspec_path = os.path.join(mobile_dir, "pubspec.yaml")
    if not os.path.exists(pubspec_path):
        print(f"[!] pubspec.yaml not found at {pubspec_path}")
        return

    with open(pubspec_path, "r", encoding="utf-8") as f:
        content = f.read()

    v_name = config.get("version_name", "1.0.0")
    v_code = config.get("version_code", 1)
    version_line = f"version: {v_name}+{v_code}"

    content = re.sub(r"^version:\s*[\d\.\+]+", version_line, content, flags=re.MULTILINE)

    with open(pubspec_path, "w", encoding="utf-8") as f:
        f.write(content)
    print(f"[+] Updated pubspec.yaml version: {v_name}+{v_code}")


def update_android_branding(mobile_dir: str, config: dict):
    build_gradle = os.path.join(mobile_dir, "android", "app", "build.gradle")
    if os.path.exists(build_gradle):
        with open(build_gradle, "r", encoding="utf-8") as f:
            content = f.read()

        package_name = config.get("package_name")
        v_name = config.get("version_name", "1.0.0")
        v_code = str(config.get("version_code", 1))

        if package_name:
            content = re.sub(
                r'applicationId\s+["\'][a-zA-Z0-9_\.]+["\']',
                f'applicationId "{package_name}"',
                content
            )
            content = re.sub(
                r'namespace\s+["\'][a-zA-Z0-9_\.]+["\']',
                f'namespace "{package_name}"',
                content
            )

        content = re.sub(r'versionCode\s+flutterVersionCode\.toInteger\(\)|\bversionCode\s+\d+', f'versionCode {v_code}', content)
        content = re.sub(r'versionName\s+flutterVersionName|\bversionName\s+["\'][\d\.]+["\']', f'versionName "{v_name}"', content)

        with open(build_gradle, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"[+] Updated Android build.gradle (pkg: {package_name}, version: {v_name} ({v_code}))")

    # Update strings.xml or AndroidManifest.xml app name label
    manifest_path = os.path.join(mobile_dir, "android", "app", "src", "main", "AndroidManifest.xml")
    if os.path.exists(manifest_path):
        with open(manifest_path, "r", encoding="utf-8") as f:
            m_content = f.read()

        app_name = config.get("app_name", "Flavor Restaurant")
        m_content = re.sub(
            r'android:label="[^"]*"',
            f'android:label="{app_name}"',
            m_content
        )
        with open(manifest_path, "w", encoding="utf-8") as f:
            f.write(m_content)
        print(f"[+] Updated Android Manifest app label: {app_name}")


def update_ios_branding(mobile_dir: str, config: dict):
    info_plist = os.path.join(mobile_dir, "ios", "Runner", "Info.plist")
    if os.path.exists(info_plist):
        with open(info_plist, "r", encoding="utf-8") as f:
            plist = f.read()

        app_name = config.get("app_name", "Flavor Restaurant")
        # Replace CFBundleDisplayName
        if "<key>CFBundleDisplayName</key>" in plist:
            plist = re.sub(
                r'(<key>CFBundleDisplayName</key>\s*<string>)[^<]*(</string>)',
                rf'\g<1>{app_name}\g<2>',
                plist
            )
        else:
            plist = plist.replace(
                "<dict>",
                f"<dict>\n\t<key>CFBundleDisplayName</key>\n\t<string>{app_name}</string>"
            )

        with open(info_plist, "w", encoding="utf-8") as f:
            f.write(plist)
        print(f"[+] Updated iOS Info.plist CFBundleDisplayName: {app_name}")


def generate_branding_dart_constants(mobile_dir: str, config: dict):
    target_path = os.path.join(mobile_dir, "lib", "core", "constants", "brand_tokens.g.dart")
    os.makedirs(os.path.dirname(target_path), exist_ok=True)

    app_name = config.get("app_name", "Flavor Restaurant")
    tenant_id = config.get("tenant_id") or config.get("restaurant_id", "default_store")
    api_base_url = config.get("api_base_url", "https://demo.flavor.restaurant/wp-json/flavor/v2")
    primary_color = config.get("primary_color", "#C62828").replace("#", "0xFF")
    secondary_color = config.get("secondary_color", "#2E7D32").replace("#", "0xFF")
    accent_color = config.get("accent_color", "#FFA000").replace("#", "0xFF")
    support_phone = config.get("support_phone", "021-88888888")
    support_email = config.get("support_email", "support@flavor.restaurant")
    privacy_url = config.get("privacy_policy_url", "")
    terms_url = config.get("terms_url", "")
    default_branch = config.get("default_branch_id", 0)

    dart_content = f"""// GENERATED CODE - DO NOT MODIFY BY HAND
// Generated by Flavor Mobile Brand Provisioning Tool

import 'package:flutter/material.dart';

abstract class BrandTokens {{
  static const String appName = '{app_name}';
  static const String tenantId = '{tenant_id}';
  static const String apiBaseUrl = '{api_base_url}';
  static const int defaultBranchId = {default_branch};

  static const Color primaryColor = Color({primary_color});
  static const Color secondaryColor = Color({secondary_color});
  static const Color accentColor = Color({accent_color});

  static const String supportPhone = '{support_phone}';
  static const String supportEmail = '{support_email}';
  static const String privacyPolicyUrl = '{privacy_url}';
  static const String termsUrl = '{terms_url}';
}}
"""
    with open(target_path, "w", encoding="utf-8") as f:
        f.write(dart_content)
    print(f"[+] Generated Dart brand tokens at: {target_path}")


def main():
    parser = argparse.ArgumentParser(description="Flavor Brand Provisioner for Mobile Apps")
    parser.add_argument("--config-url", type=str, help="WordPress REST API endpoint for brand configuration")
    parser.add_argument("--config-file", type=str, help="Local JSON file with branding parameters")
    parser.add_argument("--mobile-root", type=str, default=".", help="Root directory of Flutter project")

    args = parser.parse_args()

    if args.config_url:
        config = fetch_config_from_url(args.config_url)
    elif args.config_file:
        config = load_config_from_file(args.config_file)
    else:
        # Fallback to local default config if present
        default_file = os.path.join(args.mobile_root, "assets", "branding", "default_branding.json")
        if os.path.exists(default_file):
            config = load_config_from_file(default_file)
        else:
            print("[!] Error: You must supply --config-url or --config-file.")
            sys.exit(1)

    print(f"[*] Applying white-label configuration for tenant: {config.get('restaurant_id', 'unknown')}")
    update_pubspec_yaml(args.mobile_root, config)
    update_android_branding(args.mobile_root, config)
    update_ios_branding(args.mobile_root, config)
    generate_branding_dart_constants(args.mobile_root, config)
    print("[✓] Brand provisioning completed successfully!")


if __name__ == "__main__":
    main()
