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

-- =====================================================
-- Phase 1 #703 - ILL / Serials / Acquisitions / ISBN providers
-- =====================================================

-- Interlibrary loan requests (both borrow and lend directions)
CREATE TABLE IF NOT EXISTS library_ill_request (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ill_number VARCHAR(50) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'borrow' COMMENT 'borrow|lend',
    title VARCHAR(500) NOT NULL DEFAULT '',
    author VARCHAR(255) NOT NULL DEFAULT '',
    isbn VARCHAR(32) NOT NULL DEFAULT '',
    library_name VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Counterparty library',
    patron_id BIGINT UNSIGNED NULL COMMENT 'FK to library_patron (borrow direction)',
    request_date DATE NULL,
    due_date DATE NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT 'pending|requested|shipped|received|returned|cancelled',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_library_ill_number (ill_number),
    INDEX idx_library_ill_status (status),
    INDEX idx_library_ill_type (type),
    INDEX idx_library_ill_patron (patron_id),
    INDEX idx_library_ill_request_date (request_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Serial titles (library holdings of journals / periodicals)
CREATE TABLE IF NOT EXISTS library_serial (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    issn VARCHAR(20) NOT NULL DEFAULT '',
    frequency VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'Monthly, Quarterly, Annual, etc.',
    publisher VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(32) NOT NULL DEFAULT 'active' COMMENT 'active|ceased|suspended',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_library_serial_title (title),
    INDEX idx_library_serial_issn (issn),
    INDEX idx_library_serial_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-issue holdings against a serial title
CREATE TABLE IF NOT EXISTS library_serial_issue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    serial_id BIGINT UNSIGNED NOT NULL,
    volume VARCHAR(32) NOT NULL DEFAULT '',
    issue_number VARCHAR(32) NOT NULL DEFAULT '',
    issue_date DATE NULL,
    received_at DATE NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'received' COMMENT 'received|claimed|missing',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_library_serial_issue_serial (serial_id),
    INDEX idx_library_serial_issue_date (issue_date),
    CONSTRAINT fk_library_serial_issue_serial FOREIGN KEY (serial_id)
        REFERENCES library_serial(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acquisition budgets (fiscal-year allocations, spent is derived from orders)
CREATE TABLE IF NOT EXISTS library_acquisition_budget (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    fiscal_year SMALLINT UNSIGNED NOT NULL,
    allocated DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_library_budget_year (fiscal_year),
    INDEX idx_library_budget_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acquisition orders (vendor purchase orders)
CREATE TABLE IF NOT EXISTS library_acquisition_order (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL,
    vendor_name VARCHAR(255) NOT NULL DEFAULT '',
    order_date DATE NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft' COMMENT 'draft|ordered|received|cancelled',
    budget_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_library_order_number (order_number),
    INDEX idx_library_order_status (status),
    INDEX idx_library_order_vendor (vendor_name),
    INDEX idx_library_order_budget (budget_id),
    INDEX idx_library_order_date (order_date),
    CONSTRAINT fk_library_order_budget FOREIGN KEY (budget_id)
        REFERENCES library_acquisition_budget(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Line items on an acquisition order
CREATE TABLE IF NOT EXISTS library_acquisition_order_line (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    isbn VARCHAR(32) NOT NULL DEFAULT '',
    title VARCHAR(500) NOT NULL DEFAULT '',
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_library_order_line_order (order_id),
    INDEX idx_library_order_line_isbn (isbn),
    CONSTRAINT fk_library_order_line_order FOREIGN KEY (order_id)
        REFERENCES library_acquisition_order(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Persisted ISBN lookup provider registry (Open Library + Google Books + WorldCat)
CREATE TABLE IF NOT EXISTS library_isbn_provider (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    api_url VARCHAR(500) NOT NULL DEFAULT '',
    api_key VARCHAR(255) NOT NULL DEFAULT '',
    priority INT UNSIGNED NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_library_isbn_provider_name (name),
    INDEX idx_library_isbn_provider_priority (priority, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: default provider order. INSERT IGNORE keeps existing rows untouched on
-- re-run; LibraryIsbnProviderService also runs the same seed on first list().
INSERT IGNORE INTO library_isbn_provider (name, api_url, api_key, priority, active, created_at, updated_at)
VALUES
    ('Open Library', 'https://openlibrary.org/api/books', '', 10, 1, NOW(), NOW()),
    ('Google Books', 'https://www.googleapis.com/books/v1/volumes', '', 20, 0, NOW(), NOW()),
    ('WorldCat',     'https://www.worldcat.org/webservices/catalog/content', '', 30, 0, NOW(), NOW());

-- Issue #734: mirror the provider list into ahg_dropdown as taxonomy
-- 'isbn_provider' so any form widget that wants a provider <select> can
-- pull from the central Dropdown Manager (no hardcoded option lists).
-- The library_isbn_provider table remains the source of truth for the
-- runtime lookup loop (priority, api_url, api_key); ahg_dropdown is the
-- UI projection only.
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`, `is_active`)
VALUES
    ('isbn_provider', 'ISBN Lookup Provider', 'open_library', 'Open Library', 10, 1, 1),
    ('isbn_provider', 'ISBN Lookup Provider', 'google_books', 'Google Books', 20, 0, 1),
    ('isbn_provider', 'ISBN Lookup Provider', 'worldcat',     'WorldCat',     30, 0, 1);

-- ─────────────────────────────────────────────────────────────────────────
-- Serials management (heratio#1092): subscriptions, predictions, claims,
-- binding. The base library_serial + library_serial_issue tables are defined
-- earlier in this file; these complete the serials surface. Mirrored by the
-- 2026_06_01_0001xx migrations for environments that migrate rather than load
-- install.sql.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS library_serial_subscription (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    serial_id BIGINT UNSIGNED NOT NULL,
    subscription_start DATE NULL,
    subscription_end DATE NULL,
    subscription_cost DECIMAL(10,2) NULL,
    notification_email VARCHAR(255) NULL,
    auto_claim_max TINYINT UNSIGNED DEFAULT 3,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY serial_id_unique (serial_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_serial_prediction (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    serial_id BIGINT UNSIGNED NOT NULL,
    volume VARCHAR(32) NOT NULL DEFAULT '',
    issue_number VARCHAR(32) NOT NULL DEFAULT '',
    expected_date DATE NULL,
    days_until INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    INDEX idx_library_serial_prediction_serial (serial_id),
    INDEX idx_library_serial_prediction_expected (expected_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_claim (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    serial_id BIGINT UNSIGNED NOT NULL,
    issue_id BIGINT UNSIGNED NULL,
    claimed_at TIMESTAMP NULL,
    claimed_by VARCHAR(255) NULL,
    reason TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open' COMMENT 'open|sent|resolved|cancelled',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_library_claim_serial (serial_id),
    INDEX idx_library_claim_issue (issue_id),
    INDEX idx_library_claim_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_binding (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    serial_id BIGINT UNSIGNED NOT NULL,
    volume_range VARCHAR(120) NOT NULL DEFAULT '',
    status VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT 'pending|at_bindery|bound|shelved',
    bound_at DATE NULL,
    location VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_library_binding_serial (serial_id),
    INDEX idx_library_binding_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Serial claim + binding status taxonomies (Dropdown Manager projection)
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`, `is_active`)
VALUES
    ('library_claim_status', 'Serial Claim Status', 'open',      'Open',      10, 1, 1),
    ('library_claim_status', 'Serial Claim Status', 'sent',      'Sent',      20, 0, 1),
    ('library_claim_status', 'Serial Claim Status', 'resolved',  'Resolved',  30, 0, 1),
    ('library_claim_status', 'Serial Claim Status', 'cancelled', 'Cancelled', 40, 0, 1),
    ('library_binding_status', 'Serial Binding Status', 'pending',    'Pending',     10, 1, 1),
    ('library_binding_status', 'Serial Binding Status', 'at_bindery', 'At Bindery',  20, 0, 1),
    ('library_binding_status', 'Serial Binding Status', 'bound',      'Bound',       30, 0, 1),
    ('library_binding_status', 'Serial Binding Status', 'shelved',    'Shelved',     40, 0, 1);

SET FOREIGN_KEY_CHECKS = 1;
