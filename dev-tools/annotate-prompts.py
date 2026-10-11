#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Write per-phase progress back into the prompt collection itself.

The task file lives on Google Drive (with a tracked copy under docs/prompts/)
and its owner asked that completion be recorded *in the file*, not only in
chat. This stamps a status line under every phase header, plus a summary above
the usage guide, from the same source of truth docs/PROMPT-PROGRESS.md uses.

Idempotent by construction: every line this tool writes starts with MARKER,
they are dropped at the start of each run, and they are inserted immediately
after the "====" rule that closes a phase header without consuming any existing
line — so removing them restores the document byte for byte.

    python3 dev-tools/annotate-prompts.py \
        --prompts docs/prompts/flavor-restaurant-fix-prompts.txt \
        --status dev-tools/progress-status.json [--date 2026-10-11] [--check]

--check exits non-zero if the file is not up to date, which is what CI would
use to catch a phase that was finished but never reported.
"""
import argparse
import io
import json
import os
import re
import sys

PHASE_HEAD = re.compile(r"^فاز ([۰-۹0-9]+) — (.+?)\s*$")
PROMPT_HEAD = re.compile(r"^--- پرامپت ([۰-۹0-9]+) ---\s*$")
RULE = re.compile(r"^=+\s*$")
MARKER = ">>> "
STAMP = MARKER + "وضعیت این فاز:"
SUMMARY = MARKER + "وضعیت اجرا (خلاصهٔ کل):"
ANCHOR = "مخزن:"  # header-frame line the summary block is inserted under
LATIN = "0123456789"
PERSIAN = "۰۱۲۳۴۵۶۷۸۹"


def fa_num(value):
    """Persian digits, because every number in this document is Persian."""
    return str(value).translate(str.maketrans(LATIN, PERSIAN))


def to_int(text):
    return int(str(text).translate(str.maketrans(PERSIAN, LATIN)))


def parse_statuses(path):
    """{prompt_no: (state, commit, note)}; keys may be Persian digits.

    "_meta" is bookkeeping, not a prompt: it carries the date the stamps were
    written, so --check in CI reproduces the committed bytes instead of failing
    the day after a phase was closed.
    """
    raw = json.load(io.open(path, encoding="utf-8"))
    out = {}
    for key, val in raw.items():
        if not any(ch.isdigit() for ch in str(key)):
            continue
        out[to_int(key)] = (val.get("state", "pending"), val.get("commit", ""), val.get("note", ""))
    return out


def status_date(path):
    raw = json.load(io.open(path, encoding="utf-8"))
    meta = raw.get("_meta") or {}
    return meta.get("updated", "")


def phases_of(lines):
    """[(phase_no, title, first_prompt, last_prompt, rule_line_index)]."""
    found = []
    current = None
    for index, line in enumerate(lines):
        head = PHASE_HEAD.match(line)
        if head:
            end = index + 1
            current = [to_int(head.group(1)), head.group(2), None, None, end if end < len(lines) and RULE.match(lines[end]) else None]
            found.append(current)
            continue
        prompt = PROMPT_HEAD.match(line)
        if prompt and current is not None:
            number = to_int(prompt.group(1))
            current[2] = number if current[2] is None else current[2]
            current[3] = number
    complete = [p for p in found if p[2] is not None and p[4] is not None]
    if len(complete) != len(found):
        raise SystemExit("phase headers without a prompt list or a closing rule line: %d" % (len(found) - len(complete)))
    return complete


def stamp_lines(phase, statuses, today):
    _number, title, first, last, _rule = phase
    owned = list(range(first, last + 1))
    state_of = {n: statuses.get(n, ("pending", "", ""))[0] for n in owned}
    done = [n for n in owned if state_of[n] == "done"]
    partial = [n for n in owned if state_of[n] == "partial"]
    blocked = [n for n in owned if state_of[n] == "blocked"]
    total = len(owned)
    percent = int(round(100.0 * (len(done) + 0.5 * len(partial)) / total))

    if len(done) == total:
        verdict = "✅ کامل — %s از %s پرامپت (%s٪)" % (fa_num(total), fa_num(total), fa_num(percent))
    elif not done and not partial and not blocked:
        verdict = "⬜ شروع نشده — ۰ از %s پرامپت (۰٪)" % fa_num(total)
    else:
        bits = ["🟨 در جریان — %s از %s انجام شده (%s٪)" % (fa_num(len(done)), fa_num(total), fa_num(percent))]
        if partial:
            bits.append("بخشی: " + "، ".join(fa_num(n) for n in partial))
        if blocked:
            bits.append("مسدود: " + "، ".join(fa_num(n) for n in blocked))
        verdict = "؛ ".join(bits)

    lines = [STAMP + " " + verdict]
    commits = list(dict.fromkeys(statuses[n][1] for n in done if statuses[n][1]))
    if commits:
        # One hash per commit, not per prompt: a phase done in a single commit
        # should read as one commit.
        lines.append(MARKER + "کامیت‌های این فاز: " + " ".join(commits))
    lines.append(MARKER + "تاریخ به‌روزرسانی: %s — مرجع شمارش: dev-tools/progress-status.json" % today)
    return lines


def summary_line(statuses, total_prompts, phases, today):
    done = [n for n in statuses if statuses[n][0] == "done"]
    partial = [n for n in statuses if statuses[n][0] == "partial"]
    blocked = [n for n in statuses if statuses[n][0] == "blocked"]
    percent = int(round(100.0 * (len(done) + 0.5 * len(partial)) / total_prompts)) if total_prompts else 0
    per_phase = []
    for _num, _title, first, last, _rule in phases:
        all_n = last - first + 1
        done_n = len([n for n in range(first, last + 1) if statuses.get(n, ("pending",))[0] == "done"])
        per_phase.append("%s/%s" % (fa_num(done_n), fa_num(all_n)))
    text = SUMMARY + " از %s پرامپت %s تا انجام شده%s — %s٪ کامل. per-phase (فاز ۰ تا ۱۲): %s." % (
        fa_num(total_prompts),
        fa_num(len(done)),
        ("، %s تا بخشی و %s تا مسدود" % (fa_num(len(partial)), fa_num(len(blocked))) if (partial or blocked) else ""),
        fa_num(percent),
        " ، ".join(per_phase),
    )
    note = MARKER + "این خطوط و خطوط وضعیتِ فازها را dev-tools/annotate-prompts.py می‌نویسد (منبع: dev-tools/progress-status.json)؛ دستی ویرایششان نکنید — با python3 dev-tools/build-progress.py سند docs/PROMPT-PROGRESS.md هم بازتولید می‌شود."
    return [text, note, MARKER + "آخرین به‌روزرسانی: %s" % today]


def annotate(text, statuses, today):
    lines = [line for line in text.split("\n") if not line.startswith(MARKER)]
    phases = phases_of(lines)
    if not phases:
        raise SystemExit("no phase headers matched; refusing to write progress into the wrong file")

    total_prompts = len([line for line in lines if PROMPT_HEAD.match(line)])
    inserts = {}
    for phase in phases:
        inserts[phase[4]] = stamp_lines(phase, statuses, today)

    out = []
    for index, line in enumerate(lines):
        out.append(line)
        if index in inserts:
            out.extend(inserts[index])
    # Insert under the "مخزن:" line of the header frame, so the blank line that
    # already separates the frame from the usage guide stays where it was — that
    # is what makes a second run produce a byte-identical file.
    body = "\n".join(out)
    summary = summary_line(statuses, total_prompts, phases, today)
    header = [text for text in out if text.startswith(ANCHOR)]
    if not header:
        raise SystemExit("header anchor %r not found; refusing to guess where to write progress" % ANCHOR)
    anchor_line = header[0]
    return body.replace(anchor_line + "\n", anchor_line + "\n" + "\n".join(summary) + "\n", 1)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--prompts", required=True)
    ap.add_argument("--status", required=True)
    ap.add_argument("--date", default="")
    ap.add_argument("--check", action="store_true", help="fail if the stamp in the file is stale")
    args = ap.parse_args()

    if not os.path.isfile(args.prompts):
        raise SystemExit("prompt file not found: " + args.prompts)
    if not args.date:
        # Stamp date comes from the status file, not the clock: a CI --check run
        # the week after a phase closed must reproduce the committed bytes.
        args.date = status_date(args.status)
    if not args.date:
        import datetime

        args.date = datetime.date.today().isoformat()
    statuses = parse_statuses(args.status)
    original = io.open(args.prompts, encoding="utf-8").read()
    updated = annotate(original, statuses, args.date)

    if args.check:
        if updated != original:
            print("stale: %s does not match progress-status.json — re-run without --check" % args.prompts)
            return 1
        print("up to date: %s" % args.prompts)
        return 0

    io.open(args.prompts, "w", encoding="utf-8").write(updated)
    stamps = len([line for line in updated.split("\n") if line.startswith(MARKER)])
    print("wrote %d status line(s) into %s (%d prompts tracked)" % (stamps, args.prompts, len(statuses)))
    return 0


if __name__ == "__main__":
    sys.exit(main())
