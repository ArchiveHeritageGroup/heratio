# Research Reports — Gaps, Incomplete Code, and Enhancements

This document summarizes the current state of the Research reports subsystem (reports, report templates, PDF export) and proposes concrete, repository-grounded improvements. It is based on studying the Research package (reports views, ReportService, report templates, report PDF rendering) and the surrounding infrastructure.

1) First look — where things stand (facts)

- The Research package defines report tables and template infrastructure in database/install.sql (`research_report`, `research_report_section`, `research_report_template`).
- There is a ReportService (packages/ahg-research/src/Services/ReportService.php) that implements report CRUD, section management, template-based creation, and HTML rendering (renderReportHtml()).
- Views and routes exist for creating and viewing reports: `packages/ahg-research/resources/views/research/reports.blade.php`, `view-report.blade.php`, `new-report.blade.php`, and `report-pdf.blade.php`.
- Report templates are editable via `reportTemplates` actions in ResearchController; `research_report_template` table persists templates. The document-template rendering path supports `research_report` and `condition_report` template types.
- The code supports exporting a report to PDF via an `export=pdf` query parameter handled in the view controller.

2) Gaps (concrete, repo-evidence-backed)

- Missing automated tests for reports and template rendering
  - Evidence: packages/ahg-research/tests do not include focused tests for ReportService.create/get/render flows.
  - Impact: template regressions or rendering issues can be introduced without detection.

- Report templating is free-text / HTML-based; no structured template engine guard or sandbox
  - Evidence: renderReportHtml() concatenates section HTML strings into a report HTML page. Templates are stored and used to emit HTML directly.
  - Impact: potential XSS risks if unescaped content appears in templates; also editors may accidentally break layout.

- Partial separation of concerns: heavy controller embedded logic
  - Evidence: ResearchController contains direct DB manipulations and report creation logic (in a large god controller) rather than routing through small controller endpoints that call ReportService exclusively.
  - Impact: refactor risk and harder to unit-test endpoints.

- No fine-grained export options or metadata curation UI
  - Evidence: export=pdf is a single option; no user-facing export settings for including/excluding sections, attachments, or embedding provenance metadata.
  - Impact: curators cannot tailor exports to publication requirements.

- Limited RiC / metadata provenance in exported reports
  - Evidence: renderReportHtml provides HTML but the RiC exporter is not explicitly invoked to attach provenance (the Research RiC work is present, but report → RiC/Event mapping is not visible in ReportService).
  - Impact: exports lack machine-readable provenance linking reports to Activities/Agents/Events.

- No scheduled/templated report generation
  - Evidence: no cron/command to auto-generate periodic reports (project summaries, monthly impact reports) from templates.
  - Impact: extra manual work for recurring reporting.

3) Incomplete code (specific files / lines)

- ReportService.create and renderReportHtml
  - Files: packages/ahg-research/src/Services/ReportService.php
  - Evidence: createReport/ addSection / renderSectionHtml use DB::table inserts and raw HTML building; there are no unit tests asserting HTML output nor sanitisation.

- Controller level complexity
  - Files: packages/ahg-research/src/Controllers/ResearchController.php (methods `reports`, `reportTemplates`, and report create handlers in the large controller). Many DB::table calls (see lines ~1610–1870). These should be delegated to ReportService.

- PDF export flow
  - Files: packages/ahg-research/resources/views/research/report-pdf.blade.php and the controller action that returns it. There is no documented hook to add metadata (DOI, ORCID, RiC ids) into the exported PDF.

4) Enhancement suggestions (concrete, prioritized)

High priority
- Add automated tests for ReportService and template rendering
  - Implement unit tests that exercise create/get/getSections and renderReportHtml, asserting key HTML fragments and escaped content.
  - Add a feature test that POSTs a small report with two sections and requests export=pdf to confirm route returns 200 and a PDF content-type (or HTML render for PDF conversion path).

- Centralise report logic and remove DB writes from the god ResearchController
  - Move all report create/update/delete logic to ReportService and make controller methods thin wrappers.
  - Benefit: easier unit testing and safer refactors.

- Defensive templating and sanitisation
  - Use a templating/sandbox library or ensure server-side sanitisation of section content before rendering into final HTML. Consider using HTMLPurifier or a similar library to strip dangerous tags/attributes during renderStage.

Medium priority
- Add export options and metadata injection UI
  - Provide an "Export settings" modal allowing the user to select which sections to include, whether to embed provenance/RiC metadata, and whether to include attachments inline or as separate annexes.
  - Implement query parameters or a small settings object saved per-report export job.

- RiC mapping for reports
  - Implement a Report → RiC Event mapping in the RiCBridgeService: creating/publishing a report should emit a `research.report.published` event and optionally create an Event node in the RiC graph linking to the relevant Activity and Agents.

- Scheduled/templated report generation
  - Add an artisan command to generate reports from templates on a schedule (`php artisan research:generate-reports --template=monthly_project_summary`) and a simple UI for scheduling templates per project.

Low priority
- WYSIWYG template editor with preview and template validation
  - Improve the report template editor to allow preview rendering, warn about missing tokens, and validate that required tokens ({{project_title}}, {{author}}) exist.

- Accessibility checks on exported PDFs
  - Integrate an accessibility audit (e.g., pdfa checks or simple checks for headings, alt text) for exported PDFs.

5) Concrete implementation plan & PRs (small, reviewable)

PR A — tests + controller-thin refactor
- Move report creation logic into ReportService if needed, add PHPUnit tests for create/get/render. (estimate 1–2 days)

PR B — templating sanitisation + WYSIWYG preview
- Add HTMLPurifier sanitisation in ReportService::renderSectionHtml(), and add a preview endpoint to render an in-memory report safely. (estimate 1–2 days)

PR C — export settings + metadata injection
- Add UI modal, persist export presets, adapt PDF template to include provenance meta tags, and add a small API to generate an export job. (estimate 2–3 days)

PR D — RiC bridge for reports + scheduled generation
- Add event emission in ReportService when a report is published, implement RiCBridgeService subscriber to map reports to RiC Events, and add an artisan command to schedule template-driven reports. (estimate 4–7 days)

6) Acceptance criteria (how to know it’s done)
- Unit tests exist for ReportService and pass in CI. The controller is a thin wrapper that delegates to ReportService. No raw DB::table report inserts remain in controllers. (grep confirms)
- HTML report rendering performs sanitisation and renders known tokens correctly. XSS tests assert sanitisation works.
- Export flow supports including provenance metadata and can be scheduled. RiC exports include a Report Event node for published reports.

7) Files to inspect / start from
- packages/ahg-research/src/Services/ReportService.php
- packages/ahg-research/src/Controllers/ResearchController.php (report handlers)
- packages/ahg-research/resources/views/research/report-pdf.blade.php
- packages/ahg-research/resources/views/research/reports.blade.php & new-report.blade.php
- database install scripts: packages/ahg-research/database/install.sql (report tables)

---

Status: very good

Outstanding issue to work on
1. Add unit/feature tests for ReportService and controller report flows (create/view/export).  
2. Move report DB writes out of ResearchController into ReportService and make controller methods thin.  
3. Add sanitisation (HTMLPurifier) to ReportService::renderSectionHtml and add a preview endpoint.  
4. Implement RiC mapping for published reports and add a scheduled-report artisan command.

Reply with the number (1–4) to pick the next task and I will prepare the first PR for review.