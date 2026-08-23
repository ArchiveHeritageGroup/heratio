# The 30 CCO fields the catalogue forms were throwing away

Date: 2026-08-23
Author: Dr Johan Pieterse
Status: fixed and verified on heratio-dev

## What was happening

The gallery and museum edit forms are built to Cataloguing Cultural Objects.
Every field carries its own help text and a CCO citation, and the forms read as
a serious standards implementation.

Thirty of those fields went nowhere. They were absent from `museum_metadata`,
absent from both controllers' validators, and absent from the `$metaFields`
whitelists in `GalleryService` and `MuseumService`. A cataloguer filled them
in, pressed Save, and the values were discarded. Nothing errored, nothing was
logged, and the form came back empty next time - which reads as "I must not
have saved it" rather than "the system dropped it".

Among the thirty is `dimensions_display`, annotated in the markup as
**required, CCO 6.1**. A form that marks a field required and cannot store it
is the sharpest version of this.

Found by `tools/scan-blade-bindings.php` (#1478) and confirmed by hand against
each query and save path.

## Two hand-maintained copies of one list

Both sector services write the same `museum_metadata` table, and each carried
its own inline whitelist of columns to persist. That is how thirty fields end
up in neither: there is no single place where "the CCO fields" are named, so
adding a field to a form does not fail loudly anywhere.

The fix is `AhgCore\Support\CcoFields` - one list, used by both services for
the save, the validation rules and the SELECT. Adding a CCO field is now: add
the column in a migration, add its name to that list, add the input under the
same name. Same shape as the three divergent loan-rule lookups consolidated in
v1.154.634.

## Names match columns exactly, on purpose

Every new column is named exactly as its form field. Divergence between a form
field and its column is the root cause of the whole #1478 class, and matching
them is what lets the scanner prove these stay wired.

It costs some near-duplication: `attribution_qualifier` now sits beside an
existing unused `creator_qualifier`, and `dimensions_display` beside
`dimensions`. That is a deliberate trade. An extra column is recoverable and
visible; guessing a CCO mapping wrong and silently writing "Gallery 3, Bay B"
into `current_location_repository` would be a worse failure and a much quieter
one. Consolidating those pairs is a follow-up for someone with the CCO mapping
in front of them, noted on #1478.

## One of the thirty was a plural

`alternate_titles` needed no column at all.
`information_object_i18n.alternate_title` already exists, is already in the
i18n whitelist, and is already saved. The form field simply had an `s` on the
end. CCO 3.2 has been silently broken by one character.

That one is worth remembering when triaging the remaining 101: not every
finding needs schema.

## The row-size wall

The first cut of the migration used VARCHAR and failed partway with
`SQLSTATE[42000] 1118 Row size too large`.

`museum_metadata` already carries ninety-odd columns whose VARCHAR definitions
alone account for **63,964 of InnoDB's 65,535-byte row limit** - 98% full. A
VARCHAR counts against that limit in full; a TEXT costs only a ~20-byte pointer
because its payload lives off-page.

All nineteen new columns are therefore TEXT. None needs an index or a length
constraint, so nothing is lost. The migration also cleans up after that failed
run: three columns had been created as VARCHAR before it aborted, and `up()`
converts them - but only where the column holds no data at all, so a re-run can
never discard catalogued values.

**Anyone extending `museum_metadata` should assume the next VARCHAR will not
fit.** This is now the binding constraint on that table.

## Where the migration lives

`database/migrations/`, not the package. `ahg-museum` has no migrations
directory and no provider calling `loadMigrationsFrom()`, so a migration placed
there would silently never run - the same trap that has produced production lag
before. The existing `add_icip_sensitivity_to_museum_metadata` sits at app level
for the same reason and is the precedent followed.

## Verification

- All 19 columns present as TEXT; `museum_metadata` VARCHAR footprint back to
  62,560 bytes.
- End-to-end: saved `dimensions_display`, `materials_display`,
  `creator_display`, `location_within_repository`, `iconography`,
  `condition_summary`, `height_value` and `width_value` through
  `GalleryService::update()` and read every one back through `getBySlug()`.
  Test values removed afterwards.
- The scanner drops from 131 findings to **101**, exactly thirty fewer, and
  `gallery/edit.blade.php` and `museum/edit.blade.php` disappear from the list
  entirely. That is independent confirmation rather than my own assertion.
- `database/core/05_museum_cco_fields.sql` loads twice against `heratio_test`
  with no error.
- 440 tests pass.

Note on the round-trip check: `getBySlug()` first returned null, which looked
like a regression and was not. The record carried publication status 159
(draft), and the query correctly hides drafts from an unauthenticated caller -
a CLI script has no session. Authenticating made it behave. Worth recording
because it will look like a bug to the next person who tests a sector service
from the command line.

## What is not done

- The remaining 101 findings from #1478, of which 14 are still class A - form
  fields that discard input.
- Consolidating the near-duplicate column pairs described above.
