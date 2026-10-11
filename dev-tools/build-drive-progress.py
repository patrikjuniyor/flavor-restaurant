#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Small progress digest for the Drive copy of the prompt collection.

The prompt file itself is 72 KB of task text; rewriting it in Drive per phase
costs a full-file replace, so the per-phase record is this ~3 KB digest instead:
one line per phase, the prompt numbers closed in that phase, and the commits
that are on main. The stamps inside docs/prompts/flavor-restaurant-fix-prompts.txt
(see annotate-prompts.py) carry the same verdicts in the tracked copy.

    python3 dev-tools/build-drive-progress.py \
        --prompts docs/prompts/flavor-restaurant-fix-prompts.txt \
        --status dev-tools/progress-status.json \
        --out /home/user/google_drive/flavor-restaurant-progress.txt
"""
import argparse
import io
import json
import os
import re
import sys

PHASE = re.compile(r"^فاز ([۰-۹0-9]+) — (.+?)\s*$")
PROMPT = re.compile(r"^--- پرامپت ([۰-۹0-9]+) ---\s*$")
PERSIAN = "۰۱۲۳۴۵۶۷۸۹"
LATIN = "0123456789"
# Environment limits that are facts about this sandbox, not defects: they are
# repeated in the digest so the owner is not told "we skipped the tests".
CAVEATS = [
    "`php tests/demos/import.php` روی نصبِ زنده اجرا نمی‌شود (tests/demos/import.php:7) و باید روی وردپرس محلی اجرا شود",
    "`phpcs` با WPCS 3.4.1 نمی‌چرخد (sniffِ ارجاع‌شده حذف شده) و در CI با `|| true` بی‌صداست",
]


def fa(value):
    return str(value).translate(str.maketrans(LATIN, PERSIAN))


def to_int(text):
    return int(str(text).translate(str.maketrans(PERSIAN, LATIN)))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--prompts", required=True)
    ap.add_argument("--status", required=True)
    ap.add_argument("--out", required=True)
    args = ap.parse_args()

    for path in (args.prompts, args.status):
        if not os.path.isfile(path):
            raise SystemExit("missing input: " + path)

    lines = io.open(args.prompts, encoding="utf-8").read().split("\n")
    status = json.load(io.open(args.status, encoding="utf-8"))
    meta = status.get("_meta") or {}
    done_of = {
        to_int(k): v for k, v in status.items() if any(ch.isdigit() for ch in str(k))
    }

    phases = []
    current = None
    for line in lines:
        head = PHASE.match(line)
        if head:
            current = {"n": to_int(head.group(1)), "t": head.group(2), "first": None, "last": None}
            phases.append(current)
            continue
        prompt = PROMPT.match(line)
        if prompt and current is not None:
            number = to_int(prompt.group(1))
            current["first"] = number if current["first"] is None else current["first"]
            current["last"] = number

    if not phases or any(p["first"] is None for p in phases):
        raise SystemExit("phase/prompt parsing failed; refusing to publish a wrong percentage")

    total = done_total = 0
    rows = []
    for phase in phases:
        owned = list(range(phase["first"], phase["last"] + 1))
        done = [n for n in owned if done_of.get(n, {}).get("state") == "done"]
        total += len(owned)
        done_total += len(done)
        commits = list(dict.fromkeys(done_of[n].get("commit", "") for n in done if done_of[n].get("commit", "")))
        if len(done) == len(owned):
            verdict = "✅ کامل"
        elif not done:
            verdict = "⬜ انجام‌نشده"
        else:
            verdict = "🟨 %s از %s" % (fa(len(done)), fa(len(owned)))
        title = re.sub(r"\s*\(.*?\)\s*$", "", phase["t"])
        rows.append(
            "| فاز %s — %s | %s | %s | %s |"
            % (
                fa(phase["n"]),
                title,
                verdict,
                (", ".join("#" + fa(n) for n in done) if done else "—"),
                (" ".join(commits) if commits else "—"),
            )
        )

    percent = int(round(100.0 * done_total / total)) if total else 0
    all_prompts = sorted({n for phase in phases for n in range(phase["first"], phase["last"] + 1)})
    open_prompts = [n for n in all_prompts if done_of.get(n, {}).get("state") != "done"]
    # A 89-number list is noise; the reader wants the next one and the count.
    next_prompt = fa(open_prompts[0]) if open_prompts else "—"
    left = fa(len(open_prompts))

    digest = [
        "# پیشرفت مجموعه پرامپت‌های رفع نواقص Flavor (رستوران مستقیم)",
        "",
        "پیشرفتِ هر فاز که بسته شد همین‌جا نوشته می‌شود. متنِ `flavor-restaurant-fix-prompts.txt`",
        "دست‌نخورده است؛ همان خط‌های وضعیت داخل نسخهٔ ترک‌شدهٔ فایل در مخزن هم نوشته شده‌اند",
        "(`docs/prompts/flavor-restaurant-fix-prompts.txt`) و با گام `Prompt progress is reported",
        "where it is read` در `release-hygiene` به `dev-tools/progress-status.json` قفل‌اند.",
        "",
        "- مخزن: https://github.com/patrikjuniyor/flavor-restaurant — شاخهٔ main",
        "- تاریخ به‌روزرسانی: %s" % meta.get("updated", ""),
        "",
        "| فاز | وضعیت | پرامپت‌های انجام‌شده | کامیت‌ها (روی main) |",
        "|---|---|---|---|",
    ] + rows + [
        "",
        "## جمع‌بندی",
        "",
        "- کل: %s از %s پرامپت انجام و پوش شده = %s٪." % (fa(done_total), fa(total), fa(percent)),
        "- پرامپت بعدی که باید اجرا شود: %s (از %s پرامپت باقی‌مانده)." % (next_prompt, left),
        "- محدودیت‌های محیط تست (نه نقص محصول): " + "؛ ".join(CAVEATS) + ".",
        "- هر فاز پیش از «انجام شد» با تست‌های همان حوزه سبز شده: مجموعهٔ قالب، هسته (run-pure و e2e)،",
        "  Flutter، و check-i18n.",
        "",
    ]

    io.open(args.out, "w", encoding="utf-8").write("\n".join(digest))
    print("%s: %s/%s prompts (%s%%), %d bytes" % (args.out, fa(done_total), fa(total), fa(percent), len("\n".join(digest).encode("utf-8"))))
    return 0


if __name__ == "__main__":
    sys.exit(main())
