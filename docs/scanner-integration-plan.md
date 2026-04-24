---
title: Scanner Integration Plan
status: draft
owner: johan@theahg.co.za
created: 2026-04-23
---

# Scanner Integration Plan

How to get scanned or captured material (TIFF, JP2, PDF, WAV, MP4, 3D
meshes, structured XML, etc.) plus its descriptive metadata from a scanner,
camera, or capture application into Heratio as a fully-formed
`information_object` + `digital_object` with sector-appropriate records,
preservation metadata, and rights.

## 1. Goals

1. Operators can capture into Heratio **without using the web UI** — the
   scanner/camera application or a shared folder is enough.
2. Scans land against the **correct parent** (repository / fonds / series
   / file / item / exhibition / collection / accession — sector-dependent)
   with no manual re-filing afterwards.
3. Metadata travels with the file (sidecar or API payload) and flows into
   the **sector-appropriate tables** — archive, library, gallery, museum
   — not just the archival core. DAM (rights, technical metadata,
   preservation) is handled for every asset, cross-cutting all sectors.
4. Descriptive-standard-neutral on ingress: accepts our canonical XML plus
   common standards (EAD, MARC21, MODS, LIDO, Spectrum, DwC, Dublin Core,
   IPTC/XMP) via shipped transforms.
5. Pipeline is **idempotent and resumable** — re-dropping a file is safe,
   failures quarantine rather than crash, and retries are one click.
6. Every stage emits the right **PREMIS event** + technical metadata —
   preservation record is complete at ingest, not bolted on later.
7. Jurisdiction-neutral. Nothing in this plan is SA-specific; compliance
   hooks (POPIA, GDPR, GRAP, NAGPRA, CARE, etc.) sit on top as pluggable
   modules.

## 2. Three modes of capture (user picks, can run together)

| Mode | When to use | Auth | Destination selection |
|---|---|---|---|
| **A. Drop folder** | Offline scanning stations, batch scans, existing workflows | Filesystem ACL | Folder path OR XML sidecar |
| **B. Scanner-app → API** | VueScan/NAPS2/ScanDirect with output hooks, custom scripts | API key | Chosen in scanner app (dropdown) |
| **C. Heratio Capture desktop helper** | Ad-hoc scanning at an archivist's desk | API key (bound to user) | Browse tree in helper UI, drag & drop |

All three modes feed the **same processing engine** — which is the existing
`ahg-ingest` package, not a parallel pipeline (§2a). Choice of mode is
per-station; a site can run all three.

## 2a. Relationship to `ahg-ingest` (read this first)

`ahg-ingest` already exists and covers ~80 % of what scanning needs. The
scanner is a new **entry point** to the ingest engine, not a sibling system.

**What ahg-ingest already provides** (6-step wizard: configure → upload →
map → validate → preview → commit):

| Existing ingest concern | Used by scanner for… |
|---|---|
| `ingest_session` (parent, repository, sector, standard, parent_placement) | Destination + archival-standard config |
| Processing flags (virus/OCR/NER/summarize/spellcheck/translate/face-detect/format-id) | Per-folder or per-API-key processing defaults |
| Derivative flags (thumbnail, reference, normalize_format) | Derivative generation |
| SIP/AIP/DIP output flags | OAIS packaging — free for scanned material |
| `ingest_job` (progress, error_log, manifest) | Run-level tracking |
| `ingest_file` (staged file metadata) | Per-file record |
| `ingest_row` (per-item processing) | Per-item outcome |
| `ingest_mapping` (column→field + transforms) | **Skipped** — sidecar XML is self-describing |

**Mapping from scanner concepts to existing ingest tables:**

| Scanner concept | Existing ingest table | How it maps |
|---|---|---|
| A watched folder | `ingest_session` (long-lived, `session_kind=watched_folder`) | One session per folder, lives forever, keeps receiving files |
| An API scan session | `ingest_session` (`session_kind=scan_api`) | Created by `POST /api/v2/scan/sessions`, can be per-scan-batch or long-lived |
| An arrived file | `ingest_file` (+ `ingest_row`) | Watcher or API inserts rows |
| "Commit" | `ingest_job` | Auto-kicked on debounce when `auto_commit=1`, or by `POST /commit` |

**Operator experience:**

- `/admin/ingest/*` — the existing wizard, **unchanged**. Still the right tool
  for CSV/bulk one-offs.
- `/admin/scan/*` — new UI, but it's a **filtered view** over the same
  `ingest_session` / `ingest_job` / `ingest_file` tables
  (`WHERE session_kind <> 'wizard'`). Same data, different lens.

**Why this matters:** any improvement to ingest (new derivative, new AI
processor, new SIP format) automatically benefits scanning, and vice-versa.

## 2b. Sector and standards coverage

Heratio is a full **GLAM + DAM** platform, not archive-only. The scanner
must route incoming material into sector-appropriate records and honour the
descriptive standard the institution uses. Scope covers all four GLAM
sectors plus the cross-cutting DAM concerns (rights, preservation, technical
metadata, format identification).

### 2b.1 Per-sector destinations

`ingest_session.sector` (already exists: `archive` / `library` / `gallery` /
`museum`) decides which sector tables get populated in addition to the core
`information_object` + `digital_object`:

| Sector | Also writes to | Descriptive standards accepted |
|---|---|---|
| Archive | `event`, `relation`, `information_object_physical_location` | ISAD(G), ISAAR(CPF), ISDIAH, RAD, DACS, EAD, EAC-CPF, RiC-CM |
| Library | `library_item`, `library_copy`, `library_item_creator`, `library_item_subject`, `library_serial_issue` | MARC21, MODS, BIBFRAME, RDA, Dublin Core, ONIX |
| Gallery | `gallery_artwork`, `gallery_artist` (link), `gallery_exhibition` (link), optional `gallery_valuation` | CDWA, VRA Core 4, LIDO, CIDOC CRM |
| Museum | `museum_object`, `museum_metadata`, optionally `spectrum_object_entry` + `spectrum_acquisition` if the Spectrum workflow is active | Spectrum 5.1, LIDO, CIDOC CRM, Darwin Core (natural history), Nomenclature 4 |
| (all) DAM | `dam_asset`, `dam_iptc_metadata`, `dam_format_holdings`, `digital_object_metadata`, `media_metadata` | IPTC Photo Metadata, XMP, EXIF, PREMIS, schema.org |

Sector is determined in this order:

