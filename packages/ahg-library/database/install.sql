-- ============================================================================
-- ahg-library — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgLibraryPlugin/database/install.sql
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
-- Library Plugin Install
-- =====================================================

SET @library_exists = (SELECT COUNT(*) FROM term WHERE code = 'library' AND taxonomy_id = 70);

INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @library_exists = 0;

SET @library_id = LAST_INSERT_ID();

INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture)
SELECT @library_id, 70, 'library', 'en' FROM DUAL WHERE @library_exists = 0 AND @library_id > 0;

INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @library_id, 'en', 'Library (MARC-inspired)' FROM DUAL WHERE @library_exists = 0 AND @library_id > 0;

-- =====================================================
-- Library Level of Description Terms (taxonomy_id = 34)
-- =====================================================

-- Book
SET @book_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Book' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @book_exists IS NULL;
SET @book_id = IF(@book_exists IS NULL, LAST_INSERT_ID(), @book_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @book_id, 34, 'en' FROM DUAL WHERE @book_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @book_id, 'en', 'Book' FROM DUAL WHERE @book_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@book_id, 'level-book');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@book_id, 'library', 10);

-- Monograph
SET @mono_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Monograph' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @mono_exists IS NULL;
SET @mono_id = IF(@mono_exists IS NULL, LAST_INSERT_ID(), @mono_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @mono_id, 34, 'en' FROM DUAL WHERE @mono_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @mono_id, 'en', 'Monograph' FROM DUAL WHERE @mono_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@mono_id, 'level-monograph');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@mono_id, 'library', 20);

-- Periodical
SET @peri_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Periodical' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @peri_exists IS NULL;
SET @peri_id = IF(@peri_exists IS NULL, LAST_INSERT_ID(), @peri_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @peri_id, 34, 'en' FROM DUAL WHERE @peri_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @peri_id, 'en', 'Periodical' FROM DUAL WHERE @peri_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@peri_id, 'level-periodical');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@peri_id, 'library', 30);

-- Journal
SET @jour_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Journal' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @jour_exists IS NULL;
SET @jour_id = IF(@jour_exists IS NULL, LAST_INSERT_ID(), @jour_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @jour_id, 34, 'en' FROM DUAL WHERE @jour_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @jour_id, 'en', 'Journal' FROM DUAL WHERE @jour_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@jour_id, 'level-journal');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@jour_id, 'library', 40);

-- Article
SET @arti_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Article' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @arti_exists IS NULL;
SET @arti_id = IF(@arti_exists IS NULL, LAST_INSERT_ID(), @arti_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @arti_id, 34, 'en' FROM DUAL WHERE @arti_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @arti_id, 'en', 'Article' FROM DUAL WHERE @arti_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@arti_id, 'level-article');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@arti_id, 'library', 45);

-- Manuscript
SET @manu_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Manuscript' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @manu_exists IS NULL;
SET @manu_id = IF(@manu_exists IS NULL, LAST_INSERT_ID(), @manu_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @manu_id, 34, 'en' FROM DUAL WHERE @manu_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @manu_id, 'en', 'Manuscript' FROM DUAL WHERE @manu_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@manu_id, 'level-manuscript');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@manu_id, 'library', 50);

-- Document (shared with DAM)
SET @doc_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Document' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @doc_exists IS NULL;
SET @doc_id = IF(@doc_exists IS NULL, LAST_INSERT_ID(), @doc_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @doc_id, 34, 'en' FROM DUAL WHERE @doc_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @doc_id, 'en', 'Document' FROM DUAL WHERE @doc_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@doc_id, 'level-document');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@doc_id, 'library', 60);
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@doc_id, 'dam', 50);

