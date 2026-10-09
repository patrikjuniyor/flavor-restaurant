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
  read -r width height < <(python3 -c "
from PIL import Image
im = Image.open('$shot')
print(im.size[0], im.size[1])
" 2>/dev/null || echo "0 0")
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
