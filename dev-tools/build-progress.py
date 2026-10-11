#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Rebuild docs/PROMPT-PROGRESS.md from the prompt collection + a status file.

The prompt collection (flavor-restaurant-fix-prompts.txt, 108 prompts in 13
phases) lives outside the repo; this keeps a machine-updatable checklist of it
inside the repo so every phase can be reported against the same numbering.

    python3 dev-tools/build-progress.py --prompts <file> [--status progress-status.json]

status JSON: { "7": {"state": "done", "note": "...", "commit": "abc1234"}, ... }
state ∈ done | partial | blocked | pending
"""
import argparse
import io
import json
import os
import re
import sys

PROMPT_HEAD = re.compile(r"^--- پرامپت ([۰-۹0-9]+) ---\s*$")
PHASE_HEAD = re.compile(r"^فاز ([۰-۹0-9]+) — (.+?)\s*$")
DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789")

STATE_MARK = {
    "done": "✅ انجام شد",
    "partial": "🟨 بخشی",
    "blocked": "⛔ مسدود",
    "pending": "⬜ انجام‌نشده",
}


def to_int(text):
    return int(text.translate(DIGITS))


def parse(path):
    """Yield (phase_no, phase_title, prompt_no, title, body)."""
    lines = io.open(path, encoding="utf-8").read().split("\n")
    phase_no, phase_title = 0, "پیش‌نیاز"
    out = []
    i = 0
    while i < len(lines):
        pm = PHASE_HEAD.match(lines[i])
        if pm:
            phase_no, phase_title = to_int(pm.group(1)), pm.group(2)
            i += 1
            continue
        m = PROMPT_HEAD.match(lines[i])
        if m:
            number = to_int(m.group(1))
            body = []
            i += 1
            while i < len(lines) and not PROMPT_HEAD.match(lines[i]) and not PHASE_HEAD.match(lines[i]):
                body.append(lines[i])
                i += 1
            text = "\n".join(body).strip()
            first = next((ln.strip() for ln in text.split("\n") if ln.strip()), "")
            out.append((phase_no, phase_title, number, first, text))
            continue
        i += 1
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--prompts", required=True)
    ap.add_argument("--status", default=None)
    ap.add_argument("--out", default="docs/PROMPT-PROGRESS.md")
    ap.add_argument("--repo", default=".")
    args = ap.parse_args()

    status = {}
    if args.status and os.path.exists(args.status):
        status = json.load(io.open(args.status, encoding="utf-8"))

    rows = parse(args.prompts)
    done = sum(1 for *_x, n in [(r[2], r[2]) for r in rows] if str(n) in status and status[str(n)]["state"] == "done")
    by_state = {"done": 0, "partial": 0, "blocked": 0, "pending": 0}
    for row in rows:
        entry = status.get(str(row[2]), {"state": "pending"})
        by_state[entry["state"]] = by_state.get(entry["state"], 0) + 1

    lines = [
        "# پیشرفت مجموعه پرامپت‌های رفع نواقص (prompt fix progress)",
        "",
        "مخزن: `patrikjuniyor/flavor-restaurant` — سند مرجع: `flavor-restaurant-fix-prompts.txt` "
        "(۱۰۸ پرامپت در ۱۳ فاز).",
        "",
        "| وضعیت | تعداد |",
        "|---|---:|",
        "| ✅ انجام‌شده | %d |" % by_state.get("done", 0),
        "| 🟨 بخشی انجام‌شده | %d |" % by_state.get("partial", 0),
        "| ⛔ مسدود (نیاز به محیط/تصمیم مالک) | %d |" % by_state.get("blocked", 0),
        "| ⬜ انجام‌نشده | %d |" % by_state.get("pending", 0),
        "| **مجموع** | **%d** |" % len(rows),
        "",
        "درصد پیشرفت کامل: **%d%%** (%d/%d)" % (
            round(100.0 * by_state.get("done", 0) / max(1, len(rows))),
            by_state.get("done", 0),
            len(rows),
        ),
        "",
        "این فایل با `python3 dev-tools/build-progress.py` از روی "
        "`progress-status.json` بازتولید می‌شود؛ ویرایش دستی را از دست می‌دهد.",
        "",
    ]

    current = None
    for phase_no, phase_title, number, title, body in rows:
        if number == 1 and current is None:
            current = -1
        if phase_no != current:
            current = phase_no
            lines += ["", "## فاز %s — %s" % (phase_no, phase_title), "",
                      "| پرامپت | وضعیت | خلاصه | یادداشت / کامیت |", "|---:|---|---|---|"]
        entry = status.get(str(number), {"state": "pending"})
        summary = title[:80].replace("|", "\\|")
        note = (entry.get("note", "") or "").replace("|", "\\|")
        commit = entry.get("commit", "")
        if commit:
            note = ("%s — `commit %s`" % (note, commit)) if note else ("`commit %s`" % commit)
        lines.append("| %d | %s | %s | %s |" % (number, STATE_MARK[entry["state"]], summary, note))

    out = os.path.join(args.repo, args.out)
    io.open(out, "w", encoding="utf-8").write("\n".join(lines).rstrip() + "\n")
    print("wrote %s (%d prompts, %d done)" % (args.out, len(rows), by_state.get("done", 0)))
    return 0


if __name__ == "__main__":
    sys.exit(main())
