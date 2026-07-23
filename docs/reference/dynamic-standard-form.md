# Dynamic per-standard description form (#1425)

Reference for the machinery behind "choose a description standard on the form and
the fields swap." Covers the swap mechanism, the six standard packages, the
structured RiC-O editors, deep-link preselection, the per-record RiC view, and
the RiC-O interchange surfaces. User-facing guides: *Choosing a description
standard* and *Records in Contexts (RiC)* in the Help Center.

Shipped across v1.154.418 - v1.154.428.

## The swap mechanism

The archival Add form (`create.blade.php`) and generic edit form
(`edit.blade.php`) render a driver `<select data-standard-driver>` inside a
persistent **Administration area**, above a swappable
`<div id="standard-fields">`. Each option carries a `data-code` (the taxonomy-70
natural key: `isad` / `ric` / `dacs` / `rad` / `mods` / `dc`).

On change - and, since v1.154.427, on load when the selected option is non-ISAD -
a `swap(code)` function fetches
`GET /informationobject/standard-fields?code=<code>&slug=<slug?>` and replaces
`#standard-fields` innerHTML. ISAD restores a cached copy of the server-rendered
default. Firing on load covers three cases with one code path: a deep-link, a
record's own standard on edit, and a non-ISAD choice restored after a validation
error (the last was a latent bug - the swap previously fired only on `change`).

`InformationObjectController::standardFields()` dispatches by `code` through a
`STANDARD_CONTROLLERS` registry to the owning `*ManageController::fieldsPartial()`,
guarded by `class_exists` + `method_exists`; unknown / unwired codes fall back to
`ahg-io-manage::partials._isad-fields`. Progressive enhancement: with JS off the
default ISAD fields still submit.

## Per-standard packages

Each standard is its own package: `ahg-ric-manage`, `ahg-dacs-manage`,
`ahg-rad-manage`, `ahg-mods-manage`, `ahg-dc-manage` (ISAD is core). Each ships:

- `resources/views/_fields.blade.php` - the field accordion only (no `<form>`,
  no save button, no display-standard dropdown; nullable `$io` = empty on
  create, populated on edit). The package's own `edit.blade` `@include`s it, so
  markup lives once.
- `*ManageController::fieldsPartial(Request, ?slug)` - render `_fields` with the
  record's data (or empty for create).
- `*ManageController::persist(int $ioId, Request)` - the save logic, extracted
  from `edit()`'s POST branch; `edit()` now calls it.

`store()` / `update()` call `dispatchStandardPersist($ioId, $request)`, which
reads the hidden `_display_standard_code` and, if it maps to a wired controller,
calls that controller's `persist()` to overlay the standard-specific fields.

Absent package => option not offered => ISAD fallback. A client install ships
only the standards it needs.

## Administration area

The standard-agnostic controls - Description standard (the driver), Publication
status, Source language - are grouped in one persistent `#admin-area` accordion
that lives **outside** `#standard-fields`, so it survives a swap. Publication is
de-duplicated: each `*-manage/_fields` partial hides its own publication block
when rendered through the swap route
(`@unless(request()->routeIs('informationobject.standard-fields'))`), so the host
Administration area owns the single field; the standalone standard editors still
show their own.

## Level of description, scoped by sector

`level_of_description_sector` (term_id, sector, display_order) maps taxonomy-34
levels to `archive` / `library` / `museum` / `gallery` / `dam`.
`InformationObjectController::getFormDropdowns($culture, $sector)` filters the
Level dropdown through it (default `archive`). The RiC / DACS / RAD manage
controllers apply the same filter in their own `getFormDropdowns` (guarded by
`Schema::hasTable`, full-list fallback) so a swap does not revert to all 27
levels. MODS / DC have no Level selector.

## Deep-link preselection

`InformationObjectController::create()` accepts:

- `?standard=<code>` - **preferred**, a portable taxonomy-70 natural key.
- `?standardId=NNN` / `?display_standard_id=NNN` - a per-DB term id, kept for
  cross-system (PSIS) link compatibility. Not portable - term ids differ per
  database.
- `?parent=` - alias for `?parent_id=`.

