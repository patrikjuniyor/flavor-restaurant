#!/usr/bin/env bash
#
# Run the standalone theme test suite.
#
# These suites boot a small WordPress mock (flavor-core/tests/mock-wp-environment.php)
# and therefore need no database, no PHPUnit and no WordPress install:
#
#   composer test:theme
#
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! command -v php >/dev/null 2>&1; then
	echo "خطا: PHP روی این سیستم نصب نیست (php: command not found)." >&2
	exit 127
fi

# The mock environment talks to SQLite through PDO for the $wpdb double.
for ext in pdo_sqlite mbstring json; do
	if ! php -r "exit(extension_loaded('$ext') ? 0 : 1);"; then
		echo "خطا: افزونهٔ PHP «$ext» لازم است اما نصب نیست." >&2
		echo "راهنما: sudo apt-get install php-mbstring php-sqlite3" >&2
		exit 127
	fi
done

status=0
passed=0
failed=0

for test in tests/theme/test-*.php; do
	[ -e "$test" ] || continue
	echo "──────────────────────────────────────────────────"
	echo "▶ $(basename "$test")"
	output="$(php "$test" 2>&1)"
	code=$?
	echo "$output"

	if [ $code -ne 0 ]; then
		status=1
		failed=$((failed + 1))
	else
		passed=$((passed + 1))
	fi

	# Surface a crashed run the same way a failing assertion would.
	if echo "$output" | grep -qiE 'fatal error|uncaught'; then
		status=1
	fi
done

echo "──────────────────────────────────────────────────"
echo "نتیجه: $passed فایل تست موفق، $failed ناموفق"

exit $status
