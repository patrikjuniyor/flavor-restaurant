#!/usr/bin/env bash
#
# PHP syntax check across every shipped package.
#
# CI runs the same check (see .github/workflows/ci.yml); this script lets a
# contributor run it in one command before pushing:
#
#   composer lint
#
# Exit status — and not the output text — decides pass/fail. `php -l` prints
# "No syntax errors detected in <file>" on success, so grepping the output for
# the word "syntax" would report every healthy file as a failure.
#
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! command -v php >/dev/null 2>&1; then
	echo "خطا: PHP روی این سیستم نصب نیست (php: command not found)." >&2
	exit 127
fi

targets=(flavor flavor-core flavor-child tests dev-tools)

files=()
while IFS= read -r -d '' file; do
	files+=("$file")
done < <(find "${targets[@]}" -name '*.php' -type f -print0 2>/dev/null)

if [ "${#files[@]}" -eq 0 ]; then
	echo "هیچ فایل PHP‌ای پیدا نشد."
	exit 0
fi

echo "بررسی نحو (syntax) برای ${#files[@]} فایل PHP..."

check_one() {
	php -l "$1" >/dev/null 2>&1
}

status=0
for file in "${files[@]}"; do
	if ! check_one "$file"; then
		echo "❌ $file"
		php -l "$file" 2>&1 | sed 's/^/     /'
		status=1
	fi
done

if [ $status -ne 0 ]; then
	echo "❌ خطای نحو پیدا شد (فایل‌های بالا)."
	exit 1
fi

echo "همهٔ ${#files[@]} فایل از نظر نحو سالم‌اند."
exit 0
