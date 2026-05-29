# MARC Editor: Validation, Merge, and REST API

**Summary.** Heratio's MARC editor (issue #1098) covers the full MARC21
lifecycle for library cataloguing: MARCXML round-trip, ISO 2709 binary
encode/decode, an in-place field editor, a structural+semantic validation
engine, a merge/conflict-review workflow, a REST MARC API, and authority-link
($0) round-trip. MARC logic lives in two packages: `ahg-metadata-export`
(serializers/importers/encoders) and `ahg-library` (services, controllers,
views, REST endpoints). SOAP / the MarcEdit-proprietary remote protocol is a
documented non-goal.

## Components at a glance

| Concern | Class | Package |
|---|---|---|
| MARCXML serialize | `Services\Exporters\MarcxmlSerializer` | ahg-metadata-export |
| MARCXML import (preview/commit) | `Services\Importers\MarcXmlImporter` | ahg-metadata-export |
| ISO 2709 encode | `Services\Exporters\Marc21BinaryEncoder` | ahg-metadata-export |
| ISO 2709 decode | `Services\Marc21DecoderService` | ahg-library |
| In-place field edit | `Services\MarcEditService` | ahg-library |
| Validation engine | `Services\MarcValidationService` | ahg-library |
| Merge / conflict diff | `Services\MarcMergeService` | ahg-library |
| Authority CRUD + linking | `Services\AuthorityControlService` | ahg-library |

## Validation engine

`MarcValidationService::validate(string $xml): array` parses a MARCXML
document (one or more `<record>` elements) and returns a structured report.
It complements the XSD well-formedness check in `MarcXmlImporter::validate()`
with MARC21-specific rules the schema cannot express:

- **Leader** must be exactly 24 bytes. Per-position checks cover record
  status (05), type of record (06), bibliographic level (07), character
  coding (09, warning), **encoding level (byte 17)**, descriptive cataloguing
  form (byte 18), and the fixed entry map (20-23, warning).
- **Tag existence**: every control/data field carries a 3-character tag.
  Control fields (00X) must not carry subfields; data-field tags must not be
  in the 00X range.
- **Subfield codes** must be a single lowercase letter or digit.
- **Indicators** must be a single char (blank, digit, or lowercase letter).
- **008 fixed-field length**: 40 chars is conformant; shorter is a warning
  (legacy records routinely truncate it), longer is an error.
- **245** title statement is required.

Report shape: `{ valid, records[], error_count, warning_count, parse_error }`.
Each record entry carries `{ index, title, control_number, errors[],
warnings[] }`. `valid` is false if any record has at least one error;
warnings never fail the document.

### Endpoint

`POST /api/cataloguing/marc/validate`
Body: `{ "marcxml": "<record>...</record>" }`
Returns HTTP 200 with the report wrapped in a JSON:API-style envelope
(`data.attributes`). HTTP 422 only when the request body itself is malformed.

Server-rendered companion: upload a file, render
`marc-editor/validation-report.blade.php`.

## Merge / conflict workflow

`MarcMergeService::diff(string $incomingXml, string $culture): array` matches
the incoming record to a master `information_object` by its 001 control
number (string identifier first, numeric id fallback - same matcher as the
importer). The master is re-serialized to MARCXML and both sides are reduced
through `MarcXmlImporter::describeRecord` so they share an identical shape.
Each logical field is then classified:

- `unchanged` - incoming equals master
- `changed` - both present, values differ (a conflict the reviewer resolves)
- `added` - present in incoming, absent in master
- `removed` - present in master, absent in incoming

Scalar fields compared: 001, 245$a, 520$a, 300$a, 561$a, 541$a, 540$a, 506$a,
544$a. List fields compared as sets: 650$a, 651$a, 655$a, creators (1XX/7XX).

Report shape: `{ matched, matched_io_id, control_number, title,
has_conflicts, conflict_count, fields[], warnings[] }`. No writes happen in
the diff - resolution + commit is the caller's job.

### Endpoint

`POST /api/cataloguing/marc/merge`
Body: `{ "marcxml": "...", "culture": "en" }`
Returns HTTP 200 with the diff report.

Server-rendered companion: `marc-editor/conflict-review.blade.php` shows
master vs incoming side by side with a keep-master / take-incoming radio pair
per conflict.

## REST MARC API

`GET /api/cataloguing/marc/export?record_ids[]=N&format=iso2709|marcxml`
Exports one or more `information_object` records. `record_ids` are
information_object ids. `format` defaults to `marcxml` (returns an XML
`<collection>`); `iso2709` returns concatenated binary MARC21
(`application/marc`). 404 when none of the ids resolve.

`POST /api/cataloguing/marc/import`
Body: `{ "marcxml": "<collection>...</collection>", "culture": "en" }`
Commits every `<record>` via `MarcXmlImporter::commit` (create/update + a
hash-chained audit row per record) and returns
`{ created, updated, skipped, records[] }`. HTTP 201.

Both endpoints reuse the existing exporters/importers - no MARC logic is
reimplemented in the controller.

## Authority-link round-trip ($0)

Subject authorities are stored in `library_subject_authority` (heading,
lc_label, subject_type, uri, vocab_uri, linked_count, ...) and linked to a
catalogue record through `library_item_authority_link`
(library_item_id, authority_id, source_tag).

- **Export**: `MarcxmlSerializer` joins `library_item ->
  library_item_authority_link -> library_subject_authority` for the IO and
  emits a `$0` authority URI on each 650/651/655 whose heading matches a
  linked authority that has a URI. IOs with no library_item wrapper simply
  emit subjects without `$0` (graceful no-op via `Schema::hasTable`).
- **Import**: `MarcXmlImporter` parses each 6XX `$a`/`$0` pair into an
  `authority_links` list (with `subject_type` mapped per tag: 650=topic,
  651=place, 655=genre). On commit, each link is matched to an existing
  authority by URI (preferred) or heading, created if absent
  (`source = marc-import` when a URI is present), and a
  `library_item_authority_link` row is inserted with `linked_count`
  incremented. Re-import is idempotent (existing links are skipped).

## Authentication and permissions

All `/api/cataloguing/marc/*` endpoints sit behind the shared `api.auth`
key-auth middleware plus `api.ratelimit`. Controllers additionally enforce
`AclService` permissions via the `AuthorizesLibraryApi` trait: `read` for
validate/merge/export, `create` for import.

## Non-goal: SOAP / MarcEdit-proprietary protocol

The optional SOAP / MarcEdit remote-editing protocol from the issue is
intentionally **not** implemented. Heratio exchanges MARC over the documented
REST + MARCXML/ISO 2709 surfaces above, which are standards-based and
interoperable. The proprietary SOAP channel adds a vendor-specific dependency
without a corresponding interoperability gain and is recorded here as a
documented non-goal.

## Tests

- `packages/ahg-library/tests/Feature/MarcValidationApiTest.php` - leader
  length, encoding-level byte 17, missing 245, invalid subfield code, control
  field carrying a subfield, short-008 warning, malformed XML.
- `packages/ahg-library/tests/Feature/MarcMergeApiTest.php` - empty payload,
  unmatched record reports additions only, report shape.
- `packages/ahg-metadata-export/tests/MarcRoundTripAuthorityTest.php` - 6XX
  `$0` capture + per-tag subject_type, null-URI subjects, DB-guarded commit
  smoke test.
