#!/usr/bin/env python3
"""
heratio-capture.py — terminal capture helper for Heratio.

A pragmatic alternative to a full Tauri/Electron desktop app: a text-based
UI that walks an archivist through browsing destinations and uploading
files via the Scan API. Works anywhere Python 3 does and depends only on
`requests`.

Interactive mode (default):
    heratio-capture.py
      → prompts for Heratio URL + API key (or reads config)
      → lets you search for a parent information object
      → accepts file paths (or shell-glob) and uploads each
      → prints a summary with IO links

One-shot mode (for scripts / VueScan post-scan hook):
    heratio-capture.py --parent-slug=fonds-smith --identifier=ARC-001 FILE.tiff

Config file (~/.heratio-scan.conf) supported — same format as the other
wrapper scripts (KEY=value lines).

Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
Licensed under the GNU AGPL v3.
"""

import argparse
import json
import os
import shlex
import sys
from pathlib import Path

try:
    import requests
except ImportError:
    sys.stderr.write("heratio-capture.py needs `requests` (pip install requests)\n")
    sys.exit(1)


def load_config():
    path = Path(os.environ.get('HERATIO_SCAN_CONFIG', Path.home() / '.heratio-scan.conf'))
    if path.is_file():
        for line in path.read_text().splitlines():
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            k, _, v = line.partition('=')
            os.environ.setdefault(k.strip(), v.strip().strip('"').strip("'"))


def prompt(message: str, default: str = '') -> str:
    s = input(f"{message}" + (f" [{default}]" if default else "") + ": ").strip()
    return s or default


def choose_parent(base: str, key: str) -> dict | None:
    query = prompt("Search parent (identifier, title, slug — blank to skip)")
    if not query:
        return None
    r = requests.get(
        f"{base}/api/v2/scan/destinations",
        headers={'X-API-Key': key},
        params={'q': query},
        timeout=30,
    )
    r.raise_for_status()
    rows = r.json().get('data', [])
    if not rows:
        print("  no matches.")
        return None
    for i, row in enumerate(rows[:20]):
        identifier = f" [{row['identifier']}]" if row.get('identifier') else ""
        print(f"  {i+1:2d}. {row['title']}{identifier} — /{row['slug']}")
    sel = prompt(f"Pick 1-{min(20, len(rows))}", "1")
    try:
        idx = int(sel) - 1
        return rows[idx]
    except (ValueError, IndexError):
        return None


def create_session(base: str, key: str, parent_id: int | None,
                   sector: str, standard: str, auto_commit: bool) -> str:
    body = {'sector': sector, 'standard': standard, 'auto_commit': auto_commit}
    if parent_id:
        body['parent_id'] = parent_id
    r = requests.post(
        f"{base}/api/v2/scan/sessions",
        headers={'X-API-Key': key, 'Content-Type': 'application/json'},
        data=json.dumps(body),
        timeout=30,
    )
    r.raise_for_status()
    return r.json()['data']['token']


def upload_file(base: str, key: str, token: str, file_path: Path,
                identifier: str, title: str, sidecar: Path | None) -> dict:
    meta = {}
    if identifier: meta['identifier'] = identifier
    if title:      meta['title'] = title

    files = {'file': (file_path.name, file_path.open('rb'))}
    if sidecar and sidecar.is_file():
        files['sidecar'] = (sidecar.name, sidecar.open('rb'), 'application/xml')
    data = {'metadata': json.dumps(meta)} if meta else {}

    r = requests.post(
        f"{base}/api/v2/scan/sessions/{token}/files",
        headers={'X-API-Key': key},
        files=files,
        data=data,
        timeout=600,
    )
    if r.status_code >= 400:
        return {'success': False, 'error': f"HTTP {r.status_code}: {r.text[:400]}"}
    return r.json()


def get_status(base: str, key: str, token: str) -> dict:
    r = requests.get(f"{base}/api/v2/scan/sessions/{token}", headers={'X-API-Key': key}, timeout=30)
    r.raise_for_status()
    return r.json()['data']


