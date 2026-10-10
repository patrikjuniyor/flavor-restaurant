#!/usr/bin/env bash
# The child theme must stay in step with the parent: same version, and a
# screenshot, or the themes screen shows a grey box and a stale number.
set -uo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
fail=0

parent_version="$(grep -m1 '^Version:' "$root/flavor/style.css" | sed 's/Version: *//' | tr -d '\r')"
child_version="$(grep -m1 '^Version:' "$root/flavor-child/style.css" | sed 's/Version: *//' | tr -d '\r')"

if [ "$parent_version" != "$child_version" ]; then
  printf 'نسخهٔ چایلدتم (%s) با قالب اصلی (%s) یکی نیست.\n' "$child_version" "$parent_version"
  fail=1
else
  printf '[PASS] نسخهٔ چایلدتم با قالب اصلی یکی است (%s)\n' "$parent_version"
fi

template="$(grep -m1 '^Template:' "$root/flavor-child/style.css" | sed 's/Template: *//' | tr -d '\r')"
if [ "$template" != "flavor" ]; then
  printf 'Template چایلدتم باید «flavor» باشد، ولی «%s» است.\n' "$template"
  fail=1
else
  printf '[PASS] Template چایلدتم روی «flavor» است\n'
fi

for file in screenshot.png style.css functions.php; do
  if [ ! -f "$root/flavor-child/$file" ]; then
    printf 'چایلدتم فایلِ لازم را ندارد: %s\n' "$file"
    fail=1
  else
    printf '[PASS] flavor-child/%s موجود است\n' "$file"
  fi
done

shot="$root/flavor-child/screenshot.png"
if [ -f "$shot" ]; then
  # Read the PNG header with the standard library only. Pillow is not on the
  # CI runner, and a missing import used to read as "0 0" and fail the build
  # with a misleading size message. The IHDR chunk holds width and height at
  # bytes 16-23; anything that is not a PNG is reported as 0 0 on purpose.
  dims="$(python3 - "$shot" <<'PY' 2>/dev/null
import struct, sys
with open(sys.argv[1], 'rb') as fh:
    head = fh.read(24)
if head[:8] != b'\x89PNG\r\n\x1a\n':
    raise SystemExit(1)
w, h = struct.unpack('>II', head[16:24])
print(w, h)
PY
)" || dims="0 0"
  read -r width height <<< "${dims:-0 0}"
  if [ "$width" != "1200" ] || [ "$height" != "900" ]; then
    printf 'اسکرین‌شاتِ چایلدتم باید ۱۲۰۰×۹۰۰ باشد، ولی %s×%s است.\n' "$width" "$height"
    fail=1
  else
    printf '[PASS] اسکرین‌شاتِ چایلدتم ۱۲۰۰×۹۰۰ است\n'
  fi
  size=$(stat -c%s "$shot")
  if [ "$size" -gt 1048576 ]; then
    printf 'اسکرین‌شاتِ چایلدتم %s بایت است؛ وردپرس زیرِ یک مگابایت را توصیه می‌کند.\n' "$size"
    fail=1
  else
    printf '[PASS] حجمِ اسکرین‌شات %s بایت است\n' "$size"
  fi
fi

if [ "$fail" -ne 0 ]; then
  printf '\nبررسیِ چایلدتم ناموفق بود.\n'
  exit 1
fi
printf '\nبررسیِ چایلدتم موفق بود.\n'
