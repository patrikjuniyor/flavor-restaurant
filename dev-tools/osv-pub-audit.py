#!/usr/bin/env python3
"""OSV.dev advisory audit for the mobile Flutter lockfile (P40).

Parses mobile/pubspec.lock, queries https://api.osv.dev/v1/query for every
locked package (direct and transitive) and exits non-zero on any hit. This
is the pub-side counterpart of `composer audit` / `npm audit`; CI's theme
gates cannot reach the network, so this is the documented manual step.

Usage:  python3 dev-tools/osv-pub-audit.py [path/to/pubspec.lock]
"""
import json
import re
import sys
import urllib.request
from pathlib import Path


def parse_lock(text: str):
    pkgs, cur = {}, None
    for line in text.splitlines():
        m = re.match(r"^  ([a-z0-9_]+):\s*$", line)
        if m:
            cur = m.group(1)
            pkgs[cur] = {}
            continue
        if cur:
            d = re.match(r'^    (dependency|version): "?([^"\n]+)"?\s*$', line)
            if d:
                pkgs[cur][d.group(1)] = d.group(2)
    return {k: v for k, v in pkgs.items() if "version" in v and "dependency" in v}


def osv_hit(name: str, version: str):
    body = json.dumps(
        {"package": {"name": name, "ecosystem": "Pub"}, "version": version}
    ).encode()
    req = urllib.request.Request(
        "https://api.osv.dev/v1/query",
        data=body,
        headers={"Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.load(r).get("vulns", [])


def main() -> int:
    default = Path(__file__).resolve().parent.parent / "mobile" / "pubspec.lock"
    lock = Path(sys.argv[1] if len(sys.argv) > 1 else default)
    if not lock.is_file():
        print(f"lockfile not found: {lock}", file=sys.stderr)
        return 2
    pkgs = parse_lock(lock.read_text(encoding="utf-8"))
    if not pkgs:
        print("lockfile parsed to ZERO packages — non-vacuity failed", file=sys.stderr)
        return 2
    hits = {}
    for name, info in sorted(pkgs.items()):
        try:
            vulns = osv_hit(name, info["version"])
        except Exception as exc:  # network errors must be loud, not silent
            hits[name] = (info["version"], [f"QUERY-ERROR {exc}"])
            continue
        if vulns:
            hits[name] = (info["version"], [v.get("id", "?") for v in vulns])
    scope = f"{len(pkgs)} locked packages"
    if hits:
        print(f"OSV ADVISORIES for {scope}:")
        for name, (ver, ids) in hits.items():
            print(f"  {name} {ver}: {', '.join(ids)}")
        return 1
    print(f"OSV clean: 0 advisories across all {scope}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
