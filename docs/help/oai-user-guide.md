> Heratio Help Center article. Category: Interoperability & Harvesting.

# OAI-PMH Metadata Harvesting

Heratio exposes its published archival descriptions for external harvesting through a built-in OAI-PMH 2.0 provider. Aggregators, union catalogues, and discovery portals can periodically collect your metadata in Dublin Core, EAD, MODS, or MARCXML without any custom integration.

---

## Overview

OAI-PMH (the Open Archives Initiative Protocol for Metadata Harvesting) is a low-barrier, widely supported standard for sharing metadata between repositories. A harvester issues HTTP requests naming a "verb" (the operation it wants), and the provider answers with XML. Heratio acts as a **data provider** (the source side), serving its published information objects to any harvester that asks.

The provider is delivered by the `ahg-oai` package. It is read-only and exposes only **published** records. Records that are draft, embargoed, or otherwise unpublished are never disseminated. Deleted or permanently unpublished records can optionally be advertised as tombstones so harvesters know to purge their copies.

The provider lives entirely at two URLs:

- `/oai` - the protocol endpoint that harvesters talk to (XML responses).
- `/oai/docs` - a human-readable landing page for operators and harvester authors, listing verbs, formats, and sample queries.

---

## Key features

- **Full OAI-PMH 2.0 verb set** - all six verbs are implemented: `Identify`, `ListMetadataFormats`, `ListSets`, `ListIdentifiers`, `ListRecords`, `GetRecord`.
- **Five metadata formats** - `oai_dc` (simple Dublin Core), `oai_ead` (EAD 2002), `oai_ead3` (EAD 3), `mods` (MODS 3.5), and `marcxml` (MARC21 in the MARCXML slim envelope).
- **Sets from your hierarchy** - top-level archival collections are offered as OAI sets so a harvester can subscribe to a single collection rather than the whole repository.
- **Incremental harvesting** - the `from` and `until` date arguments let a harvester collect only records changed since its last pass.
- **Resumption tokens** - large result lists are paged automatically; the harvester follows the token from page to page.
- **Deleted-record tombstones** - records can be marked deleted so harvesters receive a `status="deleted"` header and clean up their local copy.
- **GET and POST** - both transports are supported as the spec requires.
- **Rate limiting** - 120 requests per minute per IP, generous enough for any well-behaved harvester.
- **Optional API-key authentication** - anonymous harvesting is on by default, but an operator can require an OAI-scoped API key.
- **Federation "friends"** - the `Identify` response can advertise other known OAI repositories when federation peers are configured.

---

## How to use

### Find the endpoint

The protocol endpoint is your site base URL followed by `/oai`. For example, if your repository is at `https://archive.example.org`, the OAI base URL is:

```
https://archive.example.org/oai
```

Open `/oai/docs` in a browser for the operator-facing reference page. It shows the live base URL, the supported verbs, the metadata-format table, and ready-to-paste sample queries.

### The six verbs

Each verb is supplied as a `verb=` query argument. The provider validates that the verb is legal and that the supplied arguments are allowed for that verb; anything else returns a standard OAI-PMH error code.

| Verb | What it returns | Required arguments |
|---|---|---|
| `Identify` | Repository name, base URL, protocol version, admin email(s), earliest datestamp, deleted-record policy, sample identifier | none |
| `ListMetadataFormats` | The metadata formats available for harvesting | none (optional `identifier` to query a single record) |
| `ListSets` | The archival collections offered as harvestable sets | none |
| `ListIdentifiers` | Record identifiers + datestamps (headers only) | `metadataPrefix` |
| `ListRecords` | Full records in the chosen format | `metadataPrefix` |
| `GetRecord` | One record by its OAI identifier | `identifier`, `metadataPrefix` |

`ListIdentifiers` and `ListRecords` also accept the optional `from`, `until`, and `set` arguments, plus `resumptionToken` for paging. `ListSets` accepts `resumptionToken` when the set list spans more than one page.

### Sample requests

Identify the repository:

```
/oai?verb=Identify
```

List the formats you can harvest in:

```
/oai?verb=ListMetadataFormats
```