1. Sidecar `<sector>` element (explicit, wins)
2. `ingest_session.sector` (from folder config or API session)
3. Fallback: `archive`

**No sector default is "SA-specific"** — the product stays jurisdiction-
neutral. Compliance layers (GRAP 103 valuation for SA, GDPR erasure for EU,
POPIA for SA, NAGPRA for US, CARE principles for Indigenous collections) sit
**on top of** the sector tables as pluggable hooks.

### 2b.2 Descriptive-standard selection

`ingest_session.standard` (default `isadg`) selects which field set the
sidecar parser expects and validates against. Supported values — each maps
to a schema / XSLT pair shipped with `ahg-ingest`:

```
archive:    isadg | rad | dacs | ead | eac-cpf | isdiah | isaar | ric
library:    marc21 | mods | bibframe | rda | dc | onix
gallery:    cdwa | vra-core | lido | dc
museum:     spectrum | lido | dc | dwc (natural history)
dam:        iptc | xmp | premis | schema-org
```

Standards are a **Dropdown Manager** group (`ingest_standard`), seeded with
the full list above — no hardcoding. New markets add entries without code
changes.

### 2b.3 Rights and licensing (DAM cross-cutting)

Every scan produces a DAM asset; rights must be captured at ingest, not
retrofitted:

| Sidecar field | Target table | Notes |
|---|---|---|
| `<rightsStatement>` (RightsStatements.org URI) | `rights_statement` | Controlled vocab, resolved to existing row |
| `<ccLicense>` (CC URI or slug, e.g. `cc-by-4.0`) | `rights_cc_license` | Existing table with i18n |
| `<embargoUntil>` + `<embargoReason>` | `rights_embargo` | Publication gated until date |
| `<tkLabel>` (Traditional Knowledge label) | `rights_tk_label` | For Indigenous collections; never write without verified attribution |
| `<odrlPolicy>` (slug) | `research_rights_policy` | Existing ODRL enforcement |
| `<rightsHolder>` | `rights_holder` | Linked, created if missing with sector=rights_holder |

Refuses-to-ingest rule: if `ingest_session.security_classification_id` is
set and the sidecar omits rights entirely, pipeline stops at `deriving` and
the file sits in "awaiting rights" until an archivist adds a statement. Safer
default than silent publish.

### 2b.4 Controlled vocabularies

Controlled-field values from the sidecar (subjects, places, creators,
genres, materials, techniques, taxonomy) are resolved against the
`taxonomy` table and sector-appropriate authorities:

| Vocabulary | Used by | Sidecar hint |
|---|---|---|
| AAT (Getty) | Archive, Gallery, Museum | `<subject vocab="aat" uri="...">Photographs</subject>` |
| ULAN (Getty) | Archive, Gallery, Museum | Creators — `<creator vocab="ulan" uri="...">` |
| TGN (Getty) | All | Places |
| LCSH | Library | Subjects |
| LCNAF | Library | Names |
| MeSH | Library (medical) | Subjects |
| Iconclass | Gallery, Museum | Iconographic subjects |
| Nomenclature 4 | Museum | Object classification |
| ITIS / GBIF | Museum (natural history) | Taxonomy |

Resolution order for each value: exact URI match → label match in matching
taxonomy → create-if-missing (only when session permits). Unresolvable
values go to `ingest_row.error_message` and the file waits in review.

## 3. Mode A — Drop folder

### 3.1 Folder layout

Configured per watched folder (multiple can exist). Two layout styles are
supported; operators pick one per folder:

**Style 1 — Path-as-destination** (simplest, no sidecar needed)

```
/mnt/nas/heratio/scan_inbox/<folder_code>/<parent_slug>/<identifier>/page_001.tiff
                                                       /page_002.tiff
                                                       /meta.xml   (optional)
```

The directory `<identifier>` becomes the new IO identifier; parent is resolved
from `<parent_slug>`. If `meta.xml` is present it overrides path-derived values.

**Style 2 — Flat with sidecar** (for heterogeneous scans in one folder)

```
/mnt/nas/heratio/scan_inbox/<folder_code>/ARC-2026-0001.tiff
                                          ARC-2026-0001.xml     ← required
                                          ARC-2026-0001_p2.tiff ← extra pages, same stem
```

Pair matching: `<stem>.xml` describes `<stem>.*` (all same-stem non-xml files,
sorted) as one IO with an ordered sequence of digital objects.

### 3.2 Sidecar XML contract

Heratio-owned schema — one envelope, per-sector profile inside. Every field
is optional except the sector/identity minimum the parser needs to resolve
a destination.

#### 3.2.1 Common envelope

```xml
<heratioScan xmlns="https://heratio.io/scan/v1">
  <!-- Sector + standard (override session defaults if supplied) -->
  <sector>archive</sector>                             <!-- archive|library|gallery|museum -->
  <standard>isadg</standard>                           <!-- see §2b.2 -->

  <!-- Destination resolution: supply ONE of parentSlug / parentIdentifier / parentId -->
  <parentSlug>fonds-johan-smith-papers</parentSlug>
  <repositorySlug>aahg-archive</repositorySlug>        <!-- optional fallback -->

  <!-- Common identity (maps to information_object + information_object_i18n) -->
  <identifier>JS-COR-1923-042</identifier>             <!-- required if new -->
  <title>Letter from Johan Smith to editor, 1923-07-14</title>
  <levelOfDescription>item</levelOfDescription>        <!-- controlled, sector-specific allowed values -->

  <dates>
    <date type="creation" start="1923-07-14" end="1923-07-14"/>
  </dates>

  <!-- Rights (§2b.3) -->
  <rightsStatement uri="http://rightsstatements.org/vocab/InC/1.0/"/>
  <ccLicense>cc-by-nc-4.0</ccLicense>
  <embargoUntil reason="donor-request">2035-01-01</embargoUntil>
  <odrlPolicy>research-only</odrlPolicy>
  <rightsHolder>Estate of Johan Smith</rightsHolder>

  <!-- Publication + access -->
  <publicationStatus>draft</publicationStatus>         <!-- draft|published -->
  <accessConditions>Requires reading-room permission.</accessConditions>

  <!-- Digital-object handling -->
  <digitalObject>
    <usage>master</usage>                              <!-- master|reference|thumbnail|preservation -->
    <makeDerivatives>true</makeDerivatives>
    <ocr>auto</ocr>                                    <!-- auto|skip|force -->
    <htr>skip</htr>
    <iiif>auto</iiif>                                  <!-- auto|skip; TIFF/JP2 only -->
  </digitalObject>

  <!-- EXACTLY ONE of these sector profiles, matching <sector> -->
  <archiveProfile>...</archiveProfile>
  <libraryProfile>...</libraryProfile>
  <galleryProfile>...</galleryProfile>
  <museumProfile>...</museumProfile>

  <!-- Optional: custom fields (validated against heratio's custom-field schema) -->
  <customFields>
    <field name="accession_number">2026.014</field>
  </customFields>

  <!-- Optional: collision policy -->
  <merge>add-sequence</merge>                          <!-- add-sequence|replace|error -->
</heratioScan>
```

