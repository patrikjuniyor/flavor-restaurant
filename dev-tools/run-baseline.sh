#!/usr/bin/env bash
#
# Regenerate baseline-tests.txt — one snapshot of every suite the CI gates on.
#
#   ./dev-tools/run-baseline.sh              # run everything, write the file
#   ./dev-tools/run-baseline.sh --stdout     # run everything, print only
#
# Why this exists: the repo has three independent test entry points (the theme
# suite, the core pure/integration runners, the motion guard). A change in one
# can quietly lower the count in another, and "I saw a green line" is not the
# same as "1831 checks passed, same as before". This keeps the numbers in a
# committed file so the phases can be compared against a real baseline.
#
# Note on flavor-core/tests/test-*.php: those are PHPUnit classes needing the
# WordPress test library and a real database (see flavor-core/phpunit.xml.dist).
# CI does not run them — it runs the two standalone core runners below — so
# neither does this script.
#
set -uo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

report="$(mktemp)"
trap 'rm -f "$report"' EXIT

have() { command -v "$1" >/dev/null 2>&1; }
failures=0

# summarise <log>: turn a suite's output into one short "how much passed" line.
summarise() {
	local log="$1" line
	line="$(grep -E '^Results: [0-9]+ Passed, [0-9]+ Failed' "$log" | tail -1)"
	if [ -n "$line" ]; then
		awk '/^Results: [0-9]+ Passed, [0-9]+ Failed/ {
			gsub(/[^0-9, ]/, "", $0); split($0, n, " "); p += n[1]; f += n[2]
		} END { printf "%d checks passed, %d failed", p, f }' "$log"
		return
	fi
	line="$(grep -E 'SUITE SUMMARY:' "$log" | tail -1 | tr -s ' ')"
	[ -n "$line" ] && { printf '%s' "${line# }"; return; }
	line="$(grep -E 'all [0-9]+ checks passed' "$log" | tail -1)"
	[ -n "$line" ] && { printf '%s' "$line"; return; }
	line="$(grep -E '^[a-z]{2}[[:space:]]+[0-9]+/[0-9]+ strings' "$log" | tail -1 | sed 's/^[a-z-]*[[:space:]]*//')"
	[ -n "$line" ] && { printf '%s' "$line"; return; }
	line="$(grep -E 'No issues found!' "$log" | tail -1)"
	[ -n "$line" ] && { printf '%s' "$line"; return; }
	line="$(grep -E 'All tests passed' "$log" | tail -1)"
	if [ -n "$line" ]; then
		printf '%s (%s tests)' "All tests passed" \
			"$(grep -oE '\+[0-9]+: All tests passed' "$log" | tail -1 | tr -dc '0-9')"
		return
	fi
	line="$(grep -E 'موفق بود|موفق است|سالم‌اند' "$log" | tail -1)"
	[ -n "$line" ] && { printf '%s' "$line"; return; }
	line="$(grep -E '^OK' "$log" | tail -1)"
	[ -n "$line" ] && { printf '%s' "$line"; return; }
	line="$(grep -E 'No syntax errors' "$log" | tail -1)"
	[ -n "$line" ] && { printf 'all files parse (%s files checked)' "$(grep -cE 'No syntax errors' "$log")"; return; }
	printf '%s [PASS] lines' "$(grep -cE '\[PASS\]' "$log")"
}

record() { # record <label> <command...>
	local label="$1"; shift
	local log code=0
	log="$(mktemp)"
	"$@" 2>&1 | tr '\r' '\n' >"$log" || code=${PIPESTATUS[0]}
	local summary
	summary="$(summarise "$log")"
	if [ "$code" -eq 0 ]; then
		printf '[ OK ] %-46s %s\n' "$label" "$summary" >>"$report"
	else
		printf '[FAIL] %-46s exit %d\n' "$label" "$code" >>"$report"
		tail -20 "$log" | sed 's/^/        /' >>"$report"
		failures=$((failures + 1))
	fi
	rm -f "$log"
}

{
	echo "Flavor — baseline آزمون‌ها (test baseline)"
	echo "==========================================================="
	echo "generated : $(date -u '+%Y-%m-%d %H:%M UTC')"
	echo "command   : ./dev-tools/run-baseline.sh"
	echo
	echo "Read this as the number to beat. After each phase, re-run the command"
	echo "above and compare it: a suite whose count drops has regressed even if"
	echo "its last line still says 'passed'."
	echo
	echo "environment"
	echo "-----------------------------------------------------------"
	echo "php       : $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo missing)"
	echo "composer  : $(composer --version 2>/dev/null | head -1 || echo missing)"
	echo "node      : $(node -v 2>/dev/null || echo missing)"
	echo "python3   : $(python3 --version 2>&1 || echo missing)"
	echo "gettext   : $(msgfmt --version 2>/dev/null | head -1 || echo missing)"
	echo "flutter   : $(have flutter && flutter --version 2>/dev/null | head -1 || echo 'not installed — mobile suites skipped')"
	echo
	echo "suites (the ones ci.yml gates on)"
	echo "-----------------------------------------------------------"
} >>"$report"

record "theme suite (tests/theme/*)"        bash dev-tools/run-theme-tests.sh
record "core pure logic"                    php flavor-core/tests/run-pure-tests.php
record "core e2e integration"               php flavor-core/tests/run-e2e-integration.php
record "PHP lint"                           bash dev-tools/lint-php.sh
record "motion layer guard"                 node tests/ui/motion.mjs
record "translation catalogues"             bash dev-tools/check-i18n.sh
record "translation contract"               php tests/theme/test-translation.php
record "version headers"                    php dev-tools/check-versions.php
record "class wiring"                       php dev-tools/check-class-wiring.php
record "child theme"                        bash dev-tools/check-child-theme.sh

if have flutter; then
	echo >>"$report"
	echo "mobile (Flutter)" >>"$report"
	echo "-----------------------------------------------------------" >>"$report"
	echo "the app resolves against the toolchain CI pins (3.24.x); the" >>"$report"
	echo "intl constraint in mobile/pubspec.yaml is what decides that." >>"$report"
	( cd mobile && record "flutter pub get" flutter pub get )
	( cd mobile && record "flutter analyze --fatal-infos" flutter analyze --fatal-infos )
	( cd mobile && record "flutter test" flutter test )
fi

{
	echo
	echo "reported but not gating"
	echo "-----------------------------------------------------------"
	if [ -x vendor/bin/phpcs ]; then
		echo "phpcs: $(vendor/bin/phpcs --report=summary -p 2>&1 | tail -1 | tr -s ' ')"
	else
		echo "phpcs: vendor/bin/phpcs missing — run 'composer install'"
	fi
	echo
	echo "outcome"
	echo "-----------------------------------------------------------"
	printf '%d suite(s) failed\n' "$failures"
} >>"$report"

cat "$report"

if [ "${1:-}" != "--stdout" ]; then
	cp "$report" baseline-tests.txt
	printf '\nwritten: baseline-tests.txt (%d failure(s))\n' "$failures"
fi
exit 0
