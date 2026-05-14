# KM outstanding follow-ups — 2026-05-14

After the public-web scope work landed in `/opt/ai/km/db_lookup.py` (v1.58.10
era), two known follow-ups remain so the KM can answer deeper questions
about Archivematica and so the public-web path is defence-in-depth.

## 1. Archivematica docs ingest

The curated fact card in `_PUBLIC_PROJECT_CARDS["archivematica"]` answers
the "what is Archivematica" question with one paragraph plus links. Deeper
questions — "how does AIP normalisation work", "what does the FPR include
for a TIFF", "Archivematica vs BagIt" — need the official docs in Qdrant.

**Source to ingest:** https://www.archivematica.org/en/docs/ (plus GitHub
README + Wiki where relevant). Use the same chunking pipeline that
`source: "heratio"` and `source: "ric"` go through. Tag each chunk:

- `source = "archivematica"`
- `visibility = "external"` (public docs, OK for web role)

Estimated effort: 30–60 minutes depending on doc shape.

## 2. Qdrant source-filter for web role (defence in depth)

Today, non-admin Qdrant queries are filtered only by:

```python
FieldCondition(key="visibility", match=MatchValue(value="external"))
```

A document accidentally tagged `visibility=external` that mentions an
out-of-scope topic (e.g. internal callhub references) would still surface
to a web caller via RAG retrieval — even though the db_lookup gate
correctly returns the out-of-scope refusal for "what is callhub".

**Action:** extend `_extra_filters_for_role()` in `/opt/ai/km/app.py` so the
web/anon role gets:

```python
return [
    FieldCondition(key="visibility", match=MatchValue(value="external")),
    FieldCondition(key="source",     match=MatchAny(any=[
        "atom", "heratio", "archive", "ric", "openric", "archivematica",
    ])),
]
```

This is strictly defence in depth: the db_lookup gate (curated public-card
allowlist) is the primary filter and already returns "out of scope" before
any RAG retrieval runs for project-status intents.

## Why this matters

The new public-web policy: external callers (no API key, web key, or
public chat) may receive answers about **AtoM, Heratio, AtoM-Heratio
(the AHG fork), RiC, OpenRiC, and Archivematica only**. With the curated
db_lookup cards in place, that policy holds for direct project-status
queries. The two follow-ups above complete the story for free-form RAG
queries that go through Qdrant retrieval.

## Related

- `/opt/ai/km/db_lookup.py` — `_PUBLIC_PROJECT_CARDS`,
  `_PUBLIC_PROJECT_ALIASES`, `_public_project_lookup()`,
  `_ARCHIVEMATICA_QUERY`, top-of-`lookup()` web-role gate.
- `/opt/ai/km/app.py` — `_extra_filters_for_role()` at ~line 287 is where
  the source filter would land.
- Backup of pre-change file: `/opt/ai/km/db_lookup.py.bak-20260514-082821`.
