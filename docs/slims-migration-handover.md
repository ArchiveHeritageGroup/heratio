# SLIMS/Brocade to Heratio: handover to the heratio-dev session

Origin: the SLIMS evaluation session (working dir `/usr/share/nginx/slims`), 21 July 2026.
Purpose: hand the SLIMS-side findings and four code-verified Heratio defects to the heratio-dev session, which has full repo context to file them as issues or fix them directly (dev-first).

## Background in one paragraph

SITA asked for an evaluation of SLIMS/Brocade (a ~1.1M-line MUMPS ILS serving ~1,300 SA public libraries, unmaintainable locally) and whether to modernise or migrate. A depth-verified, code-level functional comparison against Heratio concluded that Heratio is the recommended migration target (over Koha): it already carries an ILS module and, decisively, the GRAP 103 heritage-asset accounting that was the compliance driver. Full analysis and build plan are in three documents in `/usr/share/nginx/slims/`:

- `AHG-SLIMS-2026-001 ... Technical Evaluation and Modernisation Options v1.0.docx`
- `AHG-SLIMS-2026-002 ... Heratio vs SLIMS Brocade Functional Gap Analysis v1.0.docx`
- `AHG-SLIMS-2026-003 ... Combined Pack v1.0.docx` (consolidates 001+002 and adds the implementation plan)

Markdown sources for those docs sit alongside in the same folder and in `/tmp/*.md`. SLIMS source is extracted at `/tmp/bfull/` for reference.

## Part 1: Four verified defects in the Heratio library module

All four were found during the gap analysis and then re-verified against current `heratio-dev` source (file:line below). Two agent descriptions were corrected on verification - the corrected framing is what appears here. These are worth fixing regardless of the migration decision.

### DEFECT 1 (correctness): authority-to-item link dedup guard swaps its columns

- File: `packages/ahg-library/src/Services/AuthorityControlService.php:163-183` (the check is lines 165-168).
- The existence check queries `->where('library_item_id', $authorityId)->where('authority_id', $libraryItemId)` - the two IDs are transposed. The subsequent INSERT (lines 172-173) uses them correctly.
- Effect: the guard almost never matches an existing link, so `linkToItem()` inserts duplicate `library_item_authority_link` rows and double-increments `library_subject_authority.linked_count` on every re-link. Only coincidentally correct when `authorityId == libraryItemId`.
- Fix: swap the two `where` columns to match the insert (`library_item_id => $libraryItemId`, `authority_id => $authorityId`). Consider a unique index on `(library_item_id, authority_id, source_tag)` and a `linked_count` recompute to repair existing data.

### DEFECT 2 (schema drift): two competing FRBR work-key schemas

- Live code path uses the ORIGINAL schema: `library_item.work_key` (varchar 32) + table `library_work_override` (with `override_key`), created by `database/migrations/2026_05_27_010000_add_work_key_to_library_item.php` and `..._020000_create_library_work_override_table.php`. Readers/writers: `packages/ahg-biblio-frbr/src/Services/WorkKeyService.php`, `.../Controllers/WorkClusterController.php`, `.../Controllers/WorkOverrideController.php`, `.../Console/Commands/FrbrBackfillWorkKeysCommand.php`.
- A LATER migration `packages/ahg-library/database/migrations/2026_06_15_000101_create_library_interop_backbone_tables.php` (lines ~195-248) created a PARALLEL, unused schema: `library_item.frbr_work_key` (varchar 64) + table `library_item_frbr_override` + `target_work_key`.
- Effect: two work-key columns (32 vs 64 char) and two override tables coexist. The FRBR service populates only `work_key`, so any consumer reading `frbr_work_key` sees empty values. This is latent correctness/maintenance risk and schema duplication, not a hard crash (correcting the original "will fail at runtime" description).
- Fix: decide the canonical pair, migrate/backfill onto it, and drop the duplicate. Given the interop backbone is the newer intent, likely consolidate onto `frbr_work_key`/`library_item_frbr_override` and repoint the FRBR service - but confirm which is intended before migrating data.

### DEFECT 3 (dead code / runtime failure): Z39.50 importMarc writes to non-existent tables

