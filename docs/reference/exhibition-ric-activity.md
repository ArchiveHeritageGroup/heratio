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

## First slice / follow-ups

First slice = Activity node + `includes` relations + surfacing via the connections page.
Follow-ups (each needs an ahg-ric or IO-show unlock):
- resolve RiC-native entity **names** in `crossCollectionNeighbours` so the exhibition shows as
  its title (not `#id`) on an object's connections;
- an **"Appeared in exhibitions"** panel on the archival-record show page (locked IO show tree);
- richer relations from the issue: `had participant` -> Agent (curator/institution),
  `took place at` -> Place (venue), explicit `has date` Date entities;
- auto-sync on space save (today it's an explicit button).
