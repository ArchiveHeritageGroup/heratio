# Library — Condition, gaps, and roadmap for the Research module

This document summarises what the codebase currently provides for "Library"-style features within the Research module, identifies gaps and incomplete code, and proposes concrete enhancements and an implementation roadmap. It is written to be actionable: every gap cites file locations (where present) and every enhancement includes an acceptance criterion.

1. First look — gaps (what is missing now)

- No dedicated library "holdings" integration in Research
  - Evidence: research controllers and services (packages/ahg-research/src/Controllers and src/Services) reference bibliography, sources and accession links but there is no explicit Research ↔ Library holdings API connector (no `ahg-library` bridge usage in research services). Search: no `LibraryService` usage in packages/ahg-research.
  - Impact: researchers cannot reliably link project sources to library copies/holdings; provenance and duplication checks are manual.

- Limited discovery/lookup for library holdings when ingesting sources
  - Evidence: file upload handlers and `research_sources` ingestion paths (packages/ahg-research/src/Controllers/ResearchController.php and ResearchService) accept uploads but do not query `ahg-library` or `ahg-repository-manage` for matching holdings (no search call to `LibraryCatalogService`).
  - Impact: duplicate records, missed canonical identifiers (shelfmark/holding URIs).

- No circulation / availability status surfaced in Research UI
  - Evidence: views for sources/bibliography (packages/ahg-research/resources/views/research/) do not show library availability fields (no `holding_status` / `availability` columns displayed). There is no API call to fetch current availability.
  - Impact: researcher planning (e.g. where to obtain an original) is harder.

- No automated citation-to-holding resolution (ISBN/ISSN/DOI → holding)
  - Evidence: Bibliography import (BibTeX/RIS) is supported but no resolver is wired to call discovery services (Crossref/OpenLibrary/Local library index) to populate holdings or availability.

- Incomplete export connectors for library metadata
  - Evidence: exports (BibliographyService / CitationExportService) can produce CSL-JSON/BibTeX, but there is no canonical MARC/holdings export adapter for library ingest or push to `ahg-library`.

2. Incomplete or fragile code (concrete file locations)

- ResearchController god-method areas
  - File: packages/ahg-research/src/Controllers/ResearchController.php
  - Issue: large controller with inline ingestion logic; code paths that handle `source` uploads call file storage and metadata extraction inline. These should delegate to a `ResearchSourceImportService` that orchestrates library lookups and holding resolution.

- Bibliography enrichment lacks resolver wiring
  - Files: packages/ahg-research/src/Services/CitationExportService.php and packages/ahg-research/src/Services/BibliographyService.php
  - Issue: metadata enrichment stubs reference external APIs in comments but no concrete resolver class or strategy pattern to plug Crossref / OpenLibrary / LibraryCatalog resolvers.

- Missing Library bridge service
  - Expected: packages/ahg-research/src/Services/LibraryBridgeService.php (not present). A bridge service would encapsulate calls to `ahg-library` APIs (holdings search, availability, request/create hold).

- Views missing availability/holding display
  - Files: packages/ahg-research/resources/views/research/sources.blade.php and bibliography views. No markup exists to display `holding_uri`, `holding_status`, `shelfmark` or `call_number` fields.

- Tests not covering library interactions
  - Files: packages/ahg-research/tests/Feature — no tests mock outgoing library queries or assert that lookups happen during import.

3. Enhancements and suggestions (with acceptance criteria)

- Enhancement: Add a LibraryBridgeService
  - What: new service `packages/ahg-research/src/Services/LibraryBridgeService.php` that exposes:
    - searchHoldings(string $query): array (return id, label, shelfmark, availability)
    - resolveHoldingByIdentifier(string $identifier): ?array
    - requestCopy($holdingId, $userId): bool (optional)
  - Acceptance: unit tests mock `ahg-library` responses and verify the bridge returns normalized holding objects; the ResearchSourceImportService uses the bridge to attach `holding_id` to imported sources.

