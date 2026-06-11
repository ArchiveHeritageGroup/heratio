# PREMIS 3.0 metadata-export (web download)

Summary: Heratio's `ahg-metadata-export` package now serves a one-click PREMIS 3.0 preservation-metadata XML download for a single archival record and its digital object(s), at `/admin/metadata-export/premis?io=NNN`. It mirrors the METS and CIDOC-CRM exporters in the same package: a `PremisSerializer` class, a controller `downloadPremis()` method, named routes, and a dashboard format card. The serializer is strictly read-only (SELECT only, no writes, no ALTER) and probes for the preservation tables with `Schema::hasTable()` so it degrades gracefully on installs that have not run the preservation pipeline. This advances issues #1197 (unified metadata) and #1244 / #1243 (digital preservation), which remain open.

## Why this exists alongside the CLI PREMIS export

There are now two PREMIS surfaces in Heratio, and they are deliberately complementary:

- **`ahg-preservation` CLI** (`premis:export`, `PremisXmlSerializer`) - the preservation-officer tool. Adds ODRL-to-PREMIS `<rights>` projection (`ahg_premis_rights`), XSD validation, and file output. This package is locked.
- **`ahg-metadata-export` web download** (`PremisSerializer`, this document) - the standards-consistent browser download exposed next to METS / CIDOC-CRM / MODS / DACS / EAD. Object / event / agent evidence; rights-light. This package is not locked.

Both use the same namespace (`http://www.loc.gov/premis/v3`, version 3.0) and read fixity / size / format from the same tables, so output is interoperable downstream (Archivematica, Preservica, Rosetta).

## Exposure (mirrors CIDOC-CRM exactly)

- Controller: `MetadataExportController::downloadPremis(Request, ?string $ext)` - twin to `downloadCidocCrm()`. `?io=NNN` required, `?culture=` optional. Returns an `application/xml` attachment `heratio-premis-<id>.xml`; 400 on missing `io`, 404 when the serializer returns empty (IO missing or fails the gate).
- Routes (under the `admin/metadata-export` prefix, `web` + `auth` middleware, catch-all-safe):
  - `GET /admin/metadata-export/premis` -> `ahgmetadataexport.premis`
  - `GET /admin/metadata-export/premis.{ext}` where ext = `xml` -> `ahgmetadataexport.premis.ext`
- Dashboard: a `premis` entry in the controller's `$formats` array (`PREMIS 3.0 (Preservation XML)`, icon `bi-shield-check`) so the card / preview appears with the other formats.

The `/admin/metadata-export` prefix keeps these URLs clear of the `/{slug}` IO catch-all in `ahg-information-object-manage`.

## PREMIS-to-DB mapping

The serializer is `AhgMetadataExport\Services\Exporters\PremisSerializer`. It uses the shared `InformationObjectFetcher` trait for the IO lookup.

### premis:object (one per digital_object; `xsi:type="premis:file"`)

| PREMIS element | DB evidence | Omitted when |
|---|---|---|
| `objectIdentifier` (type `heratio-digital-object`) | `digital_object.id` | never |
| `fixity` / `messageDigestAlgorithm` + `messageDigest` | `digital_object.checksum_type` (normalised to SHA-256 / SHA-1 / MD5 etc.) + `digital_object.checksum` | no checksum recorded |
| `size` | `digital_object.byte_size` | null / 0 |
| `format` / `formatDesignation` (`formatName`, `formatVersion`) | `preservation_object_format.format_name` / `format_version`; falls back to `digital_object.mime_type` for the name when no identification row exists | no format name and no MIME |
| `format` / `formatRegistry` (`PRONOM`, `formatRegistryKey`) | `preservation_object_format.puid` | no PUID identified |
| `format` / `formatNote` (`Identified by <tool>`) | `preservation_object_format.identification_tool` | no tool recorded |
| `originalName` | `digital_object.name` | blank |
| `relationship` (`is part of`) -> `relatedObjectIdentifier` (type `heratio-information-object`) | `information_object.id` | never (anchors the file to its description) |

When the record has **no** digital objects, a single representation-level `premis:object` (`xsi:type="premis:representation"`) is emitted instead, identified by the IO id with `originalName` from the IO identifier / title, so the document is always well-formed.

### premis:event (one per preservation_event linked to the IO or its DOs)

Source table `preservation_event`, matched on `information_object_id = io.id` OR `digital_object_id IN (the IO's digital objects)`, ordered by `event_datetime`.

| PREMIS element | DB column |
|---|---|
| `eventIdentifier` (type `local`) | `preservation_event.id` |
| `eventType` | `event_type` |
| `eventDateTime` (ISO 8601 UTC) | `event_datetime` |
| `eventDetailInformation` / `eventDetail` | `event_detail` (omitted when blank) |
| `eventOutcomeInformation` / `eventOutcome` | `event_outcome` (default `unknown`) |
| `eventOutcomeDetail` / `eventOutcomeDetailNote` | `event_outcome_detail` (omitted when blank) |
| `linkingAgentIdentifier` | `linking_agent_type` + `linking_agent_value` (omitted when no agent) |
| `linkingObjectIdentifier` | `digital_object_id` (preferred) or `information_object_id` |

### premis:agent (one per distinct responsible system)

Derived from the distinct `linking_agent_type` + `linking_agent_value` pairs across the event list (first-seen order, deduplicated). `agentType` maps system/software/tool -> `software`, person/user -> `person`, organization/repository -> `organization`.

## Graceful degradation

`digital_object`, `preservation_object_format`, `preservation_event`, and `status` are each guarded by `Schema::hasTable()`. On an install with no preservation pipeline the export still produces a valid PREMIS document containing whatever exists (often just the representation-level object). Nothing is assumed to exist.

## Validation done

Verified on the live `heratio` DB (read-only) against records with real preservation data: IO 553 (digital object 702, a TIFF), 768, 829. Each produced well-formed XML (`DOMDocument::loadXML` + `simplexml` namespace-aware XPath) with the correct PREMIS namespace, real SHA-256 fixity, PRONOM PUID `fmt/353`, byte size, originalName, multiple events, and deduplicated agents. JSON embedded in `event_outcome_detail` is correctly XML-escaped (`XMLWriter` handles special characters). Edge cases also verified: a description-only IO (1767) yields a valid representation object; a missing IO id returns an empty string (the controller 404s); the `publicOnly` gate suppresses unpublished records.

## Constraints honoured

Read-only end to end (no INSERT/UPDATE/DELETE/ALTER). Existing exporters untouched. AHG / Plain Sailing / AGPL file headers; `@copyright` "Plain Sailing Information Systems". No em-dashes. Jurisdiction-neutral / international. `packages/ahg-metadata-export/` is not in `.locked-paths`; `packages/ahg-preservation/` is locked and was not touched.
