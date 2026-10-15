# heratio.org demo data register

heratio.org is the flagship demonstration site: every record and file on it is there to show a Heratio feature. This register lists the approved demonstration data, where its files live, what is synthetic or third-party, and what the nightly reset must never touch. It closes ticket H01 (audit of public records, attachments, sample files, print addresses and exports), reviewed on 14 October 2026 at release 1.159.15.

The rule it serves: a record or file on heratio.org is either listed here as demonstration data or it is not public. "Test" in a title does not make a record unwanted. The AI Test series, the format samples and the POPIA fixtures are how features get shown, and they stay.

## What was checked

All 432 descriptions (385 published, 47 draft), 435 authority records and 13 repositories, plus everything under `/uploads/` on the NAS. Anonymous access was tested against record pages, files, the CSV export and the print view.

- **The CSV export and the print view** carry no draft records. The 385 rows in the anonymous export are exactly the published set.
- **Files of draft records** were downloadable by direct URL until 1.159.14; their signed C2PA copies until 1.159.15. Both are now refused to anyone who cannot see drafts (GHSA-rh79-87gv-wgxf). Draft thumbnails stay public by design, under the redaction policy.
- **Material in `/uploads/` that was not demonstration data** was moved outside the web root on 14 October 2026: a database dump, user lists, internal business documents, old backup copies of record files and client scan folders. None of it is referenced by a record.
- **Real institutions shown as holding repositories** were renamed to fictional ones. The credit lines their licences require stay on each record.
- **GPS coordinates were removed** from two published phone photos.
- **The Crystal Palace records were drafts** although the public walkthrough shows their images. Their files were served only because of the bug above, so the fix stopped them loading. All 16 were published on 14 October 2026 so the demo works through the gate.

## Approved demonstration sets

Counts are published records, grouped by the feature each set shows. Slugs are examples, not complete lists.

| Set | Records | Shows | Examples |
|---|---|---|---|
| Media formats and viewers | about 235 | One record per file format, each opening in its viewer: image, TIFF (single and multipage), PDF, audio, video, office files, archives | `jpg-object-1`, `tiff-viewer-multipage`, `pdf-viewer-multi-page`, `mov-object`, `tgz-test-a-tgz` |
| Further format and gallery samples | about 60 | Format conversion and preview for less common types (BMP, PSD, SVG, AIFF, MTS, XLS, DOCX), gallery display | `test-gallery`, `test-dam`, `gallery-hand-drawing-of-bird` |
| 3D models and walkthroughs | about 34 | GLB and OBJ viewing, Gaussian splats, 3D in exhibition walkthroughs | `thoth`, `the-rosetta-stone`, `railing-pillar`, `3-dimensional-object` |
| Archaeology | 27 | Excavation contexts and the Harris matrix | `Context 1001 - Deposit` to `Context 9020 - Deposit`, `blaauwbosch-farm-2026-excavation` |
| AI processing | 21 published, plus drafts | OCR, entity extraction, summaries and translation over the synthetic `doc_001` to `doc_010` documents | `test-ai`, `ai-test-5` to `ai-test-25` |
| Redaction | within the AI set | Visual and text redaction. The original PDF is withheld and the redacted copy served | `test-ai-3`, `ai-test-20` |
| Lost places | 16 (Crystal Palace) | Reconstruction of a lost building from evidence images and a 3D splat, shown in the public walkthrough `/exhibition-space/the-crystal-palace-reconstruction/walkthrough` | `the-crystal-palace-demo-lost-places-1323`, `the-crystal-palace-3d-structure-splat`, `crystal-palace-evidence-1` to `-14` |
| Museum, library and family archives | a handful | Sector-specific description, family fonds, a farm document | `collection-museum`, `format-test-library`, `engelbrecht-family-bible`, `plaas-welgelegen` |

## Synthetic data

These look real and are not. Each must say so in its own description, and none may be published outside the gate.

- **POPIA scan fixtures** (draft records `survey-responses`, `interview-01`, `consent-forms`, `climate-measurements`, `readme`). Invented names, health details and South African ID numbers. The ID numbers are checksum-valid because the scanner rejects any that are not, so the records stay draft and their files are refused to the public.
- **AI test documents** (`doc_001_medieval_europe.pdf` to `doc_010_european_literature.pdf`, `ARCHIVAL_MISCELLANY.pdf`). Invented archival text written for the AI demonstrations.

## Third-party and licensed content

Credit lines are part of the licence. They are never edited out, even when the holding repository is renamed.

