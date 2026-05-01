# API Comparison - AtoM vs Heratio

Generated: 2026-03-17

## Current State

- **AtoM**: 112+ API endpoints (v1 legacy + v2 REST + OAI-PMH)
- **Heratio**: 18 REST endpoints (v1 read-only) + 6 OAI-PMH verbs
- **Gap**: ~94 endpoints missing

---

## What Heratio HAS (18 v1 READ-ONLY + 6 OAI)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | GET | `/api/v1/informationobjects` | Browse descriptions |
| 2 | GET | `/api/v1/informationobjects/search` | Search descriptions |
| 3 | GET | `/api/v1/informationobjects/{slug}` | Show description |
| 4 | GET | `/api/v1/actors` | Browse authority records |
| 5 | GET | `/api/v1/actors/{slug}` | Show authority record |
| 6 | GET | `/api/v1/repositories` | Browse repositories |
| 7 | GET | `/api/v1/repositories/{slug}` | Show repository |
| 8 | GET | `/api/v1/accessions` | Browse accessions |
| 9 | GET | `/api/v1/accessions/{slug}` | Show accession |
| 10 | GET | `/api/v1/donors` | Browse donors |
| 11 | GET | `/api/v1/donors/{slug}` | Show donor |
| 12 | GET | `/api/v1/functions` | Browse functions |
| 13 | GET | `/api/v1/functions/{slug}` | Show function |
| 14 | GET | `/api/v1/physicalobjects` | Browse physical objects |
| 15 | GET | `/api/v1/physicalobjects/{slug}` | Show physical object |
| 16 | GET | `/api/v1/digitalobjects` | Browse digital objects |
| 17 | GET | `/api/v1/taxonomies` | Browse taxonomies |
| 18 | GET | `/api/v1/taxonomies/{id}/terms` | List terms in taxonomy |

**OAI-PMH** (6 verbs): Identify, ListMetadataFormats, ListSets, ListIdentifiers, ListRecords, GetRecord

---

## What's MISSING - Priority Order

### Priority 1: v1 CRUD (7 endpoints)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | POST | `/api/v1/informationobjects` | Create description |
| 2 | PUT | `/api/v1/informationobjects/{slug}` | Update description |
| 3 | DELETE | `/api/v1/informationobjects/{slug}` | Delete description |
| 4 | GET | `/api/v1/informationobjects/{slug}/digitalobject` | Download digital object |
| 5 | GET | `/api/v1/informationobjects/tree/{slug}` | Get hierarchy tree |
| 6 | POST | `/api/v1/digitalobjects` | Upload digital object |
| 7 | POST | `/api/v1/physicalobjects` | Create physical object |

### Priority 2: v2 Core CRUD (12 endpoints)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | GET | `/api/v2` | API v2 root - list endpoints |
| 2 | GET | `/api/v2/descriptions` | Browse descriptions |
| 3 | POST | `/api/v2/descriptions` | Create description |
| 4 | GET | `/api/v2/descriptions/{slug}` | Read description |
| 5 | PUT/PATCH | `/api/v2/descriptions/{slug}` | Update description |
| 6 | DELETE | `/api/v2/descriptions/{slug}` | Delete description |
| 7 | GET | `/api/v2/authorities` | Browse authorities |
| 8 | GET | `/api/v2/authorities/{slug}` | Read authority |
| 9 | GET | `/api/v2/repositories` | Browse repositories |
| 10 | GET | `/api/v2/taxonomies` | Browse taxonomies |
| 11 | GET | `/api/v2/taxonomies/{id}/terms` | Terms in taxonomy |
| 12 | POST | `/api/v2/search` | Full-text search |

### Priority 3: v2 Batch + Infrastructure (16 endpoints)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | POST | `/api/v2/batch` | Batch CRUD (up to 100 ops) |
| 2 | GET | `/api/v2/keys` | List API keys |
| 3 | POST | `/api/v2/keys` | Create API key |
| 4 | DELETE | `/api/v2/keys/{id}` | Delete API key |
| 5 | GET | `/api/v2/webhooks` | List webhooks |
| 6 | POST | `/api/v2/webhooks` | Create webhook |
| 7 | GET | `/api/v2/webhooks/{id}` | Read webhook |
| 8 | PUT/PATCH | `/api/v2/webhooks/{id}` | Update webhook |
| 9 | DELETE | `/api/v2/webhooks/{id}` | Delete webhook |
| 10 | GET | `/api/v2/webhooks/{id}/deliveries` | Webhook delivery history |
| 11 | POST | `/api/v2/webhooks/{id}/regenerate-secret` | Regenerate secret |
| 12 | GET | `/api/v2/events` | Browse workflow events |
| 13 | GET | `/api/v2/events/{id}` | Read event |
| 14 | GET | `/api/v2/events/correlation/{id}` | Events by correlation |
| 15 | GET | `/api/v2/audit` | Browse audit log |
| 16 | GET | `/api/v2/audit/{id}` | Read audit entry |

