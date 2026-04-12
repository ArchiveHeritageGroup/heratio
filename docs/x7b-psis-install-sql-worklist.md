# X.7.B — PSIS install.sql Work List

Generated 2026-04-12. Drop-in CREATE TABLE DDL for each Cat-B table that Heratio code references but no PSIS plugin provisions.

For each plugin, add the DDL to its `database/install.sql` (appending before the last `COMMIT;` if present). Heratio's `ServiceProvider::boot()` auto-seeds on first boot; PSIS's own install runs on plugin activation.

## Summary

- **22 plugins** need DDL additions
- **50 tables** total
- Column names + types derived from scanning every `DB::table('X')` chain in Heratio packages + inference from value/context.
- Every DDL includes `IF NOT EXISTS` so it's idempotent and safe to re-run.

---

## ahgAIPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgAIPlugin/database/install.sql`

```sql
-- ahg_ai_prompt_template
CREATE TABLE IF NOT EXISTS `ahg_ai_prompt_template` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `is_active` TINYINT(1) NULL,
  `name` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgAiConditionPlugin (2 tables)

**Add to:** `atom-ahg-plugins/ahgAiConditionPlugin/database/install.sql`

```sql
-- ahg_ai_condition_client — API clients permitted to call the AI condition service.
-- Columns derived from `ai-condition.blade.php`:
--   name, organization, tier, api_key, monthly_limit, scans_used, is_active, can_contribute_training
CREATE TABLE IF NOT EXISTS `ahg_ai_condition_client` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `organization` VARCHAR(255) NULL,
  `tier` VARCHAR(50) NOT NULL DEFAULT 'basic',
  `api_key` VARCHAR(255) NOT NULL,
  `monthly_limit` INT UNSIGNED NOT NULL DEFAULT 1000,
  `scans_used` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `can_contribute_training` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_key` (`api_key`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_ai_condition_training
CREATE TABLE IF NOT EXISTS `ahg_ai_condition_training` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgCartPlugin (2 tables)

**Add to:** `atom-ahg-plugins/ahgCartPlugin/database/install.sql`

```sql
-- ahg_cart_downloads
CREATE TABLE IF NOT EXISTS `ahg_cart_downloads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `downloaded_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `token` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_payment_notifications
CREATE TABLE IF NOT EXISTS `ahg_payment_notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gateway` VARCHAR(255) NULL,
  `payload` JSON NULL,
  `status` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgConditionPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgConditionPlugin/database/install.sql`

```sql
-- ahg_condition_photo
CREATE TABLE IF NOT EXISTS `ahg_condition_photo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `condition_id` INT NOT NULL,
  `filename` VARCHAR(255) NULL,
  `mime_type` VARCHAR(255) NULL,
  `path` VARCHAR(255) NULL,
  `size` INT NULL,
  `sort_order` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_condition_id` (`condition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgCorePlugin (2 tables)

**Add to:** `atom-ahg-plugins/ahgCorePlugin/database/install.sql`

```sql
-- finding_aid
CREATE TABLE IF NOT EXISTS `finding_aid` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `access_conditions` VARCHAR(255) NULL,
  `accruals` VARCHAR(255) NULL,
  `acquisition` VARCHAR(255) NULL,
  `act` VARCHAR(255) NULL,
  `act_id` VARCHAR(255) NOT NULL,
  `act_name` VARCHAR(255) NULL,
  `activity_type` VARCHAR(255) NULL,
  `actor_id` VARCHAR(255) NOT NULL,
  `actor_name` VARCHAR(255) NULL,
  `added_at` VARCHAR(255) NULL,
  `all` VARCHAR(255) NULL,
  `alternate_title` VARCHAR(255) NULL,
  `any` VARCHAR(255) NULL,
  `appraisal` VARCHAR(255) NULL,
  `archival_history` VARCHAR(255) NULL,
  `arrangement` VARCHAR(255) NULL,
  `audio_channels` VARCHAR(255) NULL,
  `audio_codec` VARCHAR(255) NULL,
  `audio_sample_rate` VARCHAR(255) NULL,
  `authorized_form_of_name` VARCHAR(255) NULL,
  `basis` VARCHAR(255) NULL,
  `basis_id` VARCHAR(255) NOT NULL,
  `basis_name` VARCHAR(255) NULL,
  `bitrate` VARCHAR(255) NULL,
  `buildings` VARCHAR(255) NULL,
  `byte_size` VARCHAR(255) NULL,
  `checksum` VARCHAR(255) NULL,
  `city` VARCHAR(255) NULL,
  `code` VARCHAR(255) NULL,
  `collecting_policies` VARCHAR(255) NULL,
  `collection_type_id` VARCHAR(255) NOT NULL,
  `color` VARCHAR(255) NULL,
  `confidence` VARCHAR(255) NULL,
  `contact_person` VARCHAR(255) NULL,
  `contact_type` VARCHAR(255) NULL,
  `contains` VARCHAR(255) NULL,
  `content` VARCHAR(255) NULL,
  `content_format` VARCHAR(255) NULL,
  `copyright_jurisdiction` VARCHAR(255) NULL,
  `copyright_note` VARCHAR(255) NULL,
  `copyright_notice` VARCHAR(255) NULL,
  `copyright_status_id` VARCHAR(255) NOT NULL,
  `copyright_status_name` VARCHAR(255) NULL,
  `count` VARCHAR(255) NULL,
  `country_code` VARCHAR(255) NULL,
  `creative_commons_license_id` VARCHAR(255) NOT NULL,
  `culture` VARCHAR(255) NULL,
  `date` VARCHAR(255) NULL,
  `date_display` VARCHAR(255) NULL,
  `dates_of_existence` VARCHAR(255) NULL,
  `desc_detail_id` VARCHAR(255) NOT NULL,
  `desc_identifier` VARCHAR(255) NULL,
  `desc_institution_identifier` VARCHAR(255) NULL,
  `desc_revision_history` VARCHAR(255) NULL,
  `desc_rules` VARCHAR(255) NULL,
  `desc_sources` VARCHAR(255) NULL,
  `desc_status_id` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) NULL,
  `description_detail_id` VARCHAR(255) NOT NULL,
  `description_identifier` VARCHAR(255) NULL,
  `description_status_id` VARCHAR(255) NOT NULL,
  `disabled_access` VARCHAR(255) NULL,
  `display_standard_id` VARCHAR(255) NOT NULL,
  `document_type` VARCHAR(255) NULL,
  `due_date` VARCHAR(255) NULL,
  `duration` VARCHAR(255) NULL,
  `edition` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `embargo_type` VARCHAR(255) NULL,
  `end_date` VARCHAR(255) NULL,
  `end_time` VARCHAR(255) NULL,
  `entity_title` VARCHAR(255) NULL,
  `entity_type_id` VARCHAR(255) NOT NULL,
  `event_type` VARCHAR(255) NULL,
  `expected_end_date` VARCHAR(255) NULL,
  `expiry_date` VARCHAR(255) NULL,
  `extent_and_medium` VARCHAR(255) NULL,
  `external_url` VARCHAR(255) NULL,
  `fax` VARCHAR(255) NULL,
  `fields_json` VARCHAR(255) NULL,
  `file_size` VARCHAR(255) NULL,
  `finding_aids` VARCHAR(255) NULL,
  `first` VARCHAR(255) NULL,
  `first_name` VARCHAR(255) NULL,
  `flat` VARCHAR(255) NULL,
  `format` VARCHAR(255) NULL,
  `full_text` VARCHAR(255) NULL,
  `funding_source` VARCHAR(255) NULL,
  `geocultural_context` VARCHAR(255) NULL,
  `granted` VARCHAR(255) NULL,
  `history` VARCHAR(255) NULL,
  `holdings` VARCHAR(255) NULL,
  `icon` VARCHAR(255) NULL,
  `identifier` VARCHAR(255) NULL,
  `identifier_type` VARCHAR(255) NULL,
  `identifier_value` VARCHAR(255) NULL,
  `index` VARCHAR(255) NULL,
  `information_object_id` INT NOT NULL,
  `institution` VARCHAR(255) NULL,
  `institution_responsible_identifier` VARCHAR(255) NULL,
  `internal_structures` VARCHAR(255) NULL,
  `is` VARCHAR(255) NULL,
  `label` VARCHAR(255) NULL,
  `label_code` VARCHAR(255) NULL,
  `label_name` VARCHAR(255) NULL,
  `language` VARCHAR(255) NULL,
  `language_notes` VARCHAR(255) NULL,
  `last` VARCHAR(255) NULL,
  `last_name` VARCHAR(255) NULL,
  `latitude` VARCHAR(255) NULL,
  `level_of_description` VARCHAR(255) NULL,
  `level_of_description_id` VARCHAR(255) NOT NULL,
  `license_note` VARCHAR(255) NULL,
  `license_terms` VARCHAR(255) NULL,
  `loan_number` VARCHAR(255) NULL,
  `loan_type` VARCHAR(255) NULL,
  `location` VARCHAR(255) NULL,
  `location_of_copies` VARCHAR(255) NULL,
  `location_of_originals` VARCHAR(255) NULL,
  `longitude` VARCHAR(255) NULL,
  `mandates` VARCHAR(255) NULL,
  `media_type_id` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(255) NULL,
  `name` VARCHAR(255) NULL,
  `note` VARCHAR(255) NULL,
  `notes` VARCHAR(255) NULL,
  `object_id` VARCHAR(255) NOT NULL,
  `on` VARCHAR(255) NULL,
  `opening_times` VARCHAR(255) NULL,
  `owner_id` VARCHAR(255) NOT NULL,
  `owner_location` VARCHAR(255) NULL,
  `owner_name` VARCHAR(255) NULL,
  `owner_type` VARCHAR(255) NULL,
  `parent_id` VARCHAR(255) NULL,
  `partner_institution` VARCHAR(255) NULL,
  `path` VARCHAR(255) NULL,
  `physical_characteristics` VARCHAR(255) NULL,
  `pluck` VARCHAR(255) NULL,
  `postal_code` VARCHAR(255) NULL,
  `primary_contact` VARCHAR(255) NULL,
  `project_title` VARCHAR(255) NULL,
  `project_type` VARCHAR(255) NULL,
  `public_facilities` VARCHAR(255) NULL,
  `push` VARCHAR(255) NULL,
  `region` VARCHAR(255) NULL,
  `related_units_of_description` VARCHAR(255) NULL,
  `repository_id` VARCHAR(255) NOT NULL,
  `repository_name` VARCHAR(255) NULL,
  `repository_type` VARCHAR(255) NULL,
  `reproduction_conditions` VARCHAR(255) NULL,
  `reproduction_services` VARCHAR(255) NULL,
  `research_services` VARCHAR(255) NULL,
  `researcher_id` VARCHAR(255) NOT NULL,
  `resource_type` VARCHAR(255) NULL,
  `restriction` VARCHAR(255) NULL,
  `restriction_label` VARCHAR(255) NULL,
  `revision_history` VARCHAR(255) NULL,
  `rights_date` VARCHAR(255) NULL,
  `rights_holder` VARCHAR(255) NULL,
  `rights_holder_id` VARCHAR(255) NOT NULL,
  `rights_holder_name` VARCHAR(255) NULL,
  `rights_holder_uri` VARCHAR(255) NULL,
  `rights_note` VARCHAR(255) NULL,
  `rights_statement_id` VARCHAR(255) NOT NULL,
  `role` VARCHAR(255) NULL,
  `rules` VARCHAR(255) NULL,
  `scope_and_content` VARCHAR(255) NULL,
  `section_count` VARCHAR(255) NULL,
  `section_type` VARCHAR(255) NULL,
  `sections` VARCHAR(255) NULL,
  `sections_config` VARCHAR(255) NULL,
  `security_classification_id` VARCHAR(255) NOT NULL,
  `security_declassify_date` VARCHAR(255) NULL,
  `security_handling_instructions` VARCHAR(255) NULL,
  `security_inherit_to_children` VARCHAR(255) NULL,
  `security_reason` VARCHAR(255) NULL,
  `security_review_date` VARCHAR(255) NULL,
  `segments` VARCHAR(255) NULL,
  `slug` VARCHAR(255) NULL,
  `source_culture` VARCHAR(255) NULL,
  `source_name` VARCHAR(255) NULL,
  `source_standard` VARCHAR(255) NULL,
  `sources` VARCHAR(255) NULL,
  `start_date` VARCHAR(255) NULL,
  `start_time` VARCHAR(255) NULL,
  `status` VARCHAR(255) NULL,
  `statute_note` VARCHAR(255) NULL,
  `street_address` VARCHAR(255) NULL,
  `supervisor` VARCHAR(255) NULL,
  `telephone` VARCHAR(255) NULL,
  `template_type` VARCHAR(255) NULL,
  `term_id` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NULL,
  `transfer_type` VARCHAR(255) NULL,
  `type` VARCHAR(255) NULL,
  `type_id` VARCHAR(255) NOT NULL,
  `upload_limit` VARCHAR(255) NULL,
  `usage_conditions` VARCHAR(255) NULL,
  `value` VARCHAR(255) NULL,
  `video_codec` VARCHAR(255) NULL,
  `video_frame_rate` VARCHAR(255) NULL,
  `video_height` VARCHAR(255) NULL,
  `video_width` VARCHAR(255) NULL,
  `website` VARCHAR(255) NULL,
  `where` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_act_id` (`act_id`),
  KEY `idx_actor_id` (`actor_id`),
  KEY `idx_basis_id` (`basis_id`),
  KEY `idx_collection_type_id` (`collection_type_id`),
  KEY `idx_copyright_status_id` (`copyright_status_id`),
  KEY `idx_creative_commons_license_id` (`creative_commons_license_id`),
  KEY `idx_desc_detail_id` (`desc_detail_id`),
  KEY `idx_desc_status_id` (`desc_status_id`),
  KEY `idx_description_detail_id` (`description_detail_id`),
  KEY `idx_description_status_id` (`description_status_id`),
  KEY `idx_display_standard_id` (`display_standard_id`),
  KEY `idx_entity_type_id` (`entity_type_id`),
  KEY `idx_information_object_id` (`information_object_id`),
  KEY `idx_level_of_description_id` (`level_of_description_id`),
  KEY `idx_media_type_id` (`media_type_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_repository_id` (`repository_id`),
  KEY `idx_researcher_id` (`researcher_id`),
  KEY `idx_rights_holder_id` (`rights_holder_id`),
  KEY `idx_rights_statement_id` (`rights_statement_id`),
  KEY `idx_security_classification_id` (`security_classification_id`),
  KEY `idx_term_id` (`term_id`),
  KEY `idx_type_id` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- item_physical_location
CREATE TABLE IF NOT EXISTS `item_physical_location` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgDAMPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgDAMPlugin/database/install.sql`

