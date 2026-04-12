# X.7.F Column-Level Scan Report

Generated 2026-04-12.

## Scope

Scans every `DB::table('X')->update([...])` / `->insert([...])` / `->updateOrInsert([...], [...])` call, extracts the column-name array keys, and checks each against the real table's `SHOW COLUMNS` output. Skips AtoM-base tables (information_object, actor, repository, term, taxonomy, user, slug, relation, status, note, menu, saved_search, favorites, clipboard_save, finding_aid, numbering_scheme, acl_group, acl_user_group, function_object(_i18n), digital_object, physical_object).

## Result

**64 column-level gaps found across Heratio extension tables.** Most are in files I did NOT touch during Phase X (pre-existing drift between code and schema). Phase X.7.F scope is limited to gaps in files touched during X.1–X.9. The remaining ~58 gaps in untouched packages are documented below but not fixed — they belong to a per-package cleanup pass.

## Fixed in X.7.F (gaps in Phase-X-touched files)

| Table | Column | Action |
|---|---|---|
| `ahg_ai_suggestion` | `reviewed_by`, `reviewed_at`, `notes` | **ALTER** — added all 3 columns (Heratio extension table, safe) |
| `ahg_ai_job` | `attempts` | **Code fix** — renamed to `attempt_count` (real column) |
| `nmmz_export_permit` | `conditions` | **Code fix** — renamed to `approval_conditions` (real column) |
| `embargo_audit` | `changed_fields` | **Code fix** — removed key entirely; `old_values`/`new_values` JSON already captures the diff |

## Documented but not fixed (~58 gaps in untouched packages)

These belong to packages that weren't modified during Phase X.1–X.9. Each is a latent bug — the moment the containing controller method runs, the insert/update will fail. Listed in `docs/x7-column-gaps-raw.txt` for future reference.

**Packages with gaps:**

| Package | Gap count | Notes |
|---|---:|---|
| ahg-webhooks (`ahg_webhook_delivery`) | 3 | missing `event`, `timestamp`, `data` — likely renamed during webhook refactor |
| ahg-integrity (`destruction_certificate`) | 3 | `files_deleted`, `disposal_action_id`, `action_type` |
| ahg-extended-rights (`embargo`, `embargo_audit`) | 7 | `public_message`, `notes`, `reason`, `details`, etc. |
| ahg-heritage-manage (`heritage_contribution`, `heritage_contributor`) | 5 | verification workflow fields |
| ahg-ai-services (`job`) | 5 | duration + timing fields |
| ahg-media-processing (`media_snippets`, `media_transcription`) | 2 | |
| ahg-nmmz (`nmmz_export_permit`) | 1 | ✅ fixed above |
| ahg-research (`research_activity_log`, `research_assertion`, `research_project`, `research_project_milestone`, `research_reading_room_seat`, `research_saved_search`) | 14 | whole research package has column drift |
| ahg-ric (`ric_orphan_tracking`) | 1 | |
| ahg-retention (`rm_disposal_action`, `rm_record_disposal_class`) | 3 | |
| ahg-security-clearance (`security_access_log`, `setting`) | 4 | |
| ahg-spectrum (`spectrum_condition_photo`, `spectrum_workflow_history`) | 2 | |
| ahg-preservation (`tiff_pdf_merge_job`) | 1 | |
| ahg-forms (`ahg_form_field`, `ahg_form_template`) | 2 | |
| ahg-discovery (`ahg_discovery_log`) | 1 | |
| ahg-dedupe (`ahg_dedupe_scan`) | 2 | |
| ahg-actor-manage / ahg-ai-services (`actor_i18n`) | 1 | **⚠️ AtoM-base table** — must rewrite code, never ALTER. Writes `description_identifier` which does not exist on AtoM base. |
| ahg-api-v2 (`property`) | 2 | metadata extraction writes `created_at`/`updated_at` to a table that doesn't have them |

## Recommendation

X.7.F is complete for its stated scope (Phase-X-touched files). The remaining 58 gaps are:
1. **Pre-existing** (not caused by X.1–X.9)
2. **Dormant** in most cases (the features aren't linked from the dashboard; no live page exercises them)
3. **Per-package cleanup work** — not suitable for a single mass-fix pass

Suggested follow-up: when each of those packages is touched for a feature, run `php /tmp/x7/scan_cols.php packages /tmp/tables_existing.txt | grep <PackageFile>` as a pre-commit check, and fix the gaps in the files about to be modified.

## Impact

~4 Heratio extension tables touched by Phase X now have column-level compatibility: `ahg_ai_suggestion` (ALTER), `ahg_ai_job` (code fix), `nmmz_export_permit` (code fix), `embargo_audit` (code fix).
