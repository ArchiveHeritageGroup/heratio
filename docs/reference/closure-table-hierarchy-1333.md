# Closure-table hierarchy migration (#1333)

Replaces the hand-maintained Nested Set (`lft`/`rgt`) - which is O(n)-write and
drifts on the 322k atom/ANC instance - with closure tables, in phases. lft/rgt
stay authoritative until the read/write swap, so nothing breaks mid-migration.

## Phase 1 - foundation (done, additive, non-breaking)

- **Schema** `packages/ahg-core/database/install_closure.sql`: three NEW tables
  `information_object_closure`, `term_closure`, `menu_closure`, each
  `(ancestor, descendant, depth)` with PK `(ancestor, descendant)` (= the
  required UNIQUE), covering indexes `(ancestor, depth, descendant)` and
  `(descendant, depth)`, and FKs `ON DELETE CASCADE` to the base table. The
  self-row `(X, X, 0)` is an invariant. **No base table is altered** (AtoM base
  tables are read-only); auto-installed from `AhgCoreServiceProvider::boot()`.
- **Build command** `php artisan ahg:build-closure {--table=|--all} {--verify} {--dry-run}`
  (`AhgCore\Console\Commands\BuildClosureCommand`): set-based, depth-by-depth
  construction from `parent_id` (seed self rows, then iteratively extend each
  depth-d path by one parent->child edge) - O(edges) in a few bulk INSERTs,
  minutes on 322k, transactional. `--verify` checks the self-row invariant and
  nested-set parity `(rgt-lft-1)/2`.

### Verification (heratio_dev)

- `information_object` (381 nodes -> 1183 rows, depth 4): **0 parity mismatches**.
- `menu` (64 -> 236): **0 mismatches**.
- `term` (740 -> 1585): **1 mismatch at root term 110** - closure finds 736
  descendants via `parent_id`, the nested-set width formula gives 734. Diagnosis:
  every closure descendant is inside 110's `lft`/`rgt` range, so the nested set
  has **numbering gaps** (the closure is correct; the hand-maintained nested set
  has drifted). This is the exact fragility #1333 removes.

## Sibling ordering (done - sidecar)

Resolved in favour of an `ahg_*` sidecar `ahg_node_sibling_order
(entity, node_id, parent_id, sibling_order)` rather than a column on the
read-only AtoM base tables (Johan, 2026-06-24). `ahg:build-closure` seeds it
from the current `lft` order within each parent group (`ROW_NUMBER`), verified
to match `lft` order exactly on heratio_dev. It is regenerable derived data, so
a future reconcile with Xercode's column-based shared-DB layout is a trivial
rebuild.

## Maintenance layer (built + verified, not yet wired)

`AhgCore\Services\ClosureMaintenanceService` - transactional, set-based:
- `addNode(entity, id, parentId, ?order)` - self row + ancestor chain, append sibling order.
- `moveNode(entity, id, newParentId, ?order)` - canonical closure subtree move (detach subtree from old ancestors, reattach under new parent), no global renumber.
- `removeNode(entity, id)` - sidecar cleanup; closure rows go via the ON DELETE CASCADE FK.

Verified on real dev data (rolled-back txns): both `addNode` and `moveNode`
produce a closure **identical to a full rebuild** (incl. moving the root with
361 descendants). It is NOT yet wired into any write site, so there is no
behavior change yet.

## Write-side dual-write (done)

Every nested-set write site now also maintains the closure + sibling sidecar,
transactionally, alongside lft/rgt (lft/rgt still authoritative; no read change):
- **information_object:** `ImportJob`, `GalleryService`, `MuseumService`,
  `DataMigrationService`, `EmailCaptureService`, `SectorCsvImporter`,
  `InformationObjectService`, `InformationObjectController` (copy + add-child) -
  `addNode` on create, sidecar cleanup on delete (closure cascades via FK);
  `TreeViewPageController` reparent -> `moveNode` + `resyncSiblingOrder`;
  `TreeviewService` same-parent reorder -> `resyncSiblingOrder`.
- **term:** `TermService` (+ the inline quick-add term in `InformationObjectController`).
- **menu:** `MenuService` (create + delete + `moveToParent` -> `moveNode`).

## Remaining phases (not yet done)
- **Query swaps** - `orderBy('lft')` -> sibling-order; `whereBetween('lft',...)`
  subtree reads + ancestor lookups -> closure JOINs. Touch points span many
  (often locked) packages: information-object-manage, gallery, museum, ric,
  research, records-manage, menu-manage, metadata-export, oai, discovery,
  finding-aid/treeview - full inventory captured in Phase 1.
- **OpenSearch** - on subtree move, a single async `_update_by_query` applying
  the `ancestors` prefix delta in Painless (not per-document reindex).
- **Parity** on a copy of the 322k atom dataset before retiring lft/rgt.

## Parity verified + first read-swap (2026-06-24)

Closure built + parity-checked on dev against the authoritative parent_id
transitive closure (recursive CTE), both directions, for all three trees:
- information_object: 1183 = 1183, missing=0, extra=0 -> PASS
- term: missing=0, extra=0 -> PASS
- menu: missing=0, extra=0 -> PASS

