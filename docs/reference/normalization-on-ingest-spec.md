# Spec: Normalization on ingest (Archivematica parity)

> Status: **Phase 1 + Phase 2 BUILT on dev** (Laravel side, #1385).
> Phase 1: rule registry + NormalizationService + queued job + opt-in
> `process_normalize` toggle + preservation-master derivative + PREMIS
> `normalization` event + dashboard count (verified JPEG->TIFF master).
> Phase 2: **access copies** (purpose='access' rules -> Reference-usage
> derivatives, e.g. TIFF->JPEG, verified), **FPR admin CRUD** at
> /admin/preservation/normalization-rules (list/add/toggle/delete + menu),
> **batch backfill** command `ahg:normalize-existing` (--purpose --limit
> --mime --sync). The ingest hook now dispatches both preservation + access
> jobs when Normalize is ticked. AtoM-AHG port still to do.
> Audience: developer, digital preservation officer, sysadmin
> Applies to both codebases: Laravel Heratio + AtoM-AHG.
> Builds on: [[ingest-preservation-pipeline]] (the always-on baseline that
> already does fixity + PRONOM + virus + PREMIS on every ingested object).

## Goal

Make ingest produce a **preservation master** (and, in a later phase, an
**access copy**) in an open, long-lived format for every ingested digital
object - the way Archivematica's normalization micro-service does. Driven by
per-format rules, recorded as a PREMIS `normalization` event, and carried into
the AIP.

This is the one remaining gap between our ingest pipeline and Archivematica:
the baseline already fingerprints, identifies, virus-checks and PREMIS-logs
every object; normalization adds the format-migration step.

## Current state (the gap)

- **AtoM-AHG**: `PreservationService::convertFormat()` works -
  `selectConversionTool()` + `executeConversion()` (ImageMagick / FFmpeg /
  Ghostscript / LibreOffice), output to `…/conversions/`, logged to
  `preservation_format_conversion`. But it produces a loose file + a log row;
  it does NOT attach the output as a linked digital object, does NOT fire a
  normalization event, and nothing calls it on ingest.
- **Laravel Heratio**: `PreservationService` only **detects** tools
  (`getConversionTools()`) and reads conversion logs
  (`getConversionStats`, `getRecentConversions`). There is **no execute
  method** - the conversion engine must be ported/built.
- **Neither** side has: a format-policy registry (FPR), an ingest toggle, or
  derivative wiring.

## Design

### 1. Normalization rules (the FPR)

New table `preservation_normalization_rule`:

| column | purpose |
|---|---|
| id | PK |
| source_pronom | PRONOM PUID to match (nullable) |
| source_mime | MIME to match (nullable; pronom wins when both set) |
| purpose | `preservation` \| `access` |
| target_format | e.g. `tiff`, `pdfa`, `wav`, `mkv`, `txt` |
| tool | `imagemagick` \| `ghostscript` \| `ffmpeg` \| `libreoffice` |
| command_template | optional override of the default tool invocation |
| is_active | tinyint |

Seeded defaults (preservation purpose): images -> TIFF, office/PDF -> PDF/A,
audio -> WAV, video -> FFV1/MKV, text -> UTF-8 text. Dropdown-managed values
per project rules (no ENUM columns).

Alternative considered: reuse `preservation_policy` (`policy_type='normalization'`,
rules in `config JSON`). Rejected for clarity/reporting - a dedicated table is
the Archivematica FPR model. (Decision 2: dedicated table.)

### 2. NormalizationService (one per codebase)

`normalizeDigitalObject(int $doId, string $purpose = 'preservation'): ?object`

1. Resolve the object's identified format (uses the PRONOM result the baseline
   now records).
2. Look up the matching active rule; no rule -> no-op (logged).
3. Run the conversion. Laravel: port AtoM's `selectConversionTool` /
   `executeConversion`. AtoM: wrap existing `convertFormat`.
4. Register the output as a **linked derivative `digital_object`**:
   `parent_id` = original DO, a dedicated `usage_id` ("Preservation master" /
   "Access copy"), plus `path`, `mime_type`, `byte_size`, `checksum`,
   `checksum_type`.
5. Generate the derivative's fixity checksum.
6. Log a PREMIS `normalization` event linking source -> derivative (tool,
   version, outcome).
7. Fail-soft + idempotent (skip if a derivative for that purpose already
   exists).

`digital_object` columns confirmed present: `object_id, usage_id, mime_type,
media_type_id, name, path, sequence, byte_size, checksum, checksum_type,
parent_id`.

### 3. Ingest hook + toggle

- Add `process_normalize` (tinyint) to `ingest_session`; surface a checkbox on
  the ingest **configure** page + a global default setting (default off).
- Normalization is heavy/opinionated, so it is **opt-in** - unlike the
  always-on baseline. (Decision 3.)
- In `runPreservationBaseline()` (both sides), when `process_normalize` is on,
  **queue** a normalization job per created DO rather than running inline
  (LibreOffice/FFmpeg are slow and would stall large ingests). Laravel: queue
  dispatch; AtoM: `QueueService->dispatch`. (Decision 4.)

### 4. Surfacing

- Preservation dashboard: "Normalized objects" count + recent normalizations
  (wire into existing `getConversionStats` / `getRecentConversions`).
- Central Dashboard Data Ingest card: add "N normalized" to the preservation
  line.
- Object digital-object panel: show preservation-master / access-copy
  derivatives.

## Decisions (locked)

1. Normalized master lives as a **linked derivative `digital_object`** (visible
   + in AIP), not just a loose file.
2. Rules in a **new `preservation_normalization_rule` table** (FPR-style).
3. **Opt-in** `process_normalize` toggle + global default off; baseline stays
   always-on.
4. **Queued job per object**, not inline in the commit.
5. Phase 1 = **preservation master only**; access/DIP copies are a fast-follow.

## Phasing

- **Phase 1**: rule table + seeds + `NormalizationService` + queued ingest hook
  + `process_normalize` toggle + PREMIS `normalization` event + derivative
  attach (preservation master only). Build on **dev** first, AtoM second.
- **Phase 2**: access/DIP copies, per-rule admin CRUD (FPR UI), dashboard
  normalization panel, batch "normalize existing objects" command.

## Effort & risk

- Laravel: ~1-1.5 days (build conversion executor + service + table + migration
  + queue job + toggle + derivative wiring + dashboard). Touches `ahg-preservation`,
  `ahg-ingest`, `ahg-reports` - all locked (unlock required).
- AtoM-AHG: ~0.5-1 day (`convertFormat` exists; add table + service wrapper +
  derivative attach + event + ingest hook + toggle).
- Risks: shell-exec tools (`escapeshellarg`, timeouts, sandboxing); disk growth
  (TIFF/FFV1 masters are large); lossy/opinionated target choices (why the FPR
  + opt-in matter); LibreOffice headless concurrency.
- Hard dependency: ImageMagick / Ghostscript / LibreOffice / FFmpeg installed
  on each host (archaeology / WDB / Heratio host) - verify before enabling.
