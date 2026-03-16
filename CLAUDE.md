# CLAUDE.md - Heratio (Pure Laravel AtoM Replacement)

## Project Overview

**Heratio** is a pure Laravel 12 application that replaces AtoM's Symfony 1.4 frontend while connecting to the existing AtoM MySQL database (no schema changes). It is a monorepo with packages in `packages/`.

**Owner:** Johan Pieterse (johan@theahg.co.za)
**Organization:** The Archive and Heritage Group (Pty) Ltd
**GitHub:** https://github.com/ArchiveHeritageGroup/heratio

## Git & Commit Rules

- **NEVER execute git commit or git push directly.** Always supply the commands to the user and let them execute.
- **ALWAYS use `./bin/release` for pushes** — every push must bump a version (patch/minor/major).
- **Do NOT ask for permission to commit.** Just provide the commit/release command.
- Format: supply the user with the complete command(s) to run:
  ```bash
  cd /usr/share/nginx/heratio
  git add <files>
  ./bin/release patch "Short description of changes"
  ```
- For issue-linked releases: `./bin/release patch "Description" --issue 42`

## Server Environment

- **Server:** 192.168.0.112
- **PHP:** 8.3
- **Database:** MySQL 8 — database: `archive`, user: `root`, password: (none), socket auth
- **App path:** `/usr/share/nginx/heratio`
- **AtoM path:** `/usr/share/nginx/archive` (existing AtoM, read-only reference)
- **Uploads:** `/mnt/nas/heratio/archive`

## Architecture

```
/usr/share/nginx/heratio/          ← Laravel 12 app
├── app/                           ← Core app (auth, providers)
├── packages/                      ← Monorepo: each AHG plugin = Laravel package
│   ├── ahg-core/                  ← Models, base services, pagination, components
│   ├── ahg-theme-b5/             ← Layouts, nav, footer, static assets
│   ├── ahg-actor-manage/         ← Actor browse/show
│   ├── ahg-repository-manage/    ← Repository browse/show (ISDIAH)
│   ├── ahg-donor-manage/         ← Donor browse/show
│   ├── ahg-rights-holder-manage/ ← Rights holder browse/show
│   ├── ahg-accession-manage/     ← Accession browse/show
│   ├── ahg-storage-manage/       ← Physical object browse/show
│   ├── ahg-term-taxonomy/        ← Term + taxonomy browse/show
│   ├── ahg-function-manage/      ← Function browse/show (ISDF)
│   ├── ahg-information-object-manage/ ← IO browse/show (ISAD)
│   ├── ahg-user-manage/          ← User browse/show (admin)
│   ├── ahg-settings/             ← Settings dashboard + sections (admin)
│   ├── ahg-jobs-manage/          ← Jobs browse/show (admin)
│   └── ahg-iiif-collection/     ← IIIF Collection management (browse/view/edit/IIIF JSON)
├── bin/release                    ← Version bump + git tag + push + GH release
├── version.json                   ← Current version
└── composer.json                  ← Requires all packages via path repositories
```

## Database Rules

- **NEVER modify the AtoM database schema.** Read/write existing tables only.
- **NEVER execute INSERT, UPDATE, DELETE, ALTER, DROP** without asking the user first.
- **Read-only queries (SELECT) are permitted** for investigation.
- **The DB tables are the source of truth.** If a column exists in the table, use it in code. If it doesn't exist, don't invent it. Always `DESCRIBE table_name` to verify before coding.
- **ALL fields that exist in AtoM base DB tables MUST be used in Heratio.** No exclusion of any fields. If a field is in the AtoM database table and is used by base AtoM, it must appear in the corresponding Heratio controller, service, and view. Same data in AtoM = same data in Heratio. Zero omissions.

## Key Technical Patterns

### Class Table Inheritance
AtoM uses class table inheritance. Entity hierarchy: Object → Actor → User/Donor/Repository/RightsHolder.
- `actor_i18n` has: authorized_form_of_name, history, mandates, etc.
- Entity-specific `*_i18n` tables have entity-specific fields only
- Donor and RightsHolder tables have ONLY `id` — all data in `actor_i18n`
- Controllers must join BOTH `actor_i18n` and entity-specific i18n tables