#### 3.2.2 `<archiveProfile>` — ISAD(G) / RAD / DACS / RiC

```xml
<archiveProfile>
  <scopeAndContent>Single-page handwritten letter...</scopeAndContent>
  <extentAndMedium>1 p. : ink on paper ; 21 x 28 cm</extentAndMedium>
  <archivalHistory>Donated 1987 by family.</archivalHistory>
  <acquisition>Gift of Mary Smith, 1987-03-12.</acquisition>
  <arrangement>Chronological by creation date.</arrangement>
  <creators>
    <creator vocab="ulan" uri="http://vocab.getty.edu/ulan/500012345">Smith, Johan</creator>
  </creators>
  <subjects>
    <subject vocab="aat" uri="http://vocab.getty.edu/aat/300027854">Letters (correspondence)</subject>
  </subjects>
  <places>
    <place vocab="tgn">Cape Town, South Africa</place>
  </places>
  <genres>
    <genre vocab="aat">Correspondence</genre>
  </genres>
  <physicalLocation>Strong room A, shelf 3</physicalLocation>
</archiveProfile>
```

#### 3.2.3 `<libraryProfile>` — MARC21 / MODS / RDA

```xml
<libraryProfile>
  <isbn>978-0-14-028329-7</isbn>
  <issn>0028-0836</issn>                               <!-- for serials -->
  <edition>1st ed.</edition>
  <publisher>Penguin Books</publisher>
  <placeOfPublication>London</placeOfPublication>
  <yearOfPublication>1998</yearOfPublication>
  <pagination>vii, 312 p.</pagination>
  <dimensions>23 cm</dimensions>
  <seriesTitle>Modern Classics</seriesTitle>
  <seriesNumber>42</seriesNumber>
  <language>eng</language>
  <subjects>
    <subject vocab="lcsh">African literature—20th century.</subject>
  </subjects>
  <creators>
    <creator vocab="lcnaf" role="author">Smith, Johan, 1880-1954</creator>
  </creators>
  <holdings>
    <copy barcode="31234005678901" location="main-stacks" callNumber="PR9369.3.S55 L45 1998"/>
  </holdings>
</libraryProfile>
```

#### 3.2.4 `<galleryProfile>` — CDWA / VRA Core / LIDO

```xml
<galleryProfile>
  <artist vocab="ulan" uri="http://vocab.getty.edu/ulan/500012345"
          displayName="Smith, Johan (1880–1954)"
          role="artist"/>
  <title>Evening at Table Mountain</title>
  <workType vocab="aat">oil paintings (visual works)</workType>
  <creationDate start="1923" end="1923" type="creation"/>
  <medium>Oil on canvas</medium>
  <materials>
    <material vocab="aat">oil paint</material>
    <material vocab="aat">canvas</material>
  </materials>
  <techniques>
    <technique vocab="aat">impasto</technique>
  </techniques>
  <dimensions>
    <dimension type="height" value="60" unit="cm"/>
    <dimension type="width" value="80" unit="cm"/>
    <dimension type="depth" value="3" unit="cm"/>
  </dimensions>
  <editionInfo>Unique work</editionInfo>              <!-- or edition X of Y for prints -->
  <movement vocab="aat">Post-impressionism</movement>
  <signature>Lower right, "J. Smith 1923"</signature>
  <inscription>Verso: "For M., with love"</inscription>
  <iconography vocab="iconclass" notation="25H2"/>
  <provenance>
    <entry date="1923" owner="Artist"/>
    <entry date="1960" owner="Private collection, Cape Town"/>
    <entry date="2026" owner="AAHG collection" acquisition="gift"/>
  </provenance>
  <exhibitionHistory>
    <exhibition venue="National Gallery" start="1935" end="1935"/>
  </exhibitionHistory>
  <valuation currency="ZAR" amount="450000" date="2025-06-01" type="insurance"/>
</galleryProfile>
```

#### 3.2.5 `<museumProfile>` — Spectrum 5.1 / LIDO / Darwin Core

```xml
<museumProfile>
  <objectNumber>AAHG.2026.014.001</objectNumber>
  <accessionNumber>2026.014</accessionNumber>
  <classification vocab="nomenclature">Documentary artifact / Correspondence</classification>
  <objectType vocab="aat">letters</objectType>

  <materials>
    <material vocab="aat">paper</material>
    <material vocab="aat">iron gall ink</material>
  </materials>
  <techniques>
    <technique vocab="aat">handwriting</technique>
  </techniques>
  <measurements>
    <measurement type="height" value="28" unit="cm"/>
    <measurement type="width" value="21" unit="cm"/>
    <measurement type="weight" value="4" unit="g"/>
  </measurements>

  <!-- Cultural context -->
  <culturalAffiliation vocab="aat">South African</culturalAffiliation>
  <periodOrStyle vocab="aat">early 20th century</periodOrStyle>
  <productionPlace vocab="tgn">Cape Town</productionPlace>
  <productionDate start="1923-07-14" end="1923-07-14"/>

  <!-- Spectrum procedures (optional; triggered if Spectrum workflow is active) -->
  <spectrum>
    <acquisitionMethod>gift</acquisitionMethod>
    <acquisitionDate>2026-04-12</acquisitionDate>
    <currentLocation>Storage A / shelf 3 / box 14</currentLocation>
    <conditionGrade>good</conditionGrade>
    <conditionNotes>Minor foxing along right edge.</conditionNotes>
  </spectrum>

  <!-- Natural history (when <standard>dwc</standard>) -->
  <darwinCore>
    <scientificName>Protea cynaroides</scientificName>
    <taxonRank>species</taxonRank>
    <kingdom>Plantae</kingdom>
    <collector>Smith, J.</collector>
    <collectionDate>1923-07-14</collectionDate>
    <locality>Table Mountain, Cape Peninsula</locality>
    <decimalLatitude>-33.9628</decimalLatitude>
    <decimalLongitude>18.4098</decimalLongitude>
  </darwinCore>
</museumProfile>
```

