# Condition assessment (Spectrum 5.1) — gaps, incomplete code, and suggested enhancements

This note summarises the current state of Condition / Spectrum 5.1 support in the codebase, points to incomplete or fragile code paths, and proposes concrete enhancements and an implementation plan. All file references are exact repository paths so you can inspect or apply the changes locally.

1. Overview — what the codebase already provides
- Tables and schema
  - spectrum-related tables exist (packages/ahg-spectrum/database/install.sql and packages/ahg-condition/database/install.sql) including spectrum_condition_check, spectrum_condition_photo, spectrum_condition_photos, spectrum_condition_template, spectrum_condition_template_field, spectrum_condition_photo_comparison, spectrum_condition_photos.
- Controllers & views
  - ConditionController and SpectrumController provide condition listing, viewing, photo upload and photo comparison flows: packages/ahg-information-object-manage/src/Controllers/ConditionController.php and packages/ahg-spectrum/src/Controllers/SpectrumController.php.
  - Condition/condition-photos UI exists: packages/ahg-spectrum/resources/views/condition-photos.blade.php and packages/ahg-condition/resources/views/_condition-template-form.blade.php.
- Services
  - ConditionService handles DB interactions and photo operations: packages/ahg-condition/src/Services/ConditionService.php
  - SpectrumWorkflowService, SpectrumNotificationService and SpectrumStatisticsService provide workflow gating, notifications and statistics.
- Exports and RIC
  - The ric_extractor includes Spectrum condition extract logic (packages/ahg-ric/tools/ric_extractor_v5.py), mapping Spectrum fields into RiC/JSON-LD.
- Reminders and scheduled jobs
  - Spectrum reminder commands exist (spectrum:valuation-reminder, spectrum:condition-check-reminder) and are scheduled via the service provider.

