-- ============================================================================
-- ahg-icip — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgICIPPlugin/database/install.sql
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

-- ahgICIPPlugin Database Schema
-- Indigenous Cultural and Intellectual Property Management
-- Version 1.0.0

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. COMMUNITY REGISTRY
-- ============================================

CREATE TABLE IF NOT EXISTS icip_community (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    alternate_names JSON COMMENT 'Array of alternate names/spellings',
    language_group VARCHAR(255),
    region VARCHAR(255),
    state_territory VARCHAR(57) COMMENT 'NSW, VIC, QLD, WA, SA, TAS, NT, ACT, External' NOT NULL,
    contact_name VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(100),
    contact_address TEXT,
    native_title_reference VARCHAR(255) COMMENT 'Reference to Native Title determination',
    prescribed_body_corporate VARCHAR(255) COMMENT 'PBC name if applicable',
    pbc_contact_email VARCHAR(255),
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_state (state_territory),
    INDEX idx_language (language_group),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. CONSENT MANAGEMENT
-- ============================================

CREATE TABLE IF NOT EXISTS icip_consent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    community_id INT COMMENT 'Link to icip_community',
    consent_status VARCHAR(144) COMMENT 'not_required, pending_consultation, consultation_in_progress, conditional_consent, full_consent, restricted_consent, denied, unknown' NOT NULL DEFAULT 'unknown',
    consent_scope JSON COMMENT 'Array of scope values: preservation_only, internal_access, public_access, reproduction, commercial_use, educational_use, research_use, full_rights',
    consent_date DATE,
    consent_expiry_date DATE,
    consent_granted_by VARCHAR(255) COMMENT 'Person/authority who granted consent',
    consent_document_path VARCHAR(500) COMMENT 'Path to consent document',
    conditions TEXT COMMENT 'Consent conditions and restrictions',
    restrictions TEXT COMMENT 'Specific usage restrictions',
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_object (information_object_id),
    INDEX idx_community (community_id),
    INDEX idx_status (consent_status),
    INDEX idx_expiry (consent_expiry_date),
    FOREIGN KEY (community_id) REFERENCES icip_community(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. CULTURAL NOTICES
-- ============================================

CREATE TABLE IF NOT EXISTS icip_cultural_notice_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    default_text TEXT COMMENT 'Default notice text',
    icon VARCHAR(100) COMMENT 'Icon class or image path',
    severity VARCHAR(35) COMMENT 'info, warning, critical' DEFAULT 'warning',
    requires_acknowledgement TINYINT(1) DEFAULT 0,
    blocks_access TINYINT(1) DEFAULT 0,
    display_public TINYINT(1) DEFAULT 1,
    display_staff TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default notice types
INSERT IGNORE INTO icip_cultural_notice_type (code, name, description, default_text, severity, requires_acknowledgement, display_order) VALUES
('cultural_sensitivity', 'Cultural Sensitivity', 'General cultural sensitivity notice', 'This material may contain culturally sensitive content. Please view with respect and cultural awareness.', 'info', 0, 10),
('aboriginal_torres_strait', 'Aboriginal and Torres Strait Islander', 'General First Nations notice', 'Aboriginal and Torres Strait Islander peoples should be aware that this collection may contain images, voices, or names of deceased persons.', 'warning', 1, 20),
('deceased_person', 'Deceased Person', 'Contains images/names of deceased', 'Warning: This material contains images and/or names of people who have passed away.', 'warning', 1, 30),
('sacred_secret', 'Sacred/Secret Material', 'Contains sacred or secret cultural material', 'This material contains sacred or secret cultural content. Access may be restricted.', 'critical', 1, 40),
('mens_business', 'Men''s Business', 'Restricted to initiated men', 'This material relates to Men''s Business and may be restricted to initiated men only.', 'critical', 1, 50),
('womens_business', 'Women''s Business', 'Restricted to initiated women', 'This material relates to Women''s Business and may be restricted to initiated women only.', 'critical', 1, 60),
('ceremonial', 'Ceremonial Material', 'Contains ceremonial content', 'This material contains ceremonial content that may have cultural restrictions on viewing.', 'critical', 1, 70),
('community_only', 'Community Only', 'Restricted to specific community members', 'This material is restricted to members of the relevant community.', 'critical', 1, 80),
('seasonal_restriction', 'Seasonal Restriction', 'Viewing restricted to certain times', 'This material has seasonal viewing restrictions based on cultural protocols.', 'warning', 0, 90),
('custom', 'Custom Notice', 'Custom cultural notice', NULL, 'info', 0, 100)
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS icip_cultural_notice (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    notice_type_id INT NOT NULL,
    custom_text TEXT COMMENT 'Override default text if needed',
    community_id INT COMMENT 'Community that requested this notice',
    applies_to_descendants TINYINT(1) DEFAULT 1,
    start_date DATE COMMENT 'For seasonal restrictions',
    end_date DATE COMMENT 'For seasonal restrictions',
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_object (information_object_id),
    INDEX idx_type (notice_type_id),
    INDEX idx_dates (start_date, end_date),
    FOREIGN KEY (notice_type_id) REFERENCES icip_cultural_notice_type(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES icip_community(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User acknowledgements of cultural notices
CREATE TABLE IF NOT EXISTS icip_notice_acknowledgement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notice_id INT NOT NULL,
    user_id INT NOT NULL,
    acknowledged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    INDEX idx_notice (notice_id),
    INDEX idx_user (user_id),
    UNIQUE KEY unique_ack (notice_id, user_id),
    FOREIGN KEY (notice_id) REFERENCES icip_cultural_notice(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. TRADITIONAL KNOWLEDGE (TK) LABELS
-- ============================================

CREATE TABLE IF NOT EXISTS icip_tk_label_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    category VARCHAR(20) COMMENT 'TK, BC' NOT NULL COMMENT 'TK = Traditional Knowledge, BC = Biocultural',
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon_path VARCHAR(255) COMMENT 'Path to label icon',
    local_contexts_url VARCHAR(500) COMMENT 'Link to Local Contexts definition',
    display_order INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert TK Labels from Local Contexts
INSERT IGNORE INTO icip_tk_label_type (code, category, name, description, local_contexts_url, display_order) VALUES
('tk_a', 'TK', 'TK Attribution', 'This Label is being used to correct historical attributions that have not properly attributed Indigenous peoples'' contributions to the creation of materials.', 'https://localcontexts.org/label/tk-attribution/', 10),
('tk_cl', 'TK', 'TK Clan', 'This Label is being used to indicate that this material is traditionally and usually owned by clan members.', 'https://localcontexts.org/label/tk-clan/', 20),
('tk_f', 'TK', 'TK Family', 'This Label is being used to indicate that this material is traditionally and usually owned by family members.', 'https://localcontexts.org/label/tk-family/', 30),
('tk_mc', 'TK', 'TK Multiple Communities', 'This Label is being used to indicate that this material traditionally belongs to multiple communities.', 'https://localcontexts.org/label/tk-multiple-communities/', 40),
('tk_nc', 'TK', 'TK Non-Commercial', 'This Label is being used to indicate that this material has specific conditions for commercial use.', 'https://localcontexts.org/label/tk-non-commercial/', 50),
('tk_o', 'TK', 'TK Outreach', 'This Label is being used to indicate that there has been community outreach but no decision has been made.', 'https://localcontexts.org/label/tk-outreach/', 60),
('tk_s', 'TK', 'TK Secret/Sacred', 'This Label is being used to indicate that this material has specific restrictions around access.', 'https://localcontexts.org/label/tk-secret-sacred/', 70),
('tk_v', 'TK', 'TK Verified', 'This Label is being used to indicate that the community has verified information about this material.', 'https://localcontexts.org/label/tk-verified/', 80),
('tk_cs', 'TK', 'TK Culturally Sensitive', 'This Label is being used to indicate that this material is culturally sensitive and should be treated accordingly.', 'https://localcontexts.org/label/tk-culturally-sensitive/', 90),
('tk_cv', 'TK', 'TK Community Voice', 'This Label is being used to indicate that this material should be heard/spoken by community members.', 'https://localcontexts.org/label/tk-community-voice/', 100),
('tk_co', 'TK', 'TK Community Use Only', 'This Label is being used to indicate that this material is for community use only.', 'https://localcontexts.org/label/tk-community-use-only/', 110),
('tk_wr', 'TK', 'TK Women Restricted', 'This Label is being used to indicate that this material has gender-based restrictions for women.', 'https://localcontexts.org/label/tk-women-restricted/', 120),
('tk_wg', 'TK', 'TK Women General', 'This Label is being used to indicate that this material traditionally belongs to women.', 'https://localcontexts.org/label/tk-women-general/', 130),
('tk_mr', 'TK', 'TK Men Restricted', 'This Label is being used to indicate that this material has gender-based restrictions for men.', 'https://localcontexts.org/label/tk-men-restricted/', 140),
('tk_mg', 'TK', 'TK Men General', 'This Label is being used to indicate that this material traditionally belongs to men.', 'https://localcontexts.org/label/tk-men-general/', 150),
('tk_ss', 'TK', 'TK Seasonal', 'This Label is being used to indicate that this material has seasonal restrictions.', 'https://localcontexts.org/label/tk-seasonal/', 160),
-- Biocultural Labels
('bc_p', 'BC', 'BC Provenance', 'This Label is being used to indicate the origins and chain of custody of biological/genetic resources.', 'https://localcontexts.org/label/bc-provenance/', 200),
('bc_mc', 'BC', 'BC Multiple Communities', 'This Label is being used to indicate multiple communities have interests in this biological resource.', 'https://localcontexts.org/label/bc-multiple-communities/', 210),
('bc_cl', 'BC', 'BC Clan', 'This Label is being used to indicate clan ownership of biological/genetic resources.', 'https://localcontexts.org/label/bc-clan/', 220),
('bc_cnc', 'BC', 'BC Commercial Non-Commercial', 'This Label indicates conditions for commercial and non-commercial use.', 'https://localcontexts.org/label/bc-non-commercial/', 230),
('bc_o', 'BC', 'BC Outreach', 'This Label indicates community outreach regarding biological resources.', 'https://localcontexts.org/label/bc-outreach/', 240),
('bc_r', 'BC', 'BC Research Use', 'This Label indicates specific conditions for research use of biological resources.', 'https://localcontexts.org/label/bc-research-use/', 250)
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS icip_tk_label (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    label_type_id INT NOT NULL,
    community_id INT COMMENT 'Community that applied the label',
    applied_by VARCHAR(34) COMMENT 'community, institution' NOT NULL DEFAULT 'institution',
    local_contexts_project_id VARCHAR(255) COMMENT 'Link to Local Contexts Hub project',
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_object (information_object_id),
    INDEX idx_type (label_type_id),
    INDEX idx_community (community_id),
    INDEX idx_applied_by (applied_by),
    UNIQUE KEY unique_label (information_object_id, label_type_id),
    FOREIGN KEY (label_type_id) REFERENCES icip_tk_label_type(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES icip_community(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. CONSULTATION LOG
-- ============================================

CREATE TABLE IF NOT EXISTS icip_consultation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT COMMENT 'Can be NULL for general consultations',
    community_id INT NOT NULL,
    consultation_type VARCHAR(143) COMMENT 'initial_contact, consent_request, access_request, repatriation, digitisation, exhibition, publication, research, general, follow_up' NOT NULL,
    consultation_date DATE NOT NULL,
    consultation_method VARCHAR(57) COMMENT 'in_person, phone, video, email, letter, other' NOT NULL,
    location VARCHAR(255),
    attendees TEXT COMMENT 'List of attendees',
    community_representatives TEXT COMMENT 'Community members present',
    institution_representatives TEXT COMMENT 'Institution staff present',
    summary TEXT NOT NULL,
    outcomes TEXT,
    action_items JSON COMMENT 'Array of action items with due dates',
    follow_up_date DATE,
    follow_up_notes TEXT,
    is_confidential TINYINT(1) DEFAULT 0,
    linked_consent_id INT COMMENT 'Link to consent record if applicable',
    documents JSON COMMENT 'Array of document paths',
    status VARCHAR(63) COMMENT 'scheduled, completed, cancelled, follow_up_required' NOT NULL DEFAULT 'completed',
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_object (information_object_id),
    INDEX idx_community (community_id),
    INDEX idx_type (consultation_type),
    INDEX idx_date (consultation_date),
    INDEX idx_follow_up (follow_up_date),
    INDEX idx_status (status),
    FOREIGN KEY (community_id) REFERENCES icip_community(id) ON DELETE CASCADE,
    FOREIGN KEY (linked_consent_id) REFERENCES icip_consent(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. ACCESS RESTRICTIONS
-- ============================================

CREATE TABLE IF NOT EXISTS icip_access_restriction (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    restriction_type VARCHAR(209) COMMENT 'community_permission_required, gender_restricted_male, gender_restricted_female, initiated_only, seasonal, mourning_period, repatriation_pending, under_consultation, elder_approval_required, custom' NOT NULL,
    community_id INT COMMENT 'Community that requested restriction',
    start_date DATE,
    end_date DATE COMMENT 'NULL for indefinite restrictions',
    applies_to_descendants TINYINT(1) DEFAULT 1,
    override_security_clearance TINYINT(1) DEFAULT 1 COMMENT 'Override standard security clearance',
    custom_restriction_text TEXT COMMENT 'For custom restriction type',
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_object (information_object_id),
    INDEX idx_type (restriction_type),
    INDEX idx_community (community_id),
    INDEX idx_dates (start_date, end_date),
    FOREIGN KEY (community_id) REFERENCES icip_community(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. ICIP STATUS SUMMARY (Materialized view pattern)
-- ============================================

CREATE TABLE IF NOT EXISTS icip_object_summary (
    information_object_id INT PRIMARY KEY,
    has_icip_content TINYINT(1) DEFAULT 0,
    consent_status VARCHAR(50),
    has_cultural_notices TINYINT(1) DEFAULT 0,
    cultural_notice_count INT DEFAULT 0,
    has_tk_labels TINYINT(1) DEFAULT 0,
    tk_label_count INT DEFAULT 0,
    has_restrictions TINYINT(1) DEFAULT 0,
    restriction_count INT DEFAULT 0,
    requires_acknowledgement TINYINT(1) DEFAULT 0,
    blocks_access TINYINT(1) DEFAULT 0,
    community_ids JSON COMMENT 'Array of linked community IDs',
    last_consultation_date DATE,
    consent_expiry_date DATE,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_has_icip (has_icip_content),
    INDEX idx_consent (consent_status),
    INDEX idx_notices (has_cultural_notices),
    INDEX idx_labels (has_tk_labels),
    INDEX idx_restrictions (has_restrictions),
    INDEX idx_blocks (blocks_access)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. CONFIGURATION
-- ============================================

CREATE TABLE IF NOT EXISTS icip_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default configuration
INSERT IGNORE INTO icip_config (config_key, config_value, description) VALUES
('enable_public_notices', '1', 'Display cultural notices on public view'),
('enable_staff_notices', '1', 'Display cultural notices on staff view'),
('require_acknowledgement_default', '1', 'Require acknowledgement by default for sensitive notices'),
('consent_expiry_warning_days', '90', 'Days before consent expiry to show warning'),
('local_contexts_hub_enabled', '0', 'Enable Local Contexts Hub API integration'),
('local_contexts_api_key', '', 'API key for Local Contexts Hub'),
('default_consultation_follow_up_days', '30', 'Default follow-up period for consultations'),
('audit_all_icip_access', '1', 'Log all access to ICIP-flagged records')
ON DUPLICATE KEY UPDATE config_key = VALUES(config_key);

SET FOREIGN_KEY_CHECKS = 1;

SET FOREIGN_KEY_CHECKS = 1;