#### 3.2.6 DAM-specific augmentation (all sectors)

Embedded IPTC/XMP/EXIF from the file is **merged** with the sidecar's
`<damAugmentation>` block (sidecar wins on conflict). The parser extracts
via ExifTool and flows to `dam_iptc_metadata` + `media_metadata`.

```xml
<damAugmentation>
  <usageRights>Editorial use only. No resale.</usageRights>
  <assetRelationship type="derivative-of" targetSlug="original-scan-2024"/>
  <colourProfile>Adobe RGB (1998)</colourProfile>
  <captureDevice>Epson Expression 12000XL</captureDevice>
  <captureSoftware>VueScan 9.8.17</captureSoftware>
  <captureDate>2026-04-23T10:14:00+02:00</captureDate>
  <operatorId>scan-tech-04</operatorId>
  <qaStatus>pending-review</qaStatus>                  <!-- drives DAM workflow state -->
</damAugmentation>
```

If the target IO already exists (resolved by `identifier` within the chosen
parent), the scan is **added as an additional digital object** (next sequence
number) unless `<merge>replace</merge>` is set.

### 3.3 Watcher daemon

- Laravel command: `php artisan ahg:scan-watch`
- Supervisord unit: auto-restart, one per host
- Implementation: inotify (Linux) via `spatie/file-system-watcher`, polling
  fallback every 30 s on non-Linux hosts
- On event → insert an `ingest_file` row against the folder's long-lived
  `ingest_session` (status = `pending`) → let the queue worker process (§5).
  **Do not process inline in the watcher** — a slow OCR pass would back up
  new detections.
- Lock file `<stem>.lock` while scanning is still writing (detect via
  "unchanged size for N seconds" heuristic, configurable)

### 3.4 Post-processing disposition

Per folder config:

- `move_on_success` → `/mnt/nas/heratio/scan_inbox/.archived/<yyyy>/<mm>/`
- `move_on_failure` → `/mnt/nas/heratio/scan_inbox/.quarantine/<reason>/`
- `delete_on_success` (not default; safer to archive and let a monthly job prune)

## 4. Mode B — Scanner app direct to API

Expose a small REST surface under `/api/v2/scan/*` (fits the existing
`ahg-api` package). The scanner app (or a PowerShell/bash wrapper) hits these.

### 4.1 Endpoints

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/v2/scan/destinations?q=<search>&parent=<id>` | Autocomplete / browse parents. Returns repositories, fonds, series, files limited by ACL. |
| `POST` | `/api/v2/scan/sessions` | Start a scan session; body: `{parent_id, default_metadata}`. Creates an `ingest_session` (`session_kind=scan_api`), returns `session_token`. |
| `POST` | `/api/v2/scan/sessions/{token}/files` | Upload one file (multipart). Optional `metadata` JSON part overriding session defaults. Creates an `ingest_file` row, returns its id. |
| `POST` | `/api/v2/scan/sessions/{token}/commit` | Kick the `ingest_job` for this session. Synchronous for small sessions, async (returns job id) for larger. |
| `GET`  | `/api/v2/scan/sessions/{token}` | Poll status (reads `ingest_session.status` + latest `ingest_job`). |
| `DELETE`| `/api/v2/scan/sessions/{token}` | Abandon session (marks session `abandoned`, removes staged files). |

### 4.2 Auth

- API key from `ahg-api`, scoped `scan:write`
- Key is bound to a user → that user becomes `created_by` on the resulting IOs
- Per-key rate limit + size cap in `ahg_settings`

### 4.3 Wrapper scripts shipped with Heratio

Under `tools/scanner/`:

- `heratio-scan.ps1` (Windows / VueScan "After save" hook)
- `heratio-scan.sh` (Linux / NAPS2 "Post-scan command")
- `heratio-scan.py` (cross-platform, minimal deps)

Each wrapper reads `HERATIO_URL`, `HERATIO_API_KEY`, `HERATIO_PARENT_SLUG`
from env or a config file, does the 3-call dance, and prints the resulting IO
URL. Operators point their scanner app at the wrapper.

## 5. Processing pipeline (shared by A/B/C, reuses `ahg-ingest`)

All captures land as an `ingest_file` row against a long-lived (folder) or
short-lived (API) `ingest_session`, with the file staged under
`{heratio.uploads_path}/.scan_staging/`. The existing ingest job runner
advances the work; the scanner adds per-file state so files can progress
independently inside one long-running session.

```
pending → virus → format-id → fixity → meta → io → sector-route → do →
deriving → ocr → indexing → packaging → done
                                                                ↘ failed
