-- ============================================================================
-- ahg-heritage-manage — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgHeritagePlugin/database/install.sql
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

-- =============================================================================
-- ahgHeritagePlugin - Complete Database Schema
-- Version: 1.0.0
-- Description: Heritage discovery platform with contributor system, access mediation,
--              custodian management, and analytics
-- =============================================================================


-- =============================================================================
-- CORE LANDING PAGE TABLES
-- =============================================================================

-- Table: heritage_landing_config
-- Institution landing page configuration
CREATE TABLE IF NOT EXISTS heritage_landing_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Hero section
    hero_tagline VARCHAR(500) DEFAULT 'Discover our collections',
    hero_subtext VARCHAR(500) DEFAULT NULL,
    hero_search_placeholder VARCHAR(255) DEFAULT 'What are you looking for?',
    suggested_searches JSON DEFAULT NULL,

    -- Hero media
    hero_media JSON DEFAULT NULL,
    hero_rotation_seconds INT DEFAULT 8,
    hero_effect VARCHAR(32) COMMENT 'kenburns, fade, none' DEFAULT 'kenburns',

    -- Sections enabled
    show_curated_stories TINYINT(1) DEFAULT 1,
    show_community_activity TINYINT(1) DEFAULT 1,
    show_filters TINYINT(1) DEFAULT 1,
    show_stats TINYINT(1) DEFAULT 1,
    show_recent_additions TINYINT(1) DEFAULT 1,

    -- Stats configuration
    stats_config JSON DEFAULT NULL,

    -- Styling
    primary_color VARCHAR(7) DEFAULT '#0d6efd',
    secondary_color VARCHAR(7) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_filter_type