- **3D models of British Museum objects**, CC BY-SA 4.0, "Copyright: The Trustees of the British Museum", models by Daniel Pett. Held under the fictional Meridian Museum of Antiquities.
- **Minneapolis Institute of Art images**, public domain, with the museum's credit lines. Held under the fictional Lakeshore Institute of Art.
- **Public-domain engravings** held under the fictional Northgate University Archives.

## Files the demonstrations use

Under `/uploads/` (on disk `/mnt/nas/heratio/uploads/`):

- `r/` - record files (a link to `/mnt/nas/heratio/archive`). Gated: files of draft or restricted records are refused.
- `condition_photos/`, `ar/` - gated separately.
- `lost-place-demo/`, `lost-place-3d/`, `exhibition-*`, `3d/`, `3dmodels/`, `triposr/`, `animations/` - walkthrough and 3D assets.
- `research-works/` - Dr Johan Pieterse's papers and slides, reseeded after each reset.
- `donor_agreements/`, `loan_agreements/`, `migration/`, `test_imports/`, `registry/`, `import_templates/`, `ingest/` - workflow demonstrations.
- `watermarks/`, `waveforms/`, `barcodes/`, `conversions/`, `transcripts/` - derived files used by the viewers.

Nothing else belongs in `/uploads/`. A file added there that is not demonstration data is public to anyone who learns its address.

## What the nightly reset must never touch

The reset restores the database from the baseline at 02:20 and leaves files on disk alone. After the restore it re-applies, and must keep re-applying:

- the articles and blog section (`blog_post`, `blog_attachment`, `blog_comment`, `blog_post_link`);
- cron monitoring history (`ahg_cron_run`, `ahg_cron_missed_run`, `cron_schedule`);
- Dr Johan Pieterse's research works (`ahg:research-works-seed` with the manifest in `/mnt/nas/heratio/research-works/`).

A guided-tour sample login (ticket H06) would join this list.

The baseline itself is part of the demo data. Removing or changing anything on the live site does not last unless the baseline is re-snapshotted afterwards (`heratio-demo-snapshot.sh`), and the reset refuses to run when the baseline version differs from the deployed code.

## Adding demonstration data

1. Say in the description what the record demonstrates, and mark synthetic content as synthetic.
2. Record the rights of every file: own work, licence with credit line, or public domain.
3. No real personal data about living people, and no location data in photos: strip GPS with `exiftool -gps:all=` before upload.
4. Never use the name of a real institution as a holding repository.
5. Re-snapshot the baseline once the record is right.

## Open questions

- **"Renaldo1 Venter"** is a published authority record that appears to name a real person. Keep it only with that person's agreement, or rename it.
- **"BEAUTIFUL IN BEAUFORT-WES (2006)"** uses a still from a commercial documentary and names a living poet. The still has no rights statement.
- **"JPG Object"** (`jpg-object-1`) is a photograph of a page from a published book. The book's copyright status is not recorded.
- **"Egyptian Boat"** (`egyptian-boat`) carries a 3D model licensed CC BY-NC 4.0. Non-commercial terms sit awkwardly with a demonstration that markets the software. On 15 Oct 2026 the OpenRiC demonstration switched to the AI-generated model. The boat's digital object (#905836) was unlinked: the row and file are kept, but the boat record no longer shows 3D on heratio.org.
- **"Gilded dining suite (AI-generated 3D model)"** (#916172, `gilded-dining-suite-ai-generated-3d-model`, identifier OPENRIC-DEMO-3D-1) was created on 15 Oct 2026 for the OpenRiC demonstration. Its digital object is a copy of MUS-FURN-0002's master row and points at the same file, `/uploads/r/913847/side-chair-and-occasional-table-set-opt.glb`. Moving, replacing or deleting MUS-FURN-0002's file, including when its description is corrected, also breaks this record. Give it its own copy of the file before changing either.

## Resolved

- **MUS-FURN-0002** (`side-chair-and-occasional-table-set`) was corrected on 15 Oct 2026. It used to describe an Edwardian walnut chair and table, but its 3D model shows a gilded dining suite. It is now "Gilded Dining Suite (AI-generated 3D model)", described as an AI-generated model with no physical original, with rights type "AI-generated content", no rights holder and legal status "Synthetic demonstration data". The invented materials, measurements, date, condition and provenance were cleared. The slug is unchanged so links keep working, and the record was re-indexed.
- **"Royal Chair"** (exhibition furniture, `royal-chair-441c550f.glb`) and the gilded dining suite model were both created by Dr Johan Pieterse, which answers the question about the source image of `adult-throne-chair-f2b1e9c0.png` raised on 15 Oct 2026.
