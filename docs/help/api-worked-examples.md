---
title: API worked examples - REST, OAI-PMH and ResourceSync
slug: api-worked-examples
category: Integration
---

# API worked examples

Copy-and-run examples for the calls most integrations need: list, read, create, update, delete and search over REST v2, then harvesting over OAI-PMH and ResourceSync. Every call on this page is run by the test `packages/ahg-api/tests/Feature/WorkedExamplesTest.php` against a real Heratio install, so the calls work as shown. The ids, slugs and counts in the sample responses are only illustrations.

Replace `https://heratio.example.org` with your own Heratio address and `ahg_live_XXX` with your key. For how to get a key and what its scopes allow, see [REST API - AI assistants](/help/article/api-ai-assistants#keys); the short version is **`/api/keys`** once you are signed in.

## Before you start

Every REST v2 call except the discovery document needs a key, sent in `X-API-Key` (or `Authorization: Bearer`). Responses are JSON in one envelope:

```json
{ "success": true, "data": { ... }, "timestamp": "2026-10-27T10:15:00+02:00" }
```

Errors use the same shape with `"success": false`, an `error` and a `message`. The status codes you will meet:

| Status | Meaning |
| --- | --- |
| `401` | No key, or a key that is not valid |
| `403` | The key lacks the scope (a `read` key trying to write), or it is a public-catalogue key on a staff route |
| `404` | Not found, or not visible to this key (drafts, embargoed and restricted records look the same as missing ones) |
| `422` | The request body failed validation; the response lists the fields |
| `429` | Rate limit reached; wait the number of seconds in `Retry-After` |

: REST v2 status codes

## 1. Discover

No key needed. The answer lists the endpoints this install offers.

```bash
curl "https://heratio.example.org/api/v2"
```

## 2. List descriptions, a page at a time

```bash
curl -H "X-API-Key: ahg_live_XXX" \
  "https://heratio.example.org/api/v2/descriptions?limit=2&sort=updated"
```

```json
{
  "success": true,
  "data": {
    "total": 1200, "limit": 2, "skip": 0,
    "results": [
      { "id": 1001, "slug": "municipal-archives", "identifier": "MA",
        "title": "Municipal Archives", "level_of_description": "Fonds",
        "repository": "City Archives", "parent_id": null,
        "created_at": "...", "updated_at": "..." }
    ]
  },
  "meta": { "total": 1200, "page": 1, "limit": 2, "last_page": 600 },
  "links": { "self": ".../api/v2/descriptions?page=1&limit=2", "next": ".../api/v2/descriptions?page=2&limit=2" }
}
```

Follow `links.next` until it is absent. `limit` goes up to 100. Narrow the list with `repository=<slug>`, `level=<term id>` or `parent=<id>`; sort with `sort=updated|title|identifier|archival` and `sort_direction=asc|desc`.

## 3. Create a description

Needs the `write` scope. Only `title` is required; send the ISAD(G) fields you have.

```bash
curl -X POST -H "X-API-Key: ahg_live_XXX" -H "Content-Type: application/json" \
  "https://heratio.example.org/api/v2/descriptions" \
  -d '{
        "title": "Harbour works correspondence",
        "identifier": "WE-001",
        "parent_slug": "municipal-archives",
        "scope_and_content": "Letters about the harbour works.",
        "publication_status": "draft",
        "dates": [{ "type": "creation", "start_date": "1890-01-01", "end_date": "1899-12-31", "date_display": "1890s" }]
      }'
```

```json
{ "success": true, "data": { "id": 1275401, "slug": "harbour-works-correspondence", "title": "Harbour works correspondence" } }
```

The status is `201`. Keep the `slug`: it is how every later call names the record. Leave out `parent_slug` to create a top-level description. New records are drafts unless you send `"publication_status": "published"`, and a draft is invisible to public keys.

## 4. Read it back

```bash
curl -H "X-API-Key: ahg_live_XXX" \
  "https://heratio.example.org/api/v2/descriptions/harbour-works-correspondence"
```

The full record comes back, with its events, creators, access points, notes, digital objects, hierarchy and a `rights` block (the rights statements, licences and Local Contexts labels in force). Add `?full=false` for the core fields only.

## 5. Update a field

Needs `write`. Send only the fields you are changing; `PUT` and `PATCH` behave the same.

```bash
curl -X PATCH -H "X-API-Key: ahg_live_XXX" -H "Content-Type: application/json" \
  "https://heratio.example.org/api/v2/descriptions/harbour-works-correspondence" \
  -d '{ "scope_and_content": "Letters and plans about the harbour works." }'
```

```json
{ "success": true, "data": { "id": 1275401, "slug": "harbour-works-correspondence", "message": "Description updated." } }
```

If you would rather an archivist approve the change first, propose it instead: `POST /api/v2/descriptions/{slug}/proposals` queues it for review. See [REST API - AI assistants](/help/article/api-ai-assistants#proposals).

## 6. Search

```bash
curl -X POST -H "X-API-Key: ahg_live_XXX" -H "Content-Type: application/json" \
  "https://heratio.example.org/api/v2/search" \
  -d '{ "query": "harbour works", "limit": 5, "filters": { "repository": "municipal-archives" } }'
```

The matches are in `data.results`. Search returns published records only.

## 7. Delete

Needs the `delete` scope.

```bash
curl -X DELETE -H "X-API-Key: ahg_live_XXX" \
  "https://heratio.example.org/api/v2/descriptions/harbour-works-correspondence"
```

```json
{ "success": true, "data": { "id": 1275401, "slug": "harbour-works-correspondence", "message": "Description deleted." } }
```

A read of the same slug now answers `404`.

## 8. Harvest over OAI-PMH

No key needed unless your administrator has turned OAI authentication on, in which case send a key with the `oai` scope. The endpoint is `/oai`.

```bash
curl "https://heratio.example.org/oai?verb=Identify"
curl "https://heratio.example.org/oai?verb=ListMetadataFormats"
curl "https://heratio.example.org/oai?verb=ListRecords&metadataPrefix=oai_dc"
```

The formats on offer are `oai_dc`, `oai_ead`, `oai_ead3`, `mods`, `marcxml` and `rico`. A long list comes in pages: each page ends with a `<resumptionToken>`, and you ask for the next page with that token alone.

```bash
curl "https://heratio.example.org/oai?verb=ListRecords&resumptionToken=eyJwaGFzZSI6..."
```

A full harvest in a few lines of Python:

```python
import re, urllib.request, urllib.parse

base = "https://heratio.example.org/oai"
params = {"verb": "ListRecords", "metadataPrefix": "oai_dc"}
while True:
    xml = urllib.request.urlopen(base + "?" + urllib.parse.urlencode(params)).read().decode()
    # ... store or parse the <record> elements here ...
    token = re.search(r"<resumptionToken[^>]*>([^<]+)</resumptionToken>", xml)
    if not token:
        break
    params = {"verb": "ListRecords", "resumptionToken": token.group(1)}
```

For the next harvest, add `from=<the date of your last harvest>` to fetch only what changed. If your install advertises deletions, a deleted record comes back with `status="deleted"` in its header. More in [OAI-PMH Metadata Harvesting](/help/article/oai-user-guide).

## 9. Keep a copy in step with ResourceSync

ResourceSync (NISO Z39.99) suits a mirror that should follow every change, not just metadata records. Start from the discovery file and follow its links to the capability list and the change list:

```bash
curl "https://heratio.example.org/.well-known/resourcesync"
curl "https://heratio.example.org/resourcesync/capabilitylist.xml"
```

More in [Open Data: ResourceSync](/help/article/open-data-resourcesync-user-guide).

## GraphQL

Heratio's GraphQL endpoint is for signed-in staff, from the playground at **Admin > GraphQL** (`/admin/graphql/playground`). It does not accept API keys, so integrations and AI agents should use REST v2. Query examples are in the [GraphQL API User Guide](/help/article/graphql-user-guide).

## Rate limits

| Door | Limit |
| --- | --- |
| REST v2 | Per key: an hourly limit (1,000 by default) and, if set, a daily limit |
| REST v1 (no key) | 60 requests a minute per address |
| Browse pages on the website | Shared across all visitors; see [Bots, crawlers and search engines](/help/article/bots-crawlers-and-search) |

: Rate limits

Every v2 response carries `X-RateLimit-Limit` and `X-RateLimit-Remaining`. Past a limit the answer is `429` with `Retry-After` in seconds: wait that long, then carry on.
