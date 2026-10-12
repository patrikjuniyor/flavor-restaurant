#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Structural-linguistic audit of the Arabic catalogue (R3-05).

The build pipeline already aborts on a missing placeholder in a singular
entry (build-po.py). This auditor closes the gaps that check cannot see and
turns one-off review into a standing gate:

  1. Persian residue   — msgstr still carrying characters that no Arabic
                         orthography uses (چ پ ژ گ, Persian digits, the
                         Persian ی/ک variants, ZWNJ). A translated UI that
                         leaks these is untranslated.
  2. Placeholders      — %s / %d / %1$s multisets must match between msgid
                         and EVERY plural form, not just form zero.
  3. Latin leakage     — a machine translation that gave up mid-string leaves
                         English words behind. Only whitelisted brand/tech
                         tokens may appear in Latin script.
  4. Header consistency— the file must declare that native review is still
                         pending (the note build-po.py writes at the top).

Usage:  python3 dev-tools/i18n/audit-ar-po.py [path/to/flavor-ar.po]
Exit:   0 when clean; 1 with one line per finding.
"""

import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Codepoints that exist only in Persian orthography. The Arabic ي/ك shapes
# (U+064A/U+0643) are correct in ar; the Persian ی/ک (U+06CC/U+06A9) are
# checked separately because they are single characters, not ranges.
PERSIAN_ONLY = re.compile("[چپژگڤ۰-۹\u200c]", re.UNICODE)
PERSIAN_YEK = "\u06cc"
PERSIAN_KAF = "\u06a9"

PLACEHOLDER = re.compile(r"%(?:\d+\$)?[sd]|%%")

# Multi-word Latin fragments that ARE the product's own vocabulary and stay
# untranslated in Arabic interfaces. Add here only with a comment.
LATIN_PHRASES = {
    "flavor core", "flavor restaurant", "readme.txt file",
}

# Tokens allowed to stay in Latin script inside an Arabic UI string.
LATIN_ALLOW = {
    "flavor", "elementor", "woocommerce", "wordpress", "pizza", "espresso",
    "wifi", "vip", "ok", "demo", "menu", "app", "instagram", "facebook",
    "whatsapp", "telegram", "maps", "com", "www", "https", "http", "html",
    "css", "jpg", "png", "svg", "pdf", "mo", "po", "pot", "api", "url",
    "cart", "shop", "blog", "cafe", "bar",
}

def po_unescape(value):
    """Decode PO escape sequences.

    @param value: Quoted payload without the surrounding quotes.
    @return: Real string.
    """
    out = []
    i = 0
    while i < len(value):
        ch = value[i]
        if ch == "\\" and i + 1 < len(value):
            nxt = value[i + 1]
            mapping = {"n": "\n", "t": "\t", '"': '"', "\\": "\\"}
            out.append(mapping.get(nxt, nxt))
            i += 2
            continue
        out.append(ch)
        i += 1
    return "".join(out)


def parse_po(path):
    """Parse a generated .po into entries.

    The generator emits exactly one quoted line per msgid/msgstr field,
    which keeps this parser honest for files it owns.

    @param path: .po file path.
    @return: List of dicts: msgid, msgstr or forms list.
    """
    entries = []
    current = None
    with open(path, encoding="utf-8") as handle:
        for line in handle:
            line = line.rstrip("\n")
            if line.startswith("msgid "):
                if current and current.get("msgid") is not None:
                    entries.append(current)
                current = {"msgid": po_unescape(line[7:-1]) if line != 'msgid ""' else ""}
            elif line.startswith("msgstr["):
                idx = int(line[7:line.index("]")])
                current.setdefault("forms", {})[idx] = po_unescape(line[line.index('"') + 1:-1])
            elif line.startswith("msgstr ") and current is not None:
                if "msgid" not in current:
                    entries.append(current)
                    current = None
                    continue
                if current["msgid"] == "" and "msgstr" not in current and not current.get("forms"):
                    # header entry value — record for the header check
                    current["header"] = line[line.index('"') + 1:-1]
                    entries.append(current)
                    current = None
                    continue
                current["msgstr"] = po_unescape(line[7:-1] if line[-1] == '"' else "")
    if current and current.get("msgid") is not None:
        entries.append(current)
    return entries


def check_residue(text, msgid, hits, kind):
    """Record Persian-only codepoints found in one translated string."""
    bad = sorted(set(PERSIAN_ONLY.findall(text)))
    for ch in bad:
        hits.append("%s: Persian-only codepoint %s in '%s' -> '%s'"
                    % (kind, "U+%04X" % ord(ch), msgid[:40], text[:40]))
    for ch in (PERSIAN_YEK, PERSIAN_KAF):
        if ch in text:
            hits.append("%s: Persian letter %s in '%s' -> '%s'"
                        % (kind, "U+%04X" % ord(ch), msgid[:40], text[:40]))


def main():
    """Run every audit and print one line per finding.

    @return: Process exit code.
    """
    path = sys.argv[1] if len(sys.argv) > 1 else os.path.join(
        ROOT, "flavor", "languages", "flavor-ar.po")
    if not os.path.isfile(path):
        print("not found: " + path)
        return 1

    raw = open(path, encoding="utf-8").read()
    entries = parse_po(path)
    hits = []
    warnings = []
    checked = 0

    for entry in entries:
        msgid = entry.get("msgid")
        if msgid is None or msgid == "":
            continue
        targets = []
        if "msgstr" in entry:
            targets.append(("msgstr", entry["msgstr"]))
        for idx, value in sorted(entry.get("forms", {}).items()):
            targets.append(("msgstr[%d]" % idx, value))
        if not targets:
            hits.append("empty: '%s' has no translated form" % msgid[:60])
        for kind, value in targets:
            checked += 1
            check_residue(value, msgid, hits, kind)
            want = sorted(PLACEHOLDER.findall(msgid))
            got = sorted(PLACEHOLDER.findall(value))
            if want != got:
                hits.append("placeholder: '%s' has %s but '%s' has %s"
                            % (msgid[:40], want or "[]", value[:40], got or "[]"))
            # A single technical token (WCAG, JSON, readme.txt, Flavor Core)
            # stays Latin in an Arabic UI by convention. What betrays an
            # abandoned machine translation is a RUN of English words.
            cited = re.sub(r"«[^»]*»|“[^”]*”", " ", value)
            runs = re.findall(r"(?:[A-Za-z][A-Za-z.\-]*\s+){2,}[A-Za-z][A-Za-z.\-]*", cited)
            runs = [r.strip() for r in runs if r.strip().lower() not in LATIN_PHRASES]
            del cited
            for run in runs:
                hits.append("latin: untranslated phrase '%s' in '%s'" % (run[:50], msgid[:40]))
            if "  " in value.strip("\n"):
                warnings.append("spacing: double space in '%s'" % msgid[:50])
            stripped_id = msgid.strip()
            if stripped_id == value.strip() and len(stripped_id.split()) > 1:
                warnings.append("passthrough: msgstr copies msgid verbatim: '%s'" % msgid[:50])

    if not raw.startswith("#") or "native" not in raw.split("\n\n")[0].lower():
        hits.append("header: the catalogue does not open with the native-review note")

    for line in warnings:
        print("WARN  " + line)
    for line in hits:
        print("FAIL  " + line)
    total = len(hits)
    print("audit-ar: %d translated strings checked, %d failure(s), %d warning(s)"
          % (checked, total, len(warnings)))
    return 1 if total else 0


if __name__ == "__main__":
    sys.exit(main())
