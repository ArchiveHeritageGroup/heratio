# X.7.A.0 Column Diff Report - AtoM-base safe

All 20 target tables exist in the shared AtoM-derived DB; ALTERing any of them is forbidden. For each, compares the columns the Heratio code references (reads/writes) against the real table's columns.

## ahg_dropdowns → ahg_dropdown
- file: `packages/ahg-settings/src/Controllers/SettingsController.php`
- code columns: `name,id`
- **verdict: COLUMN GAP** - missing from target: `name `

## ahg_dropdown_values → ahg_dropdown
- file: `packages/ahg-settings/src/Controllers/SettingsController.php`
- code columns: `dropdown_id,sort_order,value`
- **verdict: COLUMN GAP** - missing from target: `dropdown_id value `

## ahg_landing_block → atom_landing_page_block
- file: `packages/ahg-landing-page/src/Services/LandingPageService.php`
- code columns: `page_id,position,id,parent_block_id,block_type_id,config,title,is_visible,created_at,updated_at`
- **verdict: SAFE RENAME** (all code columns exist on target)

## ahg_landing_block_type → atom_landing_page_block_type
- file: `packages/ahg-landing-page/src/Services/LandingPageService.php`
- code columns: `label`
- **verdict: SAFE RENAME** (all code columns exist on target)

## ahg_landing_page → atom_landing_page
- file: `packages/ahg-landing-page/src/Services/LandingPageService.php`
- code columns: `id,slug,is_active,is_default,created_by,page_type,name`
- **verdict: COLUMN GAP** - missing from target: `created_by page_type `

## ahg_landing_page_version → atom_landing_page_version
- file: `packages/ahg-landing-page/src/Services/LandingPageService.php`
- code columns: `page_id,created_at`
- **verdict: SAFE RENAME** (all code columns exist on target)

## ahg_numbering_schemes → numbering_scheme
- file: `packages/ahg-settings/src/Controllers/SettingsController.php`
- code columns: `name,id`
- **verdict: SAFE RENAME** (all code columns exist on target)

## ahg_orders → ahg_order
- file: `packages/ahg-cart/src/Controllers/CartController.php`
- code columns: `id,status,payment_reference`
- **verdict: COLUMN GAP** - missing from target: `payment_reference `

## ahg_saved_search → saved_search
- file: `packages/ahg-semantic-search/src/Services/SemanticSearchService.php`
- code columns: `user_id`
- **verdict: SAFE RENAME** (all code columns exist on target)

## ahg_search_log → saved_search_log
- file: `packages/ahg-semantic-search/src/Services/SemanticSearchService.php`
- code columns: `user_id,created_at`
- **verdict: COLUMN GAP** - missing from target: `created_at `

## ahg_semantic_sync_log → ahg_thesaurus_sync_log
- file: `packages/ahg-semantic-search/src/Services/SemanticSearchService.php`
- code columns: `synced_count,skipped_count,duration_ms,status,created_at`
- **verdict: COLUMN GAP** - missing from target: `synced_count skipped_count duration_ms `

## ahg_semantic_term → ahg_thesaurus_term
- file: `packages/ahg-semantic-search/src/Services/SemanticSearchService.php`
- code columns: `is_active,term,id,source_term_id,taxonomy_id,name,created_at,updated_at`
- **verdict: COLUMN GAP** - missing from target: `source_term_id taxonomy_id name `

## ahg_webhooks → ahg_webhook
- file: `packages/ahg-settings/src/Controllers/SettingsController.php`
- code columns: `name,url,events`
- **verdict: SAFE RENAME** (all code columns exist on target)

## clipboard → clipboard_save
- file: `packages/ahg-user-manage/src/Controllers/UserController.php`
- code columns: `user_id,created_at`
- **verdict: SAFE RENAME** (all code columns exist on target)

## favorites_item → favorites
- file: `packages/ahg-research/resources/views/research/_favorite-research-button.blade.php`
- code columns: `user_id,object_id,object_type`
- **verdict: COLUMN GAP** - missing from target: `object_id `

## ingest_column_mapping → ingest_mapping
- file: `packages/ahg-ingest/src/Services/IngestService.php`
- code columns: `session_id,source_column`
- **verdict: SAFE RENAME** (all code columns exist on target)

## ingest_validation_error → ingest_validation
- file: `packages/ahg-ingest/src/Services/IngestService.php`
- code columns: `session_id,row_number`
- **verdict: SAFE RENAME** (all code columns exist on target)

## nmmz_hia → nmmz_heritage_impact_assessment
- file: `packages/ahg-nmmz/src/Services/NmmzService.php`
- code columns: `status`
- **verdict: SAFE RENAME** (all code columns exist on target)

## orphan_work → rights_orphan_work
- file: `packages/ahg-rights-holder-manage/src/Controllers/RightsAdminController.php`
- code columns: `created_at,id,designation_date,search_status,search_notes,updated_at`
- **verdict: COLUMN GAP** - missing from target: `designation_date search_status search_notes `

## tk_label → rights_tk_label
- file: `packages/ahg-rights-holder-manage/src/Controllers/RightsAdminController.php`
- code columns: `sort_order`
- **verdict: SAFE RENAME** (all code columns exist on target)

## workflow_state → spectrum_workflow_state
- file: `packages/ahg-api/src/Controllers/LegacyApiController.php`
- code columns: `current_state`
- **verdict: SAFE RENAME** (all code columns exist on target)

