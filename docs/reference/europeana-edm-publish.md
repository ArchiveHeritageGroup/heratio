# Europeana EDM Publish (Heratio)

**Status:** Shipped in Heratio Phase 4 of issue #670 (Federation audit).
**Package:** `packages/ahg-federation/` (new subdir `src/Edm/` - the lock
on F3 SharePoint connectors is narrower than the package; only the four
F3 files in `.locked-paths` are pinned, so the EDM work is unlocked.)

## What it does

Serialises every published Heratio Information Object (status type 158,
status_id 160) to one Europeana Data Model RDF/XML file per record, then
emits a sitemap and zips everything into a single bundle ready to hand
off to Europeana's data-ingest pipeline.

Each per-record file carries the four canonical EDM classes Europeana's
ingestion gate checks for:

- `edm:ProvidedCHO` - the cultural-heritage object (dc:title, dc:description, dc:identifier, dc:creator, dc:subject, dc:date, dc:language, dc:rights, dc:format, dc:contributor, edm:type, dcterms:spatial)
- `ore:Aggregation` - the providing wrapper (edm:dataProvider, edm:provider, edm:isShownAt, edm:isShownBy, edm:object, edm:rights)
- `edm:WebResource` - one per digital surrogate (dc:format, edm:rights)
- `edm:Agent` / `edm:Place` / `edm:TimeSpan` - contextual entities by URI

## edm:type vocabulary

Europeana's edm:type is a fixed 5-bucket set: `TEXT`, `IMAGE`, `SOUND`,
`VIDEO`, `3D`. The serializer derives it from the master digital
object's MIME type, defaulting to `TEXT` when no digital surrogate is
attached.

## Rights URI

`edm:rights` is mandatory and must resolve. The serializer looks up an
attached `rights_statement.uri` first (via `object_rights_statement`),
falling back to the `federation.europeana_default_rights` setting and
finally to `http://rightsstatements.org/vocab/InC/1.0/`.

## Configurable settings

In `setting` (scope = `federation`, culture = `en`):

| Setting | Default | Purpose |
| --- | --- | --- |
| `europeana_data_provider` | `The Archive and Heritage Group` | The `edm:dataProvider` literal. |
| `europeana_country` | `South Africa` | Provider country (informational). |
| `europeana_language` | record culture | Provider primary language. |
| `europeana_default_rights` | `http://rightsstatements.org/vocab/InC/1.0/` | Fallback rights URI when nothing is linked. |

## CLI

```
php artisan europeana:export
php artisan europeana:export --out=storage/europeana/
php artisan europeana:export --since=2026-01-01
php artisan europeana:export --culture=en
```

Outputs land in `storage/europeana/` (or the absolute path passed via
`--out`):

- `record-NNNNNNN.xml` per IO
- `sitemap.xml`
- `europeana-bundle-YYYY-MM-DD.zip`

Every run writes a row to `ahg_europeana_export` (started_at,
finished_at, record_count, bundle_path, bundle_size_bytes, status,
error).

## Schedule

The package service provider registers a weekly schedule (Sundays
02:00) gated on `federation_enabled`, so the same global toggle that
disables OAI harvest also disables Europeana publish. Disable globally
via the Federation Dashboard or via `setting` row `federation_enabled`.

## Admin UI

`/federation/europeana` (alias `/admin/federation/europeana`) shows
last-run timestamp, record count, bundle size, run history, a "Generate
now" button and a "Download bundle" link. Same `auth` + `admin` +
`EnsureFederationEnabled` middleware stack as the rest of the
federation dashboard.

## DB table

```
ahg_europeana_export (
    id INT PK,
    started_at DATETIME,
    finished_at DATETIME,
    record_count INT,
    bundle_path VARCHAR(1024),
    bundle_size_bytes BIGINT,
    status VARCHAR(32),      -- running | success | error
    error TEXT,
    created_at DATETIME
)
```

Auto-seeded in the service provider boot when `Schema::hasTable` is
false, wrapped in one try/catch per `reference_ci_schema_hastable.md`.

## Test plan

- `packages/ahg-federation/tests/EdmSerializerTest.php` parses the
  output of one real IO and asserts the four EDM classes are present,
  edm:type is in the 5-bucket vocabulary, and the four mandatory
  ore:Aggregation properties are emitted.
- Skips cleanly when no DB is reachable (CI safe).

## What stays open under #670 after this

- DPLA MAP (American counterpart aggregator)
- IIIF Federation / IIIF Change Discovery API
- Per-peer styling on federated search results
- Peer health alerts