It resolves the standard to a taxonomy-70 term id and preselects the driver via
`old('display_standard_id', $preselectStandardId)`. The record action bar
(`_actions-bar.blade.php`, included by the archival `show.blade`) turns **Add
new** into a split button whose caret builds
`informationobject.create` links with `['parent' => $io->id]` (+ `'standard' =>
$code` for non-ISAD) - "Add child description in -> RiC-O / ISAD(G) / DACS / RAD
/ MODS / Dublin Core". Sector action bars are intentionally left untouched.

## Structured RiC-O editors

Two repeatable editors on the RiC-O `_fields` partial.

**Instantiations** (`rico:Instantiation`). `ric_instantiation` gained a `source`
column (`auto` | `manual`), self-installed in `AhgRicServiceProvider::boot()`.
The one-time digital-object backfill stays `auto`; the editor writes `manual`.
Rows persist through `RicEntityService::createInstantiation/updateInstantiation/
deleteInstantiation` (full RiC entities: `object` + `slug` + i18n + Fuseki) in
`RicManageController::syncManualInstantiations`, diffed against the record's
existing manual rows - auto rows are never touched.
`RicSerializationService::getInstantiationsForRecord` emits both the
digital-object instantiations and the manual `ric_instantiation` rows (filtered
to `source='manual'` so autos are not double-counted).

**Events** (`rico:Event`). Rows persist as AtoM `object(QubitEvent)` + `event` +
`event_i18n` (agent -> `event_i18n.name`, note -> `.description`, display date ->
`.date`) in `RicManageController::syncEvents`, diffed against the record's
**non-creator** events (`type_id != 111` - Creation stays owned by the creators /
date widgets). No serializer change was needed:
`RicSerializationService::getDatesForRecord` already emits every event as
`rico:hasDateRangeSet` -> `rico:hasDateRange`.

**Repeatable UI.** Rows use a `<template>` plus a generic
`[data-repeat-add]` / `[data-repeat-remove]` **document-level** click delegation
(guarded by `window.__ahgRepeatBound`) in create.blade, edit.blade, and the
standalone ric-manage editor. Document-level delegation is required because the
swap replaces `#standard-fields` via innerHTML - inline `<script>` in swapped
content never runs, and a direct `querySelectorAll(...).forEach` binding misses
swapped-in buttons. A `__IDX__` placeholder in the template is replaced by an
incrementing `data-repeat-index`.

## RiC-O interchange

One serialiser (`RicSerializationService`) behind three surfaces, all
respecting the same published + cultural-protocol access control:

- **OAI-PMH** - `metadataPrefix=rico` on `/oai` (ahg-oai), gated by
  `class_exists(RicSerializationService)`.
- **Download** - `/admin/metadata-export/ric?io=NNN` (Turtle) / `/ric.rdf`
  (RDF/XML).
- **GraphQL** - `ricO(id: Int!)` on `admin/graphql/execute`; guarded, drafts /
  restricted records return "not found".

## Persistent per-record RiC view

`ric_entity_view` (composite PK `entity_type`, `entity_id`; `view_mode`) records
each record's Record-vs-RiC preference, replacing the old session-global
`ric_view_mode`. Self-installed in `AhgRicServiceProvider::boot()`.
`RicViewModeService::mode/isRic/set` (guarded by `Schema::hasTable`) back it;
`RicController::setViewMode` persists per-record when `entity_type` + `object_id`
are posted. The composite key exists because not every wired entity is an AtoM
object-subtype - `loan` is a standalone table whose small ids would collide with
object ids in a single-column key. Wired across every entity show page (actor,
repository, function, accession, donor, rights-holder, term, storage, loan, the
four sector shows, and the archival IO show + its display-standard variants).

## Key files

| Concern | File |
| --- | --- |
| Swap host + Administration area + repeat JS | `ahg-information-object-manage/resources/views/create.blade.php`, `edit.blade.php` |
| Dispatch + deep-link + level filter | `ahg-information-object-manage/src/Controllers/InformationObjectController.php` |
| Child split-button | `ahg-information-object-manage/resources/views/_actions-bar.blade.php` |
| Per-standard field set + persist | `ahg-<std>-manage/resources/views/_fields.blade.php`, `src/Controllers/*ManageController.php` |
| RiC entity lifecycle | `ahg-ric/src/Services/RicEntityService.php` |
| RiC serialiser (all interchange) | `ahg-ric/src/Services/RicSerializationService.php` |
| Per-record view + self-install | `ahg-ric/src/Services/RicViewModeService.php`, `src/Providers/AhgRicServiceProvider.php` |
