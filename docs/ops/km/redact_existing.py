#!/usr/bin/env python3
"""
redact_existing.py - one-shot in-place redaction of existing Qdrant points.
Closes #49 follow-up: clean stale chunks ingested before redact_secrets()
shipped without dropping + re-ingesting the whole corpus.

For each collection on Qdrant, scrolls every point, runs redact_secrets()
over each string payload field, and upserts the point back when anything
changed. Vectors are NOT touched (the embedding still represents the
pre-redaction text - acceptable because retrieval is approximate; the
payload returned to callers is the redacted version).

Usage:
    python3 /opt/ai/km/redact_existing.py                # all collections
    python3 /opt/ai/km/redact_existing.py km_heratio     # one collection
    python3 /opt/ai/km/redact_existing.py --dry-run      # report only

@copyright  Johan Pieterse / Plain Sailing
@license    AGPL-3.0-or-later
"""

from __future__ import annotations
import sys
from typing import Any

from qdrant_client import QdrantClient
from qdrant_client.models import PointStruct

from redact import redact_secrets

QDRANT_URL = "http://localhost:6333"
SCROLL_BATCH = 256
UPSERT_BATCH = 128


def walk_strings_redact(value: Any) -> tuple[Any, bool]:
    """Walk a payload tree, redacting any string in place. Returns
    (new_value, changed_flag)."""
    if isinstance(value, str):
        red = redact_secrets(value)
        return red, red != value
    if isinstance(value, dict):
        changed = False
        new = {}
        for k, v in value.items():
            new_v, c = walk_strings_redact(v)
            new[k] = new_v
            changed = changed or c
        return new, changed
    if isinstance(value, list):
        changed = False
        new = []
        for v in value:
            new_v, c = walk_strings_redact(v)
            new.append(new_v)
            changed = changed or c
        return new, changed
    return value, False


def redact_collection(client: QdrantClient, name: str, dry_run: bool) -> tuple[int, int]:
    """Returns (scanned, redacted)."""
    print(f"[redact-existing] {name}")
    scanned = 0
    redacted = 0
    pending: list[PointStruct] = []
    offset = None

    while True:
        result, offset = client.scroll(
            collection_name=name,
            limit=SCROLL_BATCH,
            with_payload=True,
            with_vectors=True,   # need the vector to round-trip on upsert
            offset=offset,
        )
        if not result:
            break
        for point in result:
            scanned += 1
            new_payload, changed = walk_strings_redact(point.payload or {})
            if changed:
                redacted += 1
                if not dry_run:
                    pending.append(PointStruct(
                        id=point.id,
                        vector=point.vector,
                        payload=new_payload,
                    ))
                    if len(pending) >= UPSERT_BATCH:
                        client.upsert(collection_name=name, points=pending)
                        pending = []
        if offset is None:
            break

    if pending and not dry_run:
        client.upsert(collection_name=name, points=pending)

    print(f"  scanned {scanned}; {redacted} chunks {'would be ' if dry_run else ''}rewritten")
    return scanned, redacted


def main() -> int:
    args = sys.argv[1:]
    dry_run = '--dry-run' in args
    args = [a for a in args if a != '--dry-run']

    client = QdrantClient(url=QDRANT_URL, timeout=120)
    if args:
        names = args
    else:
        names = [c.name for c in client.get_collections().collections]

    print(f"[redact-existing] {'DRY-RUN' if dry_run else 'LIVE'} - {len(names)} collection(s): {', '.join(names)}")
    total_scanned = 0
    total_redacted = 0
    for n in names:
        try:
            s, r = redact_collection(client, n, dry_run)
            total_scanned += s
            total_redacted += r
        except Exception as e:
            print(f"  WARN: {n}: {e}", file=sys.stderr)

    print(f"[redact-existing] total: {total_scanned} scanned, {total_redacted} {'would be ' if dry_run else ''}rewritten")
    return 0


if __name__ == '__main__':
    sys.exit(main())
