# IPTC fallback in metadata exports

**Issue:** [heratio#752](https://github.com/ArchiveHeritageGroup/heratio/issues/752).
**Status:** shipped.
**Packages:** `packages/ahg-metadata-export/`, `packages/ahg-oai/`.

## TL;DR

Heratio's metadata extraction pipeline writes IPTC headers (creator,
copyright notice, keywords) from uploaded TIFF / JPEG / PSD files into
`dam_iptc_metadata`. The OAI-PMH endpoint, Dublin Core export, EAD 2002 /
EAD 3 crosswalks, and the AHG CSV / EAD bulk exports used to ignore that
data and emit empty `dc:creator` / `dc:rights` / `dc:subject` (and the EAD
equivalents) when the ISAD(G) fields were empty. From this release, when
an ISAD(G) field is empty the exporter falls through to the IPTC value
instead - audit-logging every fallback so operators can spot descriptions
that survive only because of the extracted headers.

## Field map

| IPTC label        | `dam_iptc_metadata` column | ISAD(G) canonical                 | DC predicate    | EAD element       |
| ----------------- | -------------------------- | --------------------------------- | --------------- | ----------------- |
| By-line           | `creator`                  | event type_id=111 (Creation)      | `dc:creator`    | `<origination>`   |
| Copyright Notice  | `copyright_notice`         | ISAD 3.4.2 reproduction_conditions| `dc:rights`     | `<userestrict>`   |
| Keywords          | `keywords`                 | subject access points (taxonomy 35)| `dc:subject`   | `<controlaccess><subject>` |

Precedence is always ISAD wins, IPTC fills the gap, nothing emitted when
both are empty. The fallback never overrides a populated canonical field.

## Helper

`AhgMetadataExport\Services\IptcFallbackResolver` is the single source of
truth. It exposes three single-argument convenience methods matching the
brief surface:

- `resolveCreator(int $informationObjectId): ?string`
- `resolveRights(int $informationObjectId): ?string`
- `resolveSubjects(int $informationObjectId): array`

…and three canonical-aware variants (`*WithCanonical`) for callers that
already have the ISAD values in hand and want to skip the re-query:

- `resolveCreatorsWithCanonical(int, array): array`
- `resolveRightsWithCanonical(int, ?string): ?string`
- `resolveSubjectsWithCanonical(int, array): array`

The plural shape returns the full creator / subject list (canonical or
IPTC), which is what the per-format serializers want; the single-arg
shape just returns the first hit for narrow consumers (CSV rows, single-
field emitters).

The resolver caches `dam_iptc_metadata` lookups per request and dedupes
audit rows by `(object_id, field)` so a `ListRecords` cycle that re-
renders the same record won't fill `ahg_error_log` with duplicates.

## Audit log

When a fallback fires, the resolver writes an `info`-level row to
`ahg_error_log` with the shape:

    IPTC fallback fired for information_object.id=<id> field=<creator|rights|subject> value="<truncated to 200 chars>"

Operators can sweep these with:

```sql
SELECT created_at, message
FROM ahg_error_log
WHERE level = 'info'
  AND message LIKE 'IPTC fallback fired%'
ORDER BY created_at DESC
LIMIT 100;
```

Each row tells you which IO is leaning on an IPTC value. The right
remedy is usually to promote the IPTC value into the canonical ISAD
field via the description editor; once that's done the next harvest
emits the same value from the canonical source and the audit goes quiet.

The audit is best-effort - if `ahg_error_log` is unreachable the export
still succeeds (audit is observability, not a correctness gate).

## Consumers

- `packages/ahg-oai/src/Controllers/OaiPmhController.php`
  (renderDublinCore - `dc:creator` / `dc:subject` / `dc:rights`).
- `packages/ahg-metadata-export/src/Services/Exporters/DublinCoreQualifiedSerializer.php`
  (both `dc:*` and `dcterms:*` predicates).
- `packages/ahg-metadata-export/src/Services/Exporters/Ead2002Serializer.php`
  (`<origination>`, `<userestrict>`, `<controlaccess><subject>`).
- `packages/ahg-metadata-export/src/Services/Exporters/Ead3Serializer.php`
  (same EAD3 equivalents).

The AHG CSV / EAD bulk export endpoints in `packages/ahg-export/` route
the per-record payload through these same serializers, so the fallback
fires uniformly across the dashboard CSV download and the OAI harvest.

## Malformed IPTC payloads

`dam_iptc_metadata.keywords` can be a JSON array (ExifTool's default
output), a comma / semicolon / pipe-delimited string (legacy / hand-
edited), or anything in between. The resolver tries JSON first, falls
through to delimited parsing, and silently drops parser errors so a
corrupt IPTC blob can't poison an OAI harvest. Empty / whitespace-only
strings degrade to an empty list, not an emitted blank.

## Curl smoke

A record with no ISAD author but an IPTC By-line should emit `<dc:creator>`
in its OAI-PMH `oai_dc` payload:

```bash
curl -s 'http://localhost/oai?verb=GetRecord&identifier=oai:<host>:<oai_local_id>&metadataPrefix=oai_dc' \
  | xmllint --xpath '//*[local-name()="creator"]' -
```

Same record, before this change: empty `<dc:creator/>`. After: the IPTC
By-line, and one new `info` row in `ahg_error_log`.