```

State machine, not a script — each stage checkpoints on `ingest_file.stage`
so a crashed worker resumes from the last good state. Every stage that
produces an observable outcome also emits an `oais_premis_event` row, so
the preservation record is complete without extra work.

### 5.1 Stages

1. **Virus scan** — `preservation_virus_scan` (existing table), ClamAV by
   default. Failure → quarantine, `ingest_file.status = failed:virus`.
   Emits `virusCheck` PREMIS event.
2. **Format identification** — DROID / PRONOM against
   `oais_pronom_format` + `preservation_format`. Records PUID, mime type,
   format-version on `preservation_identification`. Flags obsolete formats
   (`preservation_format_obsolescence`) for later migration planning
   (`preservation_migration_plan_object`). Emits `format identification`
   PREMIS event.
3. **Fixity** — SHA-256 + optionally MD5/SHA-512 for legacy consumers.
   Stores on `preservation_checksum` and `digital_object.checksum`. Emits
   `messageDigestCalculation` PREMIS event.
4. **Extract embedded metadata** — IPTC/EXIF/XMP/XMP-RDF via ExifTool;
   technical metadata (MIX for images, textMD for text) via JHOVE when
   available. Stores on `media_metadata` + `dam_iptc_metadata` +
   `digital_object_metadata`. Merges with sidecar `<damAugmentation>`
   (sidecar wins on conflict).
5. **Resolve or create IO** — match by `(parent_id, identifier)` first;
   fallback to slug. Create if absent. Uses the same
   `InformationObjectService` the ingest wizard uses. Resolves
   controlled-vocabulary values (§2b.4).
6. **Sector routing** — based on effective sector (§2b.1), write to
   `library_item` / `gallery_artwork` / `museum_object` / `museum_metadata`
   using the matching sidecar profile. If the museum session has Spectrum
   enabled, also create `spectrum_object_entry` + `spectrum_acquisition`
   rows and move the object into the configured Spectrum workflow state
   (`spectrum_workflow_state`).
7. **Create `digital_object`** — move staged file to canonical location
   (`{uploads_path}/r/<repo>/<hash>/<filename>`), link `dam_asset` row,
   write `digital_object.path`. Emits `replication` PREMIS event if a
   replication target is configured (`preservation_replication_target`).
8. **Derivatives** — reference JPG (web), thumbnail, IIIF-ready
   pyramid/JP2 (when source is TIFF; Cantaloupe picks them up via
   `delegates.rb`). For audio: waveform PNG + MP3 preview. For moving
   image: poster frame + MP4/480p preview. Honours
   `ingest_session.derivative_*` flags. Emits `creation (derivation)`
   PREMIS event per derivative.
9. **OCR / HTR** — hand off to `ahg-ai-services` if source is text-bearing
   and `ingest_session.process_ocr = 1` (or sidecar `<ocr>force</ocr>`).
   HTR only on explicit opt-in (more expensive, routes to 192.168.0.78
   Ollama per server config). NER (`ingest_session.process_ner`) runs
   on OCR output and links recognised entities into `relation`.
10. **Rights enforcement** — if sidecar sets embargo/ODRL/CC licence,
    create/link rows (§2b.3). If `security_classification_id` is set and
    no rights were supplied, halt here — file waits in review.
11. **Index** — ES upsert into `heratio_qubitinformationobject` (and
    `heratio_qubitactor` for any newly-created creator/rights-holder
    records).
12. **Packaging** — if the session has
    `output_generate_sip/aip/dip = 1`, emit the package(s) via
    `preservation_package` + `preservation_package_object` to the
    configured output paths. DIP includes only access derivatives; AIP
    includes the master + PREMIS metadata + fixity manifest. Free benefit
    of reusing ingest.
13. **Notify** — emit event for the scan dashboard; optionally email on
    failure (per-folder or per-API-key setting).

### 5.2 Idempotency

- `ingest_file.source_hash` = SHA-256 of (file content + sidecar).
  Duplicate drop/upload with same hash is detected; operator gets "already
  ingested as IO #nnn".
- Stage transitions use `UPDATE ... WHERE status = <prev>` so two workers
  can't double-advance the same row.
- Folder sessions never "close" — `ingest_session.status` stays `open` for
  watched folders; each processed file creates/updates an `ingest_job` row
  for that batch, so dashboards still show meaningful progress per arrival
  batch.

### 5.3 Accepted file formats

The scanner is format-agnostic — anything ExifTool + JHOVE can identify is
ingestable. Derivatives and preview generation vary by class.

| Class | Examples | Derivatives | Notes |
|---|---|---|---|
| Still image (raster) | TIFF, TIFF/G4, JPEG, PNG, BMP, HEIC, WebP | reference JPG, thumbnail, IIIF | Primary scanner output |
| Still image (RAW) | CR2, NEF, ARW, RAF, DNG, ORF | reference JPG, thumbnail | Common in gallery / photo-archive capture |
| Still image (preservation) | JP2 (lossless), DNG | IIIF pyramid | Directly fed to Cantaloupe |
| Document | PDF, PDF/A-1/2/3, plain text | thumbnail (page 1), reference PDF | PDF/A preferred for preservation; JHOVE validates |
| Office documents | DOCX, ODT, RTF | PDF reference, text extract | Via LibreOffice headless |
| Audio | WAV, BWF, FLAC, MP3, AAC | MP3 128 kbps preview, waveform PNG | BWF carries broadcast metadata → `media_metadata` |
| Moving image | MOV, MP4, MKV, MXF, DCP | MP4 480p preview, poster frame, HLS (optional) | Large; ingest async by default |
| 3D / photogrammetry | GLB, OBJ, PLY, STL, USDZ, FBX | thumbnail (rendered preview) | Gallery / museum 3D capture; 3D viewer already in Heratio |
| Structured data | MARC21 (.mrc), MODS-XML, EAD-XML, LIDO-XML, Darwin Core Archive (.zip) | — | Treated as descriptive-only records; create IO without digital object, or attach as auxiliary DO |
| Email / MBOX | .eml, .mbox, .pst | text extract, attachment extraction | Born-digital archives |
| Web archive | WARC, WACZ | replay pointer | Future — out of scope for v1 |
| BagIt container | `.zip` / directory with `bag-info.txt` + `manifest-*.txt` | — | Ingest as a bundle: manifest rows become sibling IOs; bag-info maps to session metadata |

Format decisions flow from `preservation_format` and
`preservation_migration_pathway` — obsolete formats (e.g. legacy PICT, WMF)
auto-flag for migration planning instead of being rejected.

## 6. Database additions

The scanner **reuses** `ingest_session` / `ingest_file` / `ingest_row` /
`ingest_job`. Only two new tables, plus a few columns added to the
existing ingest tables.

### 6.1 Additions to existing `ingest_*` tables

```sql
-- ingest_session: distinguish wizard runs from persistent scan entries
ALTER TABLE ingest_session
  ADD COLUMN session_kind VARCHAR(32) NOT NULL DEFAULT 'wizard' AFTER entity_type,
  -- values: 'wizard' | 'watched_folder' | 'scan_api'
  ADD COLUMN auto_commit TINYINT(1) NOT NULL DEFAULT 0 AFTER session_kind,
  ADD COLUMN source_ref VARCHAR(255) NULL AFTER auto_commit,
  -- scan_folder.code for watched_folder, api-session-token for scan_api
  ADD KEY ix_session_kind (session_kind);

-- ingest_file: per-file state so files can progress independently in a
-- long-lived session (wizard sessions currently rely on job-level state)
ALTER TABLE ingest_file
  ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending' AFTER extracted_path,
  ADD COLUMN stage VARCHAR(32) NULL AFTER status,
  ADD COLUMN source_hash CHAR(64) NULL AFTER stage,
  ADD COLUMN error_message TEXT NULL AFTER source_hash,
  ADD COLUMN attempts INT NOT NULL DEFAULT 0 AFTER error_message,
  ADD COLUMN resolved_io_id INT NULL AFTER attempts,
  ADD COLUMN resolved_do_id INT NULL AFTER resolved_io_id,
  ADD COLUMN completed_at DATETIME NULL AFTER resolved_do_id,
  ADD KEY ix_ingest_file_status (status),
  ADD KEY ix_ingest_file_hash (source_hash),
  ADD KEY ix_ingest_file_io (resolved_io_id);
