# Archivematica Integration - Implementation Plan

> Status: DRAFT plan. Tracks heratio #1401. Build target: `heratio-dev` first, then push to prod. Blueprint: AtoM `arStorageServicePlugin` (read-only ref at `/usr/share/nginx/archive`).

## 1. Context and goal

A client runs Archivematica (AM) and wants Heratio integrated with it. Today Heratio has only **native preservation parity** (`ahg-preservation`: BagIt, fixity, PRONOM, PREMIS, OAIS, normalization) and **no AM connector**. This plan delivers a real connector in **both directions**, as a new package `ahg-archivematica`.

AM has two relevant APIs:
- **Storage Service (SS) API** - where AIPs/DIPs land; browse + download packages.
- **Dashboard API** - drives processing (start transfer, poll microservice status).

AtoM integrates via the SS: settings hold `storage_service_api_url`, `storage_service_api_key`, `storage_service_username`; the plugin browses and extracts/downloads packages.

## 2. New package: `ahg-archivematica`

Standard Heratio package: `composer.json`, `ServiceProvider`, `Controllers/`, `Services/`, `routes/`, `resources/views/`, `database/install.sql`. Depends on `ahg-core`, `ahg-ingest` (reuse `IngestService::ingestFile()`), `ahg-preservation` (PREMIS/METS), `ahg-settings` (admin config).

### Config (Admin > AHG Settings > Archivematica)
- `am_ss_url`, `am_ss_api_key`, `am_ss_username` (Storage Service)
- `am_dashboard_url`, `am_dashboard_api_key`, `am_dashboard_username` (Dashboard)
- `am_default_pipeline_uuid`, `am_transfer_source_path`
- `am_dip_match_strategy` (uuid | identifier | slug)
- Stored in `ahg_settings`; never commit keys.

### Tables (`database/install.sql`)
- `am_link` - maps a Heratio `information_object` <-> AM package: `object_id`, `transfer_uuid`, `sip_uuid`, `aip_uuid`, `dip_uuid`, `status`, `am_pipeline_uuid`, timestamps.
- `am_job` - drives/monitors D2 transfers: `id`, `object_id`, `direction`, `status` (pending/processing/complete/failed), `am_uuid`, `microservice`, `last_polled_at`, `error`, `payload` (json).

## 3. Direction 1 - Archivematica -> Heratio (DIP upload / access) - PRIORITY

Goal: when AM finishes, the **DIP** (access derivatives + METS/Dublin Core) lands in Heratio, attached to the right archival description.

Two supported modes (config-selectable):

**A. Pull (Heratio polls SS)** - recommended, mirrors AtoM
1. `ArchivematicaSsClient` calls SS API to list new DIP packages (`/api/v2/file/?package_type=DIP`).
2. For each DIP: download, unpack, read `METS.xml`.
3. **Match** to an `information_object` by `am_dip_match_strategy` (AIP/AtoM UUID in METS `dmdSec`, or identifier, or slug).
4. For each access file: call `IngestService::ingestFile()` to create a `digital_object` under the matched description.
5. Map METS/PREMIS into `ahg-preservation` (fixity, format, rights) via `PremisXmlSerializer`.
6. Write/append `am_link` (dip_uuid, status=linked).

**B. Push (AM DIP-upload -> Heratio endpoint)**
- Route `POST /api/archivematica/dip` (key-auth via `ahg-api`) accepting a DIP tarball or SS callback.
- Same unpack -> match -> ingest pipeline as (A) step 2+.

Deliverables D1: `ArchivematicaSsClient`, `DipIngestService`, `IngestDipFromSs` job (+ `am:ingest-dips` command for cron), the push endpoint, `am_link` writes, admin config, tests with a sample METS/DIP fixture.

## 4. Direction 2 - Heratio -> Archivematica (drive transfers)

Goal: start and monitor AM processing from Heratio.

1. `ArchivematicaDashboardClient`:
   - Start transfer: `POST /api/transfer/start_transfer/` (name, type, paths, pipeline).
   - Approve: `POST /api/transfer/approve/`.
   - Poll: `GET /api/transfer/status/{uuid}/` and `GET /api/ingest/status/{uuid}/`.
2. `TransferService` creates an `am_job` (direction=to_am), kicks the transfer, stores `transfer_uuid`.
3. `PollArchivematicaJobs` (scheduled command `am:poll`) advances `am_job` status until SIP/AIP UUIDs are known; writes them to `am_link`.
4. On completion, optionally chain into D1 to pull the resulting DIP.
5. UI: on a record/accession show page, "Send to Archivematica" + a status panel (reads `am_job`).

Deliverables D2: `ArchivematicaDashboardClient`, `TransferService`, `PollArchivematicaJobs`, admin config, trigger UI + status panel, tests against mocked Dashboard responses.

## 5. Phasing

1. **Phase 0** - scaffold `ahg-archivematica`, config, `am_link`/`am_job` tables, SS/Dashboard clients with health-check commands (`am:ping`).
2. **Phase 1 (D1)** - DIP pull + match + ingest (mode A). The client's headline need.
3. **Phase 2 (D1 push)** - inbound endpoint (mode B) if the client prefers AM to push.
4. **Phase 3 (D2)** - drive + monitor transfers.
5. **Phase 4** - UI polish, error/retry, docs + `/help` article.

## 6. Cross-cutting

- **Idempotency:** match on UUID; skip if `am_link` already has the dip_uuid.
- **Security:** SS/Dashboard keys in `ahg_settings` only; push endpoint uses `ahg-api` key auth; validate METS before ingest.
- **Reuse, don't reinvent:** `IngestService::ingestFile()`, `ahg-preservation` PREMIS/METS, `ahg-api` auth, `ahg-settings`.
- **International:** no jurisdiction assumptions; AM is the client's, config-driven URLs only.

## 7. Open questions for the client (blockers to firm scope)

1. **Archivematica version** (affects SS/Dashboard API paths).
2. **Which API path:** Storage Service pull vs AM DIP-upload push vs Automation Tools.
3. **Matching key:** how do their AM packages reference the Heratio/AtoM description (UUID in METS? identifier?).
4. Do they need **D2 (Heratio drives transfers)** now, or only **D1 (receive DIPs)** first?

## 8. Testing

- Unit: METS parser, matcher, clients against recorded API fixtures.
- Integration: sample DIP fixture -> ingest -> assert `digital_object` + `am_link`.
- E2E (Playwright): admin config page, "Send to Archivematica" + status panel.
- Verify on `heratio-dev` (:8090) before promoting to prod.
