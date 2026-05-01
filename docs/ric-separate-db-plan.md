# OpenRiC service - separate-DB migration plan

**Status:** Planned, not yet executed. Phase 4.3 Option B + Phase 4.4.6 from the earlier split plan. A coordinated data change; schedule a maintenance window when you're ready.

**Why:** Currently `heratio.theahg.co.za` and `ric.theahg.co.za` share one MySQL database. That's a *soft* boundary - either app could technically write to the other's tables. Separate-DB makes the boundary hard and matches how a third-party adopter would run the service.

**When not to do this:** Before the split has been live for ≥ 2 weeks, or before a third-party adopter asks for the deployment recipe. No rush; the shared DB works.

---

## Target

- **`heratio` database** - stays as-is. Loses the `ric_*` tables + relations to them.
- **`openric_ric` database (new)** - owns `ric_place*`, `ric_rule*`, `ric_activity*`, `ric_instantiation*`, `ric_relation_meta`, and snapshot copies of the `object`, `slug`, `relation`, and `ahg_dropdown` rows the service reads. No writes to Heratio's DB.

## The awkward table: `object`

`ric_*` rows have `id` foreign keys into `object(id)` (class_name, created_at, updated_at). The service needs to read those rows. Options:

- **A. Denormalise into `ric_object`.** Copy only the columns the service actually uses (`class_name`, timestamps). Add a `ric_object` table in the service DB with the same PK as `ric_*`. Rewrite the service's queries to use `ric_object` instead of `object`.
- **B. Share the `object` table via a MySQL replication link.** Keeps it in sync; avoids query rewrites; adds infra complexity.
- **C. Accept eventual consistency - CDC from Heratio.** Heratio emits change events; service applies them to its own copy of `object`. Most flexible; most work.

**Recommended: A.** Simpler, self-contained, no replication infra. `object` is write-once-read-many; denormalising its minimal columns into `ric_object` is cheap.

## Same question for `relation`

The `relation` table links entities. `ric_relation_meta` is the RiC-specific metadata. The service reads both. Denormalise `relation.subject_id, object_id, start_date, end_date` into the service DB as either a `ric_relation` table or extra columns on `ric_relation_meta`.

## Same question for `ahg_dropdown`

The vocabulary taxonomies live in `ahg_dropdown`. The service reads them frequently. Options:

- Snapshot into a service-owned `openric_vocabulary` table. Refreshed on schedule or via a sync endpoint.
- Or - only share the subset with `taxonomy LIKE 'ric_%'` via a view.

**Recommended: snapshot.** Service owns its own vocabulary, refreshes on admin action.

## Same question for `slug`

Slug table maps slugs to object ids. The service generates slugs on create. Denormalise like `object`.

---

## Migration script (sketch)

One-shot, reversible. Run in a maintenance window.

```bash
#!/usr/bin/env bash
set -euo pipefail

# Create the new DB
mysql -u root -e "CREATE DATABASE openric_ric CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Clone the schema for the 5 ric_* tables + their i18n + ric_relation_meta
for t in ric_place ric_place_i18n \
         ric_rule ric_rule_i18n \
         ric_activity ric_activity_i18n \
         ric_instantiation ric_instantiation_i18n \
         ric_relation_meta; do
  mysqldump --no-data heratio "$t" | mysql openric_ric
  mysqldump --no-data=0 heratio "$t" | mysql openric_ric  # data
done

# Denormalised snapshots - ric_object, ric_slug, ric_relation, openric_vocabulary
mysql openric_ric <<'SQL'
CREATE TABLE ric_object LIKE heratio.object;
ALTER TABLE ric_object DROP INDEX class_name;  -- trim unused indexes
INSERT INTO ric_object (id, class_name, created_at, updated_at)
  SELECT id, class_name, created_at, updated_at FROM heratio.object
  WHERE class_name IN ('RicPlace','RicRule','RicActivity','RicInstantiation','QubitRelation');

CREATE TABLE ric_slug LIKE heratio.slug;
INSERT INTO ric_slug SELECT s.* FROM heratio.slug s
  JOIN ric_object o ON s.object_id = o.id;

CREATE TABLE ric_relation LIKE heratio.relation;
INSERT INTO ric_relation SELECT r.* FROM heratio.relation r
  WHERE r.id IN (SELECT relation_id FROM heratio.ric_relation_meta);

CREATE TABLE openric_vocabulary LIKE heratio.ahg_dropdown;
INSERT INTO openric_vocabulary SELECT * FROM heratio.ahg_dropdown
  WHERE taxonomy IN (
    'ric_place_type','ric_rule_type','ric_activity_type','ric_carrier_type',
    'ric_relation_type','ric_entity_type','ric_relation_category','certainty_level'
  );
SQL
```

## Service code changes

The `ahg-ric` package's `RicEntityService` queries `object`, `slug`, `relation`, and `ahg_dropdown` directly. Every such query needs to become one of:

- `ric_object` instead of `object`
- `ric_slug` instead of `slug`
- `ric_relation` instead of `relation`
- `openric_vocabulary` instead of `ahg_dropdown`

Scope: ~50 `DB::table(…)` calls across the package. Mechanical find-and-replace, plus one Laravel config to point the service's connection at the new DB.

**Critically: Heratio's copy of the package is unchanged.** Heratio still writes `object`, `slug`, `relation` for its own (non-RiC) entities. The package split (Phase 4.4.4) resolves this cleanly.

## Replication - how does Heratio see new RiC data?

Three options:

- **Do nothing.** Heratio simply doesn't see Places/Rules/etc. created via the API. The RiC graph on Heratio's side becomes read-only for those. Fine if Heratio never needs to link its own records to newly-created RiC entities.
- **One-way sync.** A periodic job pulls `openric_ric.ric_*` rows into `heratio.ric_*` read-only. Heratio can read-join but not write.
- **Two-way reconciliation.** Bidirectional sync. Don't do this; you'll have conflict-resolution nightmares.

**Recommended: one-way sync**, or do-nothing if Heratio has no need to cross-reference.

## Rollback

Until the service code is pointed at the new DB in production, Heratio's schema is unchanged. Drop `openric_ric` and the migration is undone. Once live:

1. Point the service's `DB_DATABASE` back at `heratio`.
2. Any writes the service made to `openric_ric` after cutover need replaying into `heratio`. Keep a binlog of the maintenance window.

## Checklist

- [ ] Write the migration script properly (the sketch above is illustrative).
- [ ] Test against a staging DB clone.
- [ ] Measure row counts before + after; must match exactly.
- [ ] Update `openric-service/.env` DB_DATABASE to `openric_ric`.
- [ ] Verify `php artisan ric:verify-split` still passes 15/15 from Heratio against `ric.theahg.co.za`.
- [ ] Monitor for 48 h; compare write counts in both DBs (Heratio's should be 0 new `ric_*` rows).
- [ ] Optionally drop `ric_*` tables from Heratio once confident.

## Open questions

1. Do we need Heratio to continue seeing RiC data at all? If not, "do nothing" is the simplest replication answer - Heratio reads nothing from the RiC DB, just calls the service for whatever it needs to render.
2. Is the service's DB user granted enough privileges to create its own schema changes, or is that a release-gated step for the operator?
3. When Phase 4.4.4 (package split) lands, the separate-DB move becomes much more natural - the client package has no DB queries to update. Might be cleaner to do 4.4.4 first.

---

## Change log

| Date | Change |
|---|---|
| 2026-04-18 | Initial plan. Not executed. |