### Relation Table
AtoM uses a generic `relation` table (subject_id, object_id, type_id) for many-to-many links instead of direct foreign keys. Example: donor→accession via relation, NOT accession.donor_id.

### Publication Status
NOT in `information_object` table. Stored in `status` table (type_id=158).

### Package Pattern
Each package has: composer.json, ServiceProvider (loads routes + views), Controllers, Services (if needed), routes/web.php, resources/views/.

### BrowseService Pattern
`AhgCore\Services\BrowseService` is the base for all browse pages. Subclasses override `getTable()`, `getI18nTable()`, `getI18nNameColumn()`.

## Reserved Words
MySQL 8 reserved words that need backtick escaping in aliases: `groups`, `rank`, `row_number`, `function`.

## Files That Must Not Be Modified Without Approval
```
bin/release
version.json (modified by bin/release only)
```

## No ENUM Columns

**NEVER use ENUM columns in database tables.** All enumerated values are managed via the Dropdown Manager. Use `VARCHAR(N)` with a COMMENT listing valid values if needed. All existing ENUMs must be migrated to Dropdown Manager entries.

## Branding

This is **Heratio** — not AtoM. Do NOT reference "AtoM" in code, comments, descriptions, or user-facing text. The only acceptable references to AtoM are in technical documentation explaining the migration source (e.g., CLAUDE.md, migration notes).

## Migration Rules (CRITICAL)

### Always Check AtoM First
**ALWAYS first check if code exists in `/usr/share/nginx/archive` before writing new code.** Search the AtoM codebase (plugins, framework, base) for existing templates, controllers, actions, and services. Reuse and convert — do not reinvent.

### Migrate = Copy, Not Recreate
**Migration means copying the COMPLETE file from `archive/` to `heratio/` and converting its syntax (PHP/Symfony → Blade/Laravel).** Do NOT recreate files from scratch. The AtoM source file is the blueprint — every field, every section, every conditional must be preserved. Convert syntax only; do not redesign, restructure, or omit anything.

### Compare Fields Before and After
**Before creating or updating any Heratio view/form, compare ALL fields between the AtoM source file and the Heratio target file.** List every field/section in both and confirm parity. If any field exists in AtoM but is missing in Heratio, it must be added. Zero omissions.

### New File Creation Requires User Confirmation
**ALL new file creation must be confirmed by the user first.** If the file is a migration/copy from an existing AtoM file, proceed without confirmation. If the file is genuinely NEW (no AtoM equivalent exists), ask the user before creating it. Describe what the new file does and why it is needed.

### "Clone/Match/Migrate" = Do Everything in One Pass
**When instructed to clone, match, or migrate a feature or set of pages, do ALL of it in one pass.** Find every AtoM source file (templates, actions, configs, partials), copy them completely into Heratio equivalents, and convert Symfony → Laravel/Blade syntax — all in a single operation. Do NOT split into smaller pieces, do NOT ask "should I also do X?", and do NOT second-guess scope. If AtoM has it, Heratio gets it. The answer to "should I also do X?" is always **yes** if it exists in AtoM. Treat "clone the functionality" identically to doing it page-by-page — just for all pages at once.

## Quality Standard

Every page must be identical to what AtoM/Symfony delivers. Full screens, full theme, full menus, full metadata, full digital objects. If a user can tell they're on Heratio vs Symfony, it is not ready.

Use ahg-information-object-manage/ActorController.php as the structural pattern. Follow the same service injection, validation, flash messages, and redirect pattern. Do not simplify.

- Never use placeholder comments like "// TODO: implement"
- Never return empty collections or hardcoded dummy data
- Every controller method must query the database via the injected Service class
- Every form must have full validation rules matching AtoM field requirements
- Views must render real Eloquent data, not static HTML

The controller must only call service methods. Write the ActorService class with create(), update(), delete() methods. The service must handle both the actors table and the actor_i18n table in a single transaction.

After writing the code, list every database column you wrote to and confirm it matches the AtoM schema. Flag anything you assumed or left incomplete.

look at /usr/share/nginx/archive/atom-* packages where we developed Symfony/Laravel code. This code field/functionallity is exactly what we need in Heratio but only in Laravel.

