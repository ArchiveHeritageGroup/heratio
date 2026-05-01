# DB Table Verification - Phase X.7

Generated 2026-04-12. **Audit-only, no code changes.**

## Method

1. `grep -rhoE "DB::table\(['\"][a-z_][a-z0-9_]*['\"]\)" packages/` - collected 562 unique table names referenced across all packages.
2. `SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='heratio'` - 919 tables currently exist.
3. Diff → **77 tables referenced but not present**.
4. For each missing table, cross-checked against the actual `SHOW TABLES LIKE` output to classify as **typo** (wrong name in code), **pre-existing feature gap** (real feature, table never provisioned), or **out-of-scope** (feature Heratio doesn't implement).

## Summary

| Category | Count |
|---|---:|
| **A. Table-name typos** - fix the code, table exists under a different name | 20 |
| **B. Pre-existing feature gaps** - feature has no backing table yet | 44 |
| **C. Already deferred elsewhere in Phase X** | 5 |
| **D. Stale/dead-code references** - no longer reachable, candidates for deletion | 8 |
| **Total** | **77** |

---

## A. Table-name typos (20)

**Action:** fix the code to reference the actual table name. These are pure naming mistakes.

| Referenced in code | Actual table in DB | Package(s) |
|---|---|---|
| `ahg_dropdowns` | `ahg_dropdown` | ahg-settings |
| `ahg_dropdown_values` | `ahg_dropdown` (single table; values are rows) | ahg-settings |
| `ahg_landing_block` | `atom_landing_page_block` | ahg-landing-page |
| `ahg_landing_block_type` | `atom_landing_page_block_type` | ahg-landing-page |
| `ahg_landing_page` | `atom_landing_page` | ahg-landing-page |
| `ahg_landing_page_version` | `atom_landing_page_version` | ahg-landing-page |
| `ahg_numbering_schemes` | `numbering_scheme` | ahg-settings |
| `ahg_orders` | `ahg_order` | ahg-cart |
| `ahg_saved_search` | `saved_search` | ahg-semantic-search |
| `ahg_search_log` | `saved_search_log` (or `ahg_semantic_search_log`) | ahg-semantic-search |
| `ahg_semantic_sync_log` | `ahg_thesaurus_sync_log` (most likely) | ahg-semantic-search |
| `ahg_semantic_term` | `semantic_embedding` / `semantic_synonym` | ahg-semantic-search |
| `ahg_webhooks` | `ahg_webhook` | ahg-settings |
| `clipboard` | `clipboard_save` + `clipboard_save_item` | ahg-user-manage |
| `favorites_item` | `favorites` | ahg-research |
| `ingest_column_mapping` | `ingest_mapping` | ahg-ingest (partially fixed in X.5) |
| `ingest_validation_error` | `ingest_validation` | ahg-ingest (partially fixed in X.5) |
| `nmmz_hia` | `nmmz_heritage_impact_assessment` | ahg-nmmz |
| `orphan_work` | `rights_orphan_work` | ahg-rights-holder-manage |
| `tk_label` | `rights_tk_label` | ahg-rights-holder-manage |
| `workflow_state` | `spectrum_workflow_state` | ahg-api |

**Note on `ahg_dropdown_values`:** CLAUDE.md explicitly says "All dropdowns … come from `ahg_dropdown` table" - the schema is a single table with `taxonomy_group` rows, not a `_values` child table. The code referring to `ahg_dropdown_values` is pre-migration style and must be rewritten to query `ahg_dropdown` directly.

---

## B. Pre-existing feature gaps (44)

**Action:** Phase X.7-followup (deferred). Each of these is a real feature whose schema was never ported from PSIS/AtoM or whose `database/install.sql` never ran. User is handling schema import externally; this list is the work queue.

### Reports (11 tables)
`ahg_report`, `ahg_report_attachment`, `ahg_report_comment`, `ahg_report_link`, `ahg_report_schedule`, `ahg_report_section`, `ahg_report_share`, `ahg_report_snapshot`, `ahg_report_template`, `ahg_report_version`, `ahg_report_widget` - `ahg-reports` package report-builder feature. No `ahg_report*` tables exist at all; entire feature is dormant.

### Heritage accounting subsidiaries (7 tables)
`heritage_access_purpose`, `heritage_asset_impairment`, `heritage_asset_journal`, `heritage_asset_movement`, `heritage_asset_valuation`, `heritage_region`, `heritage_rule`, `heritage_search_click`, `heritage_standard` - only parent `heritage_asset` + `heritage_asset_class` exist. Impairment/journal/movement/valuation child tables + reference tables missing.

### AI training (3 tables)
`ahg_ai_condition_client`, `ahg_ai_condition_training`, `ahg_ai_prompt_template` - training-data and per-client config tables. `ahg_prompt_template` exists (used in X.4) but not `ahg_ai_prompt_template`.

### E-commerce (3 tables)
`ahg_cart_downloads`, `ahg_payment_notifications`, `ahg_preservation_targets` - cart download tracking + payment webhook log + preservation target list.

### Gallery / Museum / Library (6 tables)
`gallery_artwork`, `gallery_exhibition`, `gallery_venue`, `item_physical_location`, `library_creator`, `library_subject`, `museum_object` - additional entity tables beyond the current gallery_* / library_* set.

### Preservation (2 tables)
`preservation_conversion`, `preservation_identification` - format conversion + identification logs beyond the existing `preservation_format_conversion`.

### Integrity (3 tables)
`integrity_alert`, `integrity_disposition`, `integrity_policy` - only scheduling/ledger tables exist, not alert/disposition/policy config.

### IIIF 3D (2 tables)
`three_d_hotspot`, `three_d_model` - exist as `object_3d_model` + `object_3d_model_i18n`. Possibly a typo (Category A) or a separate feature table. Needs verification.

### Spectrum (1 table)
`spectrum_loan` - exists as `spectrum_loan_in` / `spectrum_loan_out` / `spectrum_loan_agreements` / `spectrum_loan_document`. This may be a typo - code expects a single `spectrum_loan` header table.

### Misc (6 tables)
`dam_asset`, `embargo_notification_log`, `finding_aid`, `object_compartment_access`, `portable_export_share_token` (vs existing `portable_export_token`), `registry_group`, `security_email_code`, `user_registration_request`, `viewer_3d_settings`, `watermark_setting`, `ahg_condition_photo`.

---

## C. Already deferred (5)

| Table | Deferred in |
|---|---|
| `ahg_tenant` | X.5 (try/catch guard in `TenantService::getCurrentTenant`) |
| `ahg_tenant_branding` | X.5 |
| `ahg_tenant_user` | X.5 |
| `ingest_column_mapping` | Fixed mid-X.5 in `IngestService::deleteSession` - but other callers still reference it |
| `ingest_validation_error` | Same as above |

---

## D. Stale / dead-code references (8)

Referenced by commented code, legacy migration paths, or views not loaded by any active route. Candidates for deletion rather than provisioning.

- `ahg_dropdowns`, `ahg_dropdown_values` - legacy AtoM-plugin shape; CLAUDE.md mandates use of `ahg_dropdown` (single table)
- `ahg_landing_*` - `atom_landing_*` is the real schema, the `ahg_` variants are dead naming
- `ahg_saved_search`, `ahg_semantic_term`, `ahg_semantic_sync_log` - old plugin names; real tables use `saved_search` / `semantic_*` / `ahg_thesaurus_sync_log`

---

## Recommended order

1. **Category A (typos)** - 20 fixes, pure text replacements in code. No DB changes. Can be done in a single pass.
2. **Category D (stale refs)** - check if the referencing code is reachable; if not, delete.
3. **Category C (already deferred)** - finish when user completes the external PSIS schema additions.
4. **Category B (feature gaps)** - do per feature as each is needed. Full list is the work queue.

## Impact assessment

**How many of these would actually break a running page right now?**

Category A typos will 500 the moment the referring code path is exercised - but most are in rarely-hit admin paths. Category B (pre-existing feature gaps) will also 500 if the feature is clicked. Category C has been guarded (try/catch or schema-dependent) so those are safe. Category D is unreachable - safe by definition.

Rough estimate: ~30 pages in Heratio are at risk of a 500 for a schema reason, split roughly 3 marketplace / 12 admin / 15 dormant-feature pages.