So read-swaps are provably safe. First swap: `DocumentPriorService::compute()`
(ahg-authority-resolution, unlocked) now uses
`HierarchyQueryService::descendantIds('information_object', $fondsId, true)`
(closure when ready, lft/rgt fallback, parent_id walk as last resort) instead of
`whereBetween('lft',[lft,rgt])`. Verified it returns 361 vs the old query's 360
for the root fonds - the extra node 913771 is a real child (parent_id=1) with
lft=NULL that the drifted nested-set range MISSED. Closure is strictly more
correct here.

### Remaining descendant-READ swap sites (all in LOCKED packages - need unlock)
Pure descendant-range reads to swap to HierarchyQueryService::descendantIds /
scopeDescendants (NOT the lft>rgt sibling reads or the increment/decrement
nested-set MAINTENANCE code, which stay lft/rgt):
- packages/ahg-gallery (GalleryService: subtree count/list)
- packages/ahg-ric (OaiPmhController: ListRecords under a fonds)
- packages/ahg-ai-services (SuggestedConnectionsService: same-fonds candidates)
- packages/ahg-information-object-manage (InformationObjectService, Controller,
  TreeviewService - read paths only; the move/insert lft/rgt writes stay)

## Read-swap batch complete (2026-06-24)

Descendant-range READS swapped to HierarchyQueryService (closure when built,
lft/rgt fallback). Verified each returns the same-or-more-correct set (closure
catches null-lft drift orphans the nested-set range misses):
- DocumentPriorService (ahg-authority-resolution) - fonds IO set; 361 vs 360
- GalleryService / MuseumService / InformationObjectService / InformationObject
  Controller - subtree DELETE collections (information_object)
- TermService - subtree DELETE collection (term entity; 737 vs 735)
- ImportJob (app/Jobs) - subtree DELETE collection
- OaiPmhController (ahg-ric) - OAI ListRecords under a fonds (scopeDescendants)
- SuggestedConnectionsService (ahg-ai-services) - same-fonds candidate pairs

LEFT on lft/rgt by design:
- All nested-set MAINTENANCE (move/reparent/insert: negate-shift, increment/
  decrement gap open/close) - TreeviewService, TreeViewPageController, IO-Controller
  reparent, MenuService move. lft/rgt stays authoritative; these WRITE it.
- Sibling/next-node reads (lft > rgt / lft > lft).
- Ordering-dependent EXPORT reads where orderBy('lft') drives hierarchical
  output: MetadataExportCommand, FindingAidGenerateCommand (ahg-core), and the
  locked ahg-portable-export (BundleWorkerCommand, PortableExportController) +
  ahg-preservation (BagItService). Revisit with applySiblingOrder if/when those
  need closure; low benefit + ordering risk for now. ahg-portable-export +
  ahg-preservation also need an unlock.

Still remaining for #1333: OpenSearch ancestor-delta on move; 322k-scale parity
on a copy of the atom/ANC dataset; then retire lft/rgt.

## OpenSearch ancestor-delta on subtree move (2026-06-24)

ES indexed `lft`/`parentId` but never queried them for ancestry, had no per-doc
update path, and moves didn't touch ES at all (full ahg:es-reindex only). Added
the closure-aligned ES piece:
- EsReindexCommand now indexes `ancestors` = closure ancestor ids (excl. self)
  per IO doc, so a subtree is `terms: {ancestors: <id>}`.
- ElasticsearchService::updateSubtreeAncestorsOnMove($movedId,$old,$new) fires ONE
  _update_by_query (conflicts=proceed, wait_for_completion=false => async) with a
  painless script: for every doc whose `ancestors` contains $movedId (plus
  $movedId itself), remove the moved node's OLD ancestor chain and add the NEW
  one. The delta is identical for the whole subtree (shared prefix). No-op when
  old==new (reorder under same parent); best-effort (never throws into the move).
- Wired into TreeViewPageController reparent: capture ancestorIds BEFORE mutation,
  ancestorIds AFTER commit, fire the delta.
Verified: painless script updated 2 test docs in one query - moved [1,2]->[5,6],
descendant [1,2,8888]->[8888,5,6]; live ES returned 200 + async task id.

#1333 now: data layer + dual-write + read-swaps + ES ancestor-delta all DONE on
dev. Only 322k-scale parity (prod data copy) remains before retiring lft/rgt.

## Synthetic 322k scale gate (2026-06-25) — PASS

There is no 322k *Heratio* dataset (Heratio = 381 IOs; the 322k is the AtoM
corpus, a different app). So the scale gate was run on a synthetic 322k tree in a
throwaway `heratio_parity` DB (dropped after; this MySQL is shared with the live
381-row demo, so it was kept bounded):
- Tree: 322,000 nodes, 5-ary, max depth 8 (generated set-based in 6.8s).
- Closure build (iterative depth-by-depth, mirrors BuildClosureCommand):
  2,775,932 rows, 41.8s, 177 MB.
- PARITY: independent recursive parent_id CTE vs the built closure, both
  directions = 2,775,932 = 2,775,932, missing=0, extra=0. EXACT at scale.
- Read (subtree filter via closure PK ancestor): 19,531 ids 63ms; 97,656 ids
  107ms; whole 322k tree 184ms.

Verdict: the closure mechanism is correct + feasible + fast at 322k. The
migration is technically sound at scale and READY if/when a 322k corpus lands in
Heratio. It does NOT establish that Heratio (381 rows today) NEEDS it now -
that's the finish-vs-shelve decision.