- File: `packages/ahg-z3950/src/Services/Z3950Service.php` - `importMarc()` (from line 144) writes to `library_biblio_work`, `library_biblio_instance`, `library_biblio_agent` (lines ~327-381).
- No migration anywhere creates any `library_biblio_*` table (grep-verified across all package and app migrations).
- Effect: `importMarc()` throws if ever called. The live copy-cataloguing path uses `decodeToLibraryItem` into the real `library_item` instead, so `importMarc()` is an orphaned/dead path.
- Fix: remove `importMarc()` and its `library_biblio_*` writes, or, if the biblio scaffold is genuinely intended, add the migrations. Prefer removal to avoid a second competing bibliographic model.
- **RESOLVED (#1413).** Corrected one detail of the original triage: `importMarc()` was NOT orphaned - `Z3950Controller::import()` and `importBatch()` both call it, so the Z39.50 import buttons were actively broken, not merely dead. `importMarc()` now delegates to ahg-library's `CopyCataloguingService::import()`, creating real `library_item` records; the four `library_biblio_*` writers were deleted. Two further faults on the same path were fixed while there: the hand-rolled ISO 2709 reader in `parseMarcRecord()` read leader position 10 (the indicator length, always `2`) as the directory length, so its loop never ran and it returned `[]` for every binary record - it now delegates to ahg-library's MARC readers and handles MARCXML too, which is what yaz actually returns for USmarc targets; and `result.blade.php` referenced an undefined `$service`, which fatally errored the result-set browser you have to pass through to reach Import.

### DEFECT 4 (non-functional feature): BIBFRAME export targets a scaffold that no migration creates

- File: `packages/ahg-biblio-bf/src/Controllers/BibframeController.php` reads `library_biblio_work`/`library_biblio_agent`, guarded by `Schema::hasTable('library_biblio_work')` (lines 53-58, 91-95, 231).
- The same missing `library_biblio_*` tables as Defect 3. Because of the `hasTable` guard, BIBFRAME degrades gracefully (counts return 0, lists render empty) rather than crashing - so the feature is present in the UI but permanently produces nothing.
- Fix: tie this to the Defect 3 decision. Either create the `library_biblio_*` scaffold and a populator (from `library_item`), or drive BIBFRAME directly off `library_item` like the other exporters. Currently it is dead surface area.
- **RESOLVED (#1414).** Driven off `library_item`, per the Defect 2 decision to standardise there. New `AhgBiblioBf\Services\BiblioWorkRepository` projects the catalogue onto the BIBFRAME hierarchy - Work = a `library_item.work_key` cluster, Instance = each `library_item`, Item = each `library_copy`, Agent = `library_item_creator` (de-duplicated, roles mapped to MARC relator codes). `BibframeService`, `BibframeSerialisationService` and `BibframeController` all read through it, and RDF import now creates real catalogue records via `LibraryService` instead of writing to phantom tables. The scope was wider than the issue described: `BibframeService`, `BibframeSerialisationService` and `GraphEditorController` referenced the scaffold too, not just the controller lines cited. Two further faults fixed: `importRdf()` returned the OpenRiC proxy response instead of its stats array, so the import screen always reported 0 works/0 instances however much it imported; and `agent.blade.php` called `->toDateString()` on a query-builder string, which would have fatally errored the moment the agent list was ever non-empty.

Note the shared root cause of Defects 2-4: a `library_biblio_*` / interop-backbone bibliographic model was scaffolded in code but never fully migrated or wired, leaving competing/phantom schemas. Worth resolving as one coherent decision about whether Heratio keeps a separate biblio model or standardises on `library_item`.

**That decision is now made and applied: Heratio standardises on `library_item`.** #1412 dropped the duplicate FRBR work-key schema, #1413 and #1414 removed the last `library_biblio_*` readers and writers. No `library_biblio_*` table is referenced in code any more. One related surface is still outstanding: `packages/ahg-biblio-frbr` (`FrbrService`, `FrbrController`, and its `index`/`import`/`export` views) reads the same phantom scaffold and needs the same treatment - it can now reuse `BiblioWorkRepository`.

## Part 2: The strategic frame the dev session should know

If SITA proceeds, Heratio becomes the SLIMS migration target (Option F in the pack). The build roadmap (Combined Pack section 5) is summarised here so dev work aligns.

Foundational decision: model the 1,300 branches as repository rows in ONE shared Heratio instance (not one instance per branch). Heratio's shared-DB, repository-scoped multi-tenancy then gives a single scalable index, a union catalogue for free, and the substrate for branch-aware circulation.

Prioritised gap register (what is missing in Heratio for a national public-library ILS):

- BLOCKER: multi-branch circulation tenancy - make loan rules, holds, patrons, settings and notices branch-aware (the tenancy substrate exists; circulation logic does not use it).
- HIGH: in-app fine payment/waiver + cash-desk POS; SMS + print notice channels (schema exists, service hard-codes email); federated auth (LDAP bind is config-only, CAS/OIDC routes are advertised but absent, no SAML - a regression vs SLIMS); offline circulation; port the SLIMS physical stocktake (scan-and-reconcile against live catalogue+loans) and feed lost/missing into the GRAP impairment engine.
- MEDIUM: loan-rules engine depth (branch + calendar axes + admin editor; currently 2-D and seeded only in tests); SIP2 self-check; authority control depth (typed records/hierarchy/external-vocab sync - and Defect 1); ILL automatic multi-supplier routing + wire the inbound EDI decoder; invoicing sub-ledger (VAT + credit notes); classification schedules; GRAP engine completion (consolidate the two asset registers `ipsas_heritage_asset` vs `heritage_asset`, wire the stubbed heritage-accounting CRUD - only the OCI-movement path is live - and compute the opening-to-closing roll-forward).
- LOW: circulation statistics, richer label format library, canonical MARC persistence, and Defects 2-4 above.

Note: the acquisitions buildout doc (`docs/library-acquisitions-buildout-plan.md`) is STALE - acquisitions has since been implemented (orders/lines/receiving/budgets/write-off). Serials is genuinely deep. Discovery, interoperability, digital preservation, IIIF, multi-tenancy and MFA are Heratio strengths ahead of SLIMS.

## Part 3: Issues filed (both sides)

Filed on the Heratio (Laravel) repo `ArchiveHeritageGroup/heratio`:

- #1411 - Defect 1, authority link dedup transposes IDs
- #1412 - Defect 2, two competing FRBR work-key schemas
- #1413 - Defect 3, Z39.50 importMarc writes to non-existent `library_biblio_*` tables
- #1414 - Defect 4, BIBFRAME targets a `library_biblio_*` scaffold no migration creates

Filed on the AtoM (Symfony) repo `ArchiveHeritageGroup/atom-ahg-plugins`:

- #188 - parity twin. Verification found all four defects are Heratio-side. The AtoM plugin needs no change and is unaffected.

Key parity finding: **Defect 1 is a regression introduced by the Laravel port.** The AtoM source `ahgLibraryPlugin/lib/Service/AuthorityControlService.php:141-163` has the correct column order; the Heratio port transposed it. Fix in Heratio = adopt the AtoM order. Defects 2-4 are Heratio-only (no AtoM equivalent); the AtoM plugin standardises on the real item table and carries no `library_biblio_*` model.

## Part 4: Suggested next actions for the heratio-dev session

1. ~~Triage-label #1411-1414 and action them.~~ **Done** - all four are fixed. Defect 1 adopted the AtoM column order; Defects 2-4 were taken as one decision, standardising on `library_item`. Remaining follow-up: give `ahg-biblio-frbr` the same treatment (it still reads the phantom scaffold), and note that MARCXML parsing assumes the MARC21 slim namespace, so a target returning namespace-less MARCXML still parses empty.
2. Confirm whether the multi-branch topology decision has an owner; it gates the whole migration option.
3. When ready, the migration PoC (one library, GT.M extract to MARC21 to Heratio, reconcile) is the highest-value next experiment - `conversion/pals` and `conversion/koha` in `/tmp/bfull/` are the precedents to reuse.
