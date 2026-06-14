# DOI Management — Research docs

This document summarises the current state, gaps, incomplete code, and recommended enhancements for DOI management inside the Research module and related platform services. Place this file under /usr/share/nginx/heratio/docs/research/ so operators and developers can review and act.

1) Quick summary

DOI (Digital Object Identifier) management covers minting DOIs for research outputs, tracking DOI metadata, resolving DOI lifecycle events (e.g. deprecation, update), and integrating with external registries (DataCite, Crossref). A complete DOI workflow includes: issuance (mint), metadata deposit, landing-page/manifest generation, updates/versions, and payout/reporting where needed.

2) What to look for in the codebase (where DOI work typically lives)

- Research output models / services (packages/ahg-research/src/Services/ or packages/ahg-research/src/Models) — which store output metadata and persistent identifiers.
- Export / repository integration (packages/ahg-repository-manage or packages/ahg-datacite if present) — code that deposits metadata to external registries.
- Routes and controllers exposing `export`, `publish`, `doi/mint`, `doi/status`.
- Job/queue workers that run background DOI deposit jobs (jobs/queues named `doi-deposit` or `export`), and scheduled retry logic.
- Tests around DOI deposit flows, and settings for DataCite/Crossref credentials in .env or settings package.

3) Gaps (what is commonly missing — check these)

- No DOI minting endpoints or UI actions for "Mint DOI" on an output page.
- Missing integration service for external registries (DataCite client adapter). No config / secret fields for DOI provider credentials exposed in settings.
- No background job or queue worker to perform DOI deposit (and retry on transient errors). Deposits being done synchronously in controllers is a risk.
- No metadata mapping: there may be no deterministic mapping from platform output model → DataCite XML / JSON (title, authors, publisher, publicationYear, resourceType, identifiers, relatedIdentifiers).
- No landing-page / canonical-URL guarantee for minted DOIs (the DOI must resolve to a stable landing page with metadata). The research output public URL must be stable and canonical.
- No test coverage for DOI deposit error handling, idempotence, and versioning semantics (e.g. DOI per version vs. DOI per record).
- No provenance or audit entry capturing the external DOI deposit (should be recorded in `ai_provenance` style table or a dedicated `external_deposit` log table).
- No admin UI showing DOI deposit status, deposit queue, and retry/error controls.

4) Incomplete code (where to inspect — concrete files to check)

- packages/ahg-research/src/Services/ResearchExportService.php — does it contain a `depositToDataCite()` or `mintDoi()` method? If not, the service is incomplete.
- packages/ahg-repository-manage/src/Services/RepositoryService.php — look for archive/repository deposit methods that could be extended for DOI deposit.
- packages/ahg-research/src/Controllers/ResearchOutputsController.php or `PublicationStudioController.php` — look for `publish` / `export` actions; they may reference `exportJob()` but not implement DOI deposit.
- jobs/ or app/Jobs — look for `DepositToRegistryJob`, `DoiDepositJob`, or `ExportToDataciteJob` — absent means async deposit is missing.
- settings: packages/ahg-settings or .env.example — check for DATACITE_BASE_URL, DATACITE_USERNAME, DATACITE_PASSWORD, DOI_PROVIDER envs.
- tests: packages/ahg-research/tests/Feature — check for `DoiDepositTest` or `ExportTest` — likely missing.

5) Enhancements and suggested architecture (detailed)

A. Design principles
- Async, idempotent deposits: DOI deposit must be queued (DepositToRegistryJob). Jobs must be idempotent — if a deposit succeeded previously the job should detect the existing DOI and reconcile metadata.
- Explicit operator control: UI must allow "Queue DOI mint" and show deposit status (queued, in-progress, succeeded, failed) and error details.
- Provenance and audit: record every deposit attempt to a dedicated table (external_deposits) storing provider, request payload, response, status, retry count, operator id.
- Landing page stability: ensure `output.public_url` is canonical and stable before minting. If content is expected to change, use versioning semantics (DOI per version or DataCite `relatedIdentifiers`).
- Credential isolation: store DOI provider credentials in settings and provide an admin page for test connection. Use the platform's secret management; do not store API keys in code.

