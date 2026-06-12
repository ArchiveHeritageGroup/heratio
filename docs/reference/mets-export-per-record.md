# METS export per record

A per-record METS 1.12 (Library of Congress) XML export for published archival records, in the `ahg-api` package. METS is the standard archival-interchange wrapper used to exchange records between archives and to ingest them into preservation / repository systems. The surface is open data: no API key, read-only, published-only, CORS-open.

## Endpoint

```
GET  /mets/{idOrSlug}.xml      -> application/xml METS 1.12 document
OPTIONS /mets/{idOrSlug}.xml   -> 204 CORS preflight
```

`{idOrSlug}` is the record slug or its numeric `information_object` id. Route name `mets.show`, registered in `packages/ahg-api/routes/api.php`, handled by `AhgApi\Controllers\MetsController`.

## Document structure

One METS document carries the four standard sections:

- `mets:metsHdr` - `CREATEDATE` (UTC ISO-8601, generated at request time) + a `CREATOR` `mets:agent` of `TYPE="ORGANIZATION"` naming the holding repository (or `config('app.name')` when the record has no repository).
- `mets:dmdSec` - an `mdWrap MDTYPE="DC"` wrapping simple Dublin Core in the `oai_dc` wrapper: `dc:title`, `dc:creator` (one per creator), `dc:date`, `dc:description` (scope and content), `dc:publisher` (the repository), `dc:identifier` (the reference code and the dereferenceable record URL), `dc:type` (`Collection` for a fonds/collection/series, else `Text`). This is the SAME oai_dc shape the cite `.dc.xml` and the OAI-PMH endpoint serve.
- `mets:fileSec` - one `mets:fileGrp USE="master"` with a `mets:file` per `digital_object` row. Each file carries `MIMETYPE`, `SIZE` (byte_size), and `CHECKSUM` + `CHECKSUMTYPE` when stored, plus a `mets:FLocat LOCTYPE="URL"` `xlink:href` to the public file URL. Images get a second `FLocat` to the IIIF Image API service URL.
- `mets:structMap TYPE="physical"` - a record-level `mets:div` (`DMDID` -> the dmdSec) nesting one child `mets:div`/`mets:fptr` per file.

The root `mets:mets` declares the METS, xlink, dc, oai_dc and xsi namespaces with `xsi:schemaLocation` to the LoC METS 1.12.1 schema, and carries `OBJID` (the reference code), `LABEL` (the title), and `TYPE`.

## Resolution and the published gate (reused)

`MetsController::resolve()` reuses the exact slug -> `information_object` join + published-only gate of `EntityController`, `CitationController` and `IiifPresentationController`:

- join `information_object` -> `slug` -> `information_object_i18n` (culture) and left-join `status` on `type_id = 158` (publication status).
- a numeric token is matched as `io.id`; anything else as `s.slug`.
- the synthetic root `id = 1` is always excluded.
- only `status_id = 160` (Published) passes; an unknown, unpublished or root record yields `null` -> a clean 404 XML. A schema variance yields `null`, never an exception.

Descriptive fields reuse the cite mapping (title, identifier, date via `event` display date or start/end span, creators via `event` + `actor_i18n.authorized_form_of_name`, scope, level via `term_i18n`, repository-as-publisher via `repository` + `actor_i18n`). Digital objects + the IIIF identifier reuse the IIIF controller's logic.

## File URLs and checksums

- Public file URL: `url('/') + '/uploads/' + path + name`, normalised so `/uploads/` is rooted exactly once (the stored `digital_object.path` already begins with `/uploads/` on this deployment; the normaliser handles both forms). The deployed nginx maps `^~ /uploads/` to the storage mount. Built from `url('/')`, never a hardcoded host.
- IIIF locator (images only): the SAME `'/' -> '_SL_'` Cantaloupe identifier the deployed viewer + `IiifPresentationController` build, under `/iiif/3/`.
- Checksum: `digital_object.checksum` + `checksum_type`. The stored type is normalised to a METS `CHECKSUMTYPE` enumerated value (`MD5`, `SHA-1`, `SHA-256`, `SHA-384`, `SHA-512`, `CRC32`, `Adler-32`, ...); an unmappable type is dropped while the `CHECKSUM` is still emitted, so no invalid `CHECKSUMTYPE` is ever produced.

## Catch-all safety

The route is multi-segment and dotted (`/mets/{idOrSlug}.xml`), so the single-segment `/{slug}` archival-record catch-all (constraint `[a-z0-9][a-z0-9-]*$` - one segment, no slash, no dot) can never intercept it; a normal record slug still resolves. The `{idOrSlug}` matcher (`[A-Za-z0-9][A-Za-z0-9\-_/]*`) permits multi-segment slugs, with the trailing `.xml` pinned as a literal so the record token can never absorb the suffix. A `mets` entry is added to `ProtocolController::surfaces()` so the export is discoverable from `/open-data/protocol` and the DCAT catalogue.

## Guarantees

Read-only (SELECT only; no writes, no DDL, no new table). Every emitted value XML-entity-escaped (no injection from titles). Empty-but-valid for a record with no digital objects. International (METS + Dublin Core + xlink are standards; no jurisdiction assumptions). Permissive open CORS. No locked file touched (the whole change is under the unlocked `packages/ahg-api/` + `docs/`).
