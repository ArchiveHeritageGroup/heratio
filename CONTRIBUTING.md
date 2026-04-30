# Contributing to Heratio

Thank you for your interest in contributing to **Heratio** — the operational GLAM, archival, DAM, and records management platform with RiC as a first-class capability.

This document describes the conventions a contributor needs to follow to land a PR. It is deliberately concrete: it documents how the project actually works today, not how a generic Laravel project might.

Heratio is licensed under **AGPL-3.0-or-later**. By submitting a contribution you agree your work will be released under the same licence.

---

## Project shape

- Laravel 12, PHP 8.3, MySQL 8.
- Monorepo of ~94 path-repository packages under `packages/ahg-*`. Each package is a self-contained Laravel package with its own `composer.json`, `ServiceProvider`, routes, views, and (where applicable) `database/install.sql` + `database/seed_dropdowns.sql`.
- Storage paths are centralised in `config/heratio.php` and driven by `.env` variables. **Never hardcode a path.**
- Heratio is built for the **international** GLAM market. Country-specific compliance (POPIA, GDPR, GRAP 103, NAZ, etc.) is implemented as **pluggable per-market modules**, never as core defaults.

A more complete tour of the architecture lives in [`README.md`](README.md) and [`CLAUDE.md`](CLAUDE.md).

---

## Getting set up

```bash
git clone https://github.com/ArchiveHeritageGroup/heratio.git
cd heratio
composer install
cp .env.example .env
php artisan key:generate

# Database
mysql -u root -e "CREATE DATABASE heratio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
for f in packages/*/database/install.sql; do mysql -u root heratio < "$f"; done
php artisan ahg:seed-research-dropdowns

# Search
php artisan ahg:es-reindex --drop
```

The `.env` keys you most often need to set:

| Key | Purpose |
| --- | --- |
| `HERATIO_STORAGE_PATH`, `HERATIO_UPLOADS_PATH`, `HERATIO_BACKUPS_PATH` | Local filesystem layout |
| `ELASTICSEARCH_HOST`, `ELASTICSEARCH_PREFIX` | Heratio's own ES indices (default prefix `heratio_`) |
| `HERATIO_FUSEKI_URL`, `HERATIO_FUSEKI_DATASET` | RiC-O graph store |
| `HERATIO_OLLAMA_URL` | AI / LLM endpoint |

---

## Branching & releases

- `main` is the default branch and is what `./bin/release` pushes to.
- Feature work happens on a topic branch off `main`. Open a PR back into `main`.
- **Every push that lands on `main` bumps a version.** Releases are cut by `./bin/release`:

  ```bash
  ./bin/release patch "Short description"
  ./bin/release minor "Phase 4: Admin & Settings" --issue 42
  ./bin/release major "Breaking: …"
  ```

  `./bin/release` updates `version.json`, tags, pushes, and creates a GitHub release. Do **not** edit `version.json` by hand.

- `bin/release` and `version.json` are not modified by ad-hoc edits — only by the release script itself.

---

## Coding standards

### File header

Every new PHP file (and every non-trivial new JS/Blade/CSS file) gets the AGPL header. Helper script: `bin/add-agpl-headers.sh`. The header credits Johan Pieterse / Plain Sailing iSystems and points to the AGPL.

### Database

- Heratio's database is `heratio`. The legacy AtoM database (`archive`) is read-only reference material for migration work — never write to it.
- AtoM/Qubit base tables (`information_object`, `actor`, `object`, …) are read-only. **No `ALTER`.** New columns and indices live on `ahg_*` sidecar tables. See [`docs/adr/0001-sidecar-pattern.md`](docs/adr/) for the patterns A/B/C and when each applies.
- Never use MySQL `ENUM` columns. All enumerated values come from `ahg_dropdown` via the Dropdown Manager (`/admin/dropdowns`). Use `VARCHAR(N)` and resolve labels at render time.
- The DB tables are the source of truth. Run `DESCRIBE table_name` before coding against a column.
- Do not run `INSERT` / `UPDATE` / `DELETE` / `ALTER` / `DROP` against a contributor's working DB without their explicit consent. `SELECT` is fine.