-- Available filter types system-wide
CREATE TABLE IF NOT EXISTS heritage_filter_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    source_type VARCHAR(46) COMMENT 'taxonomy, authority, field, custom' NOT NULL,
    source_reference VARCHAR(255) DEFAULT NULL,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_code (code),
    INDEX idx_source_type (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_institution_filter
-- Institution's filter configuration
CREATE TABLE IF NOT EXISTS heritage_institution_filter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    filter_type_id INT NOT NULL,

    is_enabled TINYINT(1) DEFAULT 1,
    display_name VARCHAR(100) DEFAULT NULL,
    display_icon VARCHAR(50) DEFAULT NULL,
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    show_in_search TINYINT(1) DEFAULT 1,
    max_items_landing INT DEFAULT 6,

    is_hierarchical TINYINT(1) DEFAULT 0,
    allow_multiple TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_filter_type (filter_type_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),

    CONSTRAINT fk_heritage_inst_filter_type
        FOREIGN KEY (filter_type_id) REFERENCES heritage_filter_type(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_filter_value
-- Custom filter values for non-taxonomy filters
CREATE TABLE IF NOT EXISTS heritage_filter_value (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_filter_id INT NOT NULL,
    value_code VARCHAR(100) NOT NULL,
    display_label VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 100,
    parent_id INT DEFAULT NULL,
    filter_query JSON DEFAULT NULL,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution_filter (institution_filter_id),
    INDEX idx_parent (parent_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),

    CONSTRAINT fk_heritage_filter_value_inst
        FOREIGN KEY (institution_filter_id) REFERENCES heritage_institution_filter(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_heritage_filter_value_parent
        FOREIGN KEY (parent_id) REFERENCES heritage_filter_value(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_curated_story
-- Featured stories/collections on landing page
CREATE TABLE IF NOT EXISTS heritage_curated_story (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(500) DEFAULT NULL,
    story_type VARCHAR(50) DEFAULT 'collection',

    link_type VARCHAR(46) COMMENT 'collection, search, external, page' DEFAULT 'search',
    link_reference VARCHAR(500) DEFAULT NULL,

    item_count INT DEFAULT NULL,

    is_featured TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 100,
    is_enabled TINYINT(1) DEFAULT 1,

    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_featured (is_featured),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_hero_image
-- Hero images for rotation
CREATE TABLE IF NOT EXISTS heritage_hero_image (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    image_path VARCHAR(500) NOT NULL,
    caption VARCHAR(500) DEFAULT NULL,
    collection_name VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(500) DEFAULT NULL,

    display_order INT DEFAULT 100,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_discovery_log
-- Search analytics and logging
CREATE TABLE IF NOT EXISTS heritage_discovery_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    query_text VARCHAR(500) DEFAULT NULL,
    detected_language VARCHAR(10) DEFAULT 'en',
    query_intent VARCHAR(50) DEFAULT NULL,
    parsed_entities JSON DEFAULT NULL,
    expanded_terms JSON DEFAULT NULL,
    filters_applied JSON DEFAULT NULL,
    result_count INT DEFAULT 0,
    click_count INT DEFAULT 0,
    first_click_position INT DEFAULT NULL,

    user_id INT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,

    search_duration_ms INT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    INDEX idx_query (query_text(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DISCOVERY ENGINE TABLES
-- =============================================================================

-- Table: heritage_discovery_click
-- Track user clicks on search results for learning
CREATE TABLE IF NOT EXISTS heritage_discovery_click (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    search_log_id BIGINT NOT NULL,
    item_id INT NOT NULL,
    item_type VARCHAR(50) DEFAULT 'information_object',
    position INT NOT NULL,
    time_to_click_ms INT DEFAULT NULL,
    dwell_time_seconds INT DEFAULT NULL,

    session_id VARCHAR(100) DEFAULT NULL,
    user_id INT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_search_log (search_log_id),
    INDEX idx_item (item_id),
    INDEX idx_session (session_id),
    INDEX idx_created (created_at),

    CONSTRAINT fk_discovery_click_log
        FOREIGN KEY (search_log_id) REFERENCES heritage_discovery_log(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_learned_term
-- Learned synonyms and term relationships from user behavior
CREATE TABLE IF NOT EXISTS heritage_learned_term (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    term VARCHAR(255) NOT NULL,
    related_term VARCHAR(255) NOT NULL,
    relationship_type VARCHAR(57) COMMENT 'synonym, broader, narrower, related, spelling' DEFAULT 'related',
    confidence_score DECIMAL(5,4) DEFAULT 0.5,
    usage_count INT DEFAULT 1,

    source VARCHAR(52) COMMENT 'user_behavior, admin, taxonomy, external' DEFAULT 'user_behavior',
    is_verified TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_term_pair (institution_id, term, related_term),
    INDEX idx_term (term),
    INDEX idx_related (related_term),
    INDEX idx_confidence (confidence_score),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_search_suggestion
-- Autocomplete suggestions built from successful searches
CREATE TABLE IF NOT EXISTS heritage_search_suggestion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    suggestion_text VARCHAR(255) NOT NULL,
    suggestion_type VARCHAR(49) COMMENT 'query, title, subject, creator, place' DEFAULT 'query',

    search_count INT DEFAULT 1,
    click_count INT DEFAULT 0,
    success_rate DECIMAL(5,4) DEFAULT 0.5,
    avg_results INT DEFAULT 0,

    last_searched_at TIMESTAMP NULL,
    is_curated TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_suggestion (institution_id, suggestion_text, suggestion_type),
    INDEX idx_text (suggestion_text),
    INDEX idx_type (suggestion_type),
    INDEX idx_search_count (search_count DESC),
    INDEX idx_success_rate (success_rate DESC),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_ranking_config
-- Configurable ranking weights per institution
CREATE TABLE IF NOT EXISTS heritage_ranking_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Relevance weights
    weight_title_match DECIMAL(4,3) DEFAULT 1.000,
    weight_content_match DECIMAL(4,3) DEFAULT 0.700,
    weight_identifier_match DECIMAL(4,3) DEFAULT 0.900,
    weight_subject_match DECIMAL(4,3) DEFAULT 0.800,
    weight_creator_match DECIMAL(4,3) DEFAULT 0.800,

    -- Quality weights
    weight_has_digital_object DECIMAL(4,3) DEFAULT 0.300,
    weight_description_length DECIMAL(4,3) DEFAULT 0.200,
    weight_has_dates DECIMAL(4,3) DEFAULT 0.150,
    weight_has_subjects DECIMAL(4,3) DEFAULT 0.150,

    -- Engagement weights
    weight_view_count DECIMAL(4,3) DEFAULT 0.100,
    weight_download_count DECIMAL(4,3) DEFAULT 0.150,
    weight_citation_count DECIMAL(4,3) DEFAULT 0.200,

    -- Boost/penalty
    boost_featured DECIMAL(4,3) DEFAULT 1.500,
    boost_recent DECIMAL(4,3) DEFAULT 1.100,
    penalty_incomplete DECIMAL(4,3) DEFAULT 0.800,

    -- Freshness decay
    freshness_decay_days INT DEFAULT 365,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_entity_cache
-- Cached extracted entities for faster filtering
CREATE TABLE IF NOT EXISTS heritage_entity_cache (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,

    entity_type VARCHAR(58) COMMENT 'person, organization, place, date, event, work' NOT NULL,
    entity_value VARCHAR(500) NOT NULL,
    normalized_value VARCHAR(500) DEFAULT NULL,
    confidence_score DECIMAL(5,4) DEFAULT 1.0,

    source_field VARCHAR(100) DEFAULT NULL,
    extraction_method VARCHAR(42) COMMENT 'taxonomy, ner, pattern, manual' DEFAULT 'taxonomy',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_entity_type (entity_type),
    INDEX idx_entity_value (entity_value(100)),
    INDEX idx_normalized (normalized_value(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- CONTRIBUTOR SYSTEM TABLES
-- =============================================================================

-- Table: heritage_contributor
-- Public user accounts (separate from AtoM users)
CREATE TABLE IF NOT EXISTS heritage_contributor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    trust_level VARCHAR(45) COMMENT 'new, contributor, trusted, expert' DEFAULT 'new',
    email_verified TINYINT(1) DEFAULT 0,
    email_verify_token VARCHAR(100) DEFAULT NULL,
    email_verify_expires TIMESTAMP NULL,
    password_reset_token VARCHAR(100) DEFAULT NULL,
    password_reset_expires TIMESTAMP NULL,
    total_contributions INT DEFAULT 0,
    approved_contributions INT DEFAULT 0,
    rejected_contributions INT DEFAULT 0,
    points INT DEFAULT 0,
    badges JSON DEFAULT NULL,
    preferences JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    last_contribution_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_trust_level (trust_level),
    INDEX idx_points (points DESC),
    INDEX idx_verified (email_verified),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contribution_type
-- Types of contributions users can make
CREATE TABLE IF NOT EXISTS heritage_contribution_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'bi-pencil',
    color VARCHAR(20) DEFAULT 'primary',
    requires_validation TINYINT(1) DEFAULT 1,
    points_value INT DEFAULT 10,
    min_trust_level VARCHAR(45) COMMENT 'new, contributor, trusted, expert' DEFAULT 'new',
    display_order INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    config_json JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_active (is_active),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contribution
-- Individual contributions from users
CREATE TABLE IF NOT EXISTS heritage_contribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    information_object_id INT NOT NULL,
    contribution_type_id INT NOT NULL,
    content JSON NOT NULL,
    status VARCHAR(51) COMMENT 'pending, approved, rejected, superseded' DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT DEFAULT NULL,
    points_awarded INT DEFAULT 0,
    version_number INT DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_contributor (contributor_id),
    INDEX idx_object (information_object_id),
    INDEX idx_type (contribution_type_id),
    INDEX idx_status (status),
    INDEX idx_reviewed_by (reviewed_by),
    INDEX idx_created (created_at),
    INDEX idx_featured (is_featured),

    CONSTRAINT fk_heritage_contribution_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_heritage_contribution_type
        FOREIGN KEY (contribution_type_id) REFERENCES heritage_contribution_type(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contribution_version
-- Version history for contribution edits
CREATE TABLE IF NOT EXISTS heritage_contribution_version (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contribution_id INT NOT NULL,
    version_number INT NOT NULL,
    content JSON NOT NULL,
    created_by INT NOT NULL,
    change_summary VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_contribution (contribution_id),
    INDEX idx_version (contribution_id, version_number),

    CONSTRAINT fk_heritage_contribution_version_contribution
        FOREIGN KEY (contribution_id) REFERENCES heritage_contribution(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_heritage_contribution_version_creator
        FOREIGN KEY (created_by) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contributor_session
-- Session tokens for contributor authentication
CREATE TABLE IF NOT EXISTS heritage_contributor_session (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_contributor (contributor_id),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),

    CONSTRAINT fk_heritage_contributor_session_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contributor_badge
-- Badges that can be earned
CREATE TABLE IF NOT EXISTS heritage_contributor_badge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'bi-award',
    color VARCHAR(20) DEFAULT 'primary',
    criteria_type VARCHAR(76) COMMENT 'contribution_count, approval_rate, points, type_specific, manual' DEFAULT 'contribution_count',
    criteria_value INT DEFAULT 0,
    criteria_config JSON DEFAULT NULL,
    points_bonus INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contributor_badge_award
-- Badges awarded to contributors
CREATE TABLE IF NOT EXISTS heritage_contributor_badge_award (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    badge_id INT NOT NULL,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_contributor_badge (contributor_id, badge_id),

    CONSTRAINT fk_heritage_badge_award_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_heritage_badge_award_badge
        FOREIGN KEY (badge_id) REFERENCES heritage_contributor_badge(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ADMIN CONFIGURATION TABLES (Session 8)
-- =============================================================================

-- Table: heritage_feature_toggle
-- Feature flags per institution
CREATE TABLE IF NOT EXISTS heritage_feature_toggle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    feature_code VARCHAR(100) NOT NULL,
    feature_name VARCHAR(255) NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    config_json JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution_feature (institution_id, feature_code),
    INDEX idx_feature_code (feature_code),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_branding_config
-- Institution branding configuration
CREATE TABLE IF NOT EXISTS heritage_branding_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    logo_path VARCHAR(500) DEFAULT NULL,
    favicon_path VARCHAR(500) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#0d6efd',
    secondary_color VARCHAR(7) DEFAULT NULL,
    accent_color VARCHAR(7) DEFAULT NULL,
    banner_text VARCHAR(500) DEFAULT NULL,
    footer_text TEXT DEFAULT NULL,
    custom_css TEXT DEFAULT NULL,
    social_links JSON DEFAULT NULL,
    contact_info JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ACCESS MEDIATION TABLES (Session 6)
-- =============================================================================

-- Table: heritage_trust_level
-- User trust levels for access control
CREATE TABLE IF NOT EXISTS heritage_trust_level (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    level INT NOT NULL DEFAULT 0,
    can_view_restricted TINYINT(1) DEFAULT 0,
    can_download TINYINT(1) DEFAULT 0,
    can_bulk_download TINYINT(1) DEFAULT 0,
    is_system TINYINT(1) DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_level (level),
    INDEX idx_system (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_user_trust
-- User trust level assignments
CREATE TABLE IF NOT EXISTS heritage_user_trust (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trust_level_id INT NOT NULL,
    institution_id INT DEFAULT NULL,
    granted_by INT DEFAULT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,

    UNIQUE KEY uk_user_institution (user_id, institution_id),
    INDEX idx_trust_level (trust_level_id),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active),

    CONSTRAINT fk_heritage_user_trust_level
        FOREIGN KEY (trust_level_id) REFERENCES heritage_trust_level(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_purpose
-- Purposes for access requests
CREATE TABLE IF NOT EXISTS heritage_purpose (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    requires_approval TINYINT(1) DEFAULT 0,
    min_trust_level INT DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 100,

    INDEX idx_enabled (is_enabled),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_embargo
-- Embargoes on objects
CREATE TABLE IF NOT EXISTS heritage_embargo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    embargo_type VARCHAR(47) COMMENT 'full, digital_only, metadata_hidden' DEFAULT 'full',
    reason TEXT DEFAULT NULL,
    legal_basis VARCHAR(255) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    auto_release TINYINT(1) DEFAULT 1,
    notify_on_release TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_end_date (end_date),
    INDEX idx_type (embargo_type),
    INDEX idx_auto_release (auto_release, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_access_request
-- Access requests from users
CREATE TABLE IF NOT EXISTS heritage_access_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    object_id INT NOT NULL,
    purpose_id INT DEFAULT NULL,
    purpose_text VARCHAR(255) DEFAULT NULL,
    justification TEXT DEFAULT NULL,
    research_description TEXT DEFAULT NULL,
    institution_affiliation VARCHAR(255) DEFAULT NULL,
    status VARCHAR(57) COMMENT 'pending, approved, denied, expired, withdrawn' DEFAULT 'pending',
    decision_by INT DEFAULT NULL,
    decision_at TIMESTAMP NULL,
    decision_notes TEXT DEFAULT NULL,
    valid_from DATE DEFAULT NULL,
    valid_until DATE DEFAULT NULL,
    access_granted JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_object (object_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),

    CONSTRAINT fk_heritage_access_request_purpose
        FOREIGN KEY (purpose_id) REFERENCES heritage_purpose(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_access_rule
-- Access rules for objects/collections
CREATE TABLE IF NOT EXISTS heritage_access_rule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT DEFAULT NULL,
    collection_id INT DEFAULT NULL,
    repository_id INT DEFAULT NULL,
    rule_type VARCHAR(41) COMMENT 'allow, deny, require_approval' DEFAULT 'deny',
    applies_to VARCHAR(54) COMMENT 'all, anonymous, authenticated, trust_level' DEFAULT 'all',
    trust_level_id INT DEFAULT NULL,
    action VARCHAR(70) COMMENT 'view, view_metadata, download, download_master, print, all' DEFAULT 'view',
    priority INT DEFAULT 100,
    is_enabled TINYINT(1) DEFAULT 1,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_collection (collection_id),
    INDEX idx_repository (repository_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_popia_flag
-- POPIA/GDPR privacy flags
CREATE TABLE IF NOT EXISTS heritage_popia_flag (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    flag_type VARCHAR(116) COMMENT 'personal_info, sensitive, children, health, biometric, criminal, financial, political, religious, sexual' NOT NULL,
    severity VARCHAR(39) COMMENT 'low, medium, high, critical' DEFAULT 'medium',
    description TEXT DEFAULT NULL,
    affected_fields JSON DEFAULT NULL,
    detected_by VARCHAR(37) COMMENT 'automatic, manual, review' DEFAULT 'manual',
    is_resolved TINYINT(1) DEFAULT 0,
    resolution_notes TEXT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_at TIMESTAMP NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_flag_type (flag_type),
    INDEX idx_severity (severity),
    INDEX idx_resolved (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- CUSTODIAN INTERFACE TABLES (Session 7)
-- =============================================================================

-- Table: heritage_audit_log
-- Detailed change tracking
CREATE TABLE IF NOT EXISTS heritage_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(255) DEFAULT NULL,
    object_id INT DEFAULT NULL,
    object_type VARCHAR(100) DEFAULT 'information_object',
    object_identifier VARCHAR(255) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    action_category VARCHAR(79) COMMENT 'create, update, delete, view, export, import, batch, access, system' DEFAULT 'update',
    field_name VARCHAR(100) DEFAULT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    changes_json JSON DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id, object_type),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_category (action_category),
    INDEX idx_created (created_at),
    INDEX idx_field (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_batch_job
-- Batch job tracking
CREATE TABLE IF NOT EXISTS heritage_batch_job (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(100) NOT NULL,
    job_name VARCHAR(255) DEFAULT NULL,
    status VARCHAR(77) COMMENT 'pending, queued, processing, completed, failed, cancelled, paused' DEFAULT 'pending',
    user_id INT NOT NULL,
    total_items INT DEFAULT 0,
    processed_items INT DEFAULT 0,
    successful_items INT DEFAULT 0,
    failed_items INT DEFAULT 0,
    skipped_items INT DEFAULT 0,
    parameters JSON DEFAULT NULL,
    results JSON DEFAULT NULL,
    error_log JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    progress_message VARCHAR(500) DEFAULT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_type (job_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_batch_item
-- Individual items in a batch job
CREATE TABLE IF NOT EXISTS heritage_batch_item (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    object_id INT NOT NULL,
    status VARCHAR(57) COMMENT 'pending, processing, success, failed, skipped' DEFAULT 'pending',
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_job (job_id),
    INDEX idx_object (object_id),
    INDEX idx_status (status),

    CONSTRAINT fk_heritage_batch_item_job
        FOREIGN KEY (job_id) REFERENCES heritage_batch_job(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ANALYTICS & LEARNING TABLES (Session 9)
-- =============================================================================

-- Table: heritage_analytics_daily
-- Daily aggregate metrics
CREATE TABLE IF NOT EXISTS heritage_analytics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    date DATE NOT NULL,
    metric_type VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2) DEFAULT 0,
    previous_value DECIMAL(15,2) DEFAULT NULL,
    change_percent DECIMAL(10,2) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_date_metric (institution_id, date, metric_type),
    INDEX idx_date (date),
    INDEX idx_metric_type (metric_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_analytics_search
-- Search pattern tracking
CREATE TABLE IF NOT EXISTS heritage_analytics_search (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    date DATE NOT NULL,
    query_pattern VARCHAR(255) DEFAULT NULL,
    query_normalized VARCHAR(255) DEFAULT NULL,
    search_count INT DEFAULT 0,
    click_count INT DEFAULT 0,
    zero_result_count INT DEFAULT 0,
    avg_results DECIMAL(10,2) DEFAULT 0,
    avg_position_clicked DECIMAL(5,2) DEFAULT NULL,
    conversion_rate DECIMAL(5,4) DEFAULT 0,

    UNIQUE KEY uk_date_pattern (institution_id, date, query_pattern),
    INDEX idx_date (date),
    INDEX idx_search_count (search_count DESC),
    INDEX idx_zero_result (zero_result_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_analytics_content
-- Content performance tracking
CREATE TABLE IF NOT EXISTS heritage_analytics_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    view_count INT DEFAULT 0,
    unique_viewers INT DEFAULT 0,
    search_appearances INT DEFAULT 0,
    download_count INT DEFAULT 0,
    citation_count INT DEFAULT 0,
    share_count INT DEFAULT 0,
    avg_dwell_time_seconds INT DEFAULT NULL,
    click_through_rate DECIMAL(5,4) DEFAULT 0,
    bounce_rate DECIMAL(5,4) DEFAULT NULL,
    metadata JSON DEFAULT NULL,

    UNIQUE KEY uk_object_period (object_id, period_start, period_end),
    INDEX idx_period (period_start, period_end),
    INDEX idx_views (view_count DESC),
    INDEX idx_ctr (click_through_rate DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_analytics_alert
-- Actionable alerts and insights
CREATE TABLE IF NOT EXISTS heritage_analytics_alert (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    alert_type VARCHAR(100) NOT NULL,
    category VARCHAR(65) COMMENT 'content, search, access, quality, system, opportunity' DEFAULT 'system',
    severity VARCHAR(44) COMMENT 'info, warning, critical, success' DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT DEFAULT NULL,
    action_url VARCHAR(500) DEFAULT NULL,
    action_label VARCHAR(100) DEFAULT NULL,
    related_data JSON DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_dismissed TINYINT(1) DEFAULT 0,
    dismissed_by INT DEFAULT NULL,
    dismissed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_type (alert_type),
    INDEX idx_category (category),
    INDEX idx_severity (severity),
    INDEX idx_dismissed (is_dismissed),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_content_quality
-- Content quality scores
CREATE TABLE IF NOT EXISTS heritage_content_quality (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL UNIQUE,
    overall_score DECIMAL(5,2) DEFAULT 0,
    completeness_score DECIMAL(5,2) DEFAULT 0,
    accessibility_score DECIMAL(5,2) DEFAULT 0,
    engagement_score DECIMAL(5,2) DEFAULT 0,
    discoverability_score DECIMAL(5,2) DEFAULT 0,
    issues JSON DEFAULT NULL,
    suggestions JSON DEFAULT NULL,
    last_calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_overall (overall_score DESC),
    INDEX idx_completeness (completeness_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ENHANCED LANDING PAGE TABLES
-- =============================================================================

-- Table: heritage_featured_collection
-- Curated collections for showcase on landing page
CREATE TABLE IF NOT EXISTS heritage_featured_collection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    curator_note TEXT DEFAULT NULL,

    -- Visual
    cover_image VARCHAR(500) DEFAULT NULL,
    thumbnail_image VARCHAR(500) DEFAULT NULL,
    background_color VARCHAR(7) DEFAULT NULL,
    text_color VARCHAR(7) DEFAULT '#ffffff',

    -- Link
    link_type VARCHAR(52) COMMENT 'collection, search, repository, external' DEFAULT 'search',
    link_reference VARCHAR(500) DEFAULT NULL,
    collection_id INT DEFAULT NULL,
    repository_id INT DEFAULT NULL,
    search_query JSON DEFAULT NULL,

    -- Stats (cached)
    item_count INT DEFAULT 0,
    image_count INT DEFAULT 0,

    -- Display
    display_size VARCHAR(42) COMMENT 'small, medium, large, featured' DEFAULT 'medium',
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,

    -- Scheduling
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,

    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_featured (is_featured),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_link_type (link_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_hero_slide
-- Full-bleed hero carousel slides
CREATE TABLE IF NOT EXISTS heritage_hero_slide (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    title VARCHAR(255) DEFAULT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,

    -- Media
    image_path VARCHAR(500) NOT NULL,
    image_alt VARCHAR(255) DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    media_type VARCHAR(24) COMMENT 'image, video' DEFAULT 'image',

    -- Visual effects
    overlay_type VARCHAR(33) COMMENT 'none, gradient, solid' DEFAULT 'gradient',
    overlay_color VARCHAR(7) DEFAULT '#000000',
    overlay_opacity DECIMAL(3,2) DEFAULT 0.50,
    text_position VARCHAR(58) COMMENT 'left, center, right, bottom-left, bottom-right' DEFAULT 'left',
    ken_burns TINYINT(1) DEFAULT 1,

    -- Call to action
    cta_text VARCHAR(100) DEFAULT NULL,
    cta_url VARCHAR(500) DEFAULT NULL,
    cta_style VARCHAR(46) COMMENT 'primary, secondary, outline, light' DEFAULT 'primary',

    -- Attribution
    source_item_id INT DEFAULT NULL,
    source_collection VARCHAR(255) DEFAULT NULL,
    photographer_credit VARCHAR(255) DEFAULT NULL,

    -- Display
    display_order INT DEFAULT 100,
    display_duration INT DEFAULT 8,
    is_enabled TINYINT(1) DEFAULT 1,

    -- Scheduling
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: heritage_featured_collection already defined above (line 940)

-- Table: heritage_explore_category
-- Visual browse categories (Time, Place, People, Theme, Format, Trending)
CREATE TABLE IF NOT EXISTS heritage_explore_category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    tagline VARCHAR(255) DEFAULT NULL,

    -- Visual
    icon VARCHAR(50) DEFAULT 'bi-grid',
    cover_image VARCHAR(500) DEFAULT NULL,
    background_color VARCHAR(7) DEFAULT '#0d6efd',
    text_color VARCHAR(7) DEFAULT '#ffffff',

    -- Data source
    source_type VARCHAR(53) COMMENT 'taxonomy, authority, field, facet, custom' NOT NULL,
    source_reference VARCHAR(255) DEFAULT NULL,
    taxonomy_id INT DEFAULT NULL,

    -- Display configuration
    display_style VARCHAR(47) COMMENT 'grid, list, timeline, map, carousel' DEFAULT 'grid',
    items_per_page INT DEFAULT 24,
    show_counts TINYINT(1) DEFAULT 1,
    show_thumbnails TINYINT(1) DEFAULT 1,

    -- Landing page display
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    landing_items INT DEFAULT 6,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution_code (institution_id, code),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_source_type (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_timeline_period
-- Time periods for timeline navigation
CREATE TABLE IF NOT EXISTS heritage_timeline_period (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    name VARCHAR(100) NOT NULL,
    short_name VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,

    -- Date range
    start_year INT NOT NULL,
    end_year INT DEFAULT NULL,
    circa TINYINT(1) DEFAULT 0,

    -- Visual
    cover_image VARCHAR(500) DEFAULT NULL,
    thumbnail_image VARCHAR(500) DEFAULT NULL,
    background_color VARCHAR(7) DEFAULT NULL,

    -- Search integration
    search_query JSON DEFAULT NULL,
    date_field VARCHAR(100) DEFAULT 'dates',

    -- Stats (cached)
    item_count INT DEFAULT 0,

    -- Display
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_years (start_year, end_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SEED DATA
-- =============================================================================

-- Default filter types
INSERT IGNORE INTO heritage_filter_type (code, name, icon, source_type, source_reference, is_system) VALUES
('content_type', 'Format', 'bi-file-earmark', 'taxonomy', 'contentType', 1),
('time_period', 'Time Period', 'bi-calendar', 'field', 'date', 1),
('place', 'Place', 'bi-geo-alt', 'authority', 'place', 1),
('subject', 'Subject', 'bi-tag', 'taxonomy', 'subject', 1),
('creator', 'Creator', 'bi-person', 'authority', 'actor', 1),
('collection', 'Collection', 'bi-collection', 'field', 'repository', 1),
('language', 'Language', 'bi-translate', 'taxonomy', 'language', 1),
('glam_sector', 'Type', 'bi-building', 'taxonomy', 'glamSector', 1);

-- NER entity filter types (AI-extracted)
INSERT IGNORE INTO heritage_filter_type (code, name, icon, source_type, source_reference, is_system) VALUES
('ner_person', 'People (AI)', 'bi-person-badge', 'entity_cache', 'person', 0),
('ner_organization', 'Organizations (AI)', 'bi-building', 'entity_cache', 'organization', 0),
('ner_place', 'Places (AI)', 'bi-geo-alt-fill', 'entity_cache', 'place', 0),
('ner_date', 'Dates (AI)', 'bi-calendar-date', 'entity_cache', 'date', 0);

-- Default landing config
INSERT IGNORE INTO heritage_landing_config (id, institution_id, hero_tagline, hero_subtext, hero_search_placeholder, suggested_searches, stats_config) VALUES
(1, NULL, 'Discover Our Heritage', 'Explore collections spanning centuries of history, culture, and human achievement', 'Search photographs, documents, artifacts...', '["photographs", "maps", "letters", "newspapers"]', '{"show_items": true, "show_collections": true, "show_contributors": false}');

-- Default institution filters (only insert if not exists for global scope)
INSERT IGNORE INTO heritage_institution_filter (institution_id, filter_type_id, is_enabled, display_order, show_on_landing, show_in_search, max_items_landing)
SELECT NULL, ft.id, 1,
    CASE ft.code
        WHEN 'content_type' THEN 10
        WHEN 'time_period' THEN 20
        WHEN 'place' THEN 30
        WHEN 'subject' THEN 40
        WHEN 'creator' THEN 50
        WHEN 'collection' THEN 60
        WHEN 'language' THEN 70
        WHEN 'glam_sector' THEN 80
    END,
    CASE WHEN ft.code IN ('content_type', 'time_period', 'place', 'subject', 'creator', 'collection') THEN 1 ELSE 0 END,
    1,
    6
FROM heritage_filter_type ft
WHERE ft.is_system = 1
AND NOT EXISTS (
    SELECT 1 FROM heritage_institution_filter hif
    WHERE hif.filter_type_id = ft.id
    AND hif.institution_id IS NULL
);

-- Default ranking config
INSERT IGNORE INTO heritage_ranking_config (institution_id) VALUES (NULL);

-- Default learned terms (common synonyms)
INSERT IGNORE INTO heritage_learned_term (institution_id, term, related_term, relationship_type, confidence_score, source, is_verified) VALUES
(NULL, 'photo', 'photograph', 'synonym', 0.95, 'admin', 1),
(NULL, 'photos', 'photographs', 'synonym', 0.95, 'admin', 1),
(NULL, 'picture', 'photograph', 'synonym', 0.90, 'admin', 1),
(NULL, 'image', 'photograph', 'related', 0.85, 'admin', 1),
(NULL, 'doc', 'document', 'synonym', 0.90, 'admin', 1),
(NULL, 'letter', 'correspondence', 'related', 0.85, 'admin', 1),
(NULL, 'memo', 'memorandum', 'synonym', 0.95, 'admin', 1),
(NULL, 'map', 'cartographic material', 'related', 0.80, 'admin', 1),
(NULL, 'chart', 'map', 'related', 0.75, 'admin', 1),
(NULL, 'old', 'historic', 'related', 0.70, 'admin', 1),
(NULL, 'ancient', 'historic', 'related', 0.75, 'admin', 1),
(NULL, 'vintage', 'historic', 'related', 0.80, 'admin', 1),
(NULL, 'antique', 'historic', 'related', 0.75, 'admin', 1),
(NULL, 'arcive', 'archive', 'spelling', 0.99, 'admin', 1),
(NULL, 'photgraph', 'photograph', 'spelling', 0.99, 'admin', 1),
(NULL, 'documnet', 'document', 'spelling', 0.99, 'admin', 1);

-- Default contribution types
INSERT IGNORE INTO heritage_contribution_type (code, name, description, icon, color, requires_validation, points_value, display_order) VALUES
('transcription', 'Transcription', 'Transcribe handwritten or typed documents into searchable text', 'bi-file-text', 'primary', 1, 25, 1),
('identification', 'Identification', 'Identify people, places, or objects in photographs and documents', 'bi-person-badge', 'success', 1, 15, 2),
('context', 'Historical Context', 'Add historical context, personal memories, or background information', 'bi-book', 'info', 1, 20, 3),
('correction', 'Correction', 'Suggest corrections to existing metadata or descriptions', 'bi-pencil-square', 'warning', 1, 10, 4),
('translation', 'Translation', 'Translate content into other languages', 'bi-translate', 'secondary', 1, 30, 5),
('tag', 'Tags/Keywords', 'Add relevant tags and keywords to improve discoverability', 'bi-tags', 'dark', 0, 5, 6);

-- Default badges
INSERT IGNORE INTO heritage_contributor_badge (code, name, description, icon, color, criteria_type, criteria_value, display_order) VALUES
('first_contribution', 'First Steps', 'Made your first contribution', 'bi-star', 'warning', 'contribution_count', 1, 1),
('contributor_10', 'Active Contributor', 'Made 10 approved contributions', 'bi-star-fill', 'warning', 'contribution_count', 10, 2),
('contributor_50', 'Dedicated Contributor', 'Made 50 approved contributions', 'bi-trophy', 'warning', 'contribution_count', 50, 3),
('contributor_100', 'Heritage Champion', 'Made 100 approved contributions', 'bi-trophy-fill', 'primary', 'contribution_count', 100, 4),
('transcriber', 'Transcription Expert', 'Completed 25 transcriptions', 'bi-file-text-fill', 'primary', 'type_specific', 25, 10),
('identifier', 'Sharp Eye', 'Identified people in 25 photographs', 'bi-eye', 'success', 'type_specific', 25, 11),
('historian', 'Local Historian', 'Added context to 25 records', 'bi-book-fill', 'info', 'type_specific', 25, 12),
('perfectionist', 'High Quality', 'Maintained 95% approval rate on 20+ contributions', 'bi-check-circle-fill', 'success', 'approval_rate', 95, 20);

-- Default trust levels
INSERT IGNORE INTO heritage_trust_level (code, name, level, can_view_restricted, can_download, can_bulk_download, is_system, description) VALUES
('anonymous', 'Anonymous', 0, 0, 0, 0, 1, 'Unauthenticated visitors'),
('registered', 'Registered User', 1, 0, 1, 0, 1, 'Basic registered account'),
('contributor', 'Contributor', 2, 0, 1, 0, 1, 'Users who contribute content'),
('trusted', 'Trusted User', 3, 1, 1, 0, 1, 'Verified trusted researchers'),
('moderator', 'Moderator', 4, 1, 1, 1, 1, 'Content moderators'),
('custodian', 'Custodian', 5, 1, 1, 1, 1, 'Full custodial access');

-- Default purposes
INSERT IGNORE INTO heritage_purpose (code, name, description, requires_approval, min_trust_level, display_order) VALUES
('personal', 'Personal/Family Research', 'Research into family history and genealogy', 0, 0, 1),
('academic', 'Academic Research', 'Scholarly research for educational institutions', 0, 0, 2),
('education', 'Educational Use', 'Use in teaching and educational materials', 0, 0, 3),
('commercial', 'Commercial Use', 'For-profit use requiring license agreement', 1, 1, 4),
('media', 'Media/Journalism', 'Publication in news or media outlets', 1, 1, 5),
('legal', 'Legal/Compliance', 'Legal proceedings or compliance requirements', 1, 1, 6),
('government', 'Government/Official', 'Official government use', 1, 1, 7),
('preservation', 'Preservation/Conservation', 'Digital preservation activities', 0, 2, 8);

-- Default feature toggles
INSERT IGNORE INTO heritage_feature_toggle (institution_id, feature_code, feature_name, is_enabled, config_json) VALUES
(NULL, 'community_contributions', 'Community Contributions', 1, '{"require_moderation": true}'),
(NULL, 'user_registration', 'User Registration', 1, '{"require_email_verification": true}'),
(NULL, 'social_sharing', 'Social Sharing', 1, '{"platforms": ["facebook", "twitter", "linkedin", "email"]}'),
(NULL, 'downloads', 'Downloads', 1, '{"require_login": false, "track_downloads": true}'),
(NULL, 'citations', 'Citation Generation', 1, '{"formats": ["apa", "mla", "chicago", "harvard"]}'),
(NULL, 'analytics', 'Analytics Dashboard', 1, '{"admin_only": true}'),
(NULL, 'access_requests', 'Access Requests', 1, '{"email_notifications": true}'),
(NULL, 'embargoes', 'Embargo Management', 1, '{}'),
(NULL, 'batch_operations', 'Batch Operations', 1, '{"max_items": 1000}'),
(NULL, 'audit_trail', 'Audit Trail', 1, '{"retention_days": 365}');

-- Default branding
INSERT IGNORE INTO heritage_branding_config (institution_id, primary_color, secondary_color, banner_text, footer_text) VALUES
(NULL, '#0d6efd', '#6c757d', NULL, 'Powered by AtoM Heritage Platform');

-- Default explore categories
INSERT IGNORE INTO heritage_explore_category (institution_id, code, name, description, tagline, icon, source_type, source_reference, display_style, display_order, show_on_landing) VALUES
(NULL, 'time', 'Time', 'Browse by historical period', 'Journey through time', 'bi-clock-history', 'field', 'dates', 'timeline', 1, 1),
(NULL, 'place', 'Place', 'Browse by location', 'Explore by geography', 'bi-geo-alt', 'authority', 'place', 'map', 2, 1),
(NULL, 'people', 'People', 'Browse by person or creator', 'Discover the people', 'bi-people', 'authority', 'actor', 'grid', 3, 1),
(NULL, 'theme', 'Theme', 'Browse by subject', 'Explore by topic', 'bi-tag', 'taxonomy', 'subject', 'grid', 4, 1),
(NULL, 'format', 'Format', 'Browse by format type', 'Filter by media', 'bi-collection', 'taxonomy', 'contentType', 'grid', 5, 1),
(NULL, 'trending', 'Trending', 'Popular items this week', 'What people are viewing', 'bi-graph-up', 'custom', 'trending', 'carousel', 6, 1);

-- Default timeline periods (South African focused with international context)
INSERT IGNORE INTO heritage_timeline_period (institution_id, name, short_name, start_year, end_year, description, display_order, show_on_landing) VALUES
(NULL, 'Ancient World', 'Ancient', -5000, 499, 'Ancient civilisations, classical antiquity, and the foundations of recorded history - from early writing systems to the fall of Rome', 1, 1),
(NULL, 'Medieval Period', 'Medieval', 500, 1499, 'The Middle Ages across Europe, Asia, and Africa - feudalism, the Islamic Golden Age, Crusades, and early Renaissance', 2, 1),
(NULL, 'Early Modern Era', '1500-1799', 1500, 1799, 'Age of exploration, colonisation, the Reformation, Scientific Revolution, and the Enlightenment', 3, 1),
(NULL, '19th Century', '1800s', 1800, 1899, 'Industrial Revolution, nationalism, abolition movements, and the reshaping of global empires', 4, 1),
(NULL, 'World Wars and Upheaval', '1900-1945', 1900, 1945, 'Two World Wars, the fall of empires, the Great Depression, and the birth of modern geopolitics', 5, 1),
(NULL, 'Modern and Contemporary', '1946-Present', 1946, NULL, 'Cold War, decolonisation, civil rights, the digital revolution, and the contemporary world', 6, 1);

-- =============================================================================
-- KNOWLEDGE GRAPH TABLES
-- =============================================================================

-- Table: heritage_entity_graph_node
-- Canonical entities (deduplicated from cache)
CREATE TABLE IF NOT EXISTS heritage_entity_graph_node (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    entity_type VARCHAR(67) COMMENT 'person, organization, place, date, event, work, concept' NOT NULL,
    canonical_value VARCHAR(500) NOT NULL,
    normalized_value VARCHAR(500) NOT NULL,

    -- Linked authority records (if exists)
    actor_id INT DEFAULT NULL,
    term_id INT DEFAULT NULL,

    -- Metadata
    occurrence_count INT DEFAULT 1,
    confidence_avg DECIMAL(5,4) DEFAULT 1.0000,
    first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- External identifiers
    wikidata_id VARCHAR(20) DEFAULT NULL,
    viaf_id VARCHAR(50) DEFAULT NULL,

    -- Display metadata
    display_label VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    image_url VARCHAR(500) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_type_normalized (entity_type, normalized_value),
    INDEX idx_canonical (canonical_value(100)),
    INDEX idx_actor (actor_id),
    INDEX idx_term (term_id),
    INDEX idx_occurrence (occurrence_count DESC),
    INDEX idx_entity_type (entity_type),
    INDEX idx_wikidata (wikidata_id),
    INDEX idx_viaf (viaf_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_entity_graph_edge
-- Relationships between entities
CREATE TABLE IF NOT EXISTS heritage_entity_graph_edge (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_node_id BIGINT UNSIGNED NOT NULL,
    target_node_id BIGINT UNSIGNED NOT NULL,

    -- Relationship type
    relationship_type VARCHAR(153) COMMENT 'co_occurrence, mentioned_with, associated_with, employed_by, located_in, occurred_at, related_to, same_as, child_of, preceded_by, followed_by' NOT NULL DEFAULT 'co_occurrence',

    -- Strength metrics
    weight DECIMAL(8,4) DEFAULT 1.0000,
    co_occurrence_count INT DEFAULT 1,
    confidence DECIMAL(5,4) DEFAULT 1.0000,

    -- Source tracking
    source_object_ids JSON DEFAULT NULL,

    -- Metadata
    evidence TEXT DEFAULT NULL,
    is_inferred TINYINT(1) DEFAULT 0,
    is_verified TINYINT(1) DEFAULT 0,
    verified_by INT DEFAULT NULL,
    verified_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_edge (source_node_id, target_node_id, relationship_type),
    INDEX idx_source (source_node_id),
    INDEX idx_target (target_node_id),
    INDEX idx_type (relationship_type),
    INDEX idx_weight (weight DESC),
    INDEX idx_cooccurrence (co_occurrence_count DESC),

    CONSTRAINT fk_graph_edge_source
        FOREIGN KEY (source_node_id) REFERENCES heritage_entity_graph_node(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_graph_edge_target
        FOREIGN KEY (target_node_id) REFERENCES heritage_entity_graph_node(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_entity_graph_object
-- Object-to-node mapping (which objects contain which entities)
CREATE TABLE IF NOT EXISTS heritage_entity_graph_object (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    node_id BIGINT UNSIGNED NOT NULL,

    mention_count INT DEFAULT 1,
    confidence DECIMAL(5,4) DEFAULT 1.0000,
    source_field VARCHAR(100) DEFAULT NULL,
    extraction_method VARCHAR(42) COMMENT 'taxonomy, ner, pattern, manual' DEFAULT 'ner',

    -- Position info (for highlighting)
    positions JSON DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_object_node (object_id, node_id),
    INDEX idx_object (object_id),
    INDEX idx_node (node_id),
    INDEX idx_method (extraction_method),

    CONSTRAINT fk_graph_object_node
        FOREIGN KEY (node_id) REFERENCES heritage_entity_graph_node(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_graph_build_log
-- Track graph build operations
CREATE TABLE IF NOT EXISTS heritage_graph_build_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    build_type VARCHAR(41) COMMENT 'full, incremental, edges_only' NOT NULL DEFAULT 'incremental',
    status VARCHAR(38) COMMENT 'running, completed, failed' NOT NULL DEFAULT 'running',

    nodes_created INT DEFAULT 0,
    nodes_updated INT DEFAULT 0,
    edges_created INT DEFAULT 0,
    edges_updated INT DEFAULT 0,
    objects_processed INT DEFAULT 0,

    error_message TEXT DEFAULT NULL,

    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,

    INDEX idx_status (status),
    INDEX idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- VERIFICATION
-- =============================================================================
SELECT 'Heritage Plugin Installation Complete' as status;
SELECT
    (SELECT COUNT(*) FROM heritage_filter_type) as filter_types,
    (SELECT COUNT(*) FROM heritage_contribution_type) as contribution_types,
    (SELECT COUNT(*) FROM heritage_contributor_badge) as badges,
    (SELECT COUNT(*) FROM heritage_trust_level) as trust_levels,
    (SELECT COUNT(*) FROM heritage_purpose) as purposes,
    (SELECT COUNT(*) FROM heritage_feature_toggle) as feature_toggles,
    (SELECT COUNT(*) FROM heritage_explore_category) as explore_categories,
    (SELECT COUNT(*) FROM heritage_timeline_period) as timeline_periods;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Ported from AtoM ahgHeritageAccountingPlugin on 2026-04-30
-- ============================================================================
-- =============================================================================
-- ahgHeritageAccountingPlugin - Heritage Asset Accounting Tables
-- Multi-standard support (GRAP 103, FRS 102, GASB 34, IPSAS 45, etc.)
-- =============================================================================

-- Accounting Standards (admin-configurable)
CREATE TABLE IF NOT EXISTS `heritage_accounting_standard` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `country` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `capitalisation_required` TINYINT(1) DEFAULT 0,
    `valuation_methods` JSON NULL,
    `disclosure_requirements` JSON NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_standard_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asset Classes
CREATE TABLE IF NOT EXISTS `heritage_asset_class` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `parent_id` INT UNSIGNED NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_class_code` (`code`),
    KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Heritage Assets
CREATE TABLE IF NOT EXISTS `heritage_asset` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `object_id` INT NULL,
    `information_object_id` INT NULL,
    `accounting_standard_id` INT UNSIGNED NULL,
    `asset_class_id` INT UNSIGNED NULL,
    `asset_sub_class` VARCHAR(100) NULL,
    `recognition_status` VARCHAR(47) DEFAULT 'pending' COMMENT 'recognised, not_recognised, pending',
    `recognition_status_reason` TEXT NULL,
    `recognition_date` DATE NULL,
    `measurement_basis` VARCHAR(50) NULL,
    `acquisition_method` VARCHAR(50) NULL,
    `acquisition_date` DATE NULL,
    `acquisition_cost` DECIMAL(15,2) DEFAULT 0,
    `fair_value_at_acquisition` DECIMAL(15,2) NULL,
    `nominal_value` DECIMAL(15,2) DEFAULT 1,
    `donor_name` VARCHAR(255) NULL,
    `donor_restrictions` TEXT NULL,
    `initial_carrying_amount` DECIMAL(15,2) DEFAULT 0,
    `current_carrying_amount` DECIMAL(15,2) DEFAULT 0,
    `heritage_significance` VARCHAR(50) NULL,
    `significance_statement` TEXT NULL,
    `restrictions_on_use` TEXT NULL,
    `restrictions_on_disposal` TEXT NULL,
    `conservation_requirements` TEXT NULL,
    `insurance_required` TINYINT(1) DEFAULT 0,
    `insurance_value` DECIMAL(15,2) NULL,
    `insurance_policy_number` VARCHAR(100) NULL,
    `insurance_provider` VARCHAR(255) NULL,
    `insurance_expiry_date` DATE NULL,
    `current_location` VARCHAR(255) NULL,
    `storage_conditions` TEXT NULL,
    `condition_rating` VARCHAR(50) NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_object` (`object_id`),
    KEY `idx_information_object` (`information_object_id`),
    KEY `idx_standard` (`accounting_standard_id`),
    KEY `idx_class` (`asset_class_id`),
    KEY `idx_recognition` (`recognition_status`),
    FOREIGN KEY (`accounting_standard_id`) REFERENCES `heritage_accounting_standard`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`asset_class_id`) REFERENCES `heritage_asset_class`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valuations History
CREATE TABLE IF NOT EXISTS `heritage_valuation_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_id` INT UNSIGNED NOT NULL,
    `valuation_date` DATE NOT NULL,
    `valuation_type` VARCHAR(50) NULL,
    `previous_value` DECIMAL(15,2) NULL,
    `new_value` DECIMAL(15,2) NOT NULL,
    `valuation_method` VARCHAR(50) NULL,
    `valuer_name` VARCHAR(255) NULL,
    `valuer_credentials` VARCHAR(255) NULL,
    `valuer_organization` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_asset` (`asset_id`),
    KEY `idx_date` (`valuation_date`),
    FOREIGN KEY (`asset_id`) REFERENCES `heritage_asset`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Impairments
CREATE TABLE IF NOT EXISTS `heritage_impairment_assessment` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_id` INT UNSIGNED NOT NULL,
    `impairment_date` DATE NOT NULL,
    `impairment_type` VARCHAR(50) NULL,
    `carrying_amount_before` DECIMAL(15,2) NOT NULL,
    `impairment_loss` DECIMAL(15,2) NOT NULL,
    `carrying_amount_after` DECIMAL(15,2) NOT NULL,
    `reason` TEXT NOT NULL,
    `reversible` TINYINT(1) DEFAULT 0,
    `reversal_date` DATE NULL,
    `reversal_amount` DECIMAL(15,2) NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_asset` (`asset_id`),
    KEY `idx_date` (`impairment_date`),
    FOREIGN KEY (`asset_id`) REFERENCES `heritage_asset`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Movements/Transfers
CREATE TABLE IF NOT EXISTS `heritage_movement_register` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_id` INT UNSIGNED NOT NULL,
    `movement_date` DATE NOT NULL,
    `movement_type` VARCHAR(98) NOT NULL COMMENT 'acquisition, disposal, transfer, loan_out, loan_return, revaluation, impairment, other',
    `from_location` VARCHAR(255) NULL,
    `to_location` VARCHAR(255) NULL,
    `reason` TEXT NULL,
    `authorized_by` VARCHAR(255) NULL,
    `insurance_value` DECIMAL(15,2) NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_asset` (`asset_id`),
    KEY `idx_date` (`movement_date`),
    KEY `idx_type` (`movement_type`),
    FOREIGN KEY (`asset_id`) REFERENCES `heritage_asset`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Journal Entries
CREATE TABLE IF NOT EXISTS `heritage_journal_entry` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_id` INT UNSIGNED NOT NULL,
    `entry_date` DATE NOT NULL,
    `entry_type` VARCHAR(50) NOT NULL,
    `debit_account` VARCHAR(100) NULL,
    `credit_account` VARCHAR(100) NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `description` TEXT NULL,
    `reference` VARCHAR(100) NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_asset` (`asset_id`),
    KEY `idx_date` (`entry_date`),
    FOREIGN KEY (`asset_id`) REFERENCES `heritage_asset`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compliance Rules (database-driven)
CREATE TABLE IF NOT EXISTS `heritage_compliance_rule` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `standard_id` INT UNSIGNED NOT NULL,
    `category` VARCHAR(48) NOT NULL COMMENT 'recognition, measurement, disclosure',
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `check_type` VARCHAR(59) DEFAULT 'required_field' COMMENT 'required_field, value_check, date_check, custom',
    `field_name` VARCHAR(100) NULL,
    `condition` VARCHAR(255) NULL,
    `error_message` VARCHAR(255) NOT NULL,
    `reference` VARCHAR(100) NULL,
    `severity` VARCHAR(32) DEFAULT 'error' COMMENT 'error, warning, info',
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_standard` (`standard_id`),
    KEY `idx_category` (`category`),
    FOREIGN KEY (`standard_id`) REFERENCES `heritage_accounting_standard`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- Seed Data: Accounting Standards
-- =============================================================================

INSERT IGNORE INTO heritage_accounting_standard 
(code, name, country, description, capitalisation_required, valuation_methods, disclosure_requirements, is_active, sort_order)
VALUES
('GRAP103', 'GRAP 103 Heritage Assets', 'South Africa', 
 'Generally Recognised Accounting Practice - dedicated heritage assets standard for SA public sector',
 1, '["cost", "fair_value", "deemed_cost", "nominal"]',
 '["asset_class", "measurement_basis", "carrying_amount", "restrictions", "conservation"]', 1, 1),
('FRS102', 'FRS 102 Section 34', 'United Kingdom',
 'Financial Reporting Standard - Section 34.49-56 Heritage Assets',
 0, '["cost", "valuation", "nominal"]',
 '["nature_of_holdings", "policy", "carrying_amount", "restrictions"]', 1, 2),
('GASB34', 'GASB Statement 34', 'United States',
 'Governmental Accounting Standards Board - infrastructure and collections',
 0, '["cost", "fair_value"]',
 '["collection_description", "capitalisation_policy"]', 1, 3),
('FASB958', 'FASB ASC 958', 'United States',
 'Financial Accounting Standards Board - Not-for-profit entities collections',
 0, '["cost", "fair_value", "nominal"]',
 '["collection_description", "capitalisation_policy", "stewardship"]', 1, 4),
('PSAS3150', 'PSAS 3150 Tangible Capital Assets', 'Canada',
 'Public Sector Accounting Standard - includes heritage/works of art',
 0, '["cost", "deemed_cost"]',
 '["measurement_basis", "useful_life", "restrictions"]', 1, 5),
('IPSAS45', 'IPSAS 45 Property, Plant & Equipment', 'International (Africa)',
 'International Public Sector Accounting Standard - covers heritage assets. Used by Nigeria, Kenya, Ghana, Tanzania, Uganda, Rwanda, Botswana, Zimbabwe.',
 0, '["cost", "fair_value", "deemed_cost"]',
 '["asset_class", "measurement_basis", "useful_life", "depreciation_method", "reconciliation"]', 1, 6),
('IPSAS17', 'IPSAS 17 Property, Plant & Equipment (Legacy)', 'International',
 'Previous IPSAS standard for PPE including heritage - replaced by IPSAS 45',
 0, '["cost", "revaluation"]',
 '["measurement_basis", "depreciation", "reconciliation"]', 1, 7),
('AASB116', 'AASB 116 / PBE IPSAS 17', 'Australia / New Zealand',
 'Australian Accounting Standards Board - Property, Plant & Equipment including heritage. Based on IPSAS.',
 0, '["cost", "revaluation", "fair_value"]',
 '["measurement_basis", "depreciation_method", "useful_life", "reconciliation"]', 1, 8),
('IAS16', 'IAS 16 Property, Plant & Equipment', 'International (Private Sector)',
 'International Accounting Standard for private sector museums, galleries and cultural institutions.',
 0, '["cost", "revaluation"]',
 '["measurement_basis", "depreciation", "useful_life", "impairment"]', 1, 9),
('CUSTOM', 'Custom / Local Standard', 'Other / Custom',
 'For institutions using local accounting standards or custom requirements not covered by other standards.',
 0, '["cost", "fair_value", "nominal", "insurance", "replacement"]',
 NULL, 1, 99)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description);

-- =============================================================================
-- Seed Data: Asset Classes
-- =============================================================================

INSERT IGNORE INTO heritage_asset_class (code, name, description, is_active, sort_order) VALUES
('ART', 'Works of Art', 'Paintings, sculptures, prints, photographs', 1, 1),
('ARCH', 'Archives & Manuscripts', 'Historical documents, manuscripts, records', 1, 2),
('BOOKS', 'Rare Books & Libraries', 'Rare books, special collections', 1, 3),
('ARTIFACTS', 'Historical Artifacts', 'Objects of historical significance', 1, 4),
('NATURAL', 'Natural History', 'Specimens, fossils, geological samples', 1, 5),
('BUILDINGS', 'Heritage Buildings', 'Historic structures and monuments', 1, 6),
('LAND', 'Heritage Land & Sites', 'Archaeological sites, heritage landscapes', 1, 7),
('COLLECTIONS', 'Mixed Collections', 'Collections spanning multiple categories', 1, 8),
('INTANGIBLE', 'Intangible Heritage', 'Digital archives, recordings, oral histories', 1, 9),
('OTHER', 'Other Heritage Assets', 'Assets not classified elsewhere', 1, 99)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- =============================================================================
-- Seed Data: Compliance Rules
-- =============================================================================

-- GRAP 103 Rules (8 rules)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Asset Class Required' as name, 
           'Heritage asset must have an asset class' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset class is required for GRAP 103 compliance' as error_message,
           'GRAP 103.14' as reference, 'error' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Recognition Date Required', 
           'Date when asset was recognised must be recorded',
           'required_field', 'recognition_date', NULL,
           'Recognition date is required', 'GRAP 103.14', 'error', 2
    UNION ALL SELECT 'recognition', 'REC003', 'Significance Statement', 
           'Heritage significance must be documented',
           'required_field', 'significance_statement', NULL,
           'Heritage significance statement is required', 'GRAP 103.74', 'warning', 3
    UNION ALL SELECT 'measurement', 'MEA001', 'Measurement Basis Required', 
           'Measurement basis must be specified',
           'required_field', 'measurement_basis', NULL,
           'Measurement basis is required', 'GRAP 103.26', 'error', 10
    UNION ALL SELECT 'measurement', 'MEA002', 'Carrying Amount Required', 
           'Current carrying amount must be recorded',
           'value_check', 'current_carrying_amount', '>0',
           'Current carrying amount must be greater than zero', 'GRAP 103.26-28', 'error', 11
    UNION ALL SELECT 'measurement', 'MEA003', 'Acquisition Date', 
           'Acquisition date should be recorded',
           'required_field', 'acquisition_date', NULL,
           'Acquisition date is recommended', 'GRAP 103.36', 'warning', 12
    UNION ALL SELECT 'disclosure', 'DIS001', 'Restrictions on Use', 
           'Any restrictions on use must be disclosed',
           'required_field', 'restrictions_on_use', NULL,
           'Restrictions on use should be documented', 'GRAP 103.74(a)', 'warning', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Conservation Requirements', 
           'Conservation requirements must be disclosed',
           'required_field', 'conservation_requirements', NULL,
           'Conservation requirements should be documented', 'GRAP 103.74(b)', 'warning', 21
) r
WHERE s.code = 'GRAP103'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- FRS 102 Rules (9 rules)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Asset Class Required' as name,
           'Heritage asset must be classified' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset class is required' as error_message,
           'FRS 102.34.49' as reference, 'error' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Significance Statement',
           'Heritage characteristics must be documented',
           'required_field', 'significance_statement', NULL,
           'Heritage characteristics/significance should be documented', 'FRS 102.34.50', 'warning', 2
    UNION ALL SELECT 'recognition', 'REC003', 'Recognition Date',
           'Date of recognition/acquisition',
           'required_field', 'recognition_date', NULL,
           'Recognition date should be recorded', 'FRS 102.34.51', 'warning', 3
    UNION ALL SELECT 'measurement', 'MEA001', 'Measurement Basis',
           'Indicate if cost or valuation basis used',
           'required_field', 'measurement_basis', NULL,
           'Measurement basis should be specified', 'FRS 102.34.52', 'warning', 10
    UNION ALL SELECT 'measurement', 'MEA002', 'Carrying Amount',
           'Carrying amount if capitalised',
           'value_check', 'current_carrying_amount', '>=0',
           'Carrying amount should be recorded if capitalised', 'FRS 102.34.52', 'warning', 11
    UNION ALL SELECT 'disclosure', 'DIS001', 'Nature of Holdings',
           'Disclose nature and scale of heritage assets',
           'required_field', 'heritage_significance', NULL,
           'Nature of holdings should be documented', 'FRS 102.34.55', 'warning', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Preservation Policy',
           'Preservation and management policy',
           'required_field', 'conservation_requirements', NULL,
           'Preservation policy should be documented', 'FRS 102.34.55', 'info', 21
    UNION ALL SELECT 'disclosure', 'DIS003', 'Accounting Policy',
           'Disclose accounting policy adopted',
           'required_field', 'measurement_basis', NULL,
           'Accounting policy should be documented', 'FRS 102.34.55(a)', 'warning', 22
    UNION ALL SELECT 'disclosure', 'DIS004', 'Restrictions',
           'Disclose any restrictions on disposal',
           'required_field', 'restrictions_on_disposal', NULL,
           'Restrictions on disposal should be documented', 'FRS 102.34.55(c)', 'info', 23
) r
WHERE s.code = 'FRS102'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- GASB 34 Rules (8 rules)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Collection Designation' as name,
           'Must indicate if part of collection' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset class/collection designation required' as error_message,
           'GASB 34.27' as reference, 'error' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Acquisition Date',
           'Date asset was acquired', 'required_field', 'acquisition_date', NULL,
           'Acquisition date should be recorded', 'GASB 34.18', 'warning', 2
    UNION ALL SELECT 'recognition', 'REC003', 'Acquisition Method',
           'How asset was acquired', 'required_field', 'acquisition_method', NULL,
           'Acquisition method should be documented', 'GASB 34.18', 'warning', 3
    UNION ALL SELECT 'measurement', 'MEA001', 'Historical Cost',
           'Historical cost if capitalised', 'required_field', 'measurement_basis', NULL,
           'Measurement basis should be specified', 'GASB 34.18', 'warning', 10
    UNION ALL SELECT 'measurement', 'MEA002', 'Carrying Amount',
           'Current carrying amount', 'value_check', 'current_carrying_amount', '>=0',
           'Carrying amount should be recorded', 'GASB 34.19', 'warning', 11
    UNION ALL SELECT 'disclosure', 'DIS001', 'Collection Description',
           'Description of collection if not capitalised', 'required_field', 'significance_statement', NULL,
           'Collection description recommended', 'GASB 34.118', 'info', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Collection Criteria',
           'Criteria for adding to collection', 'required_field', 'heritage_significance', NULL,
           'Collection criteria should be documented', 'GASB 34.27', 'info', 21
    UNION ALL SELECT 'disclosure', 'DIS003', 'Conservation Policy',
           'Policy for preservation', 'required_field', 'conservation_requirements', NULL,
           'Conservation policy should be documented', 'GASB 34.27', 'info', 22
) r
WHERE s.code = 'GASB34'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- FASB 958 Rules (8 rules)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Collection Policy' as name,
           'Document collection capitalisation policy' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset classification required' as error_message,
           'FASB 958-360-25' as reference, 'error' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Acquisition Date',
           'Date item was acquired', 'required_field', 'acquisition_date', NULL,
           'Acquisition date should be recorded', 'FASB 958-360-25-2', 'warning', 2
    UNION ALL SELECT 'recognition', 'REC003', 'Donor Information',
           'Donor details for contributed items', 'required_field', 'donor_name', NULL,
           'Donor information recommended', 'FASB 958-605-25', 'info', 3
    UNION ALL SELECT 'measurement', 'MEA001', 'Fair Value',
           'Fair value at acquisition', 'required_field', 'measurement_basis', NULL,
           'Measurement basis should be specified', 'FASB 958-360-30-1', 'warning', 10
    UNION ALL SELECT 'measurement', 'MEA002', 'Carrying Amount',
           'Current carrying amount if capitalised', 'value_check', 'current_carrying_amount', '>=0',
           'Carrying amount should be recorded if capitalised', 'FASB 958-360-35', 'warning', 11
    UNION ALL SELECT 'disclosure', 'DIS001', 'Collection Description',
           'Description required for non-capitalised collections', 'required_field', 'significance_statement', NULL,
           'Collection description recommended', 'FASB 958-360-50', 'warning', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Capitalisation Policy',
           'Policy for capitalising vs not capitalising', 'required_field', 'recognition_status_reason', NULL,
           'Capitalisation policy should be documented', 'FASB 958-360-50-1', 'warning', 21
    UNION ALL SELECT 'disclosure', 'DIS003', 'Stewardship Activities',
           'Description of stewardship activities', 'required_field', 'conservation_requirements', NULL,
           'Stewardship activities should be documented', 'FASB 958-360-50-2', 'info', 22
) r
WHERE s.code = 'FASB958'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- PSAS 3150 Rules (8 rules)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Asset Classification' as name,
           'Tangible capital asset must be classified' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset class is required' as error_message,
           'PS 3150.08' as reference, 'error' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Recognition Date',
           'Date of recognition', 'required_field', 'recognition_date', NULL,
           'Recognition date should be recorded', 'PS 3150.10', 'warning', 2
    UNION ALL SELECT 'recognition', 'REC003', 'Useful Life Assessment',
           'Assessment of useful life', 'required_field', 'heritage_significance', NULL,
           'Useful life/significance assessment recommended', 'PS 3150.22', 'info', 3
    UNION ALL SELECT 'measurement', 'MEA001', 'Historical Cost',
           'Record at historical cost where determinable', 'required_field', 'measurement_basis', NULL,
           'Measurement basis should be specified', 'PS 3150.15', 'warning', 10
    UNION ALL SELECT 'measurement', 'MEA002', 'Carrying Amount',
           'Net book value', 'value_check', 'current_carrying_amount', '>=0',
           'Carrying amount should be recorded', 'PS 3150.15', 'warning', 11
    UNION ALL SELECT 'disclosure', 'DIS001', 'Heritage Disclosure',
           'Disclose heritage assets if not recognised', 'required_field', 'significance_statement', NULL,
           'Heritage significance should be documented', 'PS 3150.42', 'info', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Cost Information',
           'Cost or deemed cost', 'required_field', 'acquisition_cost', NULL,
           'Cost information should be documented', 'PS 3150.39', 'warning', 21
    UNION ALL SELECT 'disclosure', 'DIS003', 'Restrictions',
           'Any restrictions on use or disposal', 'required_field', 'restrictions_on_use', NULL,
           'Restrictions should be disclosed', 'PS 3150.42', 'info', 22
) r
WHERE s.code = 'PSAS3150'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- IPSAS 45 Rules (8 rules)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Asset Class Required' as name,
           'Asset must be classified' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset class is required' as error_message,
           'IPSAS 45.14' as reference, 'error' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Recognition Date',
           'Date asset was recognised', 'required_field', 'recognition_date', NULL,
           'Recognition date should be recorded', 'IPSAS 45.14', 'warning', 2
    UNION ALL SELECT 'recognition', 'REC003', 'Heritage Significance',
           'Documentation of heritage characteristics', 'required_field', 'significance_statement', NULL,
           'Heritage significance should be documented', 'IPSAS 45.5', 'warning', 3
    UNION ALL SELECT 'measurement', 'MEA001', 'Measurement Basis',
           'Measurement model must be specified', 'required_field', 'measurement_basis', NULL,
           'Measurement basis should be specified', 'IPSAS 45.43', 'warning', 10
    UNION ALL SELECT 'measurement', 'MEA002', 'Carrying Amount',
           'Carrying amount after recognition', 'value_check', 'current_carrying_amount', '>=0',
           'Carrying amount should be recorded', 'IPSAS 45.43', 'warning', 11
    UNION ALL SELECT 'disclosure', 'DIS001', 'Measurement Basis Disclosure',
           'Disclose measurement basis used', 'required_field', 'measurement_basis', NULL,
           'Measurement basis must be disclosed', 'IPSAS 45.88', 'warning', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Restrictions',
           'Restrictions on disposal', 'required_field', 'restrictions_on_disposal', NULL,
           'Restrictions should be disclosed', 'IPSAS 45.88', 'info', 21
    UNION ALL SELECT 'disclosure', 'DIS003', 'Conservation',
           'Conservation requirements', 'required_field', 'conservation_requirements', NULL,
           'Conservation requirements should be documented', 'IPSAS 45.88', 'info', 22
) r
WHERE s.code = 'IPSAS45'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- IPSAS 17 Rules (8 rules)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Asset Class Required' as name,
           'Asset must be classified' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset class is required' as error_message,
           'IPSAS 17.14' as reference, 'error' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Recognition Date',
           'Date of recognition', 'required_field', 'recognition_date', NULL,
           'Recognition date should be recorded', 'IPSAS 17.14', 'warning', 2
    UNION ALL SELECT 'recognition', 'REC003', 'Heritage Characteristics',
           'Document heritage characteristics', 'required_field', 'significance_statement', NULL,
           'Heritage characteristics should be documented', 'IPSAS 17.9', 'warning', 3
    UNION ALL SELECT 'measurement', 'MEA001', 'Measurement Basis',
           'Cost or revaluation model', 'required_field', 'measurement_basis', NULL,
           'Measurement basis should be specified', 'IPSAS 17.42', 'warning', 10
    UNION ALL SELECT 'measurement', 'MEA002', 'Carrying Amount',
           'Current carrying amount', 'value_check', 'current_carrying_amount', '>=0',
           'Carrying amount should be recorded', 'IPSAS 17.88', 'warning', 11
    UNION ALL SELECT 'disclosure', 'DIS001', 'Measurement Disclosure',
           'Disclose measurement basis', 'required_field', 'measurement_basis', NULL,
           'Measurement basis must be disclosed', 'IPSAS 17.88', 'warning', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Restrictions',
           'Restrictions on title', 'required_field', 'restrictions_on_use', NULL,
           'Restrictions should be disclosed', 'IPSAS 17.88(d)', 'info', 21
    UNION ALL SELECT 'disclosure', 'DIS003', 'Reconciliation',
           'Reconciliation of carrying amounts', 'required_field', 'current_carrying_amount', NULL,
           'Carrying amount reconciliation required', 'IPSAS 17.88(e)', 'info', 22
) r
WHERE s.code = 'IPSAS17'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- AASB 116 Rules (8 rules)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Asset Class Required' as name,
           'Asset must be classified' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset class is required' as error_message,
           'AASB 116.7' as reference, 'error' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Recognition Date',
           'Date of recognition', 'required_field', 'recognition_date', NULL,
           'Recognition date should be recorded', 'AASB 116.7', 'warning', 2
    UNION ALL SELECT 'recognition', 'REC003', 'Heritage Significance',
           'Cultural significance', 'required_field', 'significance_statement', NULL,
           'Heritage significance should be documented', 'AASB 116 Aus7.1', 'warning', 3
    UNION ALL SELECT 'measurement', 'MEA001', 'Measurement Basis',
           'Cost or revaluation model', 'required_field', 'measurement_basis', NULL,
           'Measurement basis should be specified', 'AASB 116.29', 'warning', 10
    UNION ALL SELECT 'measurement', 'MEA002', 'Carrying Amount',
           'Current carrying amount', 'value_check', 'current_carrying_amount', '>=0',
           'Carrying amount should be recorded', 'AASB 116.30', 'warning', 11
    UNION ALL SELECT 'disclosure', 'DIS001', 'Measurement Disclosure',
           'Disclose measurement basis', 'required_field', 'measurement_basis', NULL,
           'Measurement basis must be disclosed', 'AASB 116.73', 'warning', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Restrictions',
           'Restrictions on title', 'required_field', 'restrictions_on_use', NULL,
           'Restrictions should be disclosed', 'AASB 116.74(a)', 'info', 21
    UNION ALL SELECT 'disclosure', 'DIS003', 'Conservation',
           'Conservation policy', 'required_field', 'conservation_requirements', NULL,
           'Conservation policy should be documented', 'AASB 116 Aus73.1', 'info', 22
) r
WHERE s.code = 'AASB116'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- IAS 16 Rules (8 rules)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Asset Class Required' as name,
           'Asset must be classified' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset class is required' as error_message,
           'IAS 16.7' as reference, 'error' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Recognition Date',
           'Date of recognition', 'required_field', 'recognition_date', NULL,
           'Recognition date should be recorded', 'IAS 16.7', 'warning', 2
    UNION ALL SELECT 'recognition', 'REC003', 'Future Economic Benefits',
           'Document expected benefits', 'required_field', 'significance_statement', NULL,
           'Future benefits/significance should be documented', 'IAS 16.7(a)', 'warning', 3
    UNION ALL SELECT 'measurement', 'MEA001', 'Measurement Basis',
           'Cost or revaluation model', 'required_field', 'measurement_basis', NULL,
           'Measurement basis should be specified', 'IAS 16.29', 'error', 10
    UNION ALL SELECT 'measurement', 'MEA002', 'Carrying Amount',
           'Current carrying amount', 'value_check', 'current_carrying_amount', '>=0',
           'Carrying amount should be recorded', 'IAS 16.30', 'warning', 11
    UNION ALL SELECT 'measurement', 'MEA003', 'Cost at Recognition',
           'Cost at initial recognition', 'required_field', 'acquisition_cost', NULL,
           'Initial cost should be recorded', 'IAS 16.15', 'warning', 12
    UNION ALL SELECT 'disclosure', 'DIS001', 'Measurement Disclosure',
           'Disclose measurement basis used', 'required_field', 'measurement_basis', NULL,
           'Measurement basis must be disclosed', 'IAS 16.73(a)', 'warning', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Restrictions',
           'Restrictions on title', 'required_field', 'restrictions_on_use', NULL,
           'Restrictions should be disclosed', 'IAS 16.74(a)', 'info', 21
) r
WHERE s.code = 'IAS16'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- CUSTOM Rules (5 rules - basic)
INSERT IGNORE INTO heritage_compliance_rule 
(standard_id, category, code, name, description, check_type, field_name, `condition`, error_message, reference, severity, sort_order)
SELECT s.id, r.category, r.code, r.name, r.description, r.check_type, r.field_name, r.cond, r.error_message, r.reference, r.severity, r.sort_order
FROM heritage_accounting_standard s
CROSS JOIN (
    SELECT 'recognition' as category, 'REC001' as code, 'Asset Classification' as name,
           'Asset should be classified' as description,
           'required_field' as check_type, 'asset_class_id' as field_name, NULL as cond,
           'Asset classification is recommended' as error_message,
           'Best Practice' as reference, 'warning' as severity, 1 as sort_order
    UNION ALL SELECT 'recognition', 'REC002', 'Description',
           'Asset description', 'required_field', 'significance_statement', NULL,
           'Asset description is recommended', 'Best Practice', 'info', 2
    UNION ALL SELECT 'measurement', 'MEA001', 'Value Record',
           'Record some form of value', 'required_field', 'measurement_basis', NULL,
           'Measurement approach should be documented', 'Best Practice', 'info', 10
    UNION ALL SELECT 'disclosure', 'DIS001', 'Location',
           'Current location', 'required_field', 'current_location', NULL,
           'Asset location should be recorded', 'Best Practice', 'info', 20
    UNION ALL SELECT 'disclosure', 'DIS002', 'Condition',
           'Condition assessment', 'required_field', 'condition_rating', NULL,
           'Condition should be assessed', 'Best Practice', 'info', 21
) r
WHERE s.code = 'CUSTOM'
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Transaction log for heritage assets
CREATE TABLE IF NOT EXISTS `heritage_transaction_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `heritage_asset_id` INT UNSIGNED NOT NULL,
    `object_id` INT NULL,
    `transaction_type` VARCHAR(50) NOT NULL,
    `transaction_date` DATE NOT NULL,
    `amount` DECIMAL(15,2) NULL,
    `transaction_data` JSON NULL,
    `user_id` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_heritage_asset` (`heritage_asset_id`),
    INDEX `idx_object` (`object_id`),
    INDEX `idx_type` (`transaction_type`),
    INDEX `idx_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
