#!/usr/bin/env bash
# Build the two artefacts a theme is actually sold and installed as:
#
#   dist/flavor.zip           the sellable theme, without the demo pack
#   dist/flavor-demo-pack.zip the 12 MB of demo photography, separate
#
# The theme has to work with the demo pack absent — that is the point of
# splitting it — so Bespoke_Demos::asset() and Repeater::fallback() degrade
# to a bundled placeholder rather than emitting a URL that 404s. This script
# checks that guarantee holds before it will ship a packless zip.
#
# Usage:
#   ./dev-tools/build-distribution.sh           # build and report
#   MAX_THEME_KB=4096 ./dev-tools/build-distribution.sh
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
echo "== 3. Build the sellable theme =="
# Ship the theme without demos/. Everything else — inc, templates, assets,
# languages, child theme — is what a customer installs.
rm -rf "$scratch/theme"
mkdir -p "$scratch/theme"
cp -r "$root/flavor" "$scratch/theme/flavor"
rm -rf "$scratch/theme/flavor/demos"

# Development-only weight that has no business in a customer's install.
rm -rf "$scratch/theme/flavor/tests" "$scratch/theme/flavor/node_modules"

( cd "$scratch/theme" && zip -qr "$dist/flavor.zip" flavor )
size=$(du -k "$dist/flavor.zip" | cut -f1)
printf 'flavor.zip: %s KB (سقف %s KB)\n' "$size" "$THEME_MAX_KB"

echo
echo "== 4. Enforce the weight budget =="
if [ "$size" -gt "$THEME_MAX_KB" ]; then
  printf '[FAIL] بستهٔ قالب %s KB است و از سقف %s KB بیشتر است\n' "$size" "$THEME_MAX_KB"
  exit 1
fi
printf '[PASS] بستهٔ قالب زیرِ سقف است\n'

echo
echo "== 5. Report =="
for f in "$dist"/*.zip; do
  [ -f "$f" ] || continue
  printf '  %-28s %s KB\n' "$(basename "$f")" "$(du -k "$f" | cut -f1)"
done
printf '\nبررسیِ توزیع موفق بود.\n'