### Multilingual

- All user-facing strings are wrapped in `__('…')` and resolved against `lang/*.json`.
- DB queries against `*_i18n` tables use the `WithCultureFallback` trait (or its inline equivalent: `LEFT JOIN` current culture, `LEFT JOIN` fallback culture, `COALESCE` the result). **Never hardcode `'en'` as a culture filter.** Use `app()->getLocale()` for current culture and `config('app.fallback_locale', 'en')` for fallback — `'en'` is allowed only as the second argument to that `config()` call.
- Controlled vocabularies (ICIP, RiC-O, …) live in `data/vocabularies/*.ttl` and are resolved through `VocabularyResolverService` against the Fuseki store with a MySQL cache.

### Controllers & services

- Every controller method delegates DB work to an injected `Service` class. Don't grow fat controllers — that's a code-review blocker.
- Browse pages extend `AhgCore\Services\BrowseService`. Subclasses override `getTable()`, `getI18nTable()`, `getI18nNameColumn()`.
- The `/{slug}` catch-all in `ahg-information-object-manage` has a regex exclusion list. **When you add a new top-level route prefix, add it to that exclusion list** or your URL will silently route into the IO show page.

### Views

- Render real data from a service. Static HTML in a Blade is a smell.
- Use the central theme (`ahg-theme-b5`) and its colour tokens. No inline styles unless they're nonced (`csp_nonce()`).
- Inline `<script>` and `<style>` must carry the CSP nonce — the `InjectCspNonces` middleware handles this for most cases, but check the response headers if you see CSP violations.
- The clipboard is owned by `ahgThemeB5Plugin.bundle.js`. Don't load standalone `display-mode.js` or `voiceCommands.js` — they're already in the bundle and double-loading triggers `Identifier already declared` errors that take down all JS on the page.

### Migration (porting from AtoM)

If you're porting a feature from `/usr/share/nginx/archive`, copy the **complete** behaviour across in one pass — every field, every conditional. Compare AtoM source against the Heratio target field-by-field; if AtoM has it, Heratio gets it. The answer to "should I also do X?" is always yes.

### Branding

This project is **Heratio**. Don't reference "AtoM" in code, comments, descriptions, or user-facing text. The only acceptable references are inside technical docs that explicitly explain migration provenance.

---

## Tests, linting, and CI

```bash
# PHP syntax check (the CI also runs this)
find app packages -name "*.php" -not -path '*/vendor/*' -not -path '*/worktree/*' \
    -print0 | xargs -0 -n1 -P4 php -l > /dev/null

# Test suite
php artisan test
```

CI runs on every push and PR (see `.github/workflows/`). A red CI must be fixed, not bypassed — never push with `--no-verify` or commit hook-skipping flags.

---

## Submitting a change

1. Fork or branch from `main`.
2. Run `php artisan test` and the syntax check locally.
3. Open a PR. Describe the change in terms of *what behaviour is observable* before vs after, not *which files changed* — the diff already shows that.
4. Reference any relevant issue (`Closes #42`) and any ADR you touched.
5. A maintainer reviews. On merge, they cut a release with `./bin/release`.

For larger architectural changes — new top-level packages, schema rework, anything that crosses the AtoM-base / `ahg_*` sidecar boundary — open an ADR in `docs/adr/` first and link it from the PR.

---

## Reporting issues

- **Bugs:** [GitHub Issues](https://github.com/ArchiveHeritageGroup/heratio/issues) with reproduction steps, browser/PHP/MySQL versions, and any relevant log lines.
- **Security:** email **johan@theahg.co.za** directly, do not file a public issue.

---

## Code of conduct

Be kind, be specific, and assume the contributor on the other side of the review wants the project to succeed as much as you do. Disagreement about technical direction is welcome — disagreement framed as a personal attack is not.

---

## Licence

By contributing, you agree your work is licensed under [AGPL-3.0-or-later](https://www.gnu.org/licenses/agpl-3.0). The implication, in plain language: if you deploy a modified Heratio over the network, the source for the modified version must also be available under AGPL.
