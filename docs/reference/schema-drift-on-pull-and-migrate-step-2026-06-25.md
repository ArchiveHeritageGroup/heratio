# Preventing DB schema drift on pull (the "Unknown column marc_leader" bug)

**Summary:** A client hit `SQLSTATE[42S22] Unknown column 'marc_leader' in 'field list'`
on a `library_item` insert after a bare `git pull`. Root cause: pulling code does
not touch the database. The `marc_leader` / `marc_005` / `marc_008` /
`frbr_override_type` columns are added by a Laravel migration
(`ahg-library/database/migrations/2026_06_17_000002_add_marc_fields_to_library_item.php`,
#1281), not by `install.sql`. New code inserted those columns; the un-migrated
client schema did not have them. The fix makes schema updates automatic on every
code update.

## Why it happened

- Heratio builds the bulk schema from `database/core/*.sql` + per-package
  `install.sql` files. Incremental column/table additions ship as Laravel
  migrations under `packages/*/database/migrations` and `database/migrations`.
- `install.sql` uses `CREATE TABLE IF NOT EXISTS`, so it never adds a new column
  to an existing table. Only `php artisan migrate` applies those deltas.
- `bin/install` had no `migrate` step, and a bare `git pull` runs nothing. So any
  install that updated via plain pull (rather than the deploy script) ran new
  code against a stale schema.
- `heratio-deploy.sh` already runs `migrate --force` after its `git pull`, so
  installs that deploy via the script were fine. Bare pulls were the gap.

## The fix (three parts)

1. **`bin/install` Stage 7b** runs `php artisan migrate --force` after the plugin
   schema pass, so a fresh install applies all package migrations. Non-fatal so
   one bad migration cannot abort the whole install.

2. **`hooks/post-merge`** runs `migrate --force` (then `optimize:clear`) after
   every `git pull` / `git merge`. This closes the bare-pull gap: any pull now
   syncs the schema. It prefers `sudo -u www-data` so migrate-created files are
   not left root-owned. Activated by `core.hooksPath=hooks`, which `bin/install`
   now sets (this also activates the existing locked-paths `pre-commit` hook).

3. **Idempotent framework migrations.** The four Laravel baseline migrations
   (`create_users_table`, `create_cache_table`, `create_jobs_table`,
   `create_cron_schedule_table`) did unguarded `Schema::create()` of tables that
   `00_core_schema.sql` also creates. On a fresh `migrate` over a core-built DB
   they would abort with "table already exists" and skip every later migration
   (including the additive ones). Each `Schema::create` is now wrapped in
   `if (! Schema::hasTable(...))`, so `migrate --force` is a clean no-op for
   tables the core schema already built and proceeds to the additive deltas.
   All other Heratio migrations were already `hasTable`/`hasColumn`-guarded.

## Operator playbook

- **Always deploy with `heratio-deploy.sh`** (pull -> composer -> npm build ->
  backup -> `migrate --force` -> `optimize:clear` -> reload). A bare `git pull`
  also skips `composer install` (class-not-found) and `npm run build` (stale
  assets), not just migrations.
- **If a client already pulled without migrating**, the safe one-liner (cannot
  collide, fully idempotent) is:
  `php artisan migrate --force --path=packages/ahg-library/database/migrations`
  Plain `php artisan migrate --force` also works once the framework migrations
  are guarded; before that change it could abort on `create_users_table` if the
  Laravel baseline was never recorded in the `migrations` table.
- **Verify a column exists:**
  `php artisan tinker --execute='echo \Schema::hasColumn("library_item","marc_leader")?"OK":"MISSING";'`

## Correction (2026-06-25): migrate runs at Stage 9b, not 7b

A clean `.60` install validation exposed two ordering/guard bugs in the first cut:

1. **Stage placement.** Running `migrate` at 7b (right after the plugin schema,
   *before* seeds + admin) breaks **data-seeding migrations**: e.g.
   `2026_05_12_000002_seed_acl_permissions` inserts `acl_permission` rows for ACL
   groups + the admin user that don't exist until Stage 8/9, so it fails. Laravel
   `migrate` aborts on the first failure, skipping every later migration
   (including `add_marc_fields` → `marc_leader`) — reproducing the original bug on
   a *fresh* install. Fix: the migrate stage moved to **9b, after Stage 8 seeds +
   Stage 9 admin**. Schema-additive migrations are order-independent and still
   apply there.

2. **Unguarded duplicate create.**
   `2026_05_30_000003_create_library_trading_partners_table` did an unguarded
   `Schema::create('library_trading_partner')`, but that table is also created by
   `database/core/00_core_schema.sql` (Stage 6) → `SQLSTATE[42S01] ... already
   exists (1050)`, aborting migrate again. Fix: `Schema::hasTable` guard so it
   defers to the core-schema table. (Audit confirmed it was the only colliding
   unguarded create; the migration name is plural `..._partners` but the table is
   singular `library_trading_partner`, which is why an earlier name-based grep
   missed it.)

Lesson reinforced: `migrate` aborts on the first failing migration, so a single
unguarded/duplicate create or out-of-order data migration silently drops the rest.
Keep every migration idempotent, and run migrate only once its data prerequisites
(seeds, admin) exist. Verified on `.60`: after both fixes, migrate runs clean
(0 pending) and `library_item` has `marc_leader/marc_005/marc_008`.

## Principle

Schema changes must travel with the code that needs them. Any column or table a
service writes to must be added by a migration (idempotent), and every path that
updates code (install, deploy, bare pull) must run `migrate`.