- Enhancement: Implement Source Import pipeline
  - What: refactor source upload/import to use `ResearchSourceImportService` (or `ResearchSourcePipeline`) that:
    - extracts metadata (Tika/EXIF/OCR or embedded PDF metadata)
    - normalises identifiers (ISBN/DOI/ARK)
    - calls LibraryBridgeService::resolveHoldingByIdentifier or searchHoldings
    - stores canonical holding reference on `research_sources` (columns: `holding_uri`, `holding_label`, `holding_status`, `holding_id`)
  - Acceptance: integration test that uploads a sample PDF, pipeline attaches holding via mocked LibraryBridgeService, DB row contains holding_uri and holding_status.

- Enhancement: UI — show holdings & availability
  - What: update source and bibliography views to show holding details and a CTA to "Request copy" or "Locate in library".
  - Acceptance: views display `holding_label`, `shelfmark`, and `availability` when available; click CTA calls route that triggers `LibraryBridgeService::requestCopy`.

- Enhancement: Citation enrichment + resolver plugins
  - What: add a `CitationResolverInterface` + plugin registry to call Crossref / OpenLibrary / LocalCatalog sequentially until metadata found.
  - Acceptance: import of BibTeX with DOI triggers Crossref resolver and populates missing fields; unit tests simulate resolver responses.

- Enhancement: Export adapter for library ingest
  - What: add an exporter that emits MARCXML or a library-specific ingest format so curated bibliographies can be pushed to `ahg-library`.
  - Acceptance: `php artisan research:export-holdings --project=12 --format=marcxml` produces valid MARCXML file containing holdings found on project sources.

- Enhancement: Provenance for holding attachment
  - What: when ResearchSourceImportService attaches a holding, record provenance (agent who resolved, resolver used, timestamp) in `research_activity_log` or in a new `research_holdings_provenance` table.
  - Acceptance: sample import shows provenance rows with resolver name and confidence score.

4. Actionable implementation plan (staged, low-risk PRs)

PR 1 — LibraryBridgeService + interface (safe)
- Files to add:
  - packages/ahg-research/src/Contracts/LibraryBridgeInterface.php
  - packages/ahg-research/src/Services/LibraryBridgeService.php (default implementation delegates to `ahg-library` HTTP endpoints or falls back to a mocked local index)
  - tests/Unit/LibraryBridgeServiceTest.php
- Effort: 1–2 days

PR 2 — Source Import pipeline refactor
- Files to modify/add:
  - packages/ahg-research/src/Services/ResearchSourceImportService.php
  - refactor ResearchController upload handlers to call the new service
  - tests/Feature/ResearchSourceImportTest.php (mock LibraryBridge)
- Effort: 2–3 days

PR 3 — UI updates (holding display + request action)
- Files to modify:
  - packages/ahg-research/resources/views/research/sources.blade.php
  - packages/ahg-research/resources/views/research/bibliography.blade.php
  - add route + controller action `research.holdings.request` that proxies to `LibraryBridgeService::requestCopy`
  - tests/Feature/ResearchSourceViewTest.php
- Effort: 1–2 days

PR 4 — Citation resolvers & enrichment
- Files to add/modify:
  - packages/ahg-research/src/Services/CitationResolvers/CrossrefResolver.php
  - packages/ahg-research/src/Services/CitationResolvers/OpenLibraryResolver.php
  - registry glue in BibliographyService
  - tests/mock resolver responses and end-to-end import test
- Effort: 2–4 days

PR 5 — Export adapter (MARCXML)
- Files to add:
  - packages/ahg-research/src/Services/Export/MarCAdapter.php
  - console command `php artisan research:export-holdings` with tests
- Effort: 2–3 days

5. Tests & acceptance
- Unit tests for bridge, resolver plugins and pipeline.
- Feature tests for import flow (upload → resolve → attach holding) with mocked bridge.
- Lint all new PHP files with `php -l` before committing.

6. Notes and cautions
- Keep the LibraryBridgeInterface small and stable; prefer to add features behind the interface to avoid cross-package coupling.
- Respect rate limits on external resolvers (Crossref/OpenLibrary) and add caching for resolver results in Redis or DB.
- Ensure provenance entries are written to `research_activity_log` or a new provenance table so the holding resolution is auditable.

---

Status: very good

Next action — outstanding issue to work on
1. Implement PR1 (LibraryBridgeService + interface) and post the unified diff for review.  
2. Implement PR2 (ResearchSourceImportService) and post the patch for review.  
3. Add UI holding display + request route (small PR).  
4. Run a grep to find any existing `Library` references and list where to integrate the bridge (quick audit).
