# Clean standalone-install validation: fixes (2026-06-25)

A from-scratch standalone install (fresh `git clone` of `main` + `bin/install`,
no data clone) on a disposable validation VM surfaced several install-completeness
and display gaps. All fixed + released. This is the value of validating via one
clean reinstall rather than per-fix patches.

## 1. GLAM browse facet was empty on a fresh install ("All" only)
**Symptom:** `/glam/browse` left sidebar "GLAM Type" facet showed only "All" - no
per-type rows (archive/museum/gallery/library/dam).
**Cause:** the facet reads the `display_facet_cache` table. That table IS created
by `database/core/00_core_schema.sql` (stage 6), but `bin/install` never
populated it, and the controller has **no live fallback** for the unfiltered
landing page (`DisplayController::getCachedFacet()` returns `[]` on a cache miss).
So the cache was empty -> only the hardcoded "All" row rendered.
**Fix:** `bin/install` **stage 11b** runs `php artisan ahg:display-reindex` after
ES. Important: use `ahg:display-reindex` (writes the `glam_type` + `_all` variants
and the other facets); `ahg:refresh-facet-cache` does NOT write `glam_type` and
defaults to the `atom` discovery connection, which doesn't exist standalone.

## 2. PDF digital objects showed a grey "PDF" icon, not a first-page thumbnail
**Cause (by design, now changed):** `DigitalObjectService::upload()` only
rasterized `media_type = Image` (GD). A PDF (media_type Text) fell to
`generateGenericDerivatives()` -> a generated grey "PDF" icon PNG for usage
141/142.
**Fix:** new `DigitalObjectService::generatePdfDerivatives()` rasterizes page 0
via ImageMagick, reusing `createImagickDerivative()` (which already reads
`<master>[0]`); added an optional `?int $density` param (150 dpi) for a crisp
render. Wired into both `upload()` (web) and `generateDerivativesForMaster()`
(scanner/import) on the `application/pdf` branch, with a graceful fall-back to the
old icon when `convert` is missing or an ImageMagick PDF policy blocks it (so the
upload never fails). The browse-card resolver (`DisplayController` ~line 540) keys
off the derivative **filename** extension, so the new `thumbnail_*.jpg` renders
where the old `*_pdf.png` icon did. Requires **Ghostscript** + ImageMagick PDF NOT
blocked by `policy.xml` (both true on a standard Ubuntu install). Backfill an
existing PDF by deleting its icon 141/142 rows and re-running
`generateDerivativesForMaster($masterId)`.

## 3. Single-upload size cap was 100 MB while the platform ships 256 MB
**Cause:** `DigitalObjectController::upload()` validated `max:102400` (100 MB),
but `public/.user.ini` ships `upload_max_filesize = 256M`, so 100-256 MB GLAM
masters were rejected by Laravel validation before PHP ever saw them.
**Fix:** the validation cap now tracks PHP's own `upload_max_filesize`
(`phpUploadMaxKb()` parses the ini value), so it can never reject a file PHP would
accept, and auto-rises if the operator raises `.user.ini` per the install guide.
Note: the **CLI** php.ini is 2M and irrelevant - web uploads run under php-fpm,
which reads `public/.user.ini`.

## 4. Gallery sector parity (Actions bar + RiC view gating)
- **Actions bar missing:** the archival-IO and museum show pages render a
  horizontal `<ul class="actions">` toolbar in `@section('after-content')`; the
  gallery only had a sidebar context card. Added the toolbar (Edit / Delete / Add
  new), mirroring the museum hand-rolled pattern with gallery routes.
- **RiC panel on the standard (CDWA) view:** the `ahg-ric::_ric-entities-panel`
  (RiC Context + OpenRiC link + SHACL Validate) was inside the gallery's `@else`
  (standard) branch, so it showed on CDWA and was hidden in RiC view - backwards
  from the IO show page. Moved it out and gated it with
  `@if(session('ric_view_mode') === 'ric')`, mirroring the IO show page. The
  visible panel marker `/admin/ric/validate` is now absent on the standard view.
  (A residual "OpenRiC" string count came from the hidden side-by-side translate
  modal, `ahgTranslateSbsModal` - not the panel.)

## Operational notes (host-side; not in the repo)
- **A standalone VM needs ~8 GB RAM, not 4.** ES 8 + MySQL 8 + php-fpm OOM-thrashed
  a 4 GB VM the moment ES started (symptom: `sshd` times out "during banner
  exchange"; the VM is "running" but swapping). Bumped the libvirt domain to 8 GB
  (`virsh setmaxmem/setmem --config` after a forced off, since there was no
  `<maxMemory>` balloon slot) - persists across reboots.
- **Production OPcache hides Blade edits.** With `opcache.validate_timestamps=0`
  (production), `php artisan view:clear` deletes the compiled view file but OPcache
  keeps serving the old compiled PHP from memory. After editing a `.blade.php` on a
  production-mode box you must `systemctl reload php8.3-fpm` (or `optimize:clear` +
  reload) - `view:clear` alone is not enough. This caused a long false-negative
  while verifying the gallery RiC gating.

## Earlier in the same validation pass (already documented)
- `bin/install` runs package migrations at **stage 9b** (after seeds + admin), and
  `create_library_trading_partners_table` is guarded - see
  `schema-drift-on-pull-and-migrate-step-2026-06-25.md`. That was the first
  finding: migrate aborting on a data-seeding migration skipped `marc_leader`.
