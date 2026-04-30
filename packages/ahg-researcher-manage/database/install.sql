-- ============================================================================
-- ahg-researcher-manage — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgResearcherPlugin/database/install.sql
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

-- ============================================================
-- ahgResearcherPlugin — Database Schema
-- Researcher Collection Upload & Approval Workflow
-- ============================================================

-- 1. Submission package (one per researcher upload/import)
CREATE TABLE IF NOT EXISTS researcher_submission (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    researcher_id INT DEFAULT NULL,              -- FK to research_researcher.id (if ahgResearchPlugin installed)
    user_id INT NOT NULL,                        -- FK to user.id (AtoM user)
    title VARCHAR(255) NOT NULL,
    description TEXT,
    repository_id INT DEFAULT NULL,              -- Target repository for publishing
    parent_object_id INT DEFAULT NULL,           -- Target parent IO for placement (NULL = root)
    project_id INT DEFAULT NULL,                 -- FK to research_project.id (link to research project)
    source_type VARCHAR(27) COMMENT 'online, offline' NOT NULL DEFAULT 'online',
    source_file VARCHAR(255) DEFAULT NULL,       -- Original exchange JSON filename
    include_images TINYINT(1) DEFAULT 1,         -- Whether offline export included images
    status VARCHAR(83) COMMENT 'draft, submitted, under_review, approved, published, returned, rejected'
        NOT NULL DEFAULT 'draft',
    workflow_task_id INT DEFAULT NULL,
    total_items INT DEFAULT 0,
    total_files INT DEFAULT 0,
    total_file_size BIGINT UNSIGNED DEFAULT 0,
    return_comment TEXT DEFAULT NULL,
    reject_comment TEXT DEFAULT NULL,
    published_at DATETIME DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_researcher (researcher_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Items within a submission (each becomes an IO on publish)
--    Supports hierarchical sub-levels via parent_item_id
CREATE TABLE IF NOT EXISTS researcher_submission_item (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    parent_item_id BIGINT UNSIGNED DEFAULT NULL, -- Self-referencing for sub-levels
    item_type VARCHAR(50) COMMENT 'description, note, repository, creator' NOT NULL DEFAULT 'description',
    title VARCHAR(255) NOT NULL,
    identifier VARCHAR(255) DEFAULT NULL,
    level_of_description VARCHAR(50) DEFAULT 'item',
    scope_and_content TEXT DEFAULT NULL,
    extent_and_medium VARCHAR(500) DEFAULT NULL,
    date_display VARCHAR(255) DEFAULT NULL,
    date_start DATE DEFAULT NULL,
    date_end DATE DEFAULT NULL,
    -- Access points (comma-separated)
    creators TEXT DEFAULT NULL,
    subjects TEXT DEFAULT NULL,
    places TEXT DEFAULT NULL,
    genres TEXT DEFAULT NULL,
    -- Conditions & notes
    access_conditions TEXT DEFAULT NULL,
    reproduction_conditions TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    -- Repository info (for item_type = 'repository')
    repository_name VARCHAR(500) DEFAULT NULL,
    repository_address TEXT DEFAULT NULL,
    repository_contact VARCHAR(500) DEFAULT NULL,
    -- Reference to existing AtoM record (for offline notes/edits)
    reference_object_id INT DEFAULT NULL,
    reference_slug VARCHAR(255) DEFAULT NULL,
    -- Sort and publish tracking
    sort_order INT DEFAULT 0,
    published_object_id INT DEFAULT NULL,        -- Created IO/actor/repo after publish
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES researcher_submission(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_item_id) REFERENCES researcher_submission_item(id) ON DELETE SET NULL,
    INDEX idx_submission (submission_id),
    INDEX idx_parent (parent_item_id),
    INDEX idx_type (item_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Files attached to items
CREATE TABLE IF NOT EXISTS researcher_submission_file (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(1024) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size BIGINT UNSIGNED DEFAULT 0,
    checksum VARCHAR(64) DEFAULT NULL,           -- SHA-256
    caption TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    published_do_id INT DEFAULT NULL,            -- Created DO after publish
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES researcher_submission_item(id) ON DELETE CASCADE,
    INDEX idx_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Review/feedback log
CREATE TABLE IF NOT EXISTS researcher_submission_review (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    reviewer_id INT NOT NULL,
    action VARCHAR(53) COMMENT 'comment, return, approve, reject, publish' NOT NULL,
    comment TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES researcher_submission(id) ON DELETE CASCADE,
    INDEX idx_submission (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Workflow seed data (high IDs to avoid conflicts)
-- Wrapped in a stored procedure with table-exists guards. ahg_workflow* are
-- created by ahg-workflow (alphabetically loads later than ahg-researcher-
-- manage); on a fresh install, this seed silently skips on first pass and
-- lands on the second bin/install pass.
-- ============================================================
DROP PROCEDURE IF EXISTS ahg_researcher_seed_workflow;
DELIMITER //
CREATE PROCEDURE ahg_researcher_seed_workflow()
proc: BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ahg_workflow') THEN
        LEAVE proc;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ahg_workflow_step') THEN
        LEAVE proc;
    END IF;

    INSERT IGNORE INTO ahg_workflow (id, name, description, scope_type, trigger_event,
        applies_to, is_active, is_default, require_all_steps, notification_enabled)
    VALUES (100, 'Researcher Submission Review',
        'Two-step review for researcher-submitted collections',
        'global', 'submit', 'information_object', 1, 0, 1, 1);

    INSERT IGNORE INTO ahg_workflow_step (id, workflow_id, name, step_order, step_type,
        action_required, pool_enabled, instructions, is_active)
    VALUES
    (100, 100, 'Content Review', 1, 'review', 'approve_reject', 1,
        'Review the researcher submission for completeness, metadata quality, and adherence to descriptive standards.', 1),
    (101, 100, 'Publication Approval', 2, 'approve', 'approve_reject', 1,
        'Final approval before publishing records. Verify repository placement and access conditions.', 1);
END proc //
DELIMITER ;
CALL ahg_researcher_seed_workflow();
DROP PROCEDURE IF EXISTS ahg_researcher_seed_workflow;

SET FOREIGN_KEY_CHECKS = 1;
