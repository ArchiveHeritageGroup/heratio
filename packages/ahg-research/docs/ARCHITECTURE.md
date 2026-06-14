# ahg-research - Architecture

> For runtime operations - settings, AI usage/disclosure, testing, and common errors/fixes - see [`RUNBOOK.md`](RUNBOOK.md).

The research portal: researcher accounts, projects, collections/evidence sets,
bookings and reading-room services, bibliographies, notebooks, the "Research OS"
journey (epic #1222), and a set of AI-assisted research tools. It is a Laravel
package inside the Heratio monorepo and reuses the AtoM/Qubit base tables
(`object`, `actor`, `user`, `acl_user_group`, ...) for identity, layering its
own `research_*` sidecar tables on top.

## Directory layout

```
packages/ahg-research/
├── src/
│   ├── Controllers/        ResearchController (monolith) + delegators + slice controllers
│   ├── Services/           ResearchService + per-feature services (AI, ODRL, provisioner)
│   ├── Contracts/          UserProvisionerInterface
│   ├── Middleware/         OdrlPolicyMiddleware
│   ├── Console/Commands/   artisan commands (seeding, refresh jobs)
│   └── Providers/          AhgResearchServiceProvider (boot-time install + routes)
├── routes/web.php          + per-slice route files
├── resources/views/        Blade (research.* namespace)
├── database/               install_*.sql, seed_*.sql, migrations/ (Docker/migrate path only)
└── tests/Feature/          run against a pre-built heratio_test (see Testing)
```

## Controllers

`ResearchController` is the historical monolith (~5,800 LOC, ~128 public
methods) covering registration/profile, researchers admin, bookings/rooms/seats/
equipment, collections, saved searches, annotations, citations/bibliographies,
projects, collaboration, journal, reports, reproductions, notifications, studio,
ORCID, and exports. Newer controllers (`ResearchProjectsController`,
`ResearchWorkspaceController`, `ResearchAdminController`) currently **delegate**
back into it - routing has been re-pointed but most logic still lives in the
monolith. Decomposing it (moving logic into focused controllers, each with a
create -> list -> show smoke test) is tracked as ongoing refactor work.

Feature-slice controllers are already standalone: `ResearchCopilotController`,
`ResearchJournalController`, `ResearchLectureController`,
`ResearchTargetJournalController`, `ResearchTrainingController`, `AuditController`.

## Services

- **`ResearchService`** - the primary data-access service (researchers,
  collections, bookings, projects, ...). Controllers must go through it rather
  than querying directly.
- **`UserProvisionerInterface` / `EloquentUserProvisioner`** - the single place
  allowed to write the core auth tables (`object`/`actor`/`user`/`slug`/
  `acl_user_group`). Methods: `createUser`, `updateUser`, `addToGroup`,
  `deactivateUser`, `findByEmail`, `isInGroup`, `setPassword`. All registration,
  approve/suspend, and password-reset paths route through it so model events,
  the canonical password scheme (salt + sha1 + argon2), and ACL semantics stay
  consistent. NB: the `user` table has no `updated_at` column - never inject one.
- **AI feature services** - `WritingStudioService`, `QuestionBuilderService`,
  `AnalysisBridgeService`, `ContradictionEngineService`, `ReviewStudioService`,
  `GrantEngineService`, `SourceTriageService`, `ArgumentBuilderService`,
  `PublicationStudioService`, `ResearchCopilotService`, `ResearchStudioService`,
  `ReplicationPackService`. All AI calls must route through the AHG AI gateway
  (`AiServicesSettings` / `LlmService`), never a direct GPU-node port.
- **`AiDisclosureService`** - read-time aggregator that surfaces AI usage by
  scanning slice tables (review runs, source triage, contradictions, plus a
  manual log). Coverage is partial; new AI slices should register a detector or
  write a disclosure-log entry.

## Cross-cutting patterns

- **ODRL rights** - `OdrlService` evaluates `research_rights_policy`;
  `OdrlPolicyMiddleware` enforces `odrl:use`/`odrl:reproduce` on record view/
  print. Admins (ACL group 100, checked via `UserProvisioner::isInGroup`) bypass.
- **Research mode (experience level)** - `research_researcher.experience_level`
  (`beginning`/`intermediate`/`advanced`) curates the sidebar link set and drives
  the inline mode guide. Resolved in `_sidebar.blade.php` itself so it is correct
  on every page regardless of which controller rendered it; persisted via
  `ResearchWorkspaceController::saveExperienceLevel` and the profile form.
- **Dropdowns** - all enumerated values come from `ahg_dropdown` (no MySQL ENUM,
  no hardcoded `<option>` lists). Use `VARCHAR` columns.

## Data model & install

The package owns ~80 `research_*` sidecar tables. It does **not** rely on
`artisan migrate` in production: `AhgResearchServiceProvider::boot()` runs an
idempotent install at app boot - `Schema::hasTable()` guard + `DB::unprepared()`
of the matching `database/install_*.sql`, with dropdown seeds applied when
`ahg_dropdown` exists. Column additions to existing sidecar tables follow the
same boot-ensure pattern (`Schema::hasColumn` + `ALTER TABLE`). The
`database/migrations/` files exist only for the Docker/`migrate` path. Base
AtoM tables are never altered.

## Routing

`AhgResearchServiceProvider` registers `routes/web.php` (under the `web`
middleware) plus one route file per Research OS slice. When adding a top-level
URL, remember the locked `/{slug}` catch-all in `ahg-information-object-manage`
has a prefix exclusion list.

## Testing

The AtoM base tables (~995) come from `database/core/*.sql` dumps, **not** from
Laravel migrations, so `RefreshDatabase` cannot build a working schema (issue
#1136). Tests run against a **pre-built** `heratio_test` (clone of `archive` +
package `install.sql` applied at boot) and use **`DatabaseTransactions`** for
rollback. Build/refresh it with `bin/reset-test-db`, then:

```bash
sudo -u www-data vendor/bin/phpunit --filter ResearchUserProvisionerTest
```

Run phpunit as `www-data` so bootstrap logs are not created root-owned.
