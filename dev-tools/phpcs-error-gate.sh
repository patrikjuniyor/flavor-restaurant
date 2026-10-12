#!/usr/bin/env bash
# P43 acceptance gate: WPCS error-level violations must stay at zero.
# Warnings (docs/spacing advisories) are informational; ERRORS are policy.
# Usage: dev-tools/phpcs-error-gate.sh [repo-root]
set -euo pipefail
root="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "$root"
PHPCS="php vendor/bin/phpcs"
if [ ! -f vendor/bin/phpcs ] && [ ! -f vendor/squizlabs/php_codesniffer/autoload.php ]; then
  printf '[FAIL] phpcs not installed; run: composer install\n' >&2
  exit 2
fi
report="$($PHPCS --standard=phpcs.xml --warning-severity=0 --report=csv flavor flavor-core flavor-child 2>/dev/null || true)"
count=$(printf '%s' "$report" | grep -c '^[^,]*php",' || true)
if [ "$count" -ne 0 ]; then
  printf '[FAIL] phpcs reports %s error-level violation(s):\n' "$count"
  printf '%s\n' "$report" | grep '^[^,]*php",' | head -25
  exit 1
fi
printf '[PASS] phpcs: 0 error-level violations across flavor/, flavor-core/, flavor-child/\n'
