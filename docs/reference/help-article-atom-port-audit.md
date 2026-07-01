# Help-article AtoM-port audit (worklist)

Audit date: 30 June 2026. Read-only sweep of `docs/help/*.md` (474 articles) for
unconverted AtoM/Symfony content carried over from the `/usr/share/nginx/archive`
plugins. The problem: ~100 articles still reference commands, paths, databases, and
URLs that do not exist in Laravel Heratio - most damagingly `php symfony ...` CLI
commands that an operator will try and fail to run.

Fix pattern is the **safe trim** established on `integrity-assurance-user-guide.md`
(released v1.154.193): excise AtoM scaffolding, de-"plugin" the prose, point at the
real Heratio UI (`/admin/...`) and the *Content Authenticity in Heratio* reference,
and do NOT fabricate Heratio commands that have not been verified against code.

## Marker set used
`php symfony`, `bin/atom`, `atom-ahg-plugins`, `atom-framework`, `-u root archive`,
`ahg<Name>Plugin`, `symfony cc`, `psis.theahg`, `swiftmailer`, `apps/qubit`,
`cache/integrity_locks`, `/usr/share/nginx/archive`.

## Bucket 1 - BROKEN operator guides (fix these; ranked by fake `php symfony` count)

The strongest signal of a mis-ported operational guide is `php symfony`, since that
binary does not exist in Heratio. 46 articles contain it.

| # | Article | php symfony hits | Status |
|---|---|---|---|
| 1 | preservation-user-guide.md | 60 | DONE (v1.154.194) |
| 2 | ai-tools-user-guide.md | 55 | DONE (v1.154.195) |
| 3 | data-migration-user-guide.md | 47 | DONE (v1.154.201) |
| 4 | functions.md | 40 | todo |
| 5 | ner-user-guide.md | 18 | todo |
| 6 | duplicate-detection-user-guide.md | 18 | todo |
| 7 | statistics-user-guide.md | 16 | todo |
| 8 | privacy-user-guide.md | 15 | todo |
| 9 | portable-export-user-guide.md | 14 | todo |
| 10 | ner-knowledge-graph-technical.md | 14 | todo |
| 11 | heritage-accounting-user-guide.md | 14 | todo |
| 12 | doi-user-guide.md | 14 | todo |
| 13 | metadata-export-user-guide.md | 13 | todo |
| 14 | extended-rights-user-guide.md | 13 | todo |
| 15 | data-migration-user-manual.md | 13 | todo |
| 16 | accession-v2-user-guide.md | 13 | todo |
| 17 | user-manual.md | 12 | todo |
| 18 | atom-export-ead-language-guide.md | 11 | todo |
| 19 | forms-builder-user-guide.md | 9 | todo |
| 20 | workflow-user-guide.md | 8 | todo |
| 21 | sharepoint-user-guide.md | 8 | todo |
| 22 | ai-ops-runbook.md | 8 | todo |
| 23 | privacy-compliance-user-guide.md | 6 | todo |
| 24 | knowledge-graph-user-guide.md | 3 | todo |
| 25 | api-reporting-export.md | 3 | todo |
| 26 | discovery-user-guide.md | 2 | todo |
| 27 | fuzzy-search-technical.md | 2 | todo |
| 28-46 | (1 hit each) workflows, security-user-manual, csv-digital-object-import-guide, custom-fields/customfields, versioncontrol-user-manual, timelimitedsharelink (x2), library-system (x2), modified-files, etc. | 1 | todo |

Note: `technical-documentation.md`, `daya-magration-technical-documentation.md`,
`issue-198-implementation-plan.md`, and the `atom-heratio-*-plan` / security-audit
docs also contain `php symfony` but are part-Bucket-2 (planning/migration context) -
judge per file whether the command is instructional (fix) or historical (leave).

## Bucket 2 - LEGITIMATE AtoM context (do NOT blanket-edit)

Migration, architecture, and planning docs where referencing AtoM is correct because
they describe the AtoM -> Heratio relationship. Editing these removes accurate context:
`heratio-migration.md`, `standalone-install-plan.md`, `architecture.md`,
`plugin-architecture.md`, `roadmap.md`, `atom-ahg-framework-library-*`,
`atom-heratio-*-aligned-plan.md`, `atom-heratio-security-*`, `data-migration-exports.md`.

## Bucket 3 - FALSE POSITIVES (no action)

High marker counts driven by legit `ahg<Name>Plugin` class names or schema text, not
operator instructions: `database-erd.md` (83), `database-views.md`, and similar.

## Done

- integrity-assurance-user-guide.md - safe-trimmed, re-ingested (help #168), v1.154.193.
- preservation-user-guide.md - safe-trimmed, re-ingested (help #56), v1.154.194.
- ai-tools-user-guide.md - safe-trimmed, re-ingested (help #5), v1.154.195.
- data-migration-user-guide.md - safe-trimmed, re-ingested (help #18), v1.154.201. Real: sector:*-csv-import (kept), ahg:csv-import; Preservica + batch export are UI-only; queue:work not Gearman.
