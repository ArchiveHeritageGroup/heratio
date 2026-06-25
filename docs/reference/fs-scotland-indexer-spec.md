# FS-Scotland AI Indexer — design spec

> Tracked in GitHub issue #1336 (phases P0–P4).

Turn the HTR **bulk-annotate** tool from a manual training annotator into an
AI indexer that reads Scottish civil-registration register images and emits the
FamilySearch **Data Safe** index (per "Family Search Scotland Project
Breakdown"), via the AHG AI gateway.

## Source material

- Images: DGS film batches under `fs-metadata-capture/images/<DGS>/<DGS>_<imgno>.jpg`
  (e.g. `008066403` = 1870 Edinburgh Register of Marriages). Each film = leader/
  target cards, a printed title page, then handwritten/printed register pages
  with **many records per page**. Pulled by the "FS Metadata Capture" extension.
- Spec: the docx defines the Data Safe field set, the **field-by-event-type
  matrix** (Birth/Baptism/Marriage/Death/Burial each key a different subset),
  and the keying rules. Per-event **example images** in the docx are few-shot
  exemplars.

## Gap (today)

- `bulk-annotate` = manual box-drawing → ILM **training** annotations (corpus).
- Auto-extract exists (`HtrService::extract($img,$docType)` via gateway
  `/ai/v1/htr/legacy`, CSV download, provenance) but is **fine-tuned-model /
  `doc_type`** oriented; no Scotland schema, no segmentation, no triage, no
  keying rules, no Data Safe CSV.

## Architecture decision (corrected 2026-06-24)

Use the **already-trained HTR model via the gateway HTR plane**
(`HtrService::extract($img, $docType)` → `ai.theahg.co.za/ai/v1/htr/legacy/extract`),
NOT generic `llava` vision. HTR training has already been done (the manual
bulk-annotate corpus → `train`), so a purpose-built structured-extraction model
exists and beats a general describe model on 19th-c Scottish cursive. A vision
LLM (llava/qwen-vl via the ollama passthrough) is only a *fallback* for the
cheap **image-type triage** step. All calls route through the gateway (standing
rule) and record provenance via `extractAndRecord` / `ahg_ai_inference`.

**Operational prerequisite (blocker, 2026-06-24):** the entire AI stack is
currently served from the **down `.115` node** — general (`:5004`), ollama
(`:11434`), and the trained **HTR model (`:5006`)** all return "No route to
host." Per "we can use any GPU," the unblock is: serve the trained HTR model on
a live GPU (`.76`/`.78`) — or reboot the hung `.115` — and repoint the gateway's
HTR (and ollama) upstreams in `gpu_nodes` (Postgres `ahgai`, `/opt/ahg-ai`).
The Heratio-side indexer can be built now and validated once the model is live.

## Pipeline

1. **Ingest** a DGS folder. Derive `FS_DIGITAL_FILM_NBR` from the folder name
   (9-digit) and `FS_IMAGE_NBR` from the filename suffix (5-digit). `FS_COLLECTION_ID`
   / `FS_PPQ_ID` from project config.
2. **Image-type triage** (one cheap vision call): classify each image as
   record vs No-Extractable-Data / Blank / Duplicate / Unreadable / Other.
   Non-records get `FS_IMAGE_TYPE` set + `FS_LANGUAGE='und'` and are skipped for
   field extraction. (Handles leader cards, title pages, blanks.)
3. **Event-type detect** per record image → select the event-type field template
   from the matrix (Birth/Baptism/Marriage/Death/Burial).
4. **Multi-record segmentation + extraction** — the trained HTR model
   (`HtrService::extract` with the Scotland doc_type) returns an **array** of
   records, each numbered `FS_RECORD_NBR` 0..n, populated only with the fields
   valid for the detected event type (the `*_ORIG` system names). If the model
   returns one record per call, segment first (region/row split) then extract.
5. **Keying-rule normalisation** (prompt-encoded + PHP post-process):
   - dates: 3-letter month, no leading zero on day, earliest date for BIRTH /
     latest for other events, range→latest; 4-digit year (infer from context).
   - names: as-spelled, no titles, maiden name before surname.
   - ditto marks → inherit the previous record's field value.
   - unreadable: single char `?`, group `*`; whole image unreadable → image type.
   - crossed-out: replaced→new value; not-replaced-but-readable→keep.
   - diacritics kept as written.
6. **Human review** — repurpose the bulk-annotate grid: image on the left, the
   extracted record rows on the right, inline-editable, confirm/skip per record
   (reuse existing `ba-field` UI + save). Corrections feed the training corpus.
7. **Export** the Data Safe CSV keyed to the Index System Names; provenance
   already recorded per extraction.

## Schema / config

An "FS-Scotland profile": event-type → field list (straight from the docx
matrix), the Data Safe column order/system-names, and the project constants
(Collection/PPQ). Store as config (jurisdiction-neutral pattern; this is one
market profile, others can follow).

## Reuse

`HtrService` gateway plumbing + provenance; the bulk-annotate review UI; CSV
download; FS Overlay's form-type templating idea. New: the vision-extraction
prompt/schema, triage, segmentation, keying-rule post-process, profile config,
Data Safe CSV writer.

## Edge cases

Records spanning 2 images (key the whole record on the image it starts; skip a
continued-from-previous leading record); duplicates within a DGS; overlays
(separate record only if it pertains to a keyed event type).

## Phasing

- **P0 unblock (operator)** — serve the trained HTR model on a live GPU
  (`.76`/`.78`) or reboot `.115`; repoint the gateway HTR upstream. Confirm
  `/ai/v1/htr/legacy/health` is green + `sources` lists the trained doc_type(s).
- **P1 PoC** — Marriage only (we have an 1870 marriage register): triage +
  single-image multi-record extraction via the trained HTR model + review of
  raw accuracy on real 1870 Scottish hands. No CSV yet.
- **P2** — all five event types + image-type triage + keying-rule normaliser.
- **P3** — review grid + Data Safe CSV export + provenance surfaced.
- **P4** — accuracy tuning; optionally fine-tune a `doc_type` model from the
  accumulated human corrections (closes the loop with the manual annotator).

## Review-grid overlay + responsive listing (built 2026-06-24)

The review grid (`/admin/ai/htr/fs-index`) is the P3 surface. Two design points
the implementation locked in:

**Responsive listing, per-image extraction.** "List images" (`fsIndexRun` ->
`FsScotlandIndexerService::listImages`) returns one Data Safe row per image with
the constant/per-image fields filled (DGS, image number, Collection, PPQ, event
type) and the event fields blank - NO HTR call, so a 49-image folder lists in
~1.5s. The earlier design ran a synchronous full-folder HTR sweep inside the
`run` request (~1.4s/image => ~70s for 49 images), which blew past the
proxy/PHP timeout: the page hung at "Running...", the grid never filled and the
page felt frozen. Extraction now happens per image, on demand:
- clicking a grid row -> `fsIndexFields` -> `reviewImage()` does ONE extract and
  returns both the assembled Data Safe row (fills the row, non-empty cells only
  so typed Collection/PPQ survive) AND the overlay boxes;
- "Read all" iterates the rows sequentially (abortable via "Stop"), filling each
  as it completes - responsive, no single blocking request.

**Image overlay for corrections.** `reviewImage()`/`rawFields()` tag each model
field with its bbox (natural-image pixels) and its Data Safe target
(`FsModelFieldMap::fsFieldFor`). The grid draws scaled boxes over the source
image (blue = maps to a cell, orange = no Data Safe home). Drag a box to
reposition; click it to jump to its cell; "Recognise boxes" re-crops the current
boxes and re-reads them via the shared HTR overlay endpoint
(`fsOverlayRecognise`, crop -> gateway `/legacy/ocr-finetuned`), filling the
linked cells; "Draw box" adds a box bound to the focused cell. Corrected cells
flow to "Download corrected CSV" (`csvFromRows`) and "Save corrections"
(`saveCorrections`, written to `storage/app/fs-corrections/<dgs>.json` as the
future fine-tune corpus).

Verified end-to-end headless on dev 2026-06-24 (Marriage folder): list 1.6s/49
rows, row-click loads the 4665px scan + 9 scaled boxes + filled row, Collection
preserved. Values are still the births-trained model's guesses on marriage hands
(garbage) until a marriage `doc_type` model is trained - that's the P4/model-side
item, not a Heratio gap.

## Convergence on one overlay: fs-overlay is the FS indexer (2026-06-24)

Decision (Johan): rather than maintain two box-drawing UIs, make the existing
**FS Overlay** (`/admin/ai/htr/fs-overlay`) FS-aware and retire the inline
overlay from the fs-index grid. fs-overlay already has the mature machinery
(canvas, draggable boxes, per-form-type server-saved position templates, OCR
form-type auto-detect, Donut prefill, per-box HTR recognise) - it just lacked
the FamilySearch Data Safe schema.

Added to fs-overlay (`fs-overlay.blade.php`, all additive):
- Four `FORM_TEMPLATES`: `fs-scotland-marriage|birth|death|baptism`, with rough
  default field boxes the operator drags + "Sync to server" to save a real
  layout per form type (reused across every image of that type).
- `FS_SYS` map: each box label -> Data Safe system name (Groom Surname ->
  PR_NAME_SURN_ORIG, Bride Forename -> SP_NAME_GN_ORIG, Event Day/Month/Year ->
  EVENT_*_ORIG, Baptism's Birth Day/Month/Year -> PR_BIR_*, etc.).
- A "FamilySearch Data Safe" bar (Collection ID + PPQ ID + Download Data Safe
  CSV), shown only when an `fs-scotland-*` form type is active. Export builds one
  row per image from `entry.fields` (+ DGS/image-nbr parsed from the filename),
  POSTs to `fsIndexCsvRows` with `normalize:true` -> server runs
  `FsKeyingRules::normalizeRecord` (month/day/year/sex/name) -> `FsDataSafeCsv`.

Other changes:
- `FsIndexerController::csvFromRows` gained a `normalize` flag (keying rules
  before CSV) so the FS Overlay export is normalised like the batch path.
- `htrServeCroppedImage` + `htrServeImage` allow-list now includes
  `base_path('fs-metadata-capture').'/'` (was hardcoded to
  `/usr/share/nginx/heratio/FamilySearch/`, which 403'd dev + the fs-metadata
  register scans on both envs).
- fs-index grid kept as the fast batch-review + CSV view; its inline overlay
  removed; right panel is now a plain preview + "Correct in FS Overlay" link.

Verified headless (playwright + google-chrome, demo login) 2026-06-24: select
fs-scotland-marriage -> Data Safe bar shows; Load a DGS folder -> field list
shows FS labels (Groom Surname, Bride Forename); type a value -> Download Data
Safe CSV = 38 cols, value lands in the right system column (Groom Surname ->
PR_NAME_SURN_ORIG), 49 rows, normalised, 0 JS errors. FsScotlandIndexerTest 6/6.

## Full per-event field set + multi-record bands (2026-06-24)

Scottish statutory registers are tabular with MULTIPLE records per image (e.g.
008066403_00033 = 3 marriage blocks; FS_RECORD_NBR increments 0,1,2 per image).
The FS Overlay templates now model this:
- `FS_RECORD_FIELDS` holds the complete Data Safe field set per event type
  (Marriage = groom+bride incl. age/occupation/marital/both sets of parents;
  Birth/Baptism = name/sex/event date/parents (+PR_BIR_* for baptism); Death =
  +age/occupation/marital/spouse; Burial = +PR_DEA_* death date). FS_SYS maps
  every label to its Data Safe system name.
- A "Records / image" selector (1-8). `fsGenerateFields(eventType, n)` replicates
  the field set into n vertical record bands (auto-gridded; operator drags +
  "Sync to server" saves the layout per form type). Field labels are
  record-prefixed "R{n} <field>"; all are whitelisted in ALLOWED_FIELDS.
- Export groups the R{n} fields into one Data Safe row per block, FS_RECORD_NBR
  reset per image, emitting a row only when the block has a name (rule). Images
  marked with an Image Type export as a single non-record row (Language 'und').

Verified headless 2026-06-24: marriage x3 = 63 boxes; filling blocks 1+2 ->
2 CSV record rows (RECORD_NBR 0,1) with names in PR_/SP_ columns; 0 JS errors.

## Crop fix (2026-06-24)

"Mark area" + "Crop now" 500'd with "Read-only file system" ("Crop error:
Unexpected token '<'" in the browser): `htrFsOverlayManualCrop` overwrote the
ORIGINAL image (`imagejpeg($imagePath)`), but the FS scans live under /usr
(read-only to php-fpm via ProtectSystem) and overwriting a multi-record register
page would destroy its other blocks. Now non-destructive: the crop is written to
the viewer's cropped-cache key only; the original is never touched (clear the
cache to restore). The crop fetch also parses defensively (no raw JSON-parse
error on a non-2xx HTML response).

## Sync-to-server / layout-persistence fix (2026-06-24)

"Sync to Server did not save the layout": two causes.
1. Multi-record layouts collapsed on reload - the records-per-image count reset
   to 1, so applyFormTemplate only rebuilt the R1 band and R2..Rn boxes vanished.
   Fix: applyFormTemplate now restores the records count from the saved layout
   (highest R{n} in savedPositions) and regenerates the bands before rebuilding
   COLUMNS, so every saved band reappears. Verified: a saved 3-band marriage
   layout restores records=3 + 63 fields on reselect.
2. baMigrateToServer ("Sync to server") fired POSTs without awaiting and always
   showed "Synced (N)" even on a 419 (stale CSRF), so failures looked like
   success. Now it awaits + status-checks each save, uses the live meta CSRF
   token, reports the real result on the FS status line (no blocking alert):
   "Saved N layout(s)" / "Save FAILED (session expired - refresh)".
Server save/load round-trip (fsOverlaySavePositions/LoadPositions) was already
correct - positions persist to storage/app/fs-overlay-positions.json per form_type.
