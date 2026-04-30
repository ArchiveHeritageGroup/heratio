-- ============================================================================
-- ahg-help — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgHelpPlugin/database/install.sql
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

-- ahgHelpPlugin database tables
-- Online help system with searchable documentation

CREATE TABLE IF NOT EXISTS help_article (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL,
    title VARCHAR(500) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'User Guide',
    subcategory VARCHAR(100) DEFAULT NULL,
    source_file VARCHAR(500) DEFAULT NULL,
    body_markdown MEDIUMTEXT NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    body_text MEDIUMTEXT NOT NULL,
    toc_json TEXT DEFAULT NULL,
    word_count INT UNSIGNED DEFAULT 0,
    sort_order INT DEFAULT 100,
    is_published TINYINT(1) DEFAULT 1,
    related_plugin VARCHAR(255) DEFAULT NULL,
    tags VARCHAR(1000) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    KEY idx_category (category),
    KEY idx_related_plugin (related_plugin),
    FULLTEXT KEY ft_search (title, body_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS help_section (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id BIGINT UNSIGNED NOT NULL,
    heading VARCHAR(500) NOT NULL,
    anchor VARCHAR(255) NOT NULL,
    level TINYINT NOT NULL DEFAULT 2,
    body_text TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    KEY idx_article (article_id),
    FULLTEXT KEY ft_section_search (heading, body_text),
    FOREIGN KEY (article_id) REFERENCES help_article(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