```

The `ingest_file` additions are harmless for the existing wizard flow — it
just leaves them at their defaults.

### 6.2 New tables

```sql
CREATE TABLE scan_folder (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code                VARCHAR(64) NOT NULL UNIQUE,        -- used in paths + source_ref
  label               VARCHAR(255) NOT NULL,
  path                VARCHAR(1024) NOT NULL,             -- watched directory
  layout              VARCHAR(32) NOT NULL,               -- 'path' | 'flat-sidecar'
  ingest_session_id   INT NOT NULL,                       -- FK ingest_session (kind=watched_folder)
  disposition_success VARCHAR(32) NOT NULL DEFAULT 'move',
  disposition_failure VARCHAR(32) NOT NULL DEFAULT 'quarantine',
  enabled             TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_scan_folder_enabled (enabled),
  KEY ix_scan_folder_session (ingest_session_id)
);

CREATE TABLE scan_session_token (
  token             VARCHAR(64) PRIMARY KEY,
  ingest_session_id INT NOT NULL,                         -- FK ingest_session (kind=scan_api)
  api_key_id        INT NULL,
  user_id           INT NULL,
  expires_at        DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_scan_token_session (ingest_session_id),
  KEY ix_scan_token_expires (expires_at)
);
```

`scan_folder` holds only what's specific to a watched-folder entry point
(filesystem path, layout, disposition). **Processing config** (OCR, virus
scan, SIP, derivatives, parent, sector, standard) lives on the associated
`ingest_session` — operators configure it with the existing ingest config
screen, which means zero duplicated config UI.

No ENUMs — `session_kind`, `status`, `stage`, `layout`, `disposition_*`
are VARCHARs with Dropdown Manager groups (`ingest_session_kind`,
`ingest_file_status`, `ingest_file_stage`, `scan_folder_layout`,
`scan_disposition`).

## 7. Package layout

Split across two packages to keep responsibilities clean:

**`ahg-ingest` (existing) — additions only:**

- New columns on `ingest_session` / `ingest_file` (§6.1)
- `IngestService` gains an `ingestFile(Session $s, $stagedPath, array $meta)`
  method that the scanner callers use — same code path that the wizard's
  `commit` step uses, just invoked per file instead of per batch
- `IngestJob` runner learns to run in "streaming" mode for sessions with
  `session_kind != 'wizard'` (no "complete when all files done" assumption)
- No new UI — wizard is unchanged

**`ahg-scan` (new) — entry points + UI for the continuous stream:**

```
packages/ahg-scan/
├── composer.json
├── database/
│   ├── install.sql              ← scan_folder, scan_session_token + ALTERs on ingest_*
│   └── seed_dropdowns.sql       ← scan_folder_layout, scan_disposition, ingest_session_kind, ingest_file_status, ingest_file_stage
├── src/
│   ├── Providers/ScanServiceProvider.php
│   ├── Controllers/
│   │   ├── ScanDashboardController.php     ← /admin/scan
│   │   ├── ScanFolderController.php        ← /admin/scan/folders CRUD
│   │   ├── ScanInboxController.php         ← filtered view of ingest_file, retry, discard
│   │   └── Api/ScanApiController.php       ← /api/v2/scan/* endpoints
│   ├── Services/
│   │   ├── WatchedFolderService.php        ← folder → ingest_session binding
│   │   ├── SidecarParser.php               ← XML → ingest_row-compatible payload
│   │   ├── DestinationResolver.php         ← parentSlug → parent_id
│   │   └── ScanSessionTokenService.php     ← API session token lifecycle
│   ├── Jobs/
│   │   └── EnqueueFileFromFolder.php       ← on filesystem event
│   ├── Console/
│   │   ├── ScanWatchCommand.php            ← `ahg:scan-watch`
│   │   └── ScanReprocessCommand.php        ← `ahg:scan-reprocess --file=N`
│   └── routes/{web.php, api.php}
├── resources/
│   └── views/admin/scan/{dashboard,folders,inbox,detail}.blade.php
└── tools/                                   ← wrapper scripts (§4.3)
    ├── heratio-scan.ps1
    ├── heratio-scan.sh
    └── heratio-scan.py
```

Note: all processing services (derivatives, virus, OCR dispatch, IO/DO
creation) stay in `ahg-ingest` — `ahg-scan` never re-implements them. If
it's tempting to add one to `ahg-scan`, that's a signal it belongs in
`ahg-ingest` instead.

Slug catch-all exclusion list (§"Slug Catch-All Route" in CLAUDE.md) must be
extended with `scan` when the routes go live.

## 8. UI

All under `/admin/scan/*`. Every screen is a filtered view over the existing
`ingest_*` tables; no data duplication.

| Route | Purpose | Data source |
|---|---|---|
| `/admin/scan` | Live dashboard: counts per status, 24 h throughput, failed queue, per-folder throughput | `ingest_file` joined to `ingest_session` where `session_kind <> 'wizard'` |
| `/admin/scan/folders` | CRUD on `scan_folder` rows; **"Configure processing" button** deep-links to the existing `/ingest/configure/{session_id}` wizard screen for the backing session | `scan_folder` + linked `ingest_session` |
| `/admin/scan/inbox` | Paginated list of in-flight files, filter by status/folder/date | `ingest_file` (scoped as above) |
| `/admin/scan/inbox/{id}` | Detail: file preview (IIIF if TIFF), sidecar JSON, stage log, Retry / Force-reprocess / Discard | `ingest_file` row |
| `/admin/scan/sessions` | Active API scan sessions (for Mode B debugging) | `scan_session_token` + `ingest_session` |

Reusing the `/ingest/configure` screen for folder processing config is the
big ergonomic win: operators who already know the ingest wizard immediately
know how to configure a watched folder's processing.

Mode C helper app is a separate deliverable (§9), not a Heratio page.

## 9. Mode C — Desktop Capture helper (phase 3)

Small cross-platform app (Tauri preferred; Electron acceptable) that:

1. Logs in with a Heratio URL + API key.
2. Browses the archival hierarchy (hits `/api/v2/scan/destinations`).
3. User picks a parent → app creates a scan session → exposes a **local
   folder** (`~/Heratio Capture/<parent_slug>/`) that the scanner app writes
   into.
4. Helper watches that folder, uploads new files to the session, shows live
   upload/commit status.
5. Big green **Commit to Heratio** button.

Keeps Heratio clean — all scanner-app integration work lives in the helper,
and the helper talks only to the documented API (so third parties can build
their own, e.g. a museum that wants a mobile capture app).

## 10. Config (central, in `config/heratio.php` + `ahg_settings`)

| Key | Default | Notes |
|---|---|---|
| `heratio.scan.staging_path` | `{storage_path}/.scan_staging` | Intermediate storage during pipeline |
| `heratio.scan.quarantine_path` | `{storage_path}/.scan_quarantine` | Failed files |
| `heratio.scan.archive_path` | `{storage_path}/.scan_archived` | Processed originals kept N days |
| `ahg_settings.scan_virus_required` | `1` | Refuse ingest if virus scanner unreachable |
| `ahg_settings.scan_max_upload_mb` | `2048` | Per-file cap for Mode B |
| `ahg_settings.scan_ocr_default` | `auto` | `auto|skip|force` when sidecar omits |
| `ahg_settings.scan_retry_attempts` | `5` | Automatic retries before hard-fail |
| `ahg_settings.scan_retry_backoff_minutes` | `15,60,240,1440,4320` | Exponential |

No hardcoded paths; all derived from `heratio.storage_path` per CLAUDE.md.

## 11. Phased delivery

Scope is noticeably smaller than the original plan because the ingest engine
exists — no new job runner, no new derivative service, no new IO/DO creation
path.

| Phase | Scope | Duration (est.) | Exit criteria |
|---|---|---|---|
| **P1 — Ingest streaming mode** | `ingest_session.session_kind/auto_commit/source_ref` + `ingest_file` per-file state columns (§6.1). Teach `IngestService`/`IngestJob` to run in streaming mode. Add `ingestFile()` entry point that reuses the wizard's commit logic per file. | 1.5 weeks | Existing wizard unchanged; manually-inserted `ingest_file` rows on a `session_kind=watched_folder` session get processed end-to-end into IO + DO |
| **P2 — Mode A watcher + dashboard + archive profile** | `scan_folder` table, `ScanWatchCommand`, admin dashboard (read-only filtered view of ingest), folder CRUD reusing `/ingest/configure`, Mode A path-layout, archive-sector sidecar profile | 2 weeks | Drop TIFF into folder with ISAD(G) sidecar → IO + DO + controlled-vocab resolution → dashboard shows throughput |
| **P3 — Sector profiles (library / gallery / museum) + DAM augmentation** ✅ **DELIVERED 2026-04-24** | Library/gallery/museum sidecar parsers + sector-routing stage writing `library_item` / `gallery_artwork` / `museum_object` / `museum_metadata`, Spectrum workflow hook (opt-in per session), `damAugmentation` merge into `dam_iptc_metadata`, authority auto-creation for creators/artists (opt-out per session), controlled-vocab lookup (creation deferred to P7) | 3 weeks | ✅ End-to-end verified: library sidecar → `library_item` + creators + subjects + holdings; gallery sidecar → `gallery_artwork` + auto-created actor + `gallery_artist` + creation event + `gallery_valuation` + `museum_metadata`; museum sidecar → `museum_object` + `museum_metadata` + (when opted-in) `spectrum_object_entry` + `spectrum_acquisition` |
| **P4 — PREMIS + format ID + rights enforcement** ✅ **DELIVERED 2026-04-24** | Siegfried-based PRONOM format-ID stage (+ `preservation_format_obsolescence` for at-risk formats), PREMIS event emission via `preservation_event` at every stage (virusCheck / formatIdentification / messageDigestCalculation / ingestion / creation(derivation)), rights-enforcement stage (`rights_statement` + `rights_cc_license` + `rights_embargo` + `rights_tk_label` + `object_rights_holder` + `research_rights_policy` binding), `awaiting_rights` hold state + admin "Release rights" resume action | 2 weeks | ✅ End-to-end: every ingested file has PREMIS event chain + PUID + checksum + fixity record; classified session with no sidecar rights → held for review; admin release resumes deriving + indexing. |
| **P5 — Sidecar + Mode B API** ✅ **DELIVERED 2026-04-24** | Sidecar XML parser (envelope + archive profile, sector profiles preserved for P3), Mode A flat-sidecar layout, `scan_session_token` table, `/api/v2/scan/*` endpoints, wrapper scripts (ps1/sh/py) | 2.5 weeks | ✅ VueScan/NAPS2 post-scan hook creates fully-described IO via wrapper; archive sector fully round-trips. Library/gallery/museum profile *routing* (writing to sector tables) deferred to P3 as originally planned. |
| **P6 — Reliability + Capture helper** ✅ **DELIVERED 2026-04-24** | Exponential retry/backoff ladder via `ahg:scan-retry-failed` (cron every 5 min; 15/60/240/1440/4320 minute defaults), bulk retry+discard on Inbox list, "Restore from quarantine" on detail view, per-folder email notifications on final failure, BagIt container ingest (zip+dir) with manifest-sha256/512 verification, Capture TUI helper (`heratio-capture.py`) as an interactive alternative to a full Tauri app | 4 weeks | ✅ End-to-end: BagIt zip with `External-Identifier` + `data/*` → one IO with multiple DOs; retry scheduler re-dispatches failed files after backoff windows; capture TUI browses destinations and uploads via the Scan API. Full Tauri/Electron desktop app remains on backlog (TUI covers the workflow). |
| **P7 — Hardening** ✅ **DELIVERED 2026-04-24** | IIIF pyramid pre-generation (ImageMagick ptif), HTR integration via `AhgAiServices\HtrService` (gated by session `process_ocr`), `MediaDerivativeService` for audio (MP3 + waveform PNG via ffmpeg) / video (MP4 480p + poster) / 3D (delegates to existing `ThreeDThumbnailService`), audit_log rows on every scanner-created IO+DO, `AlternateFormatTransformer` framework with EAD-to-heratioScan XSLT shipped (MARC21/MODS/LIDO detected as pending). Bulk-ops delivered earlier in P6. | 3 weeks | ✅ End-to-end verified: EAD file → XSLT transform → full heratioScan with identifier / title / scope / dates / creators → IO created. WAV master → waveform thumbnail + MP3 reference DOs. TIFF master → pyramid TIFF derivative. Every scanner-created IO/DO has audit_log entry. |

Total: ~18 weeks serial. P1 gates everything. P2 + P4 can overlap; P3 + P5
can overlap with two developers. Sector coverage is the big expansion
versus the earlier archive-only plan — it's where most of the sector value
is delivered. SIP/AIP/DIP packaging still needs no new work.

## 12. Open questions

1. **Sidecar format** — stick with our own `heratioScan` XML, or also accept
   METS? (METS is heavier but expected by some institutions.) **Proposed**:
   support our XML as canonical, ship a METS→heratioScan transformer as a
   plugin rather than in core.
2. **Identifier collisions** — if two scans drop the same `identifier` under
   the same parent, is that a duplicate page (add sequence) or a conflict
   (quarantine)? **Proposed**: default to "add sequence"; flag with
   `<merge>error</merge>` in sidecar to opt into strict.
3. **Multi-file items** — is the unit of work a single TIFF/PDF, or a
   multi-file "bag" (many TIFFs = one item)? Plan above supports both via
   Style 1 directory-per-IO and Style 2 shared-stem.
4. **Storage location for masters** — ~~one hash-bucketed pool
   (`{uploads_path}/r/<repo>/<hash>/`) or separate `masters/` vs
   `derivatives/` roots?~~ **Resolved (2026-04-24)**: per-IO directory
   `{heratio.uploads_path}/<io_id>/` with `master_*`, `reference_*`,
   `thumbnail_*` filename prefixes — matches the existing
   `DigitalObjectService::upload()` convention used by wizard uploads and
   by every legacy AtoM-derived ingest path. Keeps all derivatives next
   to their master on disk, which IIIF (Cantaloupe) and the DAM viewers
   already assume. Scanner pipeline uses this layout via
   `IngestService::createDigitalObjectFromPath()`. The hash-bucketed
   pool was rejected because it would fork the filesystem convention
   for one entry point only.
5. **TWAIN / WIA integration** — do we want the Mode C helper to drive the
   scanner directly (bypassing a third-party app), or always sit behind
   VueScan/NAPS2? **Proposed**: never drive hardware directly in v1 — let
   the scanner app do what it's good at.
6. **Wizard "commit" semantics in streaming mode** — when
   `session_kind=watched_folder` and `auto_commit=1`, a session never really
   "finishes". Dashboards need a meaningful grouping unit (day? arrival
   batch? manual "close batch" button?). **Proposed**: implicit batches
   keyed on quiet-period — each run of the job runner for a session emits a
   new `ingest_job` row, closed when the file queue drains. Revisit after
   P2 dogfooding.
7. **Spectrum workflow auto-activation** — ~~should a museum-sector scan
   automatically create `spectrum_object_entry` + `spectrum_acquisition`
   rows and enter the institution's configured Spectrum workflow?~~
   **Resolved (2026-04-24)**: opt-in per `scan_folder` / API session via
   `ingest_session.spectrum_auto_enter` (default 0). When enabled and the
   sidecar includes a `<spectrum>` block, the museum routing path creates
   both rows with `workflow_state='received'`. Exposed as a toggle in the
   folder edit form.
8. **Authority-record creation** — ~~when a sidecar names a new creator
   (`<creator>` with no matching ULAN URI), do we auto-create an actor
   record, or hold the file?~~ **Resolved (2026-04-24)**: auto-create at
   `description_status_id=232` (Draft) by default via
   `ingest_session.output_create_authorities` (default 1). Draft actor
   gets the sidecar's `uri=` stored in `actor.description_identifier` for
   later reconciliation with ULAN / LCNAF / ORCID. Operators can disable
   auto-creation per folder; missing creators then surface as soft
   warnings in the Inbox detail view rather than failing the ingest.
9. **RAW format handling** — proprietary camera RAW (CR2/NEF/ARW etc.) is
   opaque to most tools. Do we keep RAW as preservation master only and
   derive from an embedded JPEG, or require DNG conversion at ingest?
   **Proposed**: keep RAW + auto-convert to DNG as a preservation
   derivative (open, ISO-standard); deliver DNG to Cantaloupe. Flag as
   an open `preservation_migration_pathway` in case archival strategy
   changes.
10. **BagIt semantics** — a `bag-info.txt` has several conventional fields
    (`Source-Organization`, `Contact-Email`, `External-Identifier`). Do we
    map these to core IO fields or keep them as provenance metadata only?
    **Proposed**: treat `External-Identifier` as IO identifier; everything
    else goes into `media_metadata` as provenance. Map table shipped with
    the BagIt ingester.
11. **Alternate descriptive-standard ingress** — when an institution drops
    an EAD/MARC/LIDO file instead of our heratioScan XML, do we ship
    XSLTs for each, or require external pre-conversion? **Proposed**: ship
    XSLTs for the common four (EAD, MARC21-XML, MODS, LIDO) in
    `ahg-ingest/resources/transforms/` — P7 scope. Anything else goes
    through a separate transform package.

## 12a. OAIS packaging (delivered between P6 and P7)

The plan called packaging "a free benefit of reusing ingest." That was
aspirational — there was no actual packager anywhere in Heratio when
scanning work began. **Delivered 2026-04-24** as a standalone service
`AhgIngest\Services\OaisPackagerService` usable from any context:

- Builds SIP / AIP / DIP as BagIt 1.0 zips
- SIP = master + descriptive XML; AIP = SIP + all derivatives + PREMIS
  events + fixity manifest; DIP = access derivatives + descriptive only
- Writes `preservation_package` + `preservation_package_object` +
  `preservation_package_event` rows
- Emits PREMIS `accession (SIP)` / `preservation (AIP)` /
  `dissemination (DIP)` events per package
- Default export under `{heratio.storage_path}/packages/exports/`;
  overridable via `ingest_session.output_sip_path/aip_path/dip_path`

Scanner pipeline wires packaging into `stagePackaging()` after
indexing; honours session-level flags.

**Wizard commit runner** — also delivered 2026-04-24 as
`AhgIngest\Services\IngestCommitRunner` + `ahg:ingest-commit` artisan
command + POST action on the commit page. Walks `ingest_row` rows,
creates IOs via `InformationObjectService::create()`, attaches
digital objects via `IngestService::ingestFile()`, tracks progress in
`ingest_job`, and invokes `OaisPackagerService` per IO when session
flags request it. The wizard's "Start Commit" button now does what
its name says.

## 13. Out of scope

- Driving scanner hardware directly from Heratio (no TWAIN/WIA/SANE)
- Auto-cropping, deskewing, colour correction — belongs in the scanner app
- Form-recognition / structured data extraction — separate AI pipeline
- Importing from existing DAM systems — covered by the existing `ahg-ingest`
  wizard (same engine the scanner uses, different entry point)