### Priority 4: v2 Publishing + Uploads (4 endpoints)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | GET | `/api/v2/publish/readiness/{slug}` | Check publish readiness |
| 2 | POST | `/api/v2/publish/execute/{slug}` | Publish description |
| 3 | POST | `/api/v2/upload` | Generic file upload |
| 4 | POST | `/api/v2/descriptions/{slug}/upload` | Upload for description |

### Priority 5: v2 Conditions + Photos (8 endpoints)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | GET | `/api/v2/conditions` | Browse condition assessments |
| 2 | POST | `/api/v2/conditions` | Create condition |
| 3 | GET | `/api/v2/conditions/{id}` | Read condition |
| 4 | PUT/PATCH | `/api/v2/conditions/{id}` | Update condition |
| 5 | DELETE | `/api/v2/conditions/{id}` | Delete condition |
| 6 | GET | `/api/v2/descriptions/{slug}/conditions` | Conditions for description |
| 7 | GET | `/api/v2/conditions/{id}/photos` | Photos for condition |
| 8 | POST | `/api/v2/conditions/{id}/photos` | Upload condition photo |

### Priority 6: v2 Heritage Assets + Valuations (8 endpoints)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | GET | `/api/v2/assets` | Browse heritage assets |
| 2 | POST | `/api/v2/assets` | Create asset |
| 3 | GET | `/api/v2/assets/{id}` | Read asset |
| 4 | PUT/PATCH | `/api/v2/assets/{id}` | Update asset |
| 5 | GET | `/api/v2/descriptions/{slug}/asset` | Asset for description |
| 6 | GET | `/api/v2/valuations` | Browse valuations |
| 7 | POST | `/api/v2/valuations` | Create valuation |
| 8 | GET | `/api/v2/assets/{id}/valuations` | Valuations for asset |

### Priority 7: v2 Privacy/Compliance (6 endpoints)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | GET | `/api/v2/privacy/dsars` | Browse DSARs |
| 2 | POST | `/api/v2/privacy/dsars` | Create DSAR |
| 3 | GET | `/api/v2/privacy/dsars/{id}` | Read DSAR |
| 4 | PUT/PATCH | `/api/v2/privacy/dsars/{id}` | Update DSAR |
| 5 | GET | `/api/v2/privacy/breaches` | Browse breaches |
| 6 | POST | `/api/v2/privacy/breaches` | Create breach |

### Priority 8: v2 Mobile Sync (2 endpoints)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | GET | `/api/v2/sync/changes` | Get changes since timestamp |
| 2 | POST | `/api/v2/sync/batch` | Batch sync operations |

### Legacy/Additional (4 endpoints)

| # | Method | URL | Purpose |
|---|--------|-----|---------|
| 1 | GET/POST | `/api/search/io` | Search IOs (legacy) |
| 2 | GET/POST | `/api/autocomplete/glam` | GLAM autocomplete |
| 3 | GET | `/api/export-preview` | Export statistics |
| 4 | GET | `/api/reports/pending-counts` | Pending counts for badges |

---

## Authentication

### AtoM v2
- Header: `X-API-Key: key` or `Authorization: Bearer key`
- Scopes: read, write, delete, batch, events:read, audit:read, audit:admin
- API keys managed via `/api/v2/keys` endpoints

### Heratio v1
- Laravel throttle middleware (60 req/min)
- No API key management yet

---

## Response Format

### AtoM v2
```json
{
  "success": true,
  "data": { ... },
  "timestamp": "2025-03-17T...",
  "correlation_id": "uuid"
}
```

### Heratio v1
```json
{
  "data": [ ... ],
  "meta": { "total": 100, "page": 1, "limit": 10, "last_page": 10 },
  "links": { "self": "...", "next": "...", "prev": "..." }
}
```

---

## Implementation Notes

- AtoM source: `/usr/share/nginx/archive/atom-ahg-plugins/ahgAPIPlugin/` (v2) + `/usr/share/nginx/archive/plugins/arRestApiPlugin/` (v1)
- Heratio source: `/usr/share/nginx/heratio/packages/ahg-api/` (v1) + `/usr/share/nginx/heratio/packages/ahg-oai/` (OAI-PMH)
- AtoM v2 uses `AtomFramework\Routing\RouteLoader` with 57 action classes in `modules/apiv2/`
- Heratio v1 uses standard Laravel API routes with 9 controller classes in `src/Controllers/V1/`
- Total gap: ~94 endpoints to implement
