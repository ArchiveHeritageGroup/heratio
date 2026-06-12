# Exhibition as a RiC Activity (#1195)

Publishes an Exhibition Space (the digital twin) into the RiC knowledge graph as a first-class
`rico:Activity`, tied to its displayed objects via `rico:includes` (inverse `rico:isIncludedIn`).
The exhibition then sits in the same graph as archival activities, and each placed object's
cross-collection connections list the exhibitions it featured in.

## How it works (ahg-exhibition, no ahg-ric edit)

The write goes through the RiC entity engine by **calling** `AhgRic\Services\RicEntityService`
(`createActivity` / `updateActivity` / `createRelation`) - ahg-ric stays untouched/locked.

- `ExhibitionRicService::syncSpace($space)`:
  - creates (or refreshes) a `rico:Activity` for the space - name = space name, start/end dates
    from `MIN(starts_at)`/`MAX(ends_at)` of its placements, plus a `date_display`;
  - stores the activity id on `ahg_exhibition_space.ric_activity_id` (provider auto-adds the
    column) so re-sync updates rather than duplicates;
  - for each distinct, non-corridor placed object, creates an `includes` relation
    (`ric_relation_type` dropdown code -> `rico:includes` / inverse `rico:isIncludedIn`),
    skipping objects already linked (idempotent).
  - `available()` guards on the RiC engine being installed (`class_exists` + `ric_activity`
    table) so the feature degrades cleanly on installs without RiC.
- `ExhibitionSpaceController::syncRic($slug)` -> route `exhibition-space.sync-ric`
  (POST, `acl:update`). A **Publish to RiC graph** button on the exhibition-space show page
  (auth) triggers it and reports how many objects were linked.

## Surfacing

Once published, the existing **Cross-collection Connections** page (#1197) shows the link both
ways: the Activity lists its included objects (Records & descriptions), and each object lists the
Activity. Verified: a space with 23 placements -> Activity + 23 `includes` relations; the
activity's connections show 23 records, and an object's connections show the activity.

## Richer relations: participant / venue / date (#1218, from #1214 item 3)

On top of the `includes` object links, `syncSpace` now also emits the activity's **who / where /
when** when a catalogue `exhibition` row is linked to the space. The link is
`ahg_exhibition_placement.exhibition_id` -> `exhibition.id`; `primaryExhibitionForSpace()` picks
the most common non-null `exhibition_id` among the space's placements. When no placement carries
one (the common case for a standalone digital twin), nothing extra is emitted and the #1195
behaviour is unchanged.

All three predicates are resolved by `RicEntityService::createRelation()` from the
`ric_relation_type` dropdown metadata (`predicate` / `inverse`) - they are **not** hard-coded in
ahg-exhibition. We pass the dropdown code; the engine supplies the canonical CURIE.

| Relation | Dropdown code | RiC-O predicate | Inverse | Source (exhibition row) |
|---|---|---|---|---|
| had participant | `performed_by` | `rico:isOrWasPerformedBy` | `rico:performsOrPerformed` | `curator_id` (FK to actor, preferred) / `curator_name` / `designer_name` / `organized_by` |
| took place at | `has_or_had_location` | `rico:hasOrHadLocation` | `rico:isOrWasLocationOf` | `exhibition_venue` via `venue_id`, else denormalised `venue_name` / `venue_address` / `venue_city` / `venue_country` |
| has date | (attribute, no edge) | `ric:beginningDate` / `ric:endDate` literals | - | `opening_date` / `closing_date` (falls back to `actual_closing_date`, then placement `MIN/MAX`) |

### Why these RiC-O predicates

- **had participant -> `rico:isOrWasPerformedBy`.** RiC-O has no distinct `hadParticipant`
  property; participation in a `rico:Activity` by a `rico:Agent` is expressed through the
  performance relation `isOrWasPerformedBy` (inverse `performsOrPerformed`). Curators, designers
  and organisers of an exhibition are exactly the agents that carried out the activity, so this
  is the correct RiC-O modelling. (The `performed_by` dropdown is defined Activity-domain ->
  Agent-range, which matches.)
- **took place at -> `rico:hasOrHadLocation`.** This is the canonical RiC-O location relation;
  its domain is open (`rico:Thing`), so an Activity locating its venue is in scope. The venue is
  resolved/created as a `rico:Place`.
- **has date -> activity date attributes.** RiC-O's date relations (`hasBeginningDate` /
  `hasEndDate` / `isAssociatedWithDate`) target a separate `rico:Date` instance. This schema
  models a date as a **literal attribute** on `ric_activity` (`start_date` / `end_date`), with no
  `ric_date` entity table. To honour "no new table / no ALTER", `has date` is implemented by
  writing the exhibition's opening/closing dates onto the activity (preferred over the
  placement-derived run dates) **and** stamping the same `start_date` / `end_date` onto every
  emitted participant/venue relation row, rather than minting a `rico:Date` node.

### Resolve + idempotency

- Agents are resolved by `actor_i18n.authorized_form_of_name` (current culture); a missing one is
  created via `RicEntityService::createAgent(['name' => ...])`. A real `curator_id` FK is honoured
  directly when it points at an existing `actor` row (and the free-text `curator_name` is then
  skipped to avoid a duplicate).
- The venue is resolved by `ric_place_i18n.name`; a missing one is created via `createPlace`.
- Each link is deduped by `(subject_id = activity, object_id = target, ric_relation_meta.dropdown_code)`
  before `createRelation`, so **re-publishing never duplicates** a participant or venue relation.
- Everything is `Schema::hasTable`-guarded and wrapped so a missing/absent field is skipped
  cleanly and the sync never 500s.

### Verified (#1218)

A space linked (via placements) to an exhibition with curator/designer/organiser + venue +
opening/closing dates published as: 1 Activity, 16 `includes`, **3 `performed_by`**
(`rico:isOrWasPerformedBy`) and **1 `has_or_had_location`** (`rico:hasOrHadLocation`) relations,
each stamped with the `2025-04-01..2025-08-31` date range, and the activity carrying the same
start/end. Re-publishing returned `participants=0, venues=0` (no duplicates). A space with no
linked exhibition, and an exhibition with no venue/participant/date, both emitted only the #1195
`includes` links with no error. No new table, no ALTER, no ahg-ric edit.

## First slice / follow-ups

First slice = Activity node + `includes` relations + surfacing via the connections page.
#1218 added the participant / venue / date relations above.
Follow-ups (each needs an ahg-ric or IO-show unlock):
- resolve RiC-native entity **names** in `crossCollectionNeighbours` so the exhibition shows as
  its title (not `#id`) on an object's connections;
- an **"Appeared in exhibitions"** panel on the archival-record show page (locked IO show tree);
- a true `rico:Date` entity (needs a `ric_date` table / ahg-ric change) if date nodes are wanted
  over the current literal date attributes;
- auto-sync on space save (today it's an explicit button).
