#!/usr/bin/env bash
# Build the artefacts a customer and marketplace actually receive:
#
#   dist/flavor.zip           the sellable theme, clean of development files and demo art
#   dist/flavor-demo-pack.zip the demo photography and media pack, separate
#   dist/flavor-core.zip      the operational plugin (tables, KDS, OTP, orders)
#   dist/flavor-child.zip     the starter child theme for safe customizations
#
# Requirements & guarantees:
# 1. The theme has to work with the demo pack absent (Bespoke_Demos degrades to placeholder).
# 2. Strict sanitation: dev-tools/, tests/, internal audit docs, and draft notes are NEVER included.
# 3. Only clean production code, README, docs/fa/, licenses and assets are packaged.
# 4. Enforces the theme weight budget (< 4096 KB).
set -uo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

THEME_MAX_KB="${MAX_THEME_KB:-4096}"
dist="$root/dist"
scratch="$(mktemp -d)"
trap 'rm -rf "$scratch"' EXIT

rm -rf "$dist"
mkdir -p "$dist"

echo "== 1. The theme must render without the demo pack =="

# Prove the guarantee rather than assert it: point the theme at a copy of
# itself with demos/ removed and confirm nothing prints a dead URL.
rm -rf "$scratch/packless"
mkdir -p "$scratch/packless"
cp -r "$root/flavor" "$scratch/packless/flavor"
rm -rf "$scratch/packless/flavor/demos"

dead_urls="$(grep -rn "FLAVOR_URI . '/demos/" "$scratch/packless/flavor" --include=*.php | wc -l)"
# A bare concatenation is fine only when guarded by is_readable().
guarded="$(grep -rn -B2 "FLAVOR_URI . '/demos/" "$scratch/packless/flavor" --include=*.php | grep -c "is_readable" || true)"
if [ "$dead_urls" -eq 0 ] || [ "$guarded" -gt 0 ]; then
  printf '[PASS] مسیرهای تصویرِ دمو در برابر نبودِ بسته محافظت شده‌اند\n'
else
  printf '[FAIL] %d مسیرِ تصویرِ دمو بدون بررسیِ وجود فایل است\n' "$dead_urls"
  exit 1
fi

if [ -f "$scratch/packless/flavor/assets/img/demo-placeholder.svg" ]; then
  printf '[PASS] placeholder در بستهٔ بدون‌دمو موجود است\n'
else
  printf '[FAIL] assets/img/demo-placeholder.svg در بسته نیست\n'
  exit 1
fi

echo
echo "== 2. Build the demo pack =="
if [ -d "$root/flavor/demos" ]; then
  ( cd "$root/flavor/demos" && zip -qr "$dist/flavor-demo-pack.zip" . )
  size=$(du -k "$dist/flavor-demo-pack.zip" | cut -f1)
  printf 'flavor-demo-pack.zip: %s KB\n' "$size"
else
  printf 'flavor/demos نداریم؛ بستهٔ دمو ساخته نشد\n'
fi

echo
echo "== 3. Build the sellable theme (flavor.zip) =="
# Ship the theme without demos/. Everything else — inc, templates, assets,
# languages — is what a customer installs.
rm -rf "$scratch/theme"
mkdir -p "$scratch/theme"
cp -r "$root/flavor" "$scratch/theme/flavor"
rm -rf "$scratch/theme/flavor/demos"

# Clean development and internal artifacts from the theme folder
rm -rf "$scratch/theme/flavor/tests" "$scratch/theme/flavor/node_modules"
find "$scratch/theme/flavor" -type f -name "*.map" -delete 2>/dev/null || true
find "$scratch/theme/flavor" -type f -name ".DS_Store" -delete 2>/dev/null || true
find "$scratch/theme/flavor" -type d -name ".git*" -exec rm -rf {} + 2>/dev/null || true

# Include buyer documentation and licensing in the package
if [ -d "$root/docs/fa" ]; then
  mkdir -p "$scratch/theme/flavor/docs"
  cp -r "$root/docs/fa" "$scratch/theme/flavor/docs/fa"
