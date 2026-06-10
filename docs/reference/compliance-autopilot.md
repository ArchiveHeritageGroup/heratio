# Compliance Autopilot (PII scan -> auto-drafted ROPA)

heratio#1199 first slice. Admin page **/admin/privacy/autopilot** (Compliance Autopilot button
on the ROPA / Article 30 register). Scans catalogue descriptions for personal data and
auto-drafts a Records of Processing Activities (Article 30) entry for the DPO to review + save.

## Pipeline
Scan up to N information_object descriptions (title + scope) with the existing PiiScanService
-> aggregate the PII categories found (emails, phones, IP addresses, national identifiers,
payment cards, dates of birth) with per-category record + occurrence counts and sample object
ids -> pre-fill a ROPA draft (name, purpose, lawful basis, categories of data = the categories
found, subjects, recipients, retention, security) -> the DPO edits and creates it via
Article30Service::create (ahg_processing_activity).

## Where it lives
- `packages/ahg-privacy/src/Services/ComplianceAutopilotService` (scanCatalogue, draftRopa).
- `ComplianceAutopilotController` (index, scanAjax, createRopa).
- Routes `ahgprivacy.autopilot(.scan|.create)` under `admin/privacy` (`dp.enabled`,`auth`).
- View `resources/views/autopilot.blade.php` (hint `privacy::autopilot`).

## Notes
- Jurisdiction-neutral; PiiScanService is market-pluggable (POPIA / GDPR / etc.).
- It drafts; a human always reviews + confirms against the applicable regime. Nothing is
  auto-finalised.
- Next slices: scope to a collection/repository; suggest legal basis per jurisdiction module;
  auto-link DPIA when special-category data is found; schedule periodic re-scans + gap alerts.
