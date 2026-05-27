# IO missing PSIS-parity endpoints (#742)

Eight endpoints added to `packages/ahg-information-object-manage/` to close
the PSIS-parity gap on the archival-description module. All are sibling
controllers under the locked `packages/ahg-information-object-manage/`
subtree — none touch `show.blade.php` or any file in the show-page render
path.

## Endpoints

| Method | Path | Controller | Auth |
|---|---|---|---|
| GET | `/informationobject/{slug}/modifications` | `ModificationsController@index` | public |
| GET | `/informationobject/{slug}/tree-view` | `TreeViewPageController@show` | public |
| POST | `/informationobject/tree-sync/{id}` | `TreeViewPageController@sync` | auth |
| POST | `/informationobject/tree-move` | `TreeViewPageController@move` | auth + acl:update |
| GET | `/informationobject/slug-preview?title=&id=` | `SlugController@preview` | public |
| POST | `/informationobject/{id}/finding-aid` | `FindingAidActionsController@upload` | auth + acl:create |
| DELETE | `/informationobject/{id}/finding-aid` | `FindingAidActionsController@destroy` | auth + acl:delete |
| GET | `/informationobject/browse/hierarchyData?root_id=` | `HierarchyDataController@data` | public |

## Modifications page

Source of truth: `ahg_audit_log` filtered by `entity_type='information_object'`
and `entity_id={IO}`. Paginated 25/row, most recent first. Uses Bootstrap 5
classes + bi-* icons (`bi-clock-history`, `bi-calendar3`, `bi-tag`,
`bi-person`). Action codes are mapped to human strings via a lookup table
(create / update / delete / move / rename / publish / finding_aid_upload /
finding_aid_generate / finding_aid_delete / login / logout). Falls back to
`action_name` then the raw action token so we never render an empty cell.

If the `ahg_audit_log` table doesn't exist (`ahg-audit-trail` not installed)
the page still renders, just with an empty list — no 500.

## Tree-view page

Full-width jstree at `/informationobject/{slug}/tree-view` extending
`theme::layout_1col`. Loads jstree 3.3.16 from CDN (+ jQuery 3.7.1 SRI-pinned).
The data feed is `/informationobject/browse/hierarchyData` which lazy-loads
children per parent_id. Ancestor chain of the current node is pre-expanded.

When the viewer is authenticated, jstree's `dnd` plugin is enabled and
`move_node.jstree` POSTs to `/informationobject/tree-move` with CSRF token.
The server-side move uses the standard nested-set negate / close / open /
restore pattern, transactional, with three guards: can't move the root, can't
move into your own descendant, can't move to be your own parent. Move events
are written to the audit log via `AhgCore\Support\AuditLog::captureMutation()`.

`/informationobject/tree-sync/{id}` re-fetches one subtree as jstree JSON —
identical shape to `browse/hierarchyData` but rooted at the specified id.
Used by the "Sync" button after external edits.

## Slug preview

`GET /informationobject/slug-preview?title=Hello&id=42` returns
`{ slug, conflict, fallback }`. The slug is generated with `Str::slug()`,
collision-resolved against the `slug` table with `-2`, `-3`, ... suffixes,
and excludes `id` from the conflict check (so renames don't always report
collisions against themselves). Pure non-ASCII titles fall back to
`record-{id}` (matching the `InformationObjectController` controller-side
fallback).

## Finding-aid REST twin

`FindingAidController` keeps the slug-keyed PSIS-parity form flow
(`/informationobject/{slug}/findingaid/upload` etc.). The new
`FindingAidActionsController` adds the JSON-friendly id-keyed REST pair
(`/informationobject/{id}/finding-aid` POST + DELETE) for the tree-view
buttons and external integrations. Both surfaces share the same on-disk
layout (`public/downloads/finding-aid-{id}.{ext}`) and audit log entries
(`finding_aid_upload` / `finding_aid_delete`).

## Lock safety

All five new controllers are siblings under the one-shot unlock of
`packages/ahg-information-object-manage/`. Verified via:

```bash
git diff --name-only HEAD -- packages/ahg-information-object-manage/resources/views/show.blade.php packages/ahg-information-object-manage/resources/views/partials/
# (empty)
./bin/check-locked  # exit 0
```

No files reachable from `show.blade.php` are modified.

## Tests

`tests/Feature/InformationObjectMissingEndpointsTest.php` covers the
happy-path render of the two HTML pages, the JSON shape of `hierarchyData`,
the slug-preview behaviour (empty, kebab-case, conflict), and verifies
that the three mutating POST/DELETE endpoints reject unauthenticated
traffic. Twelve test methods.