```sql
-- dam_asset
CREATE TABLE IF NOT EXISTS `dam_asset` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgExtendedRightsPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgExtendedRightsPlugin/database/install.sql`

```sql
-- embargo_notification_log
CREATE TABLE IF NOT EXISTS `embargo_notification_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `days_before` VARCHAR(255) NULL,
  `embargo_id` INT NOT NULL,
  `error` VARCHAR(255) NULL,
  `notification_type` VARCHAR(255) NULL,
  `recipients` JSON NULL,
  `sent` VARCHAR(255) NULL,
  `sent_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_embargo_id` (`embargo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgGalleryPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgGalleryPlugin/database/install.sql`

```sql
-- gallery_artwork
CREATE TABLE IF NOT EXISTS `gallery_artwork` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `digital_object_id` INT NOT NULL,
  `object_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object_id` (`digital_object_id`),
  KEY `idx_object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgHeritageAccountingPlugin (4 tables)

**Add to:** `atom-ahg-plugins/ahgHeritageAccountingPlugin/database/install.sql`

```sql
-- heritage_asset_impairment
CREATE TABLE IF NOT EXISTS `heritage_asset_impairment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_date` DATETIME NULL,
  `heritage_asset_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_heritage_asset_id` (`heritage_asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- heritage_asset_journal
CREATE TABLE IF NOT EXISTS `heritage_asset_journal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `heritage_asset_id` INT NOT NULL,
  `journal_date` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_heritage_asset_id` (`heritage_asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- heritage_asset_movement
CREATE TABLE IF NOT EXISTS `heritage_asset_movement` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `heritage_asset_id` INT NOT NULL,
  `movement_date` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_heritage_asset_id` (`heritage_asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- heritage_asset_valuation
CREATE TABLE IF NOT EXISTS `heritage_asset_valuation` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `heritage_asset_id` INT NOT NULL,
  `valuation_date` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_heritage_asset_id` (`heritage_asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgHeritagePlugin (5 tables)

**Add to:** `atom-ahg-plugins/ahgHeritagePlugin/database/install.sql`

```sql
-- heritage_access_purpose
CREATE TABLE IF NOT EXISTS `heritage_access_purpose` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `display_order` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- heritage_region
CREATE TABLE IF NOT EXISTS `heritage_region` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- heritage_rule
CREATE TABLE IF NOT EXISTS `heritage_rule` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- heritage_search_click
CREATE TABLE IF NOT EXISTS `heritage_search_click` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dwell_time_ms` INT NULL,
  `search_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_id` (`search_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- heritage_standard
CREATE TABLE IF NOT EXISTS `heritage_standard` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgIntegrityPlugin (3 tables)

**Add to:** `atom-ahg-plugins/ahgIntegrityPlugin/database/install.sql`

```sql
-- integrity_alert — integrity-check alerts (critical / warning / info)
-- Columns derived from `integrity/alerts.blade.php`:
--   severity, created_at, message, object_id, status
CREATE TABLE IF NOT EXISTS `integrity_alert` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_id` INT UNSIGNED NULL,
  `severity` VARCHAR(20) NOT NULL DEFAULT 'info',
  `message` TEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `details` JSON NULL,
  `triggered_by` VARCHAR(100) NULL,
  `resolved_at` DATETIME NULL,
  `resolved_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_severity` (`severity`),
  KEY `idx_status` (`status`),
  KEY `idx_object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- integrity_disposition: REMOVED — was a typo in IntegrityController, now
-- repointed to the canonical `integrity_disposition_queue` which already
-- exists. No new table needed.

-- integrity_policy — configurable integrity check policies (daily/weekly/monthly)
-- Columns derived from `integrity/policy-edit.blade.php` form:
--   name, description, frequency, is_active
CREATE TABLE IF NOT EXISTS `integrity_policy` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `frequency` VARCHAR(20) NOT NULL DEFAULT 'weekly',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_run_at` DATETIME NULL,
  `next_run_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_next_run_at` (`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgLibraryPlugin (2 tables)

**Add to:** `atom-ahg-plugins/ahgLibraryPlugin/database/install.sql`

```sql
-- library_creator
CREATE TABLE IF NOT EXISTS `library_creator` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL,
  `work_count` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- library_subject
CREATE TABLE IF NOT EXISTS `library_subject` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_count` INT NULL,
  `name` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgMultiTenantPlugin (3 tables)

**Add to:** `atom-ahg-plugins/ahgMultiTenantPlugin/database/install.sql`

```sql
-- ahg_tenant
CREATE TABLE IF NOT EXISTS `ahg_tenant` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `is_default` TINYINT(1) NULL,
  `name` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_tenant_branding
CREATE TABLE IF NOT EXISTS `ahg_tenant_branding` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_tenant_user
CREATE TABLE IF NOT EXISTS `ahg_tenant_user` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgMuseumPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgMuseumPlugin/database/install.sql`

```sql
-- museum_object
CREATE TABLE IF NOT EXISTS `museum_object` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `accession_number` VARCHAR(255) NULL,
  `barcode` VARCHAR(255) NULL,
  `identifier` VARCHAR(255) NULL,
  `object_id` INT NOT NULL,
  `object_number` VARCHAR(255) NULL,
  `repository` VARCHAR(255) NULL,
  `slug` VARCHAR(255) NULL,
  `title` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgPortableExportPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgPortableExportPlugin/database/install.sql`

```sql
-- portable_export_share_token — time-limited share tokens for portable exports
-- Columns derived from `PortableExportController.php:251` insert:
--   export_id, token, expires_at, max_downloads, download_count
CREATE TABLE IF NOT EXISTS `portable_export_share_token` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `export_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NULL,
  `max_downloads` INT UNSIGNED NULL,
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `revoked_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_export_id` (`export_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgPreservationPlugin (3 tables)

**Add to:** `atom-ahg-plugins/ahgPreservationPlugin/database/install.sql`

```sql
-- ahg_preservation_targets
CREATE TABLE IF NOT EXISTS `ahg_preservation_targets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `failed_syncs` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NULL,
  `name` VARCHAR(255) NULL,
  `successful_syncs` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- preservation_conversion
CREATE TABLE IF NOT EXISTS `preservation_conversion` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `status` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- preservation_identification
CREATE TABLE IF NOT EXISTS `preservation_identification` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `confidence` VARCHAR(255) NULL,
  `count` INT NULL,
  `puid` VARCHAR(255) NULL,
  `warning` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgRegistryPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgRegistryPlugin/database/install.sql`

```sql
-- registry_group
CREATE TABLE IF NOT EXISTS `registry_group` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgReportsPlugin (11 tables)

**Add to:** `atom-ahg-plugins/ahgReportsPlugin/database/install.sql`

```sql
-- ahg_report
CREATE TABLE IF NOT EXISTS `ahg_report` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(255) NULL,
  `chart_config` VARCHAR(255) NULL,
  `created_by` INT NULL,
  `data_source` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `filters` VARCHAR(255) NULL,
  `is_public` TINYINT(1) NULL,
  `is_shared` TINYINT(1) NULL,
  `layout_config` VARCHAR(255) NULL,
  `name` VARCHAR(255) NULL,
  `query_definition` JSON NULL,
  `status` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_attachment
CREATE TABLE IF NOT EXISTS `ahg_report_attachment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_path` VARCHAR(255) NULL,
  `file_size` INT NULL,
  `filename` VARCHAR(255) NULL,
  `mime_type` VARCHAR(255) NULL,
  `report_id` INT NOT NULL,
  `uploaded_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_comment
CREATE TABLE IF NOT EXISTS `ahg_report_comment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comment` TEXT NULL,
  `report_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_link
CREATE TABLE IF NOT EXISTS `ahg_report_link` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `description` TEXT NULL,
  `report_id` INT NOT NULL,
  `title` VARCHAR(255) NULL,
  `url` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_schedule
CREATE TABLE IF NOT EXISTS `ahg_report_schedule` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_section
CREATE TABLE IF NOT EXISTS `ahg_report_section` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT NOT NULL,
  `sort_order` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_share
CREATE TABLE IF NOT EXISTS `ahg_report_share` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by` INT NULL,
  `expires_at` DATETIME NULL,
  `is_active` TINYINT(1) NULL,
  `report_id` INT NOT NULL,
  `token` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_snapshot
CREATE TABLE IF NOT EXISTS `ahg_report_snapshot` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by` INT NULL,
  `report_id` INT NOT NULL,
  `snapshot_data` JSON NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_template — saved report templates (report-builder feature)
-- Columns derived from `ReportBuilderController::apiTemplateSave` insert:
--   name, description, template_data (JSON), category, created_by
CREATE TABLE IF NOT EXISTS `ahg_report_template` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `template_data` JSON NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'General',
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_version
CREATE TABLE IF NOT EXISTS `ahg_report_version` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by` INT NULL,
  `notes` TEXT NULL,
  `report_id` INT NOT NULL,
  `version_data` JSON NULL,
  `version_label` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ahg_report_widget
CREATE TABLE IF NOT EXISTS `ahg_report_widget` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT NOT NULL,
  `sort_order` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgSecurityClearancePlugin (2 tables)

**Add to:** `atom-ahg-plugins/ahgSecurityClearancePlugin/database/install.sql`

```sql
-- object_compartment_access
CREATE TABLE IF NOT EXISTS `object_compartment_access` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `compartment_id` INT NOT NULL,
  `granted_by` INT NULL,
  `object_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_compartment_id` (`compartment_id`),
  KEY `idx_object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- security_email_code
CREATE TABLE IF NOT EXISTS `security_email_code` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NULL,
  `expires_at` DATETIME NULL,
  `user_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgSemanticSearchPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgSemanticSearchPlugin/database/install.sql`

```sql
-- ahg_search_template
CREATE TABLE IF NOT EXISTS `ahg_search_template` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgSpectrumPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgSpectrumPlugin/database/install.sql`

```sql
-- spectrum_loan
CREATE TABLE IF NOT EXISTS `spectrum_loan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `direction` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

## ahgUserRegistrationPlugin (1 tables)

**Add to:** `atom-ahg-plugins/ahgUserRegistrationPlugin/database/install.sql`

```sql
-- user_registration_request
CREATE TABLE IF NOT EXISTS `user_registration_request` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_notes` TEXT NULL,
  `reviewed_at` DATETIME NULL,
  `reviewed_by` INT NULL,
  `status` VARCHAR(255) NULL,
  `user_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```