List the collections offered as sets:

```
/oai?verb=ListSets
```

List record identifiers in Dublin Core:

```
/oai?verb=ListIdentifiers&metadataPrefix=oai_dc
```

Harvest full records in MODS:

```
/oai?verb=ListRecords&metadataPrefix=mods
```

Incrementally harvest EAD 3 records changed on or after a date:

```
/oai?verb=ListRecords&metadataPrefix=oai_ead3&from=2026-01-01
```

Fetch a single record in MARCXML (substitute your real OAI identifier):

```
/oai?verb=GetRecord&identifier=oai:archive.example.org:100002&metadataPrefix=marcxml
```

### Metadata formats

| metadataPrefix | Format | Schema |
|---|---|---|
| `oai_dc` | Dublin Core (simple) | `oai_dc.xsd` |
| `oai_ead` | EAD 2002 (full hierarchy with descendants) | `ead.xsd` |
| `oai_ead3` | EAD 3 | `ead3.xsd` |
| `mods` | MODS 3.5 | `mods-3-5.xsd` |
| `marcxml` | MARC21 (MARCXML slim envelope) | `MARC21slim.xsd` |

Every published record is available in every format; there are no per-record format restrictions. Dublin Core is rendered directly by the provider; the EAD, MODS, and MARCXML outputs are produced by the shared `ahg-metadata-export` serializers, so OAI output matches what those tools produce elsewhere in Heratio.

Dublin Core mapping is drawn from the archival description and its related data:

- `dc:title` - record title.
- `dc:creator` - creators from creation events, with an embedded-image attribution fallback when the descriptive author slot is empty.
- `dc:subject` - subject access points, with a keyword fallback from embedded image metadata.
- `dc:description` - scope and content.
- `dc:publisher` / `dc:contributor` - from publication and contribution events.
- `dc:date` - creation event dates (structured first, free-text date as fallback).
- `dc:type` - level of description.
- `dc:format` - extent and medium.
- `dc:identifier` - the record URL and its reference code.
- `dc:source` - location of originals.
- `dc:language` - source language.
- `dc:relation` - the holding repository (URL and name).
- `dc:coverage` - place access points.
- `dc:rights` - reproduction conditions, falling back to access conditions, then to an embedded copyright notice.

### Sets

A "set" in this provider is a top-level archival collection. By default `ListSets` lists only the top-level collections (immediate children of the repository root) that are published. A harvester can then restrict `ListIdentifiers` or `ListRecords` to one collection with `set=<the set spec>`. The set membership is resolved through the collection's hierarchy range, so the set includes the collection and all of its descendants.

### Incremental and paged harvesting

To harvest only what changed, supply `from` and/or `until` as ISO 8601 dates (for example `2026-01-01` or `2026-01-01T00:00:00Z`). The provider matches these against each record's last-modified datestamp.

When a result list is larger than one page, the response ends with a `<resumptionToken>`. The harvester sends that token back (with only the `verb`) to fetch the next page, and repeats until no token is returned. A harvester that honours resumption tokens will walk every page and is unlikely to hit the rate limit.

### Deleted records

The `Identify` response advertises a deleted-record policy of `transient`, meaning the provider keeps deletion tombstones for a period but does not guarantee they are kept forever. When a record is tombstoned, harvesters that reach the deleted phase receive a `<header status="deleted">` with the deletion datestamp and no metadata body, which tells them to purge their copy. During `ListRecords` and `ListIdentifiers` the provider streams live records first, then transitions to tombstones via a resumption token, so a harvester that follows tokens ends up with every live record plus every tombstone in the requested date range.

Operators record tombstones with an Artisan command:

```bash
# Tombstone a single record by its OAI local identifier
php artisan oai:mark-deleted 100002 --reason="merged into 100050"

# Tombstone every record that has an OAI identifier but is no longer published
php artisan oai:mark-deleted --all-unpublished

# List current tombstones (no writes)
php artisan oai:mark-deleted --list
```

If the deleted-record table is not present, the provider falls back to advertising a deleted-record policy of `no` and simply omits tombstones, so harvesting still works.