-- =====================================================
-- Subject Authority Tables (Issue #55)
-- =====================================================

-- Subject Authority - stores controlled subject headings with usage tracking
CREATE TABLE IF NOT EXISTS library_subject_authority (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    heading VARCHAR(500) NOT NULL COMMENT 'The subject heading text',
    heading_normalized VARCHAR(500) NOT NULL COMMENT 'Normalized form for matching',
    heading_type VARCHAR(68) COMMENT 'topical, personal, corporate, geographic, genre, meeting' DEFAULT 'topical',
    source VARCHAR(50) DEFAULT 'lcsh' COMMENT 'Source vocabulary (lcsh, mesh, local, etc.)',
    lcsh_id VARCHAR(100) COMMENT 'Authority record ID (e.g., sh85034652)',
    lcsh_uri VARCHAR(500) COMMENT 'Full URI to authority record',
    suggested_dewey VARCHAR(50) COMMENT 'Suggested Dewey classification for this subject',
    suggested_lcc VARCHAR(50) COMMENT 'Suggested LCC classification for this subject',
    broader_terms JSON COMMENT 'Parent/broader subject terms',
    narrower_terms JSON COMMENT 'Child/narrower subject terms',
    related_terms JSON COMMENT 'Related subject terms',
    usage_count INT UNSIGNED DEFAULT 1 COMMENT 'Number of times used in catalog',
    first_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_heading (heading_normalized, heading_type, source),
    INDEX idx_usage (usage_count DESC),
    INDEX idx_type (heading_type),
    INDEX idx_source (source),
    FULLTEXT INDEX ft_heading (heading)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entity-Subject Map - bridges NER entities to subject authorities
CREATE TABLE IF NOT EXISTS library_entity_subject_map (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL COMMENT 'NER entity type (PERSON, ORG, GPE, etc.)',
    entity_value VARCHAR(500) NOT NULL COMMENT 'Original entity value',
    entity_normalized VARCHAR(500) NOT NULL COMMENT 'Normalized entity value for matching',
    subject_authority_id BIGINT UNSIGNED NOT NULL COMMENT 'FK to subject authority',
    co_occurrence_count INT UNSIGNED DEFAULT 1 COMMENT 'Times this entity appeared with this subject',
    confidence DECIMAL(5,4) DEFAULT 1.0000 COMMENT 'Confidence score for the mapping',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_normalized),
    INDEX idx_authority (subject_authority_id),
    INDEX idx_confidence (confidence DESC),
    FOREIGN KEY (subject_authority_id) REFERENCES library_subject_authority(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alter existing library_item_subject table to add authority link fields
-- Note: These ALTER statements are idempotent (safe to run multiple times)
-- Check if columns exist before adding to avoid errors on re-run

-- Add lcsh_id column if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_item_subject' AND COLUMN_NAME = 'lcsh_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE library_item_subject ADD COLUMN lcsh_id VARCHAR(100) AFTER uri',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add authority_id column if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_item_subject' AND COLUMN_NAME = 'authority_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE library_item_subject ADD COLUMN authority_id BIGINT UNSIGNED AFTER lcsh_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add dewey_number column if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_item_subject' AND COLUMN_NAME = 'dewey_number');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE library_item_subject ADD COLUMN dewey_number VARCHAR(50) AFTER authority_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add lcc_number column if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_item_subject' AND COLUMN_NAME = 'lcc_number');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE library_item_subject ADD COLUMN lcc_number VARCHAR(50) AFTER dewey_number',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add subdivisions JSON column if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_item_subject' AND COLUMN_NAME = 'subdivisions');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE library_item_subject ADD COLUMN subdivisions JSON AFTER lcc_number',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK constraint to authority table (only if column exists and constraint doesn't)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_item_subject' AND CONSTRAINT_NAME = 'fk_item_subject_authority');
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_item_subject' AND COLUMN_NAME = 'authority_id');
SET @sql = IF(@fk_exists = 0 AND @col_exists > 0,
    'ALTER TABLE library_item_subject ADD CONSTRAINT fk_item_subject_authority FOREIGN KEY (authority_id) REFERENCES library_subject_authority(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Link library_item_creator to actor (Authority Record). Nullable so the
-- existing free-text path still works when no matching actor exists yet;
-- LibraryService::syncCreators upserts the actor on save and populates this.
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_item_creator' AND COLUMN_NAME = 'actor_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE library_item_creator ADD COLUMN actor_id INT UNSIGNED NULL AFTER name, ADD INDEX idx_library_item_creator_actor (actor_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
