#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Scan Persian source strings for the typo classes prompt 12 lists.

Reports only; it never edits. Run it before and after a sweep so the drop in
the count is auditable, and add it to CI so a new string cannot re-introduce
one of these classes.

    python3 dev-tools/i18n/scan-persian-typos.py           # summary, fail on hits
    python3 dev-tools/i18n/scan-persian-typos.py --list    # every hit
    python3 dev-tools/i18n/scan-persian-typos.py --docs    # also scan markdown

A string that legitimately contains an Arabic letter (a search box that tells
users «ي» and «ك» are accepted too) opts out with a trailing comment:

    i18n-scan:ignore

Exit code is 0 when nothing is left to fix, 1 otherwise.
"""
import argparse
import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Only translatable string literals count: __( '…', 'domain' ) and friends.
CALL = re.compile(
    r"""(?:__|_e|_x|_ex|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_n|_nx)"""
    r"""\s*\(\s*(?P<q>['"])(?P<text>(?:\\.|[^\\])*?)(?P=q)""",
    re.S,
)

IGNORE = "i18n-scan:ignore"

CHECKS = [
    (
        "arabic-letters-in-persian",
        re.compile(r"[يكى]"),
        "Arabic ي/ك/ى inside a Persian string (Persian ی/ک expected)",
    ),
    (
        "space-before-punctuation",
        re.compile("(?<=\\S)[ \\t\\u200c]+[،؛!?.:](?=\\s|$)"),
        "space before punctuation",
    ),
    (
        "verb-without-zwnj",
        re.compile(r"(?:می|نمی) (?:شود|شوند|شد|شدند|شده|شونده|گردد|گردند|کند|کنند|کرد|کردند|دهد|دهند|داشت|گیرد|گیرند|باشد|باشند|تواند|توانند)"),
        "«می شود» should be «می‌شود» (ZWNJ, not space)",
    ),
    (
        "compound-without-zwnj",
        re.compile(r"به روز رسانی|زير مجموعه|بالا دستی"),
        "compound written with spaces instead of ZWNJ",
    ),
    (
        "double-space",
        re.compile(r"[^\n] {2,}[^ *`|]"),
        "double space inside a UI string",
    ),
    (
        "repeated-punctuation",
        re.compile(r"\.{3,}|،،|;;"),
        "repeated punctuation",
    ),
    (
        "stray-foreign-word",
        re.compile(r"(?<![A-Za-z/\\._\-])(?:t[íi]tulo|contrase[ñn]a|env[íi]o|comida)(?![A-Za-z])"),
        "untranslated foreign word left in a Persian string",
    ),
]


def php_files():
    for base in ("flavor", "flavor-core", "flavor-child"):
        for dirpath, dirnames, filenames in os.walk(os.path.join(ROOT, base)):
            dirnames[:] = [d for d in dirnames if d not in ("node_modules", "vendor", "languages")]
            for name in sorted(filenames):
                if name.endswith(".php"):
                    yield os.path.join(dirpath, name)


def md_files():
    for pattern in ("README.md", os.path.join("docs", "fa", "*.md"), os.path.join("docs", "raastichin", "*.md")):
        import glob as _glob

        for path in sorted(_glob.glob(os.path.join(ROOT, pattern))):
            yield path


def scan(path, rel, hits, check_markdown_text=False):
    text = io.open(path, encoding="utf-8").read()
    lines = text.split("\n")
    if check_markdown_text:
        blobs = [(i, line, line) for i, line in enumerate(lines)]
    else:
        blobs = []
        for match in CALL.finditer(text):
            blobs.append((text.count("\n", 0, match.start("text")), match.group("text"), text))
    for lineno, blob, _ in blobs:
        if IGNORE in (lines[lineno] if lineno < len(lines) else ""):
            continue
        for name, pattern, why in CHECKS:
            for found in pattern.finditer(blob):
                hits.append((name, rel, lineno + 1, found.group(0)[:70], why))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--list", action="store_true", help="print every hit, not just the tally")
    ap.add_argument("--docs", action="store_true", help="also scan README.md and docs/fa/*.md")
    args = ap.parse_args()

    hits = []
    for path in php_files():
        scan(path, os.path.relpath(path, ROOT), hits)
    if args.docs:
        for path in md_files():
            scan(path, os.path.relpath(path, ROOT), hits, check_markdown_text=True)

    tally = {}
    for name, _rel, _line, _snippet, _why in hits:
        tally[name] = tally.get(name, 0) + 1

    print("Persian string hygiene — %s" % ("PHP sources + docs" if args.docs else "translatable strings in PHP sources"))
    print("=" * 62)
    for name, _pattern, _why in CHECKS:
        print("%-26s %4d" % (name, tally.get(name, 0)))
    print("%-26s %4d" % ("total", len(hits)))
    if args.list:
        print("")
        for name, rel, line, snippet, why in hits:
            print("%-26s %s:%d  %s\n%26s   %s" % (name, rel, line, repr(snippet), "", why))
    if not hits:
        print("\nپاک — هیچ‌کدام از این طبقات باقی نمانده است.")
    return 1 if hits else 0


if __name__ == "__main__":
    sys.exit(main())
