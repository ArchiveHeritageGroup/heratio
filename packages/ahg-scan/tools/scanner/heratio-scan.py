#!/usr/bin/env python3
"""
heratio-scan.py — upload a scanned file to Heratio via the Scan API.

Cross-platform; needs only the Python standard library + `requests`.

Environment variables (or ~/.heratio-scan.conf with KEY=value lines):
    HERATIO_URL           e.g. https://heratio.theahg.co.za
    HERATIO_API_KEY       API key with scan:write scope
    HERATIO_PARENT_ID     int; existing IO id (or use HERATIO_PARENT_SLUG)
    HERATIO_PARENT_SLUG   URL slug of the parent IO
    HERATIO_SECTOR        archive | library | gallery | museum (default: archive)
    HERATIO_STANDARD      e.g. isadg, marc21, lido, spectrum (default: isadg)

Usage:
    heratio-scan.py FILE [--identifier ID] [--title T] [--sidecar PATH]

Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
Licensed under the GNU AGPL v3.
"""

import argparse
import json
import os
import sys
from pathlib import Path

try:
    import requests
except ImportError:
    sys.stderr.write("heratio-scan.py needs the `requests` package (pip install requests)\n")
    sys.exit(1)


def load_config():
    config_file = Path(os.environ.get('HERATIO_SCAN_CONFIG', Path.home() / '.heratio-scan.conf'))
    if config_file.is_file():
        for line in config_file.read_text().splitlines():
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            key, _, value = line.partition('=')
            key, value = key.strip(), value.strip().strip('"').strip("'")
            os.environ.setdefault(key, value)


def resolve_parent(url: str, key: str, slug: str) -> int:
    r = requests.get(
        f"{url}/api/v2/scan/destinations",
        headers={'X-API-Key': key},
        params={'q': slug},
        timeout=30,
    )
    r.raise_for_status()
    for row in r.json().get('data', []):
        if row.get('slug') == slug:
            return int(row['id'])
    raise RuntimeError(f"Parent slug not found: {slug}")


def main() -> int:
    parser = argparse.ArgumentParser(description="Upload a scanned file to Heratio.")
    parser.add_argument('file', help="Path to the scanned file")
    parser.add_argument('--identifier', default='', help="IO identifier")
    parser.add_argument('--title', default='', help="IO title")
    parser.add_argument('--sidecar', default='', help="Optional heratioScan XML sidecar")
    args = parser.parse_args()

    load_config()

    url = os.environ.get('HERATIO_URL')
    key = os.environ.get('HERATIO_API_KEY')
    if not url or not key:
        sys.stderr.write("HERATIO_URL and HERATIO_API_KEY must be set\n")
        return 1

    sector = os.environ.get('HERATIO_SECTOR', 'archive')
    standard = os.environ.get('HERATIO_STANDARD', 'isadg')

    file_path = Path(args.file)
    if not file_path.is_file():
        sys.stderr.write(f"File not found: {file_path}\n")
        return 1

    headers = {'X-API-Key': key}

    # --- resolve parent ---
    parent_id = None
    if os.environ.get('HERATIO_PARENT_ID'):
        parent_id = int(os.environ['HERATIO_PARENT_ID'])
    elif os.environ.get('HERATIO_PARENT_SLUG'):
        parent_id = resolve_parent(url, key, os.environ['HERATIO_PARENT_SLUG'])

    # --- create session ---
    body = {'sector': sector, 'standard': standard, 'auto_commit': True}
    if parent_id:
        body['parent_id'] = parent_id
    r = requests.post(
        f"{url}/api/v2/scan/sessions",
        headers={**headers, 'Content-Type': 'application/json'},
        data=json.dumps(body),
        timeout=30,
    )
    r.raise_for_status()
    token = r.json()['data']['token']

    # --- upload ---
    meta = {}
    if args.identifier: meta['identifier'] = args.identifier
    if args.title:      meta['title'] = args.title

    files = {'file': (file_path.name, file_path.open('rb'))}
    if args.sidecar:
        sc = Path(args.sidecar)
        if sc.is_file():
            files['sidecar'] = (sc.name, sc.open('rb'), 'application/xml')
    data = {'metadata': json.dumps(meta)}

    r = requests.post(
        f"{url}/api/v2/scan/sessions/{token}/files",
        headers=headers,
        files=files,
        data=data,
        timeout=300,
    )
    if r.status_code >= 400:
        sys.stderr.write(f"Upload failed ({r.status_code}): {r.text[:500]}\n")
        return 1
    resp = r.json()
    if not resp.get('success'):
        sys.stderr.write(f"Upload rejected: {json.dumps(resp)[:500]}\n")
        return 1

    print(f"Uploaded to Heratio. Session: {token}")
    print(f"Status: {url}/api/v2/scan/sessions/{token}")
    return 0


if __name__ == '__main__':
    sys.exit(main())