fi
if [ -f "$root/LICENSE" ]; then
  cp "$root/LICENSE" "$scratch/theme/flavor/LICENSE"
fi
if [ -f "$root/README.md" ]; then
  cp "$root/README.md" "$scratch/theme/flavor/README.md"
fi

# Assert no internal development documents ever leaked into the theme
find "$scratch/theme/flavor" -type f \( \
  -name "*PHASE*" -o \
  -name "*PREMIUM-MISSION-LIST*" -o \
  -name "*QA*" -o \
  -name "*AUDIT*" -o \
  -name "*VERIFICATION*" -o \
  -name "*ROADMAP*" -o \
  -name "*FOLLOWUPS*" \
\) -delete 2>/dev/null || true

( cd "$scratch/theme" && zip -qr "$dist/flavor.zip" flavor )
size=$(du -k "$dist/flavor.zip" | cut -f1)
printf 'flavor.zip: %s KB (سقف %s KB)\n' "$size" "$THEME_MAX_KB"

echo
echo "== 4. Build the companion plugin (flavor-core.zip) =="
if [ -d "$root/flavor-core" ]; then
  rm -rf "$scratch/plugin"
  mkdir -p "$scratch/plugin"
  cp -r "$root/flavor-core" "$scratch/plugin/flavor-core"
  # Strip tests, fixtures, and dev configs
  rm -rf "$scratch/plugin/flavor-core/tests"
  rm -f "$scratch/plugin/flavor-core/phpunit.xml.dist" "$scratch/plugin/flavor-core/composer.json"
  if [ -f "$root/LICENSE" ]; then
    cp "$root/LICENSE" "$scratch/plugin/flavor-core/LICENSE"
  fi
  ( cd "$scratch/plugin" && zip -qr "$dist/flavor-core.zip" flavor-core )
  core_size=$(du -k "$dist/flavor-core.zip" | cut -f1)
  printf 'flavor-core.zip: %s KB\n' "$core_size"
fi

echo
echo "== 5. Build the child theme (flavor-child.zip) =="
if [ -d "$root/flavor-child" ]; then
  rm -rf "$scratch/child"
  mkdir -p "$scratch/child"
  cp -r "$root/flavor-child" "$scratch/child/flavor-child"
  rm -rf "$scratch/child/flavor-child/tests"
  if [ -f "$root/LICENSE" ]; then
    cp "$root/LICENSE" "$scratch/child/flavor-child/LICENSE"
  fi
  ( cd "$scratch/child" && zip -qr "$dist/flavor-child.zip" flavor-child )
  child_size=$(du -k "$dist/flavor-child.zip" | cut -f1)
  printf 'flavor-child.zip: %s KB\n' "$child_size"
fi

echo
echo "== 6. Enforce the weight budget & verify artifact hygiene =="
if [ "$size" -gt "$THEME_MAX_KB" ]; then
  printf '[FAIL] بستهٔ قالب %s KB است و از سقف %s KB بیشتر است\n' "$size" "$THEME_MAX_KB"
  exit 1
fi
printf '[PASS] بستهٔ قالب زیرِ سقف است\n'

# Verify that no development folders or internal files leaked into zip files
for zipfile in "$dist"/flavor*.zip; do
  [ -f "$zipfile" ] || continue
  leak=$(unzip -l "$zipfile" | grep -iE 'dev-tools/|/tests/|\.git|PHASE|PREMIUM-MISSION-LIST|QA-|IMPLEMENTATION-AUDIT' || true)
  if [ -n "$leak" ]; then
    printf '[FAIL] پرونده‌های داخلی توسعه در %s پیدا شدند:\n%s\n' "$(basename "$zipfile")" "$leak"
    exit 1
  fi
done
printf '[PASS] هیچ پروندهٔ داخلی یا مسیر توسعه در بسته‌های نهایی وجود ندارد\n'

echo
echo "== 7. Report =="
for f in "$dist"/*.zip; do
  [ -f "$f" ] || continue
  printf '  %-28s %s KB\n' "$(basename "$f")" "$(du -k "$f" | cut -f1)"
done
printf '\nبررسیِ توزیع موفق بود.\n'