B. Concrete components to implement
1) DOI Provider Adapter (ProviderPattern)
   - Interface: `DoiProviderInterface` with methods `mint(array $metadata): DoiResult`, `update(string $doi, array $metadata): DoiResult`, `status(string $doi): DoiStatus`.
   - Implementations: `DataCiteAdapter`, `CrossrefAdapter` (if required). Place under packages/ahg-datacite or packages/ahg-common/src/Services/DoiProviders.

2) Deposit Job
   - Job: `DepositToDoiProviderJob` accepts (output_id, provider, payload) and performs mint/update via DoiProviderInterface. Writes to `external_deposits` table.
   - Retries: exponential backoff for transient errors; permanent error classification for invalid metadata.

3) External Deposits table
   - Migration: `external_deposits` with columns {id, output_id, provider, request_payload(json), response_payload(json), status(enum queued|in_progress|success|failed), provider_identifier (doi), retries, created_by, created_at, updated_at }

4) UI / Controller
   - Output page: "Mint DOI" button (requires operator permission). POST /research/outputs/{id}/doi/mint queues the Deposit Job and shows queued state.
   - Admin queue: /admin/deposits lists deposit attempts, filter by provider/status, re-queue failed items.

5) Metadata mapping
   - Mapping service `DoiMetadataMapper` maps output model → DataCite schema (JSON or XML). Ensure contributors map to `creators` and affiliations as appropriate. Include relatedIdentifiers for versions and related outputs.

6) Versioning & DOI policy
   - Decide policy: DOI-per-record with versioned metadata, or DOI-per-version. Document default and provide UI to choose on mint.

7) Tests
   - Unit tests for mapper, provider adapter (mock HTTP client), Job tests (with fake queue), and feature tests for controller endpoints.

C. Operational considerations
- Dry-run mode: allow operator to run a dry-run deposit to validate metadata before minting.
- Monitoring & alerts: emit metrics for deposit latency & failure rates; send admin alerts on repeated failures.
- Idempotency keys: use an `external_deposits` marker so repeated job runs for same output/provider update existing DOI rather than mint new.

6) Example file list & migrations to add

- packages/ahg-datacite/src/Adapters/DataCiteAdapter.php (new)
- packages/ahg-research/src/Jobs/DepositToDoiProviderJob.php (new)
- packages/ahg-research/src/Services/DoiMetadataMapper.php (new)
- packages/ahg-research/src/Controllers/OutputDoiController.php (new endpoints mint/status/requeue)
- packages/ahg-research/database/migrations/xxxx_xx_xx_create_external_deposits_table.php (new migration)
- packages/ahg-research/resources/views/research/outputs/doi_status.blade.php (small partial)

7) Acceptance criteria (how to know this is done)
- Operator can click "Mint DOI" on an output page which queues an async job and returns a deposit record with queued status.
- Job interacts with a DoiProviderAdapter (mocked in test) to mint an identifier and stores provider identifier in output model and deposit table.
- Deposit attempts are logged in external_deposits table with status, request/response payloads, and operator id.
- Admin UI shows deposit history and allows re-queue of failed items.
- Tests cover mapping, adapter behaviour (mock HTTP), job success and retry logic, and endpoint permission checks.

8) Implementation plan (staged PRs)
- PR 1 (small): Add external_deposits migration + model + simple deposit table; add DoiMetadataMapper skeleton and unit tests for mapping minimal metadata. (1–2 days)
- PR 2 (medium): Add DepositToDoiProviderJob + DoiProviderInterface + DataCiteAdapter (mockable HTTP client) + job tests with fake queue (2–4 days)
- PR 3 (medium): Add OutputDoiController endpoints, route, and small UI button + partial for status; feature test to queue job (1–2 days)
- PR 4 (small): Admin deposits UI + requeue API + tests + docs (1–2 days)
- PR 5 (ops): Add admin settings for provider credentials + test connection and docs (0.5–1 day)

9) Next steps I can do now (pick one)
1. Create PR 1 patch (external_deposits migration + model + mapper skeleton + tests) and post /tmp/pr_doi_1.patch for your review.  
2. Create PR 2 patch (DoiProviderInterface + DataCiteAdapter skeleton + Deposit job skeleton) and post /tmp/pr_doi_2.patch.  
3. Insert a small help doc under docs/research/doi-management.md summarising this plan (I already wrote this doc).  
4. Do nothing — wait for your direction.

Status: very good
