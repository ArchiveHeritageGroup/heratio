# Heratio Overlay Install - How-To

This guide covers **Scenario 1: overlaying Heratio onto an existing AtoM database**. If you have no AtoM and want a clean Heratio install, see [`standalone-install-plan.md`](standalone-install-plan.md) instead.

The overlay approach **preserves every row** of your existing AtoM database. Heratio adds its own tables, adds missing columns to shared tables, and seeds its own settings + help - but never deletes, modifies, or drops anything that AtoM put there.

---

## When to use the overlay

- You already run AtoM on a customer site and want Heratio's UI / AI / RiC / DAM features without losing your catalogue.
- You're cutting a customer over from AtoM to Heratio and want both bootable side-by-side during testing.
- You need to upgrade a Heratio install that was originally created from an older AtoM cutover (re-running the overlay is safe).

---

## Prerequisites

| Requirement | Notes |
| --- | --- |
| Existing AtoM MySQL database | Must contain at minimum: `information_object`, `actor`, `term`, `taxonomy` |
| Reference Heratio MySQL database | A working Heratio install on the same MySQL server, used as the schema source. Default name: `heratio` |
| MySQL 8 | Heratio requires utf8mb4 collation |
| PHP 8.3+ | with PDO_mysql, mysqli |
| Heratio code checkout | `/path/to/heratio` (this directory) |
| `.env` configured | `DB_DATABASE` pointing at the **target** DB; `DB_PASSWORD` set |

---

## What gets copied

The overlay performs **eight stages**, in order:

| # | Stage | Effect on target | Idempotent? |
| --- | --- | --- | --- |
| 1 | Pre-flight | Read-only checks | yes |
| 2 | Schema overlay | `CREATE TABLE IF NOT EXISTS` for tables in reference but not target | yes |
| 3 | Column-delta sync | `ALTER TABLE ADD COLUMN` for columns in reference but not target | yes |
| 4 | Settings replicate | `INSERT IGNORE` into `ahg_settings` (preserves customer-set values) | yes |
| 5 | Help replicate | `INSERT IGNORE` into `help_article` + `help_section` | yes |
| 6 | Auto-seed boot | Laravel ServiceProviders run their per-package dropdown seeders | yes |
| 7 | ES reindex | Build / refresh `heratio_*` Elasticsearch indices | yes |
| 8 | Smoke test | Curl key URLs and report status codes | n/a |

Anything you've manually customised on the target - settings rows, theme colours, custom dropdowns, edited records - is **preserved** because every replicated INSERT uses `INSERT IGNORE` (unique-key conflicts no-op).

---

## Running the overlay

```bash
cd /path/to/heratio
./bin/install-overlay --target=<your_db_name>
```

**Default:** uses `heratio` as the reference DB. Override with `--reference=<other_db>` if you keep your reference under a different name.

The script prompts before applying. Pass `--yes` to skip the prompt (useful in CI / automation).

### Dry-run first

Always dry-run before applying:

```bash
./bin/install-overlay --target=customer_db --dry-run
```

This prints every table that would be added, every column that would be created, and every row-replicate that would happen - without mutating the target.

### Skip flags

Each stage can be skipped if you've already done it manually or want to defer it:

```bash
./bin/install-overlay --target=customer_db \
  --skip-schema      # tables already overlaid
  --skip-columns     # column sync already done
  --skip-help        # help articles already in place
  --skip-settings    # ahg_settings already seeded
  --skip-es          # ES reindex deferred
  --skip-smoke       # post-install HTTP test deferred
```

### Common invocations

| Goal | Command |
| --- | --- |
| First overlay onto a fresh AtoM clone | `./bin/install-overlay --target=customer_db` |
| Re-run after fixing a problem | `./bin/install-overlay --target=customer_db --yes` |
| Just sync new help articles + settings | `./bin/install-overlay --target=customer_db --skip-schema --skip-columns --skip-es --skip-smoke --yes` |
| Just refresh ES | `php artisan ahg:es-reindex` (no need for the wrapper) |