def interactive() -> int:
    print("Heratio Capture — terminal helper (Ctrl-C to quit at any time)")
    base = prompt("Heratio URL", os.environ.get('HERATIO_URL', 'https://heratio.theahg.co.za'))
    key = os.environ.get('HERATIO_API_KEY') or prompt("API key (scan:write)")
    if not base or not key:
        sys.stderr.write("Cannot continue without URL + API key\n")
        return 1

    parent = choose_parent(base, key)
    parent_id = parent['id'] if parent else None
    if parent:
        print(f"  → using parent: {parent['title']} (id={parent['id']})")

    sector = prompt("Sector (archive | library | gallery | museum)",
                    os.environ.get('HERATIO_SECTOR', 'archive'))
    standard = prompt("Descriptive standard",
                      os.environ.get('HERATIO_STANDARD', 'isadg'))

    token = create_session(base, key, parent_id, sector, standard, auto_commit=True)
    print(f"  → session: {token}")

    uploaded = []
    while True:
        raw = prompt("File to upload (blank to finish, or 'status' to poll)")
        if not raw:
            break
        if raw == 'status':
            s = get_status(base, key, token)
            print("  " + ", ".join(f"{k}={v}" for k, v in (s.get('counts') or {}).items()))
            continue
        # Support multiple space-separated paths via shell-style split
        for p in shlex.split(raw):
            path = Path(p).expanduser()
            if not path.is_file():
                print(f"  ✗ not a file: {path}")
                continue
            ident = prompt(f"    identifier for {path.name}", "")
            title = prompt(f"    title     for {path.name}", ident)
            sidecar_str = prompt(f"    sidecar xml (optional)", "")
            sidecar = Path(sidecar_str).expanduser() if sidecar_str else None
            res = upload_file(base, key, token, path, ident, title, sidecar)
            if res.get('success'):
                print(f"    ✓ uploaded, file id {res['data']['ingest_file_id']}")
                uploaded.append((path.name, res['data']['ingest_file_id']))
            else:
                print(f"    ✗ {res.get('error', 'unknown error')}")

    if uploaded:
        print(f"\nSession {token}: {len(uploaded)} upload(s) dispatched")
        print(f"  Status: {base}/api/v2/scan/sessions/{token}")
        print(f"  Inbox:  {base}/admin/scan/inbox?folder=")
    return 0


def one_shot(argv: list[str]) -> int:
    ap = argparse.ArgumentParser(description="Upload one file to Heratio via the Scan API.")
    ap.add_argument('file', help="Path to the scan file")
    ap.add_argument('--parent-id', type=int, default=None)
    ap.add_argument('--parent-slug', default=None)
    ap.add_argument('--sector', default=os.environ.get('HERATIO_SECTOR', 'archive'))
    ap.add_argument('--standard', default=os.environ.get('HERATIO_STANDARD', 'isadg'))
    ap.add_argument('--identifier', default='')
    ap.add_argument('--title', default='')
    ap.add_argument('--sidecar', default='')
    args = ap.parse_args(argv)

    base = os.environ.get('HERATIO_URL')
    key = os.environ.get('HERATIO_API_KEY')
    if not base or not key:
        sys.stderr.write("Set HERATIO_URL and HERATIO_API_KEY (or use ~/.heratio-scan.conf)\n")
        return 1

    parent_id = args.parent_id
    if not parent_id and args.parent_slug:
        r = requests.get(
            f"{base}/api/v2/scan/destinations",
            headers={'X-API-Key': key},
            params={'q': args.parent_slug},
            timeout=30,
        )
        r.raise_for_status()
        for row in r.json().get('data', []):
            if row.get('slug') == args.parent_slug:
                parent_id = int(row['id'])
                break
        if not parent_id:
            sys.stderr.write(f"Parent slug not found: {args.parent_slug}\n")
            return 1

    token = create_session(base, key, parent_id, args.sector, args.standard, auto_commit=True)
    sidecar = Path(args.sidecar).expanduser() if args.sidecar else None
    res = upload_file(base, key, token, Path(args.file).expanduser(),
                      args.identifier, args.title, sidecar)
    if not res.get('success'):
        sys.stderr.write(f"Upload failed: {res.get('error')}\n")
        return 1
    print(f"Uploaded. Session: {token}")
    print(f"Status: {base}/api/v2/scan/sessions/{token}")
    return 0


def main() -> int:
    load_config()
    if len(sys.argv) == 1:
        return interactive()
    return one_shot(sys.argv[1:])


if __name__ == '__main__':
    sys.exit(main())
