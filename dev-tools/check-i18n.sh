#!/usr/bin/env bash
# Guard the translation catalogues.
#
# Three failure modes, all silent without this:
#   1. A developer adds a string and the committed POT goes stale, so
#      translators work from a catalogue that no longer matches the code.
#   2. A .po is edited by hand and picks up a malformed placeholder or a
#      wrong number of plural forms. msgfmt catches both.
#   3. A translation key no longer matches any source string, so it applies
#      to nothing and the UI silently reverts to Persian.
#
# Every check regenerates into a scratch directory and compares, ignoring
# the creation date — that line changes on every build and would otherwise
# make the comparison always fail.
set -uo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

fail=0
scratch="$(mktemp -d)"
trap 'rm -rf "$scratch"' EXIT

strip_volatile() {
  # The POT-Creation-Date and PO-Revision-Date change on every build.
  grep -v -e '^"POT-Creation-Date:' -e '^"PO-Revision-Date:' -e '^"X-Generator:'
}

echo "== 1. Committed POT matches what the source produces =="
if ! php dev-tools/build-pot.php >/dev/null 2>&1; then
  printf 'build-pot.php failed to run\n'
  fail=1
else
  for pot in flavor/languages/flavor.pot flavor-core/languages/flavor-core.pot; do
    [ -f "$pot" ] || continue
    name="$(basename "$pot")"
    # build-pot.php writes in place, so hold the committed copy and put it
    # back: a check must never leave the working tree dirty.
    cp "$pot" "$scratch/committed.pot"
    strip_volatile < "$scratch/committed.pot" > "$scratch/committed.txt"
    strip_volatile < "$pot" > "$scratch/rebuilt.txt"
    if cmp -s "$scratch/committed.txt" "$scratch/rebuilt.txt"; then
      printf '[PASS] %s درست و به‌روز است\n' "$name"
    else
      printf '[FAIL] %s با کد هم‌خوان نیست؛ dev-tools/build-pot.php را اجرا و نتیجه را کامیت کنید\n' "$name"
      diff -u "$scratch/committed.txt" "$scratch/rebuilt.txt" | head -30
      fail=1
    fi
    cp "$scratch/committed.pot" "$pot"
  done
fi

echo
echo "== 2. Committed .po files match what the generator produces =="
if command -v python3 >/dev/null 2>&1; then
  for po in flavor/languages/flavor-*.po; do
    [ -f "$po" ] || continue
    name="$(basename "$po")"
    # flavor-ar.po -> ar
    locale="${name#flavor-}"
    locale="${locale%.po}"
    cp "$po" "$scratch/committed.po"
    if python3 dev-tools/i18n/build-po.py "$locale" >/dev/null 2>&1; then
      # Same date-instability as the POT, hence the same stripping.
      strip_volatile < "$scratch/committed.po" > "$scratch/po-committed.txt"
      strip_volatile < "$po" > "$scratch/po-rebuilt.txt"
      if cmp -s "$scratch/po-committed.txt" "$scratch/po-rebuilt.txt"; then
        printf '[PASS] %s با مولد هم‌خوان است\n' "$name"
      else
        printf '[FAIL] %s با dev-tools/i18n/build-po.py هم‌خوان نیست\n' "$name"
        fail=1
      fi
    else
      printf '[FAIL] build-po.py برای %s ناموفق بود\n' "$name"
      fail=1
    fi
    # Same reason as above: restore what was committed.
    cp "$scratch/committed.po" "$po"
  done
else
  printf 'python3 در دسترس نیست؛ بررسیِ .po رد شد\n'
fi

echo "== 3. msgfmt accepts every catalogue =="
if command -v msgfmt >/dev/null 2>&1; then
  for po in flavor/languages/*.po flavor-core/languages/*.po; do
    [ -f "$po" ] || continue
    if msgfmt --check --strict -o "$scratch/check.mo" "$po" 2> "$scratch/msgfmt.err"; then
      printf '[PASS] %s از نظر msgfmt سالم است\n' "$(basename "$po")"
    else
      printf '[FAIL] msgfmt فایل %s را رد کرد:\n' "$(basename "$po")"
      sed 's/^/       /' "$scratch/msgfmt.err"
      fail=1
    fi
  done
else
  printf 'msgfmt در دسترس نیست؛ اعتبارسنجی رد شد (در CI نصب می‌شود)\n'
fi

echo
echo "== 4. Compiled .mo files exist for every .po =="
for po in flavor/languages/*.po; do
  [ -f "$po" ] || continue
  mo="${po%.po}.mo"
  if [ -f "$mo" ]; then
    printf '[PASS] %s کامپایل شده است\n' "$(basename "$mo")"
  else
    printf '[FAIL] %s کامپایل نشده؛ msgfmt -o %s %s را اجرا کنید\n' "$(basename "$po")" "$(basename "$mo")" "$(basename "$po")"
    fail=1
  fi
done

echo
if [ "$fail" -ne 0 ]; then
  printf 'بررسیِ ترجمه ناموفق بود.\n'
  exit 1
fi
printf 'بررسیِ ترجمه موفق بود.\n'
