-- ============================================================================
-- ahg-dam — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgDAMPlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install — Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- ahgDAMPlugin - Database Schema
-- Digital Asset Management with IPTC, watermarks, 
-- derivatives, and Creative Commons licensing
-- =====================================================

-- =====================================================
-- DAM IPTC Metadata
-- =====================================================
CREATE TABLE IF NOT EXISTS dam_iptc_metadata (
    id INT NOT NULL AUTO_INCREMENT,
    object_id INT NOT NULL,
    creator VARCHAR(255) DEFAULT NULL,
    creator_job_title VARCHAR(255) DEFAULT NULL,
    creator_address TEXT,
    creator_city VARCHAR(255) DEFAULT NULL,
    creator_state VARCHAR(255) DEFAULT NULL,
    creator_postal_code VARCHAR(50) DEFAULT NULL,
    creator_country VARCHAR(255) DEFAULT NULL,
    creator_phone VARCHAR(100) DEFAULT NULL,
    creator_email VARCHAR(255) DEFAULT NULL,
    creator_website VARCHAR(500) DEFAULT NULL,
    headline VARCHAR(500) DEFAULT NULL,
    duration_minutes INT UNSIGNED DEFAULT NULL COMMENT 'Running time in minutes (rounded)',
    caption TEXT,
    keywords TEXT,
    iptc_subject_code VARCHAR(255) DEFAULT NULL,
    intellectual_genre VARCHAR(255) DEFAULT NULL,
    asset_type VARCHAR(100) DEFAULT NULL COMMENT 'PBCore: Documentary, Feature, Short, News, etc.',
    genre VARCHAR(255) DEFAULT NULL COMMENT 'Film genre: Documentary, Drama, etc.',
    contributors_json JSON DEFAULT NULL COMMENT 'Structured credits: [{role, name}]',
    color_type VARCHAR(52) COMMENT 'color, black_and_white, mixed, colorized' DEFAULT NULL COMMENT 'Color or B&W',
    audio_language VARCHAR(255) DEFAULT NULL COMMENT 'Audio language(s) - ISO codes',
    subtitle_language VARCHAR(255) DEFAULT NULL COMMENT 'Subtitle language(s)',
    production_company VARCHAR(500) DEFAULT NULL COMMENT 'Production company/studio',
    distributor VARCHAR(500) DEFAULT NULL COMMENT 'Distributor/Broadcaster',
    broadcast_date VARCHAR(100) DEFAULT NULL,
    awards TEXT COMMENT 'Awards and nominations',
    series_title VARCHAR(500) DEFAULT NULL COMMENT 'If part of a series',
    episode_number VARCHAR(50) DEFAULT NULL COMMENT 'Episode number if applicable',
    season_number VARCHAR(50) DEFAULT NULL COMMENT 'Season number if applicable',
    iptc_scene VARCHAR(255) DEFAULT NULL,
    date_created DATE DEFAULT NULL,
    city VARCHAR(255) DEFAULT NULL,
    state_province VARCHAR(255) DEFAULT NULL,
    country VARCHAR(255) DEFAULT NULL,
    country_code VARCHAR(10) DEFAULT NULL,
    production_country VARCHAR(100) DEFAULT NULL COMMENT 'Country where film/video was produced',
    production_country_code CHAR(3) DEFAULT NULL COMMENT 'ISO 3166-1 alpha-3 production country code',
    sublocation VARCHAR(500) DEFAULT NULL,
    title VARCHAR(500) DEFAULT NULL,
    job_id VARCHAR(255) DEFAULT NULL,
    instructions TEXT,
    credit_line VARCHAR(500) DEFAULT NULL,
    source VARCHAR(500) DEFAULT NULL,
    copyright_notice TEXT,
    rights_usage_terms TEXT,
    license_type VARCHAR(91) COMMENT 'rights_managed, royalty_free, creative_commons, public_domain, editorial, other' DEFAULT NULL,
    license_url VARCHAR(500) DEFAULT NULL,
    license_expiry DATE DEFAULT NULL,
    model_release_status VARCHAR(52) COMMENT 'none, not_applicable, unlimited, limited' DEFAULT 'none',
    model_release_id VARCHAR(255) DEFAULT NULL,
    property_release_status VARCHAR(52) COMMENT 'none, not_applicable, unlimited, limited' DEFAULT 'none',
    property_release_id VARCHAR(255) DEFAULT NULL,
    artwork_title VARCHAR(500) DEFAULT NULL,
    artwork_creator VARCHAR(255) DEFAULT NULL,
    artwork_date VARCHAR(100) DEFAULT NULL,
    artwork_source VARCHAR(500) DEFAULT NULL,
    artwork_copyright TEXT,
    persons_shown TEXT,
    camera_make VARCHAR(100) DEFAULT NULL,
    camera_model VARCHAR(100) DEFAULT NULL,
    lens VARCHAR(255) DEFAULT NULL,
    focal_length VARCHAR(50) DEFAULT NULL,
    aperture VARCHAR(20) DEFAULT NULL,
    shutter_speed VARCHAR(50) DEFAULT NULL,
    iso_speed INT DEFAULT NULL,
    flash_used TINYINT(1) DEFAULT NULL,
    gps_latitude DECIMAL(10,8) DEFAULT NULL,
    gps_longitude DECIMAL(11,8) DEFAULT NULL,
    gps_altitude DECIMAL(10,2) DEFAULT NULL,
    image_width INT DEFAULT NULL,
    image_height INT DEFAULT NULL,
    resolution_x INT DEFAULT NULL,
    resolution_y INT DEFAULT NULL,
    resolution_unit VARCHAR(20) DEFAULT NULL,
    color_space VARCHAR(50) DEFAULT NULL,
    bit_depth INT DEFAULT NULL,
    orientation VARCHAR(50) DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY object_id (object_id),
    KEY idx_creator (creator),
    KEY idx_keywords (keywords(255)),
    KEY idx_date_created (date_created),
    KEY idx_asset_type (asset_type),
    KEY idx_genre (genre),
    KEY idx_production_company (production_company(100)),
    KEY idx_broadcast_date (broadcast_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Media Derivatives
-- =====================================================
CREATE TABLE IF NOT EXISTS media_derivatives (
    id INT NOT NULL AUTO_INCREMENT,
    digital_object_id INT NOT NULL,
    derivative_type VARCHAR(48) COMMENT 'thumbnail, poster, preview, waveform' NOT NULL,
    derivative_index INT DEFAULT 0,
    path VARCHAR(500) NOT NULL,
    metadata JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_digital_object (digital_object_id),
    KEY idx_type (derivative_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Watermark Type
-- =====================================================
CREATE TABLE IF NOT EXISTS watermark_type (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    image_file VARCHAR(255) NOT NULL,
    position VARCHAR(50) DEFAULT 'repeat',
    opacity DECIMAL(3,2) DEFAULT 0.30,
    active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Watermark types seed data
INSERT IGNORE INTO watermark_type (code, name, image_file, position, opacity, active, sort_order) VALUES
('DRAFT', 'Draft', 'draft.png', 'center', 0.40, 1, 1),
('COPYRIGHT', 'Copyright', 'copyright.png', 'bottom right', 0.30, 1, 2),
('CONFIDENTIAL', 'Confidential', 'confidential.png', 'repeat', 0.40, 1, 3),
('SECRET', 'Secret', 'secret_copyright.png', 'repeat', 0.40, 1, 4),
('TOP_SECRET', 'Top Secret', 'top_secret_copyright.png', 'repeat', 0.50, 1, 5),
('NONE', 'No Watermark', '', 'none', 0.00, 1, 6),
('SAMPLE', 'Sample', 'sample.png', 'center', 0.50, 1, 7),
('PREVIEW', 'Preview Only', 'preview.png', 'center', 0.40, 1, 8),
('RESTRICTED', 'Restricted', 'restricted.png', 'repeat', 0.35, 1, 9);

-- =====================================================
-- Watermark Settings
-- =====================================================
CREATE TABLE IF NOT EXISTS watermark_setting (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Watermark settings seed data
INSERT IGNORE INTO watermark_setting (setting_key, setting_value, description) VALUES
('default_watermark_enabled', '1', 'Enable watermarking by default'),
('default_watermark_type', 'COPYRIGHT', 'Default watermark type for new uploads'),
('apply_watermark_on_view', '1', 'Apply watermark when viewing images (IIIF)'),
('apply_watermark_on_download', '1', 'Apply watermark when downloading'),
('security_watermark_override', '1', 'Security classification overrides default watermark'),
('watermark_min_size', '200', 'Minimum image dimension (px) to apply watermark');

-- =====================================================
-- Custom Watermark
-- =====================================================
CREATE TABLE IF NOT EXISTS custom_watermark (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    object_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL = global watermark',
    name VARCHAR(100) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    position VARCHAR(50) DEFAULT 'center',
    opacity DECIMAL(3,2) DEFAULT 0.40,
    created_by INT UNSIGNED DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_object (object_id),
    KEY idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Object Watermark Setting
-- =====================================================
CREATE TABLE IF NOT EXISTS object_watermark_setting (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    object_id INT UNSIGNED NOT NULL,
    watermark_enabled TINYINT(1) DEFAULT 1,
    watermark_type_id INT UNSIGNED DEFAULT NULL,
    custom_watermark_id INT UNSIGNED DEFAULT NULL,
    position VARCHAR(50) DEFAULT 'center',
    opacity DECIMAL(3,2) DEFAULT 0.40,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY object_id (object_id),
    KEY watermark_type_id (watermark_type_id),
    KEY custom_watermark_id (custom_watermark_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Creative Commons License tables
-- NOTE: These tables are defined in ahgRightsPlugin/data/install.sql
-- Do not duplicate here - ensure ahgRightsPlugin is installed first
-- =====================================================

-- =====================================================
-- Rights Derivative Rule
-- =====================================================
CREATE TABLE IF NOT EXISTS rights_derivative_rule (
    id INT NOT NULL AUTO_INCREMENT,
    object_id INT DEFAULT NULL COMMENT 'NULL = applies to collection or global',
    collection_id INT DEFAULT NULL COMMENT 'NULL = applies to object or global',
    is_global TINYINT(1) DEFAULT 0,
    rule_type VARCHAR(75) COMMENT 'watermark, redaction, resize, format_conversion, metadata_strip' NOT NULL,
    priority INT DEFAULT 0,
    applies_to_roles JSON DEFAULT NULL COMMENT 'Array of role IDs, NULL = all',
    applies_to_clearance_levels JSON DEFAULT NULL COMMENT 'Array of clearance level codes',
    applies_to_purposes JSON DEFAULT NULL COMMENT 'Array of purpose codes',
    watermark_text VARCHAR(255) DEFAULT NULL,
    watermark_image_path VARCHAR(500) DEFAULT NULL,
    watermark_position VARCHAR(72) COMMENT 'center, top_left, top_right, bottom_left, bottom_right, tile' DEFAULT 'bottom_right',
    watermark_opacity INT DEFAULT 50 COMMENT '0-100',
    redaction_areas JSON DEFAULT NULL COMMENT 'Array of {x, y, width, height, page}',
    redaction_color VARCHAR(7) DEFAULT '#000000',
    max_width INT DEFAULT NULL,
    max_height INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_object (object_id),
    KEY idx_collection (collection_id),
    KEY idx_rule_type (rule_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Rights Derivative Log
-- =====================================================
CREATE TABLE IF NOT EXISTS rights_derivative_log (
    id INT NOT NULL AUTO_INCREMENT,
    digital_object_id INT NOT NULL,
    rule_id INT DEFAULT NULL,
    derivative_type VARCHAR(50) DEFAULT NULL,
    original_path VARCHAR(500) DEFAULT NULL,
    derivative_path VARCHAR(500) DEFAULT NULL,
    requested_by INT DEFAULT NULL,
    request_purpose VARCHAR(100) DEFAULT NULL,
    request_ip VARCHAR(45) DEFAULT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_digital_object (digital_object_id),
    KEY idx_rule (rule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Security Watermark Log
-- =====================================================
CREATE TABLE IF NOT EXISTS security_watermark_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    object_id INT UNSIGNED NOT NULL,
    digital_object_id INT UNSIGNED DEFAULT NULL,
    watermark_type VARCHAR(36) COMMENT 'visible, invisible, both' NOT NULL DEFAULT 'visible',
    watermark_text VARCHAR(500) NOT NULL,
    watermark_code VARCHAR(100) NOT NULL,
    file_hash VARCHAR(64) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id, created_at),
    KEY idx_object (object_id),
    KEY idx_code (watermark_code),
    KEY idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DAM Display Standard Term (taxonomy_id = 70)
-- =====================================================
SET @dam_exists = (SELECT COUNT(*) FROM term WHERE code = 'dam' AND taxonomy_id = 70);

INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @dam_exists = 0;

SET @dam_id = LAST_INSERT_ID();

INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture)
SELECT @dam_id, 70, 'dam', 'en' FROM DUAL WHERE @dam_exists = 0 AND @dam_id > 0;

INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @dam_id, 'en', 'Photo/DAM (IPTC/XMP)' FROM DUAL WHERE @dam_exists = 0 AND @dam_id > 0;

-- =====================================================
-- DAM Level of Description Terms (taxonomy_id = 34)
-- =====================================================

-- Photograph (shared with Gallery)
SET @photo_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Photograph' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @photo_exists IS NULL;
SET @photo_id = IF(@photo_exists IS NULL, LAST_INSERT_ID(), @photo_exists);
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture)
SELECT @photo_id, 34, '', 'en' FROM DUAL WHERE @photo_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @photo_id, 'en', 'Photograph' FROM DUAL WHERE @photo_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@photo_id, 'level-photograph');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@photo_id, 'dam', 10);
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@photo_id, 'gallery', 20);

-- Audio
SET @audio_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Audio' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @audio_exists IS NULL;
SET @audio_id = IF(@audio_exists IS NULL, LAST_INSERT_ID(), @audio_exists);
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture)
SELECT @audio_id, 34, '', 'en' FROM DUAL WHERE @audio_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @audio_id, 'en', 'Audio' FROM DUAL WHERE @audio_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@audio_id, 'level-audio');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@audio_id, 'dam', 20);

-- Video
SET @video_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Video' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @video_exists IS NULL;
SET @video_id = IF(@video_exists IS NULL, LAST_INSERT_ID(), @video_exists);
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture)
SELECT @video_id, 34, '', 'en' FROM DUAL WHERE @video_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @video_id, 'en', 'Video' FROM DUAL WHERE @video_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@video_id, 'level-video');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@video_id, 'dam', 30);

-- Image
SET @image_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Image' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @image_exists IS NULL;
SET @image_id = IF(@image_exists IS NULL, LAST_INSERT_ID(), @image_exists);
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture)
SELECT @image_id, 34, '', 'en' FROM DUAL WHERE @image_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @image_id, 'en', 'Image' FROM DUAL WHERE @image_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@image_id, 'level-image');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@image_id, 'dam', 40);

