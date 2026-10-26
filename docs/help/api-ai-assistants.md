---
title: REST API - AI assistants (AHG Archives MCP)
slug: api-ai-assistants
category: Integration
---

# REST API - AI assistants (AHG Archives MCP)

The AHG Archives MCP server lets Claude, ChatGPT and other assistants search and read a catalogue, and lets a signed-in archivist's assistant propose changes for review. It reads PSIS (AtoM with the AHG extensions) and Heratio through the same calls to REST API v2. This page lists those calls as Heratio answers them (heratio#1558).

## Keys

Every call sends a key in `X-API-Key` (or `Authorization: Bearer`). Keys are made and revoked at **`/api/keys`** once you are signed in. That page shows each key's scopes, when it was last used, its expiry and its daily limit.

A key acts as the person who made it:

- **A user with no groups** (the MCP's public service key) gets a key for the public catalogue only. It reads the description, authority, repository, taxonomy, exhibition and search routes, which show only what a visitor may see. Every other route answers 403, "This key may read the public catalogue only."
- **Staff** get the scopes their own role holds, never more.

Each key has an hourly limit and, optionally, a daily limit. Past either one, the API answers `429` with a `Retry-After` header in seconds.

## What a key may see

Every read applies the same gate as the public site:

- published records only;
- no record under an active embargo, a security classification above public, an ICIP/TK protocol, or an ODRL "use" prohibition.

This holds for single reads, lists, search results, a record's ancestors and children count, and an authority record's related descriptions. A hidden record answers exactly like a missing one: `404`, `{"message": "Description '...' not found."}`. Authority records follow the public rule too: published and not embargoed.

## Calls

| Call | Answers |
| --- | --- |
| `GET /api/v2/descriptions?limit=&skip=&parent=<id>` | `data.results[]`, `data.total`. Newest first; a parent's children come in archival order. |
| `GET /api/v2/descriptions/{slug}?full=true` | The record, plus `dates`, `names`, `subjects`, `places`, `notes`, `hierarchy[]`, `children_count`, `provenance` and `digital_objects[]`. |
| `GET /api/v2/descriptions/{slug}/text?start=&chars=` | `texts[]`: extracted text, OCR and transcriptions of the record's files, paged by character. |
| `POST /api/v2/search` `{query, limit, skip}` | `data.results[]` of `{slug, title, level_of_description}`, plus `data.total`. |
| `GET /api/v2/authorities?search=&entity_type=person\|organization\|family&limit=` | `data.results[]`. Every word of `search` must appear in the name, in any order. |
| `GET /api/v2/authorities/{slug}` | The authority record. |
| `GET /api/v2/taxonomies/{id}/terms?search=` | `data.terms[]`, `data.total`. Places are taxonomy 42. |
| `GET /iiif/manifest/{slug}` | IIIF Presentation 3.0 manifest, gated like the reads. |

Lists answer in one shape: `data = {total, limit, skip, results}` (terms: `terms`), with `meta` and `links` for page-based clients. Before heratio#1558, `data` was the bare list.

`digital_objects[]` link only to copies the caller may open, as plain paths on this site: `thumbnail_url`, `reference_url`, and `master_url` (only for staff who may read masters). A record with redaction regions offers no reference or master, and no file text.

## Privacy

Field-level redaction rules apply to the full read and to file text, for everyone except administrators. A rule on the field `transcript` masks file text.

## Proposals

An archivist's assistant, signed in with the archivist's own key, may propose additions to a record. Nothing on the record changes: every proposal waits for a person to approve it. The key needs the write scope, and its owner must be allowed to edit the record.

`POST /api/v2/descriptions/{slug}/proposals` with:

```json
{"source": "glam-mcp",
 "entities": [{"type": "PERSON", "value": "Debora Engelbrecht",
               "evidence": "Named on the reverse of the photograph",
               "sources": ["https://..."],
               "authority": {"dates_of_existence": "1890-1950", "history": "...", "places": "..."},
               "actor_id": 123, "link_reason": "same name and dates"}]}
```

- `type` is PERSON, ORG, GPE (a place) or DATE. Each request takes at most 50.
- `actor_id` (people and organisations) or `term_id` (places) names the existing record the proposal most likely is.
- Values already waiting or linked for the record are skipped and counted in `skipped_existing`.

Names and places go to **AI > NER review** and on to the **Authority Resolution** queue. There the reviewer sees the evidence, the sources, who proposed it, and the proposed record first, marked "proposed". Creating a new authority record pre-fills it from the proposed details. Dates stay in the NER dialog. Re-running NER on a record leaves proposals alone.

Field, translation, transcription and relationship proposals answer `501` until their review screens are added.

`GET /api/v2/proposals?mine=1&status=&count=&start=` lists the caller's proposals, newest first, with each one's status (pending, linked, rejected, and so on) and the record's slug and title. `mine=0` lists everyone's and needs an administrator or editor.
