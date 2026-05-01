# AtoM Upgrade Checklist

Heratio is a standalone Laravel 12 application but ships with tooling that
imports content from AtoM - XLIFF translations, schema patterns, taxonomy
seeds, partial templates. When Heratio is installed onto a host that already
runs AtoM (or when AtoM is later upgraded under a host running Heratio), the
imported artefacts may drift from the AtoM source. This document is the
review/run checklist to keep Heratio in sync without losing local
customisations.

Owner: johan@theahg.co.za
Companion issues: #34 (multi-language platform), #35 (translation service)

---

## When to run this checklist

- Fresh Heratio install on a host with an existing AtoM (any version)
- Upgrading the underlying AtoM (e.g. 2.10 → 2.12)
- After applying an AtoM plugin update that ships new translations or schema
- Quarterly drift sweep, even if no upgrade has happened

---

## 1. Pre-flight inventory

```bash
# Find every AtoM install on the host
find / -maxdepth 4 -name 'symfony' -type f 2>/dev/null | head

# Identify the AtoM version at the canonical path
grep "'sf_app_version'" /usr/share/nginx/archive/apps/qubit/config/app.yml.tmpl
cat /usr/share/nginx/archive/VERSION 2>/dev/null

# Inventory plugin XLIFFs (these are NOT in apps/qubit/i18n)
find /usr/share/nginx/archive/plugins -path '*/i18n/*/messages.xml' 2>/dev/null
find /usr/share/nginx/archive/atom-ahg-plugins -path '*/i18n/*/messages.xml' 2>/dev/null
```

