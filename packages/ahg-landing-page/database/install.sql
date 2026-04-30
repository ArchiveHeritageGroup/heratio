-- ============================================================================
-- ahg-landing-page — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgLandingPagePlugin/database/install.sql
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

-- ============================================================================
-- ahgLandingPagePlugin Database Schema
-- Version: 1.0.0
-- ============================================================================
-- Visual landing page builder with drag-and-drop blocks
-- Creates custom landing pages with configurable content blocks
-- ============================================================================

-- Block Types - Defines available block components
CREATE TABLE IF NOT EXISTS atom_landing_page_block_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'bi-square',
    category VARCHAR(50) DEFAULT 'content',
    config_schema JSON COMMENT 'JSON Schema for block configuration',
    default_config JSON COMMENT 'Default configuration values',
    is_container TINYINT(1) DEFAULT 0 COMMENT 'Can contain nested blocks',
    is_active TINYINT(1) DEFAULT 1,
    load_order INT DEFAULT 100,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Landing Pages - Main page entities
CREATE TABLE IF NOT EXISTS atom_landing_page (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_default TINYINT(1) DEFAULT 0 COMMENT 'Default homepage',
    is_active TINYINT(1) DEFAULT 1,
    meta_title VARCHAR(255),
    meta_description TEXT,
    layout VARCHAR(50) DEFAULT 'default',
    css_classes VARCHAR(255),
    custom_css TEXT,
    custom_js TEXT,
    user_id INT COMMENT 'Creator/last editor',
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_default (is_default),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Page Blocks - Block instances on pages
CREATE TABLE IF NOT EXISTS atom_landing_page_block (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    block_type_id INT NOT NULL,
    parent_block_id INT NULL COMMENT 'For nested blocks in columns',
    column_slot VARCHAR(20) NULL COMMENT 'Column position (left, center, right)',
    title VARCHAR(255),
    position INT DEFAULT 0,
    config JSON COMMENT 'Block-specific configuration',
    css_classes VARCHAR(255),
    container_type VARCHAR(20) DEFAULT 'container' COMMENT 'container, container-fluid, none',
    background_color VARCHAR(50),
    text_color VARCHAR(50),
    padding_top VARCHAR(20) DEFAULT 'py-4',
    padding_bottom VARCHAR(20) DEFAULT 'py-4',
    col_span INT DEFAULT 12 COMMENT 'Bootstrap column span (1-12)',
    is_visible TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES atom_landing_page(id) ON DELETE CASCADE,
    FOREIGN KEY (block_type_id) REFERENCES atom_landing_page_block_type(id) ON DELETE RESTRICT,
    FOREIGN KEY (parent_block_id) REFERENCES atom_landing_page_block(id) ON DELETE CASCADE,
    INDEX idx_page (page_id),
    INDEX idx_position (page_id, position),
    INDEX idx_parent (parent_block_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Page Versions - Version history for rollback
CREATE TABLE IF NOT EXISTS atom_landing_page_version (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    version_number INT NOT NULL,
    status VARCHAR(38) COMMENT 'draft, published, archived' DEFAULT 'draft',
    snapshot JSON NOT NULL COMMENT 'Complete page state snapshot',
    notes TEXT,
    user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES atom_landing_page(id) ON DELETE CASCADE,
    INDEX idx_page_version (page_id, version_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Log - Track all changes
CREATE TABLE IF NOT EXISTS atom_landing_page_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    page_id INT,
    block_id INT,
    data JSON,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_page (page_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT BLOCK TYPES
-- ============================================================================

INSERT IGNORE INTO atom_landing_page_block_type
    (machine_name, label, description, icon, category, config_schema, default_config, is_container, load_order)
VALUES
-- Layout Blocks
('row_1_col', '1 Column Row', 'Single column layout container', 'bi-layout-three-columns', 'layout',
 '{"type":"object","properties":{}}', '{}', 1, 10),

('row_2_col', '2 Column Row', 'Two column layout container', 'bi-layout-split', 'layout',
 '{"type":"object","properties":{"left_width":{"type":"integer","default":6},"right_width":{"type":"integer","default":6}}}',
 '{"left_width":6,"right_width":6}', 1, 11),

('row_3_col', '3 Column Row', 'Three column layout container', 'bi-layout-three-columns', 'layout',
 '{"type":"object","properties":{"col_widths":{"type":"array","default":[4,4,4]}}}',
 '{"col_widths":[4,4,4]}', 1, 12),

-- Content Blocks
('hero_banner', 'Hero Banner', 'Large hero section with image and text', 'bi-image', 'content',
 '{"type":"object","properties":{"title":{"type":"string"},"subtitle":{"type":"string"},"background_image":{"type":"string"},"overlay_opacity":{"type":"number","default":0.5},"height":{"type":"string","default":"400px"},"text_align":{"type":"string","default":"center"},"button_text":{"type":"string"},"button_url":{"type":"string"}}}',
 '{"title":"Welcome","subtitle":"","background_image":"","overlay_opacity":0.5,"height":"400px","text_align":"center","button_text":"","button_url":""}', 0, 20),

('text_content', 'Text Content', 'Rich text content block', 'bi-text-paragraph', 'content',
 '{"type":"object","properties":{"content":{"type":"string"},"format":{"type":"string","default":"html"}}}',
 '{"content":"","format":"html"}', 0, 21),

('image_carousel', 'Image Carousel', 'Slideshow of images', 'bi-images', 'content',
 '{"type":"object","properties":{"images":{"type":"array","items":{"type":"object","properties":{"url":{"type":"string"},"caption":{"type":"string"},"link":{"type":"string"}}}},"autoplay":{"type":"boolean","default":true},"interval":{"type":"integer","default":5000}}}',
 '{"images":[],"autoplay":true,"interval":5000}', 0, 22),

-- Archive Blocks
('search_box', 'Search Box', 'Global search input', 'bi-search', 'archive',
 '{"type":"object","properties":{"placeholder":{"type":"string","default":"Search the archive..."},"show_advanced":{"type":"boolean","default":false}}}',
 '{"placeholder":"Search the archive...","show_advanced":false}', 0, 30),

('statistics', 'Statistics', 'Display archive statistics', 'bi-bar-chart', 'archive',
 '{"type":"object","properties":{"stats":{"type":"array","items":{"type":"object","properties":{"entity":{"type":"string"},"label":{"type":"string"},"icon":{"type":"string"}}}},"columns":{"type":"integer","default":4}}}',
 '{"stats":[{"entity":"informationobject","label":"Records","icon":"bi-archive"},{"entity":"repository","label":"Repositories","icon":"bi-building"},{"entity":"actor","label":"People & Organizations","icon":"bi-people"},{"entity":"digitalobject","label":"Digital Objects","icon":"bi-file-earmark-image"}],"columns":4}', 0, 31),

('browse_panels', 'Browse Panels', 'Browse category cards', 'bi-grid-3x3-gap', 'archive',
 '{"type":"object","properties":{"panels":{"type":"array","items":{"type":"object","properties":{"title":{"type":"string"},"icon":{"type":"string"},"url":{"type":"string"},"count_entity":{"type":"string"}}}},"columns":{"type":"integer","default":3},"show_counts":{"type":"boolean","default":true}}}',
 '{"panels":[{"title":"Archival descriptions","icon":"bi-archive","url":"/informationobject/browse","count_entity":"informationobject"},{"title":"Authority records","icon":"bi-people","url":"/actor/browse","count_entity":"actor"},{"title":"Institutions","icon":"bi-building","url":"/repository/browse","count_entity":"repository"}],"columns":3,"show_counts":true}', 0, 32),

('recent_items', 'Recent Items', 'Recently added content', 'bi-clock-history', 'archive',
 '{"type":"object","properties":{"entity_type":{"type":"string","default":"informationobject"},"limit":{"type":"integer","default":6},"columns":{"type":"integer","default":3},"show_date":{"type":"boolean","default":true},"show_thumbnail":{"type":"boolean","default":true}}}',
 '{"entity_type":"informationobject","limit":6,"columns":3,"show_date":true,"show_thumbnail":true}', 0, 33),

('holdings_list', 'Holdings List', 'Top-level fonds/collections', 'bi-list-ul', 'archive',
 '{"type":"object","properties":{"limit":{"type":"integer","default":10},"repository_id":{"type":"integer"},"sort":{"type":"string","default":"title"},"show_level":{"type":"boolean","default":true},"show_extent":{"type":"boolean","default":false}}}',
 '{"limit":10,"repository_id":null,"sort":"title","show_level":true,"show_extent":false}', 0, 34),

('featured_items', 'Featured Items', 'Hand-picked featured content', 'bi-star', 'archive',
 '{"type":"object","properties":{"items":{"type":"array","items":{"type":"integer"}},"columns":{"type":"integer","default":3},"show_description":{"type":"boolean","default":true},"show_thumbnail":{"type":"boolean","default":true}}}',
 '{"items":[],"columns":3,"show_description":true,"show_thumbnail":true}', 0, 35),

('repository_spotlight', 'Repository Spotlight', 'Featured repository with holdings', 'bi-building', 'archive',
 '{"type":"object","properties":{"repository_id":{"type":"integer"},"max_holdings":{"type":"integer","default":5},"show_description":{"type":"boolean","default":true},"show_contact":{"type":"boolean","default":true}}}',
 '{"repository_id":null,"max_holdings":5,"show_description":true,"show_contact":true}', 0, 36),

('map_block', 'Map', 'Interactive map with repository locations', 'bi-geo-alt', 'archive',
 '{"type":"object","properties":{"center_lat":{"type":"number","default":-26.2041},"center_lng":{"type":"number","default":28.0473},"zoom":{"type":"integer","default":6},"show_all_repositories":{"type":"boolean","default":true},"repository_ids":{"type":"array","items":{"type":"integer"}},"height":{"type":"string","default":"400px"}}}',
 '{"center_lat":-26.2041,"center_lng":28.0473,"zoom":6,"show_all_repositories":true,"repository_ids":[],"height":"400px"}', 0, 37),

-- Navigation Blocks
('header_section', 'Header Section', 'Page header with optional breadcrumb', 'bi-card-heading', 'navigation',
 '{"type":"object","properties":{"title":{"type":"string"},"subtitle":{"type":"string"},"show_breadcrumb":{"type":"boolean","default":false}}}',
 '{"title":"","subtitle":"","show_breadcrumb":false}', 0, 40),

('footer_section', 'Footer Section', 'Page footer with links', 'bi-card-text', 'navigation',
 '{"type":"object","properties":{"columns":{"type":"array","items":{"type":"object","properties":{"title":{"type":"string"},"links":{"type":"array","items":{"type":"object","properties":{"text":{"type":"string"},"url":{"type":"string"}}}}}}},"show_social":{"type":"boolean","default":true}}}',
 '{"columns":[],"show_social":true}', 0, 41),

('quick_links', 'Quick Links', 'Row of quick link buttons', 'bi-link-45deg', 'navigation',
 '{"type":"object","properties":{"links":{"type":"array","items":{"type":"object","properties":{"text":{"type":"string"},"url":{"type":"string"},"icon":{"type":"string"},"style":{"type":"string","default":"primary"}}}}}}',
 '{"links":[]}', 0, 42),

('copyright_bar', 'Copyright Bar', 'Simple copyright notice', 'bi-c-circle', 'navigation',
 '{"type":"object","properties":{"text":{"type":"string"},"year":{"type":"string","default":"auto"},"organization":{"type":"string"}}}',
 '{"text":"All rights reserved.","year":"auto","organization":""}', 0, 43),

-- Utility Blocks
('spacer', 'Spacer', 'Vertical spacing', 'bi-distribute-vertical', 'utility',
 '{"type":"object","properties":{"height":{"type":"string","default":"2rem"}}}',
 '{"height":"2rem"}', 0, 50),

('divider', 'Divider', 'Horizontal line divider', 'bi-dash-lg', 'utility',
 '{"type":"object","properties":{"style":{"type":"string","default":"solid"},"color":{"type":"string","default":"#dee2e6"},"width":{"type":"string","default":"100%"}}}',
 '{"style":"solid","color":"#dee2e6","width":"100%"}', 0, 51);

-- ============================================================================
-- DEFAULT LANDING PAGE (Optional Sample)
-- ============================================================================

INSERT IGNORE INTO atom_landing_page (name, slug, description, is_default, is_active)
VALUES ('Home', 'home', 'Default homepage', 1, 1);

-- Add default blocks to homepage (only if page was just created)
SET @page_id = LAST_INSERT_ID();
SET @has_blocks = (SELECT COUNT(*) FROM atom_landing_page_block WHERE page_id = @page_id);

INSERT IGNORE INTO atom_landing_page_block (page_id, block_type_id, position, config)
SELECT @page_id, id, 1, '{"title":"Welcome to the Archive","subtitle":"Explore our collections","height":"350px","text_align":"center"}'
FROM atom_landing_page_block_type WHERE machine_name = 'hero_banner' AND @page_id > 0 AND @has_blocks = 0;

INSERT IGNORE INTO atom_landing_page_block (page_id, block_type_id, position, config)
SELECT @page_id, id, 2, '{"placeholder":"Search our collections..."}'
FROM atom_landing_page_block_type WHERE machine_name = 'search_box' AND @page_id > 0 AND @has_blocks = 0;

INSERT IGNORE INTO atom_landing_page_block (page_id, block_type_id, position, config)
SELECT @page_id, id, 3, default_config
FROM atom_landing_page_block_type WHERE machine_name = 'statistics' AND @page_id > 0 AND @has_blocks = 0;

INSERT IGNORE INTO atom_landing_page_block (page_id, block_type_id, position, config)
SELECT @page_id, id, 4, default_config
FROM atom_landing_page_block_type WHERE machine_name = 'browse_panels' AND @page_id > 0 AND @has_blocks = 0;

INSERT IGNORE INTO atom_landing_page_block (page_id, block_type_id, position, config)
SELECT @page_id, id, 5, '{"entity_type":"informationobject","limit":6,"columns":3}'
FROM atom_landing_page_block_type WHERE machine_name = 'recent_items' AND @page_id > 0 AND @has_blocks = 0;

SET FOREIGN_KEY_CHECKS = 1;