-- Dataset
SET @data_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Dataset' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @data_exists IS NULL;
SET @data_id = IF(@data_exists IS NULL, LAST_INSERT_ID(), @data_exists);
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture)
SELECT @data_id, 34, '', 'en' FROM DUAL WHERE @data_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @data_id, 'en', 'Dataset' FROM DUAL WHERE @data_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@data_id, 'level-dataset');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@data_id, 'dam', 70);

-- =====================================================
-- DAM External Links
-- =====================================================
CREATE TABLE IF NOT EXISTS dam_external_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    object_id INT NOT NULL,
    link_type VARCHAR(166) COMMENT 'ESAT, IMDb, SAFILM, NFVSA, Wikipedia, Wikidata, VIAF, YouTube, Vimeo, Archive_org, BFI, AFI, Letterboxd, MUBI, Filmography, Review, Academic, Press, Other' NOT NULL DEFAULT 'Other',
    url VARCHAR(500) NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    person_name VARCHAR(255) DEFAULT NULL,
    person_role VARCHAR(100) DEFAULT NULL,
    verified_date DATE DEFAULT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_object (object_id),
    KEY idx_link_type (link_type),
    KEY idx_person (person_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DAM Format Holdings
-- =====================================================
CREATE TABLE IF NOT EXISTS dam_format_holdings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    object_id INT NOT NULL,
    format_type VARCHAR(188) COMMENT '35mm, 16mm, 8mm, Super8, VHS, Betacam, U-matic, DV, DVD, Blu-ray, LaserDisc, Digital_File, DCP, ProRes, Nitrate, Safety, Polyester, Audio_Reel, Audio_Cassette, Vinyl, CD, Other' NOT NULL DEFAULT 'Other',
    format_details VARCHAR(255) DEFAULT NULL,
    holding_institution VARCHAR(255) DEFAULT NULL,
    holding_location VARCHAR(255) DEFAULT NULL,
    accession_number VARCHAR(100) DEFAULT NULL,
    condition_status VARCHAR(63) COMMENT 'excellent, good, fair, poor, deteriorating, unknown' DEFAULT 'unknown',
    access_status VARCHAR(106) COMMENT 'available, restricted, preservation_only, digitized_available, on_request, staff_only, unknown' DEFAULT 'unknown',
    access_url VARCHAR(500) DEFAULT NULL,
    access_notes TEXT DEFAULT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    verified_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_object (object_id),
    KEY idx_format_type (format_type),
    KEY idx_institution (holding_institution),
    KEY idx_condition (condition_status),
    KEY idx_access (access_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DAM Version Links
-- =====================================================
CREATE TABLE IF NOT EXISTS dam_version_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    object_id INT NOT NULL,
    related_object_id INT NOT NULL,
    version_type VARCHAR(73) COMMENT 'language, format, restoration, directors_cut, censored, other' NOT NULL DEFAULT 'language',
    title VARCHAR(255) DEFAULT NULL,
    language_code CHAR(3) DEFAULT NULL,
    language_name VARCHAR(50) DEFAULT NULL,
    year VARCHAR(10) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_object (object_id),
    KEY idx_related (related_object_id),
    KEY idx_version_type (version_type),
    KEY idx_language (language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
