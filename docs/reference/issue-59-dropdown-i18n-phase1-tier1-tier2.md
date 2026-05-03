# Issue #59 - Dropdown Manager per-language filter (Phase 1 + Tier 1 + Tier 2 shipped)

## Summary

Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/59 - "Dropdown Manager: per-language filter + translatable labels".

Phase 1 (schema + en seed) and Phase 2 Tier 1 (3 service helpers) and Phase 2 Tier 2 (10 in-blade query swaps) are live. Result: every dropdown rendered through `AhgSettingsService::getDropdownChoices*` or via the 10 swapped blades is culture-aware via a COALESCE chain `i18n[current_culture] -> i18n[en] -> ahg_dropdown.label`. Translations are stored in a new `ahg_dropdown_i18n` AHG sidecar table.

Phase 2 Tier 3 (5 controller files), `registry_dropdown` migration (1 service file), Phase 3 (Dropdown Manager UI), and Phase 4 (verification) remain to do.

## What was shipped

### Phase 1 - schema + idempotent en seed

New AHG sidecar table:

```sql
CREATE TABLE IF NOT EXISTS ahg_dropdown_i18n (
    id      INT          NOT NULL,
    culture VARCHAR(16)  NOT NULL,
    label   VARCHAR(255) NOT NULL,
    PRIMARY KEY (id, culture),
    CONSTRAINT ahg_dropdown_i18n_FK_1
        FOREIGN KEY (id) REFERENCES ahg_dropdown(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

DDL lives in `packages/ahg-dropdown-manage/database/install_i18n.sql`.

`AhgDropdownManageServiceProvider::boot()` calls `ensureI18nTable()`:
1. Skip if `ahg_dropdown` (parent) does not exist (fresh install before stage 6 of bin/install).
2. Run `install_i18n.sql` if `ahg_dropdown_i18n` does not exist.
3. `INSERT IGNORE ... LEFT JOIN ... WHERE i18n.id IS NULL` to seed missing en rows. Cheap on every boot once seeded; no-op when caught up.

Seeded 3902 / 3902 parent rows on first boot.

### Phase 2 Tier 1 - service helpers in `packages/ahg-core/src/Services/AhgSettingsService.php`

Three methods rewritten to share a new `dropdownQueryWithI18n($extraSelect = [])` base query:

- `getDropdownChoices($taxonomy, $includeEmpty = true): array` - returns `[code => label]`.
- `getDropdownChoicesWithAttributes($taxonomy): \Illuminate\Support\Collection` - returns Collection of stdClass keyed by code (changed from array for `->isEmpty()` compatibility on the legacy callers in `_form.blade.php`).
- `resolveDropdownLabelForTaxonomy($taxonomy, $code): ?string` - single-row lookup.

The shared helper:

```php
protected static function dropdownQueryWithI18n(array $extraSelect = []): \Illuminate\Database\Query\Builder
{
    $culture = (string) app()->getLocale();
    $hasI18n = Schema::hasTable('ahg_dropdown_i18n');

    $select = array_merge(['d.code'], $extraSelect);
    $q = DB::table('ahg_dropdown as d');

    if ($hasI18n) {
        $q->leftJoin('ahg_dropdown_i18n as di_cur', function ($j) use ($culture) {
            $j->on('di_cur.id', '=', 'd.id')->where('di_cur.culture', '=', $culture);
        });
        $q->leftJoin('ahg_dropdown_i18n as di_fb', function ($j) {
            $j->on('di_fb.id', '=', 'd.id')->where('di_fb.culture', '=', 'en');
        });
        $select[] = DB::raw("COALESCE(NULLIF(di_cur.label, ''), NULLIF(di_fb.label, ''), d.label) AS label");
    } else {
        $select[] = 'd.label';
    }

    return $q->select($select);
}
```

`Schema::hasTable()` guard means installs without the i18n table degrade gracefully to the parent `ahg_dropdown.label` column. No exception, no behaviour change.

### Phase 2 Tier 2 - 10 in-blade query swaps

Each file's inline `\Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy', X)->where('is_active', 1)->orderBy('sort_order')->get()` was replaced with `\AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes(X)` (or `getDropdownChoices(X, false)` for the pluck-style callers).

| # | File | Taxonomies |
|---|---|---|
| 1 | `packages/ahg-ric/resources/views/_ric-entity-modal.blade.php` | ric_activity_type, ric_place_type, ric_rule_type, ric_carrier_type, ric_relation_type |
| 2 | `packages/ahg-ric/resources/views/_relation-editor.blade.php` | ric_relation_type, certainty_level |
| 3 | `packages/ahg-research/resources/views/research/equipment.blade.php` | equipment_type, equipment_condition |
| 4 | `packages/ahg-research/resources/views/research/seats.blade.php` | seat_type |
| 5 | `packages/ahg-research/resources/views/research/profile.blade.php` | id_type |
| 6 | `packages/ahg-research/resources/views/research/public-register.blade.php` | id_type |
| 7 | `packages/ahg-research/resources/views/research/register.blade.php` | id_type |
| 8 | `packages/ahg-research/resources/views/research/walk-in.blade.php` | id_type |
| 9 | `packages/ahg-vendor/resources/views/_add-item-modal.blade.php` | condition_grade |
| 10 | `packages/ahg-vendor/resources/views/_form.blade.php` | contract_status, risk_level, contract_counterparty_type, currency (via `$dd($taxonomy)` closure) |