---

## Post-install verification

After the overlay reports `Done`, walk through:

1. **Log in** as the existing AtoM admin - credentials carry over (the overlay doesn't touch the `users` / `actor` tables).
2. **`/admin/ahgSettings/plugins`** - verify the plugin grid renders. Some `atom_plugin` rows may need to be inserted if they didn't exist on the AtoM side; see "Plugin row sync" below.
3. **`/admin/ahgSettings/themes`** - set the customer's brand palette and logo.
4. **`/admin/dropdowns`** - confirm dropdowns are seeded (~3,800 rows is typical).
5. **`/glam/browse`** - confirm records render with facets and counts.
6. **One IO show page** - confirm the right sidebar (Provenance, Condition, AI Tools, etc.) renders only the panels for plugins that are enabled.

---

## Plugin row sync

Heratio plugins are listed in the `atom_plugin` table. AtoM-derived databases generally lack rows for Heratio-specific plugins (e.g. `ahgFtpPlugin`, `ahgAiConditionPlugin`). If the plugin grid at `/admin/ahgSettings/plugins` is missing cards, sync from the reference:

```bash
mysqldump -u root --no-create-info --skip-extended-insert heratio atom_plugin \
  | sed 's/^INSERT INTO/INSERT IGNORE INTO/' \
  | mysql -u root <target_db>
```

This is `INSERT IGNORE`, so existing customer-enabled plugin rows are preserved.

---

## Storage path notes

If your existing AtoM uploads live at `/usr/share/nginx/<site>.atom-old/uploads/`, keep them in place and let the existing nginx alias chain serve them. No file move needed - Heratio's `digital_object` rows reference paths that already work.

For a fresh upload destination going forward, set:

```env
HERATIO_STORAGE_PATH=/mnt/nas/heratio/<site>
```

…in `.env` and create the directory tree (`uploads/`, `backups/`).

---

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| `Access denied for user 'root'@'localhost' (using password: NO)` during Stage 3 | PHP/PDO can't authenticate via socket | Set `DB_PASSWORD` in `.env`, or `export MYSQL_PWD=...` before running |
| `1452 Cannot add or update a child row` during Stage 2 | FK target column-type mismatch | The script wraps imports in `FOREIGN_KEY_CHECKS=0`. If you still see this, run `mysql --force` manually on the dump |
| `Empty filter facets at /glam/browse` | `display_facet_cache` table empty | Run `php artisan ahg:display-reindex` |
| `/admin/ahgSettings/plugins` shows 0 cards | `atom_plugin` rows not synced | See "Plugin row sync" above |
| `/help/article/<slug>` returns 404 | help_article rows not synced (Stage 5 was skipped) | Re-run `./bin/install-overlay --target=<db> --skip-schema --skip-columns --skip-es --skip-smoke --yes` |
| Smoke test reports `000` for all paths | nginx vhost not configured for the new install | Configure nginx separately; the overlay does not touch nginx |

---

## Roll-back

The overlay is non-destructive - there's nothing to "undo". To remove Heratio without affecting AtoM data:

1. Disable the Heratio nginx vhost (or change DNS back to AtoM).
2. (Optional) Drop the Heratio-only tables that the overlay added - list them with:
   ```sql
   SELECT table_name FROM information_schema.tables
    WHERE table_schema = '<target_db>'
      AND table_name LIKE 'ahg\\_%';
   ```
3. Heratio-added columns on shared tables (`information_object.heratio_*` etc.) can be left in place - they're nullable and ignored by AtoM.

---

## Related

- [`standalone-install-plan.md`](standalone-install-plan.md) - Scenario 2: clean install with no AtoM
- [`x7b-psis-install-sql-worklist.md`](x7b-psis-install-sql-worklist.md) - outstanding install.sql gaps per package
- `database/tools/overlay-schema.sh` - schema-overlay helper (called by `bin/install-overlay`)
- `database/tools/sync-columns.php` - column-delta helper (called by `bin/install-overlay`)
- `bin/install-overlay` - the orchestration script
