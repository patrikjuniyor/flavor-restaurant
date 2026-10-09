#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Build a .po catalogue for one language from flavor.pot plus translations.

Why a generator instead of a hand-edited .po:

  * A missed placeholder (%s, %1$s) silently corrupts the message at
    runtime. Here it aborts the build instead.
  * A renamed source string would quietly orphan its translation in a
    hand-edited .po — the entry stays in the file and never applies. Here
    an unknown key aborts too.
  * Untranslated entries are emitted empty, which is how WordPress falls
    back to the source language. Prefilling them with the Persian text
    would make the catalogue look complete while translating nothing.

Usage:
    python3 dev-tools/i18n/build-po.py            # all languages
    python3 dev-tools/i18n/build-po.py ar         # one language
"""

import os
import re
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Arabic plurals: zero, one, two, few, many, other.
LANGUAGES = {
    "ar": {
        "name": "Arabic",
        "team": "Flavor Translations",
        "plural_forms": "nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 "
                        ": n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);",
    },
}

PLACEHOLDER = re.compile(
    r"%(?:\d+\$(?:\d+)?)?[sdoxXeEfFgGbcC%]|%1\$s|%\d+\$s|%s|%d"
)


def load_translations(locale):
    """Import the translation parts for one locale.

    @param locale: Language code.
    @return: (dict of msgid -> msgstr, dict of msgid -> list of plural forms)
    """
    folder = os.path.join(ROOT, "dev-tools", "i18n")
    singular = {}
    plurals = {}
    for part in sorted(os.listdir(folder)):
        if not part.startswith(locale + "-part") or not part.endswith(".py"):
            continue
        namespace = {}
        path = os.path.join(folder, part)
        with open(path, encoding="utf-8") as handle:
            exec(compile(handle.read(), path, "exec"), namespace)  # noqa: S102
        for key, value in namespace.items():
            if key.startswith("PART_"):
                singular.update(value)
            elif key == "PLURALS":
                plurals.update(value)
    return singular, plurals


def parse_pot(path):
    """Parse a POT file into a list of (msgid, msgid_plural, comment, refs).

    @param path: Path to the .pot file.
    @return: List of entries.
    """
    with open(path, encoding="utf-8") as handle:
        text = handle.read()

    entries = []
    for block in text.split("\n\n"):
        if not block.strip():
            continue
        msgid = None
        plural = None
        refs = []
        comments = []
        for line in block.split("\n"):
            if line.startswith("#:"):
                refs.append(line[2:].strip())
            elif line.startswith("#."):
                comments.append(line[2:].strip())
            matched = re.match(r'^msgid "(.*)"$', line)
            if matched and msgid is None:
                msgid = matched.group(1)
                continue
            matched = re.match(r'^msgid_plural "(.*)"$', line)
            if matched:
                plural = matched.group(1)
        if msgid is None or "" == msgid:
            # The header block is the one entry with an empty msgid; every
            # real message has text. Letting it through emits a second
            # `msgid ""` and msgfmt rejects the file as a duplicate.
            continue
        entries.append((msgid, plural, comments, refs))
    return entries


def unescape(value):
    """Turn POT escape sequences back into real characters.

    @param value: Escaped string.
    @return: Decoded string.
    """
    return (
        value.replace('\\"', '"')
        .replace("\\n", "\n")
        .replace("\\t", "\t")
        .replace("\\\\", "\\")
    )


def escape(value):
    """Escape a string for a .po file.

    @param value: Raw string.
    @return: Escaped string.
    """
    return (
        value.replace("\\", "\\\\")
        .replace('"', '\\"')
        .replace("\n", "\\n")
        .replace("\t", "\\t")
    )


def placeholders_of(text):
    """Extract the ordered placeholder tokens from a string.

    @param text: Message text.
    @return: List of placeholder tokens.
    """
    return re.findall(r"%(?:\d+\$)?[sd]|%s|%d", text)


def main():
    """Build the catalogues.

    @return: Process exit code.
    """
    targets = sys.argv[1:] or sorted(LANGUAGES)
    pot_path = os.path.join(ROOT, "flavor", "languages", "flavor.pot")
    entries = parse_pot(pot_path)
    failed = False

    for locale in targets:
        if locale not in LANGUAGES:
            print("Unknown locale: " + locale)
            return 1
        config = LANGUAGES[locale]
        singular, plurals = load_translations(locale)

        known = set(singular) | set(plurals)
        unknown = known - {unescape(e[0]) for e in entries}
        if unknown:
            print("\n%s: %d translation key(s) match no msgid in the POT:" % (locale, len(unknown)))
            for key in sorted(unknown):
                print("   " + key[:90])
            print("A renamed source string means its translation now applies to nothing.")
            failed = True

        lines = [
            "# Translation of Flavor in " + config["name"] + ".",
            "# Copyright (C) Flavor",
            "# This file is distributed under the GPL-2.0-or-later.",
            "#",
            "# Source strings are Persian; this catalogue is translated FROM Persian.",
            'msgid ""',
            'msgstr ""',
            '"Project-Id-Version: Flavor\\n"',
            '"Report-Msgid-Bugs-To: https://github.com/patrikjuniyor/flavor-restaurant/issues\\n"',
            '"PO-Revision-Date: %s\\n"' % time.strftime("%Y-%m-%d %H:%M+0000", time.gmtime()),
            '"Last-Translator: Flavor Translations\\n"',
            '"Language-Team: ' + config["team"] + '\\n"',
            '"Language: ' + locale + '\\n"',
            '"MIME-Version: 1.0\\n"',
            '"Content-Type: text/plain; charset=UTF-8\\n"',
            '"Content-Transfer-Encoding: 8bit\\n"',
            '"Plural-Forms: ' + config["plural_forms"] + '\\n"',
            '"X-Generator: Flavor build-po.py\\n"',
            "",
        ]

        translated = 0
        total = 0

        for msgid, plural, comments, refs in entries:
            source = unescape(msgid)
            total += 1
            lines.append("#: " + " ".join(refs))
            for comment in comments:
                lines.append("#. " + comment)
            lines.append('msgid "' + msgid + '"')

            if plural:
                source_plural = unescape(plural)
                lines.append('msgid_plural "' + plural + '"')
                forms = plurals.get(source)
                if forms:
                    expected = int(re.search(r"nplurals=(\d+)", config["plural_forms"]).group(1))
                    if len(forms) != expected:
                        print("\n%s: '%s' has %d plural forms, the language needs %d"
                              % (locale, source, len(forms), expected))
                        failed = True
                        forms = None
                if forms:
                    for index, form in enumerate(forms):
                        if placeholders_of(source) != placeholders_of(form) and index == 0:
                            print("\n%s: placeholder mismatch in '%s'" % (locale, source))
                            failed = True
                        lines.append('msgstr[%d] "%s"' % (index, escape(form)))
                    translated += 1
                else:
                    for index in range(int(re.search(r"nplurals=(\d+)", config["plural_forms"]).group(1))):
                        lines.append('msgstr[%d] ""' % index)
                    del source_plural
            else:
                value = singular.get(source)
                if value:
                    if placeholders_of(source) != placeholders_of(value):
                        print("\n%s: placeholder mismatch" % locale)
                        print("   msgid: " + source)
                        print("   msgstr: " + value)
                        failed = True
                    lines.append('msgstr "' + escape(value) + '"')
                    translated += 1
                else:
                    # Empty means "not translated yet", which is how
                    # WordPress falls back to the source string.
                    lines.append('msgstr ""')

            lines.append("")

        out_path = os.path.join(ROOT, "flavor", "languages", "flavor-" + locale + ".po")
        with open(out_path, "w", encoding="utf-8") as handle:
            handle.write("\n".join(lines))

        percent = 100.0 * translated / total if total else 0
        print("%-8s %4d/%d strings  (%5.1f%%)  ->  %s"
              % (locale, translated, total, percent, os.path.relpath(out_path, ROOT)))

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