---

## Configuration

The provider works out of the box with sensible defaults; every setting below is optional. Settings are held in the `setting` table under the `oai` scope and surface in the admin UI at **Admin -> AHG Settings -> OAI-PMH**.

| Setting | Purpose | Default behaviour when unset |
|---|---|---|
| `oai_repository_identifier` | The repository identifier published in `Identify` and used to compose record identifiers | Falls back to the request host name |
| `oai_repository_code` | A short code prefixed onto each local id so federated harvesters can tell records apart when repositories share a host | No prefix; the bare local id is used |
| `oai_admin_emails` | Comma-separated list of administrative contact emails for `Identify` | Falls back to the application's configured "from" mail address |
| `sample_oai_identifier` | The example local id shown in the `Identify` sample identifier | Uses a built-in placeholder |
| `resumption_token_limit` | Page size for resumption-token paging | 100 records per page |
| `oai_additional_sets_enabled` | When enabled, offers descendant collections as sets (sub-fonds-level granularity) as well as top-level collections | Off; only top-level collections are sets |
| `oai_authentication_enabled` | When enabled, requires an OAI-scoped API key to harvest | Off; anonymous harvesting is allowed |

### OAI identifier format

Record identifiers follow the pattern `oai:{repository identifier}:{local id}`. When a repository code is set, the local part is prefixed with that code and a dash (`oai:{repository identifier}:{code}-{local id}`), which lets a federated harvester distinguish records that originate from this repository even when several repositories share one hostname.

### Authentication

With `oai_authentication_enabled` switched on, harvesters must present a key using any of the shapes the rest of the Heratio API accepts:

- `X-API-Key:` header
- `Authorization: Bearer ...` header
- `?api=...` query argument

A key is accepted when it is active, not expired, and either carries no scope restriction or includes the `oai` scope (or the `*` wildcard). A harvest attempt without a valid key receives an HTTP 401 with a short plain-text message, since OAI-PMH has no native authentication verb.

### Compression and rate limiting

Responses are gzipped at the web-server layer when the harvester sends `Accept-Encoding: gzip`, roughly halving transferred bytes. The endpoint is rate-limited to 120 requests per minute per IP.

### Federation "friends"

When OAI peers are configured (active peers of type `oai_pmh` with a base URL, in the federation package), the `Identify` response includes a `<friends>` description listing those peers' base URLs. This is omitted entirely when no such peers exist.

---

## Error codes

The provider returns the standard OAI-PMH error codes inside a well-formed envelope, including: `badArgument`, `badResumptionToken`, `badVerb`, `cannotDisseminateFormat`, `idDoesNotExist`, `noRecordsMatch`, `noMetadataFormats`, and `noSetHierarchy`. A harvester can rely on these to distinguish a malformed request from an empty result.

---

## Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| `badVerb` error | The `verb` argument is missing, misspelled, or not one of the six legal verbs |
| `cannotDisseminateFormat` | The `metadataPrefix` is not one of `oai_dc`, `oai_ead`, `oai_ead3`, `mods`, `marcxml` |
| `noRecordsMatch` | No published records (and no tombstones) fall in the requested date range or set |
| `idDoesNotExist` | The `identifier` is malformed or points to a record that is not published |
| HTTP 401 | Authentication is enabled but no valid OAI-scoped key was supplied |
| Harvester stops early | Confirm the client honours `resumptionToken`; without it, only the first page is collected |

---

## References

- **Source package:** `packages/ahg-oai/`
- **Controllers:** `OaiPmhController` (protocol endpoint), `OaiDocsController` (landing page)
- **Console command:** `php artisan oai:mark-deleted`
- **Routes:** `GET|POST /oai` (named `oai`), `GET /oai/docs` (named `oai.docs`)
- **Operator landing page:** `/oai/docs`
- **Serializers reused for EAD/MODS/MARCXML:** `ahg-metadata-export`
- **GitHub issue:** [#606](https://github.com/ArchiveHeritageGroup/heratio/issues/606)
- **OAI-PMH 2.0 specification:** https://www.openarchives.org/OAI/openarchivesprotocol.html