Record the AtoM version and plugin set in the upgrade ticket. The
`lang/_meta.json` provenance file (added by deliverable #4 below) tracks
which AtoM version each translation was last imported from.

---

## 2. UI string translations - XLIFF re-import

The bulk of the multi-language platform is auto-managed by
`php artisan ahg:translation-import-xliff`. The command is **idempotent and
safe to re-run** at any time.

### Standard upgrade run

```bash
# Dry-run: see what would change without touching disk
php artisan ahg:translation-import-xliff --diff

# Apply: prefer the new AtoM strings, prune orphaned keys, include plugin XLIFFs
php artisan ahg:translation-import-xliff \
    --mode=prefer-source \
    --prune \
    --source-extra=/usr/share/nginx/archive/plugins \
    --source-extra=/usr/share/nginx/archive/atom-ahg-plugins

# Bust the cache so __() picks up the new strings
php artisan view:clear

# Coverage report - per-locale % of keys translated
php artisan ahg:translation-coverage
```

### Modes (passed to `--mode`)

| Mode | Behaviour | When to use |
|---|---|---|
| `merge` (default) | Existing JSON values win over XLIFF source | Day-to-day, protect manual translations |
| `prefer-source` | XLIFF source wins UNLESS `lang/_meta.json` flags the entry as `hand_edited: true` | After AtoM upgrade - AtoM's improved wording trickles in, your customisations stay |
| `overwrite` | XLIFF source ALWAYS wins (full replace) | Initial install, or recovery from corrupted JSON |

### Locale auto-discovery

`scandir($sourceDir)` runs every time, so any new locale directory in
`apps/qubit/i18n/` (e.g. AtoM 2.12 might add `chr` Cherokee or `yo` Yoruba)
becomes a `lang/{code}.json` automatically. The locale won't appear in the
end-user dropdown until added to `setting.i18n_languages`:

```sql
INSERT INTO setting (scope, name, editable) VALUES ('i18n_languages', 'chr', 1);
```

Or use `--auto-enable-threshold=70` on the import command - auto-inserts the
setting row when a locale reaches ≥70% coverage.

---

## 3. Per-record `*_i18n` schema drift

This is **not** auto-managed. AtoM occasionally adds columns to `*_i18n`
tables (e.g. AtoM 2.7 added `culture_label` to `term_i18n`). Heratio's services
SELECT explicit columns, so a new AtoM column is invisible to Heratio until
someone adds it to the entity service's SELECT list.

Per upgrade, walk this matrix:

| `*_i18n` table | Heratio service that SELECTs from it | Review |
|---|---|---|
| `information_object_i18n` | `InformationObjectService::getBySlug`, `MuseumService::getBySlug`, `LibraryService::getById`, `GalleryService::getBySlug`, `DamService::getById` | Diff `DESCRIBE information_object_i18n` against the SELECT list; add new columns as `COALESCE(ioi_cur.col, ioi_fb.col) AS col` |
| `actor_i18n` | `ActorService::getById`, `RepositoryService::getById` (repositories are actors) | Diff `DESCRIBE actor_i18n` |
| `repository_i18n` | `RepositoryService::getById` | Diff `DESCRIBE repository_i18n` |
| `term_i18n` | Inline queries in BrowseService and friends | Diff `DESCRIBE term_i18n` |
| `event_i18n` | Inline in IO show date rendering | Diff `DESCRIBE event_i18n` |
| `function_object_i18n` | `FunctionService` | Diff |

### Drift detection helper

```bash
# Generate a column-by-column diff between the running Heratio DB and an AtoM 2.12 schema dump
mysql -u root heratio -e "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='heratio' AND TABLE_NAME LIKE '%_i18n' ORDER BY TABLE_NAME, ORDINAL_POSITION" > /tmp/heratio-i18n.txt
mysql -u root archive -e "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='archive' AND TABLE_NAME LIKE '%_i18n' ORDER BY TABLE_NAME, ORDINAL_POSITION" > /tmp/atom-i18n.txt
diff -u /tmp/heratio-i18n.txt /tmp/atom-i18n.txt
```

Any `+` line in the diff is a new AtoM column Heratio hasn't picked up yet.
Decision per column:

- **Add to Heratio?** Update the entity service's SELECT list, add a fallback
  COALESCE pair, expose in the show view if relevant.
- **Skip?** Log it in `docs/atom-divergence.md` so the decision is durable.

---

## 4. Plugin XLIFF imports

AtoM plugins (`/plugins/*/i18n/*`, `/atom-ahg-plugins/*/i18n/*`) ship their
own translations. The `--source-extra` flag stacks these on top of the core
XLIFFs. **Plugin translations override core when they collide** - same
behaviour as AtoM's runtime.

```bash
# Discover everything
find /usr/share/nginx/archive -path '*/i18n/*/messages.xml' \
  | sed -E 's|/i18n/[^/]+/messages.xml||' \
  | sort -u
```

Add each unique base path to a `--source-extra` flag.

---

## 5. Heratio-only translation keys

Of Heratio's ~3290 `__()` keys, ~2000 don't exist in any AtoM XLIFF (RiC
Explorer, AI Tools, Privacy Dashboard, Spectrum, Heritage Accounting, etc.).
These are unaffected by an AtoM upgrade - they need their own translation
sourcing path:

- Manual authorship in `lang/{locale}.json`
- The MT workflow in issue #35 (admin translates each key via the modal)
- A vendor pack (commission a translator for a specific locale)
- DeepL/Argos batch-run via `ahg:translation-mt-batch` (future deliverable)

Track per-locale coverage of Heratio-only keys with
`ahg:translation-coverage --heratio-only`.

---

## 6. Hand-edited translation preservation

Translations a Heratio admin authored manually (via the issue #35 modal,
`POST /admin/translation/save`) are flagged in `lang/_meta.json`:

```json
{
  "af": {
    "Edit": { "hand_edited": true, "source_atom_version": null, "imported_at": null }
  }
}
```

When `--mode=prefer-source` runs after an AtoM upgrade, entries flagged
`hand_edited: true` are NOT overwritten. They survive every upgrade until an
admin explicitly clears the flag.

To force-replace a hand-edited entry:

```bash
php artisan ahg:translation-import-xliff --mode=overwrite --locale=af --keys="Edit"
```

---

## 7. Settings table sync

`setting.i18n_languages` rows control which locales appear in the end-user
language dropdown. After an upgrade:

```sql
-- Show enabled locales
SELECT name FROM setting WHERE scope='i18n_languages' AND editable=1;

-- Enable a new locale (after verifying its coverage is acceptable)
INSERT INTO setting (scope, name, editable) VALUES ('i18n_languages', 'chr', 1)
  ON DUPLICATE KEY UPDATE editable=1;

-- Disable a locale that's now too out of date
UPDATE setting SET editable=0 WHERE scope='i18n_languages' AND name='ur';
```

---

## 8. Post-upgrade verification

```bash
# Every entity show page returns 200 in EN and AF (baseline cultures)
for url in /understream-figure /actor/historical /repository/d6mh-ktzy-h6qz \
           /library/wildlife-photos /gallery/fragments-of-a-silent-city \
           /dam/title-of-object /museum/duck-duck-go; do
  for c in en af; do
    code=$(curl -sk -o /dev/null -w "%{http_code}" "https://heratio.theahg.co.za${url}?sf_culture=${c}")
    echo "  ${c}  ${url}  HTTP ${code}"
  done
done

# No new ERROR lines in the last 100 log entries
tail -100 storage/logs/laravel.log | grep -i ERROR | grep -v "ActorBrowseService"

# Translation coverage above the configured threshold
php artisan ahg:translation-coverage --fail-below=50
```

If any of these fail, **revert the import** rather than band-aiding:

```bash
# Last-good lang/*.json files are in the previous git commit
git checkout HEAD~1 -- lang/
php artisan view:clear
```

---

## 9. Per-version notes

Track AtoM version specifics here as upgrades happen.

### AtoM 2.7 → 2.8

- (placeholder)

### AtoM 2.8 → 2.9

- (placeholder)

### AtoM 2.10 → 2.11

- (placeholder)

### AtoM 2.11 → 2.12

- (placeholder - fill in when 2.12 ships)

---

## Related documents

- `CLAUDE.md` - Fresh Install Procedure (now references this checklist)
- Issue #34 - Multi-language platform (audit + status)
- Issue #35 - Translation service (MT workflow + `ahg_translation_*` tables)
- Heratio source-of-truth XLIFFs: `apps/qubit/i18n/{locale}/messages.xml` in
  the AtoM install referenced by `--source` (default
  `/usr/share/nginx/archive/apps/qubit/i18n`).
