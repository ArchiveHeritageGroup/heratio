# Archivematica -> Heratio DIP Pull - Runbook

**Summary:** How to get Archivematica DIPs (access derivatives + metadata) into Heratio as digital objects. Heratio integrates by **pulling** DIPs from the Archivematica **Storage Service (SS)** and attaching each DIP's access files to the matching archival description. This is the supported path (mirrors AtoM's SS plugin). Package: `ahg-archivematica`. Tracks #1401.

> **Do NOT use Archivematica's built-in "Upload DIP to AtoM" step.** Heratio is a Laravel rewrite, not AtoM, and does not expose AtoM's Symfony REST API - so that step fails with **"AtoM not found."** That is expected; turn it off (set "Upload DIP" to *Do not upload* / *Store DIP*). Heratio pulls from the SS instead.

## 1. How it works
`am:ingest-dips` (or the queued job) does, per DIP: download from the SS -> unpack -> parse `METS.xml` -> **match to an existing information_object** -> attach each access file as a `digital_object` via the canonical `IngestService::ingestFile()` -> map PREMIS fixity/format into `ahg-preservation` -> write an `am_link` row. Idempotent: a DIP already linked (by `dip_uuid`) is skipped.

## 2. Configuration (once)
Admin -> **AHG Settings -> Archivematica**:
- **Storage Service URL / API key / username** - the AM SS (`/api/v2/file/` API; auth header `ApiKey user:key`).
- **Dashboard URL / API key / username** - only needed for Direction 2 (Heratio drives transfers).
- **DIP match strategy** - see below.
Stored in `ahg_settings`; keys are never committed.

Health check (read-only): `sudo -u www-data php artisan am:ping` - confirms SS + Dashboard reachability.

## 3. Matching - the one thing that must be right
A DIP is attached to an **existing** Heratio description. The DIP must carry a key that names it. Three strategies (`am_dip_match_strategy`):

| Strategy | DIP must carry | Matched against |
|---|---|---|
| `identifier` (default) | Dublin Core `dc:identifier` | `information_object.identifier` |
| `slug` | `dc:identifier` = the record's slug | `slug` table |
| `uuid` | an AIP/SIP/transfer/DIP UUID in the METS | `am_link` (only records Heratio itself sent to AM - the D2 round-trip) |

**Use `identifier` or `slug` for DIPs created outside Heratio.** `uuid` only works after a Heratio -> AM round-trip (it reads `am_link`, which is empty until Heratio has sent transfers).

### The correct workflow (so DIPs actually match)
1. **Create the archival description in Heratio first**, note its **identifier** (or slug).
2. **Start the Archivematica transfer with a `metadata.csv`** (in the transfer's `metadata/` folder) whose `dc.identifier` column = that Heratio identifier. AM writes it into the DIP METS `dmdSec`.
3. Let AM process; the DIP lands in the SS.
4. `sudo -u www-data php artisan am:ingest-dips --sync` -> the DIP's access files attach to that description.

A DIP with no linking identifier stays `unmatched` (by design - Heratio will not guess a target).

## 4. Running the pull
```
sudo -u www-data php artisan am:ping                     # reachability (read-only)
sudo -u www-data php artisan am:ingest-dips --limit=5 -v  # controlled batch, verbose
sudo -u www-data php artisan am:ingest-dips --sync        # ingest inline (no queue worker)
```
Schedule `am:ingest-dips` on cron for hands-off pulls. Always run artisan as `www-data`, never root.

## 5. Verified end-to-end (2026-07-17, heratio-dev)
A minimal valid DIP fixture (METS with `dc:identifier` + one `USE="access"` file) was run through `DipIngestService::ingestFromPath()` against a description matched by `identifier`. Result: `status=linked, files_ingested=1`; a `digital_object` was created under the description, PREMIS fixity recorded, and an `am_link` row written. The Heratio ingest side works end to end. (The synthetic object was removed after the test.)

## 6. Known blocker on the current AM test VM
The AM VM's existing DIPs are **not yet ingestable**, and this is an **Archivematica-side** issue, not Heratio:
- **~half return HTTP 500 on download** from the SS - the package is registered but its file is not retrievable from the SS storage location (check the SS space/location config and that the packages are actually stored, not just recorded).
- **the downloadable half are empty** - METS has no `dc:identifier`, no `OBJID`, and **zero `USE="access"` files**. These transfers produced no access derivatives and carry no linking metadata.

To fix on the AM side: run real transfers that (a) normalize to access derivatives (so the DIP has `USE="access"` files), (b) include a `metadata.csv` with `dc.identifier` (per section 3), and (c) are stored in a reachable SS location. Then re-run `am:ingest-dips`.

## 7. Alternative: push (Mode B)
If you want AM to push instead of Heratio polling: `POST /api/archivematica/dip` (ahg-api key auth) accepts a DIP tarball and runs the same match+ingest pipeline. It needs AM-side automation (Automation Tools / a post-store hook) to call it - the native AtoM upload cannot. Pull is simpler and is the recommended path.

## 8. Reference
- Package: `packages/ahg-archivematica` (`ArchivematicaSsClient`, `MetsParser`, `DipMatcher`, `DipIngestService`, commands `am:ping`/`am:ingest-dips`/`am:poll`).
- Plan: `docs/archivematica-integration-plan.md` (#1401).
- Reuses `IngestService::ingestFile()`, `ahg-preservation` PREMIS/METS, `ahg-api` auth, `ahg-settings`.
