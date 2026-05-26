# METS Phase 4 - SIP / AIP / DIP profiles + PROV-O SPARQL endpoint

## Summary

Phase 4 of issue #658 (METS + PROV-O audit). Two deliverables:

1. The METS exporter now emits three OAIS profile shapes (SIP, AIP, DIP)
   from the same `MetsSerializer` class.
2. A minimal SPARQL 1.1 SELECT endpoint over the PROV-O graph for a single
   information object lives at `/admin/sparql`.

Earlier phases:

- Phase 1 (v1.86.0) - per-IO METS exporter with PREMIS-in-METS digiprovMD.
- Phase 3 (v1.85.0) - PROV-O JSON serializer for the preservation event chain.

Outstanding for Phase 5: full-corpus SPARQL, federated queries, CONSTRUCT /
ASK / DESCRIBE forms, swap the in-memory engine for easyrdf or rdfquery.

## SIP / AIP / DIP profile separation

OAIS / Archivematica naming. One METS document per profile:

| Profile | When to use it | dmdSec | amdSec | fileSec |
|---|---|---|---|---|
| SIP (Submission Information Package) | Initial deposit / handoff to preservation | Minimal Dublin Core (title + identifier + first date + canonical URL) | rightsMD + sourceMD only (no PREMIS) | master / original only |
| AIP (Archival Information Package) | Internal preservation master, full forensic trail | Full Dublin Core (Phase 1 shape) | rightsMD + sourceMD + full PREMIS digiprovMD chain | master + preservation + access |
| DIP (Dissemination Information Package) | Public download / data export | Full Dublin Core | rightsMD only (PREMIS suppressed to keep agent / forensic metadata out of the public surface) | access copies only |

### API

```php
use AhgMetadataExport\Services\Exporters\MetsSerializer;

$s = new MetsSerializer();
$xml = $s->serializeRecord($ioId, 'en', 'AIP');  // 'SIP' | 'AIP' | 'DIP'
```

`MetsSerializer::serializeRecord()` keeps Phase 1's default of `'AIP'` so
existing callers and the action-bar download link in the IO show page do
not change behaviour. Pass `'SIP'` or `'DIP'` for the other shapes.

### PROFILE URIs

The `<mets PROFILE="...">` attribute swaps per profile:

| Profile | URI |
|---|---|
| SIP | `https://heratio.theahg.co.za/profiles/mets/io-sip-v1` |
| AIP | `https://heratio.theahg.co.za/profiles/mets/io-aip-v1` |
| DIP | `https://heratio.theahg.co.za/profiles/mets/io-dip-v1` |

`MetsSerializer::PROFILE` is now an alias of `PROFILE_AIP` for back-compat
with any caller that referenced it before Phase 4.

### Route integration

The existing `informationobject.export.mets` route accepts a new
`?profile=sip|aip|dip` query parameter once the controller thread-through
patch lands (see the Unlock checklist below). Default stays `aip`.

```
GET /informationobject/{slug}/export/mets
GET /informationobject/{slug}/export/mets?profile=sip
GET /informationobject/{slug}/export/mets?profile=dip
```

## SPARQL endpoint

`POST /admin/sparql` and `GET /admin/sparql?query=...` answer SPARQL 1.1
SELECT queries against the in-memory PROV-O graph built from
`preservation_event` rows for the requested IO. The result body matches
[SPARQL 1.1 Query Results JSON Format](https://www.w3.org/TR/sparql11-results-json/).

The path lives under `/admin/` so the slug catch-all regex in
ahg-information-object-manage (currently locked) does not intercept it.
Moving to bare `/sparql` requires unlocking that package and adding
`sparql` to the exclusion list.

### Parameters

| Parameter | Where | Required | Purpose |
|---|---|---|---|
| `ioId` | query string or form body | yes | Pre-filters the triple set to the named information_object. Full-corpus SPARQL is Phase 5. |
| `query` | query string, form body, or raw POST body with `Content-Type: application/sparql-query` | yes | The SPARQL SELECT text. |

### Auth

Either a logged-in web session OR a `Bearer` token equal to the value of
`ahg_setting.sparql_bearer_token`. Missing/blank setting means the Bearer
path is closed - external clients without the token can't query.

To enable the Bearer path, insert the setting once:

```sql
INSERT INTO ahg_setting (`key`, `value`) VALUES ('sparql_bearer_token', '<long-random-secret>')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
```

### Example query

```sparql
PREFIX prov: <http://www.w3.org/ns/prov#>
PREFIX ahg:  <https://heratio.theahg.co.za/ns/ahg/>

SELECT ?activity ?type ?outcome
WHERE {
  ?activity a prov:Activity ;
            ahg:eventType ?type ;
            ahg:eventOutcome ?outcome .
}
LIMIT 50
```

Default prefixes (rdf, rdfs, xsd, prov, premis, ahg) are pre-declared so
short queries omit the `PREFIX` block.

### What the engine supports

- `PREFIX` declarations
- `SELECT (DISTINCT)? *|?vars` projection
- Basic graph patterns inside `WHERE { }` with `.` and `;` separators
- The `a` shorthand for `rdf:type`
- `LIMIT N`
- IRIs, prefixed names, literals, and variables

What it deliberately does not (Phase 5 work):

- `OPTIONAL`, `UNION`, `FILTER`, `MINUS`, property paths, subqueries
- `ASK`, `CONSTRUCT`, `DESCRIBE`
- Federated queries (`SERVICE`)
- Full-corpus SPARQL without `ioId` pre-filter

## Files added / modified

```
packages/ahg-metadata-export/src/Services/Exporters/MetsSerializer.php          (refactored: profile-aware)
packages/ahg-metadata-export/src/Services/Sparql/ProvOGraphBuilder.php          (new)
packages/ahg-metadata-export/src/Services/Sparql/SimpleSparqlEngine.php         (new)
packages/ahg-metadata-export/src/Controllers/SparqlController.php               (new)
packages/ahg-metadata-export/routes/web.php                                     (+ /admin/sparql)
packages/ahg-metadata-export/tests/MetsSerializerTest.php                       (+ profile tests)
packages/ahg-metadata-export/tests/SimpleSparqlEngineTest.php                   (new)
docs/help/mets-export-profiles.md                                               (new, /help article)
```

## Unlock checklist (for the IO controller patch)

The controller method that calls `MetsSerializer::serializeRecord()` lives in
the locked `ahg-information-object-manage` package. To make the
`?profile=sip|aip|dip` query parameter live, unlock the controller for
one release:

```
./bin/unlock packages/ahg-information-object-manage/src/Controllers/ExportController.php
```

Then change the `mets()` method's two-line body to:

```php
$profile = strtoupper((string) request()->query('profile', 'aip'));
$serializer = new \AhgMetadataExport\Services\Exporters\MetsSerializer();
$body = $serializer->serializeRecord((int) $io->id, $culture, $profile);
```

Until that lands, the new profile shapes are reachable programmatically
(through the service) and the SPARQL endpoint is fully live; the
`?profile=` query parameter on the existing METS download link is the
only piece blocked behind the unlock.

## easyrdf - not added

The Phase 4 brief mentioned `easyrdf/easyrdf` as an option. We did not
add it because:

- The Phase 4 SPARQL scope is bounded (one IO at a time, no federation,
  SELECT only), and a 300-line in-memory engine covers it.
- Avoiding a root composer.json edit while parallel agents are touching
  other packages reduces merge conflicts.

Phase 5 (full-corpus SPARQL, CONSTRUCT, federation) will need easyrdf or
an equivalent - that is the right moment to take the dependency.
