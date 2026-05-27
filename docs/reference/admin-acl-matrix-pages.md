# Admin ACL Matrix Pages

Heratio ships two admin matrix pages for managing group-level ACL grants in one place:

- `/admin/term-permissions` (alias `/admin/termPermission`) - rows are ACL groups, columns are taxonomies broken out per action (create / view / update / delete).
- `/admin/translate-permissions` (alias `/admin/translatePermission`) - rows are ACL groups, columns are enabled locales pulled from the `i18n_languages` system setting (with a bundled fallback list).

Both pages render under the existing `admin` middleware and use a click-to-save AJAX pattern - no submit buttons, no page reloads. The POST endpoints return a small JSON body (`{ok: true, granted: bool}`) so the frontend can confirm each cell or revert the checkbox on failure.

## Storage model

Both pages write rows into the canonical `acl_permission` table - the same table the per-group editor at `/admin/acl/group/{id}` writes into. Nothing new in the schema.

| Matrix | `acl_permission` row shape |
|---|---|
| Term ACL | `group_id`, `object_id` = taxonomy id, `action` in (`create`, `read`, `update`, `delete`), `grant_deny=1` |
| Translate ACL | `group_id`, `object_id=NULL`, `action='translate'`, `grant_deny=1`, `constants='{"language":"<code>"}'` |

The translate page uses `constants` for the per-locale scope so the legacy whole-group `translate` grant (a row with `constants=NULL`) keeps working untouched.

## PSIS-parity context

PSIS exposed `/admin/termPermission` and `/admin/translatePermission` only as static "permission denied" placeholder templates. The real matrix UI never existed there. The Heratio implementation is parity-plus: same URL surface, real matrix behind it. Twin issue `atom-ahg-plugins#89` stays open so the AtoM side can backfill if it ever needs to.

## Files

- Controllers: `packages/ahg-acl/src/Controllers/TermPermissionController.php`, `packages/ahg-acl/src/Controllers/TranslatePermissionController.php`
- Views: `packages/ahg-acl/resources/views/term-permissions.blade.php`, `packages/ahg-acl/resources/views/translate-permissions.blade.php`
- Routes: `packages/ahg-acl/routes/web.php` (block at the top of the admin group)
- Tests: `tests/Feature/AclMatrixTest.php`

## Deferred follow-up

The matching accession AJAX endpoints from `atom-ahg-plugins#89` (`checkIdentifierAvailable` + `relatedDonor` autocomplete) were not built in this pass: the `ahg-accession-manage/` package is locked end-to-end (line 93 of `.locked-paths`, part of the IO show tree lockdown). They belong in a separate unlock-and-release pass and have been called out in the issue thread.