2. First look — gaps (what's missing / weak)
- Incomplete or fragile photo annotation support
  - condition-photos view includes JS hooks for save/get annotations and an export endpoint (spectrum.saveAnnotations, spectrum.getAnnotations, spectrum.exportAnnotatedPhoto), but annotations storage and retrieval flow is thin and lacks robust schema versioning and provenance capture.
  - Files: packages/ahg-spectrum/resources/views/condition-photos.blade.php (js references), packages/ahg-spectrum/routes/web.php (save/get routes).
- Image comparison / visual-diff workflow is basic
  - There is a `spectrum_condition_photo_comparison` table and UI to create comparisons, but no automated per-photo diffing (edge detection/SSIM) or optional cloud job to precompute diffs for large batches.
  - Files: packages/ahg-condition/src/Services/ConditionService.php (photo comparison data insertion points), view at packages/ahg-spectrum/resources/views/condition-photos.blade.php
- Lack of structured template enforcement and validation
  - Condition template fields are stored (spectrum_condition_template_field) but controllers often accept freeform payloads; field-level validation and typed values (numeric/enum) are not consistently enforced.
  - Files: packages/ahg-condition/resources/views/_condition-template-form.blade.php, packages/ahg-condition/src/Services/ConditionService.php
- Missing or inconsistent provenance and audit metadata on condition checks and photos
  - Spectrum RiC extractor reads many fields (checked_by, workflow_state) but the condition_photo rows and condition_check events lack consistent authorship/provenance capture for AI-assisted annotations or automated imports.
  - Files: packages/ahg-condition/src/Services/ConditionService.php; ric_extractor references spectrum_condition_check fields but provenance entry points are sparse.
- UI/UX issues on mobile and accessibility
  - condition-photos page heavy on JS; no explicit accessible text-tour fallback or low-bandwidth mode; photo upload UX needs client-side validation for file size and type.
  - Files: packages/ahg-spectrum/resources/views/condition-photos.blade.php
- Test coverage gaps
  - ConditionService and photo/annotation endpoints have limited automated tests (unit/feature) to catch regressions.
  - Files: packages/ahg-condition/tests/ (few if any tests found in quick scan)
- Publication / publish-guard coupling
  - Spectrum publish-guard (spectrum_require_photos etc.) depends on table existence; enforcement exists in SpectrumPublishGuardService, but cross-checking with upload/annotation status is brittle if background jobs fail.
  - Files: packages/ahg-spectrum/src/Services/SpectrumPublishGuardService.php

3. Incomplete code (exact files with partial/fragile implementations)
- packages/ahg-spectrum/resources/views/condition-photos.blade.php
  - JS saves annotations to routes but lacks robust error handling, retries, and provenance stamping in the payload.
- packages/ahg-condition/src/Services/ConditionService.php
  - Implements DB insert/get for spectrum_condition_check and spectrum_condition_photo, but business logic (templating enforcement, photo annotation merging, compare-job scheduling) is partial.
- packages/ahg-spectrum/src/Controllers/SpectrumController.php
  - condition-related handlers use raw DB::table calls and construct views; some endpoints build large queries without pagination or caching.
- packages/ahg-ric/tools/ric_extractor_v5.py
  - Extraction of condition assessments exists but may not include attachments/transcriptions or annotation provenance; some fields are mapped but the extractor runs as a utility rather than an event-driven exporter.
- packages/ahg-spectrum/src/Services/SpectrumNotificationService.php
  - Notification queuing exists but failure handling / visibility into failed sends could be improved (metrics/logging present but not surfaced).

4. Enhancements and suggestions (concrete, actionable)
A. Data & Schema
- Add `annotations` table or versioned `spectrum_condition_photo.annotations` JSON with schema version and `ai_provenance_id` optional FK. This makes annotation diffs, rollbacks, and AI provenance explicit.
- Support a `condition_check.status` enum and a `provenance_log` entry for automated imports and AI suggestions.

B. Photo processing & visual diffs
- Add optional server-side image-diff worker that computes SSIM or structural diffs between `before`/`after` photos and stores a thumbnail delta; surface a comparison UI showing the diff overlay and change heatmap.
- Use existing PhotoProcessor (packages/ahg-media-processing/src/Services/PhotoProcessor.php) to create derivative thumbs and histogram metadata suitable for automated comparisons.

C. Template enforcement & validation
- Extend `spectrum_condition_template_field` metadata with `data_type` (int/string/enum/date) and `required` flag, then validate payloads on save in ConditionService; return per-field validation errors to the UI.

D. Provenance and AI
- Ensure every annotation save and every automated import writes a row in `ai_provenance` (if AI used) and in a `condition_provenance` table capturing {actor_id, method, source, ip, user_agent, timestamp, field_diffs}.
- UI should label AI-proposed annotations and require curator Accept/Reject before they become canonical.

E. Performance and caching
- Cache generated condition lists and the count metrics used by the Spectrum dashboard and invalidate on create/update/delete of checks or photos.
- Add ETag / Last-Modified headers for endpoints that return heavy condition manifests (spectrumExport / condition lists).

F. Accessibility & mobile UX
- Provide a "Text tour" fallback: an ordered list of condition stops with descriptions and the ability to play their TTS. This helps low-bandwidth and accessibility.
- Ensure file inputs and action buttons have ARIA labels and keyboard navigability.

G. Tests & CI
- Add feature tests for ConditionService: create check, add photo, add annotation, export RiC fragment. Add integration tests for the photo-diff worker.

H. Workflow & scheduling
- Make condition reminders and overdue checks observable (metrics + inbox notifications). Add an admin UI to re-run failed background jobs for photo ingestion and diffing.

I. RiC & exports
- Ensure ric_extractor includes annotation attachment links and provenance references (ai_provenance id). Add a sample export test that runs the extractor over a sample check and verifies JSON-LD nodes exist.

5. Implementation plan (staged, reviewable PRs)
- PR A (small, safe) — Annotation provenance
  - Add `spectrum_condition_photo.annotation_provenance` (JSON) or new `spectrum_photo_annotation` table; modify saveAnnotations endpoint to include provenance id or store ai_provenance FK. Add unit tests. (Est: 1–2 days)
- PR B — Photo derivatives & visual diff worker
  - Use PhotoProcessor to generate diffs; create worker job `ComputePhotoDiffJob` and store derivative thumbnails and heatmaps; add UI overlay in condition-photos view. (Est: 3–5 days)
- PR C — Template validation
  - Add `data_type` and `required` columns to `spectrum_condition_template_field`, enforce validation in ConditionService and surfacing errors to the view. (Est: 2–3 days)
- PR D — Provenance & RiC enhancements
  - Write ai_provenance entries on AI/automation; extend ric_extractor to include annotations and provenance links; add export test. (Est: 2–4 days)
- PR E — Tests & monitoring
  - Add PHPUnit + integration tests; instrument SpectrumNotificationService and add metrics for job failures. (Est: 2–3 days)

6. Acceptance criteria
- SaveAnnotations produces a database row with a provenance reference and version number; `getAnnotations` returns versions. UI shows version history.
- Photo diff worker produces an overlay thumbnail and the UI can toggle original/diff/overlay modes.
- Template field validation blocks a save if `required` fields are missing; UI shows per-field error messages.
- RiC export includes condition assessment nodes with annotation and provenance references.
- Tests for the above run in CI and pass.

7. Files to inspect / review (quick checklist)
- packages/ahg-condition/src/Services/ConditionService.php
- packages/ahg-spectrum/resources/views/condition-photos.blade.php
- packages/ahg-spectrum/routes/web.php (save/get/export endpoints)
- packages/ahg-ric/tools/ric_extractor_v5.py (condition extraction)
- packages/ahg-media-processing/src/Services/PhotoProcessor.php

---
Status: very good

outstanding issue to work on
1. Implement annotation provenance (PR A): add annotation provenance table/field and update save/get endpoints so each annotation write records provenance and versioning.  
2. Scaffold and implement the ComputePhotoDiffJob and UI overlay for diffs (PR B).  
3. Add template field `data_type` + server-side validation and update ConditionService (PR C).  
4. Extend the ric_extractor to include annotation attachments + provenance and add a sample export test (PR D).