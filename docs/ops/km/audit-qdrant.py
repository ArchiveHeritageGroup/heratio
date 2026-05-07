#!/usr/bin/env python3
"""
audit-qdrant.py - daily defence-in-depth credential scan over every Qdrant
collection. Closes #49.

Why: ingest_heratio.py has a redact_secrets() pass before chunks land in
Qdrant. Other ingest scripts (ingest_qa, ingest_atom_docs, ingest_ric) may
not; new collections may bypass redaction entirely. A daily scrolling scan
catches anything that slipped through.

What it does:
    1. Connects to local Qdrant (http://localhost:6333).
    2. Lists every collection.
    3. Scrolls every point in every collection.
    4. For each payload string field, runs the same regex set the
       redact_secrets() function uses + extends it with a couple of
       extra explicit-leak patterns.
    5. On any hit: print collection / point id / matched field / matched
       text excerpt and exit 2.
    6. On clean run: print summary and exit 0.

Run from cron via /etc/systemd/system/km-audit-qdrant.timer (daily). On
exit 2, systemd marks the unit FAILED and journalctl shows the offending
rows. Operator deletes the points (curl -XPOST .../points/delete) and
re-runs to confirm clean.

@copyright  Johan Pieterse / Plain Sailing
@license    AGPL-3.0-or-later
"""

from __future__ import annotations
import re
import sys
from typing import Iterable

try:
    from qdrant_client import QdrantClient
except ImportError:
    print("[audit-qdrant] qdrant_client not installed; pip install qdrant-client", file=sys.stderr)
    sys.exit(3)

QDRANT_URL = "http://localhost:6333"
SCROLL_BATCH = 256

# Mirror of ingest_heratio.py:122-134 + a couple of extras (explicit
# password literals that have shown up in past leaks). Each tuple is
# (regex, label) - the label is what gets printed on a hit so the
# operator can grep the journal for "Merlot" / "ssh-key" / etc.
PATTERNS: list[tuple[re.Pattern, str]] = [
    (re.compile(r'password\s*[:=]\s*[`\'"]?[^\s`\'",]{6,}', re.I),                                'password=VALUE'),
    (re.compile(r'(?:api[_-]?key|secret|token|bearer)\s*[:=]\s*[`\'"]?[A-Za-z0-9_\-./+]{12,}', re.I), 'apikey/token=VALUE'),
    (re.compile(r'[A-Z][A-Z0-9_]+(?:PASSWORD|KEY|TOKEN|SECRET)\s*=\s*[`\'"]?[^\s`\'",]{4,}'),     'ENV_VAR=VALUE'),
    (re.compile(r'\b(?:Merlot|AtoM)@\d+\b'),                                                       'literal Merlot@/AtoM@'),
    (re.compile(r'\bahg_ai_demo_internal_\d+\b'),                                                  'demo internal key'),
    (re.compile(r'\b(?:10|192\.168|172\.(?:1[6-9]|2\d|3[01]))\.\d+\.\d+\b'),                       'RFC1918 IP'),
    (re.compile(r'(?:ssh-(?:rsa|ed25519|dss)|-----BEGIN [A-Z ]+ KEY-----)'),                       'SSH/PEM key'),
    # Extras over the redactor:
    (re.compile(r'\bsk-[A-Za-z0-9]{20,}\b'),                                                       'OpenAI/Anthropic-style key'),
    (re.compile(r'\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b'),                         'JWT token'),
]

# Redaction noise to ignore: redact_secrets replaces hits with placeholders.
# A scan that just finds the placeholder is fine - that's the redactor doing
# its job, not a leak. Skip these substrings before regexing the payload.
REDACTED_PLACEHOLDERS = ('<REDACTED>', '<INTERNAL_IP>', '<SSH_KEY>')


def iter_strings(payload) -> Iterable[tuple[str, str]]:
    """Yield (field-path, string-value) for every string in a payload tree."""
    if payload is None:
        return
    if isinstance(payload, str):
        yield ('', payload)
        return
    if isinstance(payload, dict):
        for k, v in payload.items():
            for sub_path, sub_val in iter_strings(v):
                yield (f'{k}.{sub_path}'.rstrip('.'), sub_val)
        return
    if isinstance(payload, list):
        for i, v in enumerate(payload):
            for sub_path, sub_val in iter_strings(v):
                yield (f'[{i}].{sub_path}'.rstrip('.'), sub_val)
        return
    # numbers, bools, etc. - ignore.


def scan_text(text: str) -> list[tuple[str, str]]:
    """Return [(label, excerpt), ...] for every pattern that matched."""
    if not text or len(text) < 6:
        return []
    cleaned = text
    for ph in REDACTED_PLACEHOLDERS:
        cleaned = cleaned.replace(ph, '')
    hits = []
    for pat, label in PATTERNS:
        m = pat.search(cleaned)
        if m:
            excerpt = m.group(0)
            if len(excerpt) > 80:
                excerpt = excerpt[:77] + '...'
            hits.append((label, excerpt))
    return hits


def scan_collection(client: QdrantClient, name: str) -> int:
    """Scroll a collection, return total hits."""
    print(f"[audit-qdrant] scanning collection: {name}")
    total_points = 0
    total_hits = 0
    offset = None
    while True:
        result, offset = client.scroll(
            collection_name=name,
            limit=SCROLL_BATCH,
            with_payload=True,
            with_vectors=False,
            offset=offset,
        )
        if not result:
            break
        for point in result:
            total_points += 1
            for field_path, value in iter_strings(point.payload):
                hits = scan_text(value)
                for label, excerpt in hits:
                    total_hits += 1
                    print(f"  HIT: collection={name} point_id={point.id} field={field_path or '<root>'} pattern='{label}' text={excerpt!r}")
        if offset is None:
            break
    print(f"  scanned {total_points} points; {total_hits} hits")
    return total_hits


def main() -> int:
    try:
        client = QdrantClient(url=QDRANT_URL, timeout=30)
        collections = [c.name for c in client.get_collections().collections]
    except Exception as e:
        print(f"[audit-qdrant] FATAL: cannot reach Qdrant at {QDRANT_URL}: {e}", file=sys.stderr)
        return 3

    if not collections:
        print("[audit-qdrant] no collections found - nothing to scan")
        return 0

    print(f"[audit-qdrant] {len(collections)} collection(s): {', '.join(collections)}")
    total_hits = 0
    for name in collections:
        try:
            total_hits += scan_collection(client, name)
        except Exception as e:
            print(f"  WARN: scan of {name} threw: {e}", file=sys.stderr)
    print(f"[audit-qdrant] total hits across all collections: {total_hits}")
    return 2 if total_hits > 0 else 0


if __name__ == '__main__':
    sys.exit(main())