## How translation now works for ahg_dropdown values

1. Author inserts a row into `ahg_dropdown_i18n` for the target culture: `INSERT INTO ahg_dropdown_i18n (id, culture, label) VALUES (<dropdown_id>, 'af', '<af label>')`.
2. Any caller using one of the three `AhgSettingsService` helpers (or any of the 10 swapped blades) gets the af label automatically when `app()->getLocale() === 'af'`.
3. Rows without an af entry fall back to en via the COALESCE chain.

For the user-authored side of this (UI to write to `ahg_dropdown_i18n`), see Phase 3 - Dropdown Manager UI - which is still to do.

## What remains for issue #59

### Phase 2 Tier 3 - 5 controller files (no blade touched)

Same swap recipe as Tier 2 but in PHP:

- `packages/ahg-library/src/Services/LibraryService.php:555` - creator roles
- `packages/ahg-records-manage/src/Controllers/ClassificationController.php:170` - classification scheme
- `packages/ahg-records-manage/src/Controllers/ComplianceController.php:31, 45` - compliance frameworks
- `packages/ahg-records-manage/src/Controllers/ReviewController.php:40, 64` - review decisions
- `packages/ahg-vendor/src/Controllers/VendorController.php:1339` - vendor types

### registry_dropdown migration (1 service file, 2 method bodies)

Need to create `registry_dropdown_i18n` table (same shape as `ahg_dropdown_i18n` but FK to `registry_dropdown(id)` and note that `registry_dropdown` uses `dropdown_group` as the grouping column, not `taxonomy`). Add boot-time seed in `AhgRegistryServiceProvider`. Then patch `RegistryService::getDropdowns()` (line 426) and the single-row lookup at line 475 to use a COALESCE join.

### Phase 3 - Dropdown Manager UI

Decisions taken with user:

- Source dispatcher pattern: extend `DropdownController::edit($taxonomy)` to `edit($source, $taxonomy)` (breaking route signature change). New route: `/admin/dropdowns/{source}/{taxonomy}`.
- Tier 2 batching (already executed): all 10 blade files in one batch after MVP service-helper rewrite proved out.

Open decisions still needed before Phase 3 starts:

- Draft `entity_type` values: literal table names (`ahg_dropdown`, `registry_dropdown`, `term`, `setting`) vs AtoM-style class names (`QubitAhgDropdown` etc).

UI scope for Phase 3:

- Index `/admin/dropdowns` gets two filters: Source (All / ahg_dropdown / registry_dropdown / term / setting) and Culture (defaults to `app()->getLocale()`).
- Edit `/admin/dropdowns/{source}/{taxonomy}` - side-by-side editor partial mirroring `_translate-sbs.blade.php`. Per-row Save POSTs to `/admin/dropdowns/{source}/{id}/i18n` with `{culture, label}`. Admin auto-applies; editor queues into `ahg_translation_draft` with `entity_type` discriminator.
- `TranslationController::draftApprove()` extension: handle `class_name=QubitDropdown[Backend]` so approving a queued dropdown draft applies it to the right `_i18n` table - same pattern as `class_name=QubitMuseumMetadata` from #56.

### Phase 4 - verification

- Curl `/openric-demo-leroux-journals?sf_culture=af` after author saves an af value for `ric_activity_type=production` - should render `<option>Produksie</option>` (already verified manually with a hand-inserted i18n row).
- Editor draft round-trip via the existing `/admin/translation/drafts` queue.
- Fallback safety: rename `ahg_dropdown_i18n` table -> confirm UI still renders parent `label` everywhere.

## Constraints honoured

- Zero AtoM base tables touched (no ALTER on `term`, `term_i18n`, `setting`, `setting_i18n`, `object`, etc.). New table is in the AHG sidecar namespace, follows the pattern set by `museum_metadata_i18n` from #56.
- All page edits in Tier 2 done in one approved batch after Tier 1 proved out, per the user's approval workflow. Subsequent page work returns to per-file approval (memory rule `feedback_lock_all_pages`).
- No em-dashes anywhere (memory rule `feedback_no_em_dashes`).

## Released

Phase 1 + Tier 1 + Tier 2 staged together (11 files). User runs `./bin/release patch` with the message in the chat history; the release auto-clears Laravel view cache via `php artisan view:clear` once the merge is in. Currently the release is in progress as of 2026-05-03.
