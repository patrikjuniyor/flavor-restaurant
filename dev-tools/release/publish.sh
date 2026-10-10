#!/usr/bin/env bash
# Owner-side release: build, copy, sign, verify. Run on the release machine only.
#
# The signing key never enters the repository and is never printed. This script
# reads it from the path in FLAVOR_RELEASE_SECRET_KEY, which must live outside
# the repo. It writes only public artefacts into release-site/flavor/. It does
# not commit or push: you review the diff and push, and the publish workflow
# then deploys release-site/ to GitHub Pages after verifying it again.
#
# Required environment:
#   FLAVOR_RELEASE_SECRET_KEY   path to the secret key file (outside the repo)
#   FLAVOR_RELEASE_URL_PREFIX   public folder URL, e.g.
#                               https://<owner>.github.io/flavor-restaurant/flavor/
#                               (must end with /; no guessing: you set it)
#
# Optional:
#   FLAVOR_RELEASE_EXPIRES_DAYS  manifest lifetime in days (default 30)
#   FLAVOR_RELEASE_CHANGELOG     path to a changelog file (max 30 lines)
#
# Usage:
#   FLAVOR_RELEASE_SECRET_KEY=$HOME/keys/flavor/secret.key \
#   FLAVOR_RELEASE_URL_PREFIX=https://<owner>.github.io/flavor-restaurant/flavor/ \
#   ./dev-tools/release/publish.sh 1.7.0
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$root"

fail() { echo "خطا: $*" >&2; exit 1; }

version="${1:-}"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "نسخه را به شکل x.y.z بدهید (مثلاً 1.7.0)."

secret="${FLAVOR_RELEASE_SECRET_KEY:-}"
[[ -n "$secret" ]] || fail "FLAVOR_RELEASE_SECRET_KEY تنظیم نشده است (مسیر فایل کلید خصوصی)."
[[ -f "$secret" ]] || fail "فایل کلید خصوصی پیدا نشد."

# The secret must be outside the repo. Resolve both sides to real paths first.
secret_real="$(cd "$(dirname "$secret")" && pwd -P)/$(basename "$secret")"
repo_real="$(pwd -P)"
case "$secret_real" in
  "$repo_real"/*) fail "کلید خصوصی داخل مخزن است. آن را به مسیر امن خارج از مخزن ببرید." ;;
esac

perm="$(stat -c '%a' "$secret" 2>/dev/null || echo '?')"
if [[ "$perm" != "600" && "$perm" != "400" ]]; then
  echo "هشدار: دسترسی فایل کلید ($perm) سخت‌گیرانه نیست؛ پیشنهاد: chmod 600." >&2
fi

prefix="${FLAVOR_RELEASE_URL_PREFIX:-}"
[[ "$prefix" == https://*/ ]] || fail "FLAVOR_RELEASE_URL_PREFIX باید با https:// شروع و با / تمام شود."

public_key="release-site/public.key"
[[ -f "$public_key" ]] || fail "release-site/public.key نیست. ابتدا keygen را اجرا و کلید عمومی را commit کنید."

# Refuse to publish from a tree with uncommitted theme changes: the artefact
# must match a reviewed commit.
if [[ -n "$(git status --porcelain -- flavor dev-tools/build-distribution.sh 2>/dev/null)" ]]; then
  fail "تغییرات commit‌نشده در flavor/ یا build-distribution.sh وجود دارد. اول commit کنید."
fi

dest_dir="release-site/flavor"
zip_name="flavor-${version}.zip"
dest_zip="${dest_dir}/${zip_name}"
mkdir -p "$dest_dir"
[[ ! -e "$dest_zip" ]] || fail "${dest_zip} از قبل وجود دارد؛ نسخهٔ منتشرشده بازنویسی نمی‌شود."

echo "== 1/5 آزمون‌ها"
php tests/theme/test-update-channel.php >/dev/null || fail "آزمون کانال به‌روزرسانی شکست خورد."
php tests/theme/test-release-tooling.php >/dev/null || fail "آزمون ابزار انتشار شکست خورد."

echo "== 2/5 ساخت بسته"
./dev-tools/build-distribution.sh >/dev/null || fail "build-distribution.sh شکست خورد."
[[ -f dist/flavor.zip ]] || fail "dist/flavor.zip ساخته نشد."

cp dist/flavor.zip "$dest_zip"

echo "== 3/5 امضا"
sign_args=(
  sign
  "--zip=${dest_zip}"
  "--version=${version}"
  "--url=${prefix}${zip_name}"
  "--secret-key=${secret_real}"
  "--out=${dest_dir}"
  "--expires-days=${FLAVOR_RELEASE_EXPIRES_DAYS:-30}"
  "--released=$(date -u +%Y-%m-%d)"
)
if [[ -n "${FLAVOR_RELEASE_CHANGELOG:-}" ]]; then
  sign_args+=("--changelog=${FLAVOR_RELEASE_CHANGELOG}")
fi
php dev-tools/release/build-release.php "${sign_args[@]}" || { rm -f "$dest_zip"; fail "امضا شکست خورد."; }

echo "== 4/5 بررسی با کلید عمومی (همان کاری که CI می‌کند)"
php dev-tools/release/build-release.php verify \
  "--dir=${dest_dir}" "--public-key=${public_key}" "--url-prefix=${prefix}" \
  || fail "بررسی مانیفست شکست خورد؛ فایل‌های تولیدشده را حذف و دوباره امتحان کنید."

echo "== 5/5 آماده برای commit"
echo "فایل‌های عمومی (بدون کلید خصوصی):"
ls -1 "$dest_dir" | sed 's/^/  /'
echo
echo "بعد از بررسی، این دستورها را اجرا کنید:"
echo "  git add release-site/flavor"
echo "  git commit -m 'release: flavor ${version}'"
echo "  git push"
echo "سپس GitHub Actions با workflow «publish-release» آن را منتشر می‌کند."
