-- ============================================================================
-- ahg-workflow — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgWorkflowPlugin/database/install.sql
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
-- ahgWorkflowPlugin - Database Schema V2.0
-- Configurable Approval Workflow System
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================







-- ============================================================
-- Table: ahg_workflow
-- Main workflow definition table
-- Workflows can be scoped to repository or collection level
-- Values managed via ahg_dropdown (taxonomy: workflow_scope_type, workflow_trigger_event, workflow_applies_to)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_workflow` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `scope_type` VARCHAR(50) NOT NULL DEFAULT 'global',
    `scope_id` INT DEFAULT NULL COMMENT 'repository_id or information_object_id depending on scope_type',
    `trigger_event` VARCHAR(50) NOT NULL DEFAULT 'submit',
    `applies_to` VARCHAR(50) NOT NULL DEFAULT 'information_object',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Default workflow for scope',
    `require_all_steps` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Must complete all steps in order',
    `allow_parallel` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Allow parallel step execution',
    `auto_archive_days` INT DEFAULT NULL COMMENT 'Auto-archive completed tasks after N days',
    `notification_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_scope` (`scope_type`, `scope_id`),
    KEY `idx_trigger` (`trigger_event`),
    KEY `idx_active` (`is_active`),
    KEY `idx_default` (`is_default`, `scope_type`, `scope_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: ahg_workflow_step
-- Individual steps within a workflow
-- Each step can require specific roles (integration with security clearance)
-- Values managed via ahg_dropdown (taxonomy: workflow_step_type, workflow_action_required)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_workflow_step` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `workflow_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `step_order` INT NOT NULL DEFAULT 1,
    `step_type` VARCHAR(50) NOT NULL DEFAULT 'review',
    `action_required` VARCHAR(50) NOT NULL DEFAULT 'approve_reject',
    `required_role_id` INT DEFAULT NULL COMMENT 'AtoM role_id or null for any authenticated user',
    `required_clearance_level` INT DEFAULT NULL COMMENT 'Security clearance level from ahgSecurityClearancePlugin',
    `allowed_group_ids` TEXT COMMENT 'JSON array of allowed group IDs',
    `allowed_user_ids` TEXT COMMENT 'JSON array of specific user IDs',
    `pool_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Allow task claiming from pool',
    `auto_assign_user_id` INT DEFAULT NULL COMMENT 'Auto-assign to specific user',
    `escalation_days` INT DEFAULT NULL COMMENT 'Days before escalation',
    `escalation_user_id` INT DEFAULT NULL COMMENT 'User to escalate to',
    `notification_template` VARCHAR(100) DEFAULT 'default' COMMENT 'Email template name',
    `instructions` TEXT COMMENT 'Instructions shown to reviewer',
    `checklist` TEXT COMMENT 'JSON array of checklist items',
    `is_optional` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_workflow` (`workflow_id`),
    KEY `idx_order` (`workflow_id`, `step_order`),
    KEY `idx_role` (`required_role_id`),
    KEY `idx_clearance` (`required_clearance_level`),
    CONSTRAINT `fk_step_workflow` FOREIGN KEY (`workflow_id`)
        REFERENCES `ahg_workflow` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: ahg_workflow_task
-- Active workflow tasks (items currently in workflow)
-- Values managed via ahg_dropdown (taxonomy: workflow_task_status, workflow_priority, workflow_decision)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_workflow_task` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `workflow_id` INT NOT NULL,
    `workflow_step_id` INT NOT NULL,
    `object_id` INT NOT NULL COMMENT 'information_object.id or other entity',
    `object_type` VARCHAR(50) NOT NULL DEFAULT 'information_object',
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `priority` VARCHAR(50) NOT NULL DEFAULT 'normal',
    `submitted_by` INT NOT NULL COMMENT 'User who submitted item to workflow',
    `assigned_to` INT DEFAULT NULL COMMENT 'User who claimed/was assigned the task',
    `claimed_at` DATETIME DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `decision` VARCHAR(50) DEFAULT 'pending',
    `decision_comment` TEXT,
    `decision_at` DATETIME DEFAULT NULL,
    `decision_by` INT DEFAULT NULL,
    `checklist_completed` TEXT COMMENT 'JSON object of completed checklist items',
    `metadata` TEXT COMMENT 'Additional JSON metadata',
    `previous_task_id` INT DEFAULT NULL COMMENT 'Link to previous step task',
    `retry_count` INT NOT NULL DEFAULT 0,
    `escalated_at` DATETIME DEFAULT NULL,
    `queue_id` INT UNSIGNED DEFAULT NULL COMMENT 'V2.0: Work queue assignment',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_workflow` (`workflow_id`),
    KEY `idx_step` (`workflow_step_id`),
    KEY `idx_object` (`object_id`, `object_type`),
    KEY `idx_status` (`status`),
    KEY `idx_assigned` (`assigned_to`),
    KEY `idx_submitted` (`submitted_by`),
    KEY `idx_due` (`due_date`),
    KEY `idx_priority` (`priority`),
    KEY `idx_pending_pool` (`status`, `assigned_to`),
    KEY `idx_queue` (`queue_id`),
    CONSTRAINT `fk_task_workflow` FOREIGN KEY (`workflow_id`)
        REFERENCES `ahg_workflow` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_task_step` FOREIGN KEY (`workflow_step_id`)
        REFERENCES `ahg_workflow_step` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: ahg_workflow_history
-- Complete audit trail of all workflow actions
-- action values managed via ahg_dropdown (taxonomy: workflow_history_action)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_workflow_history` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `task_id` INT DEFAULT NULL COMMENT 'Link to task (null if task deleted)',
    `workflow_id` INT NOT NULL,
    `workflow_step_id` INT DEFAULT NULL,
    `object_id` INT NOT NULL,
    `object_type` VARCHAR(50) NOT NULL DEFAULT 'information_object',
    `action` VARCHAR(50) NOT NULL COMMENT 'Action code from ahg_dropdown workflow_history_action taxonomy',
    `from_status` VARCHAR(50) DEFAULT NULL,
    `to_status` VARCHAR(50) DEFAULT NULL,
    `performed_by` INT NOT NULL,
    `performed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `comment` TEXT,
    `metadata` TEXT COMMENT 'JSON additional data',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `correlation_id` VARCHAR(36) DEFAULT NULL COMMENT 'V2.0: Groups bulk operation events',
    PRIMARY KEY (`id`),
    KEY `idx_task` (`task_id`),
    KEY `idx_workflow` (`workflow_id`),
    KEY `idx_object` (`object_id`, `object_type`),
    KEY `idx_performer` (`performed_by`),
    KEY `idx_action` (`action`),
    KEY `idx_date` (`performed_at`),
    KEY `idx_correlation` (`correlation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: ahg_workflow_notification
-- Email notification queue and log
-- notification_type values managed via ahg_dropdown (taxonomy: workflow_notification_type)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_workflow_notification` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `task_id` INT DEFAULT NULL,
    `user_id` INT NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL COMMENT 'Type from ahg_dropdown workflow_notification_type taxonomy',
    `subject` VARCHAR(500) NOT NULL,
    `body` TEXT NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `sent_at` DATETIME DEFAULT NULL,
    `error_message` TEXT,
    `retry_count` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_task` (`task_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_type` (`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- V2.0: Table: ahg_workflow_queue
-- Work queue categories for task routing (#173)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_workflow_queue` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `sla_days` INT DEFAULT NULL COMMENT 'Default SLA days for this queue',
    `icon` VARCHAR(50) DEFAULT 'fa-inbox',
    `color` VARCHAR(7) DEFAULT '#6c757d',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_slug` (`slug`),
    KEY `idx_active` (`is_active`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- V2.0: Table: ahg_workflow_sla_policy
-- SLA policies for queues/workflows (#174, #186)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_workflow_sla_policy` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `queue_id` INT UNSIGNED DEFAULT NULL,
    `workflow_id` INT DEFAULT NULL,
    `warning_days` INT DEFAULT 3,
    `due_days` INT DEFAULT 5,
    `escalation_days` INT DEFAULT 7,
    `escalation_user_id` INT DEFAULT NULL,
    `escalation_action` VARCHAR(50) DEFAULT 'notify_lead' COMMENT 'From ahg_dropdown workflow_escalation_action',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_queue` (`queue_id`),
    KEY `idx_workflow` (`workflow_id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Insert default workflow (optional - can be customized)
-- ============================================================
INSERT IGNORE INTO `ahg_workflow` (`id`, `name`, `description`, `scope_type`, `trigger_event`, `applies_to`, `is_active`, `is_default`) VALUES
(1, 'Standard Review Workflow', 'Default two-step review and approval workflow for archival descriptions', 'global', 'submit', 'information_object', 1, 1);

INSERT IGNORE INTO `ahg_workflow_step` (`id`, `workflow_id`, `name`, `description`, `step_order`, `step_type`, `action_required`, `pool_enabled`, `instructions`) VALUES
(1, 1, 'Initial Review', 'Review submission for completeness and accuracy', 1, 'review', 'approve_reject', 1, 'Check that all required fields are completed and the description follows standards.'),
(2, 1, 'Final Approval', 'Final approval before publication', 2, 'approve', 'approve_reject', 1, 'Verify the description is ready for public access.');

-- ============================================================
-- V2.0: Default work queues (#173)
-- ============================================================
INSERT IGNORE INTO `ahg_workflow_queue` (`id`, `name`, `slug`, `description`, `sort_order`, `sla_days`, `icon`, `color`) VALUES
(1, 'Intake', 'intake', 'New submissions awaiting initial review', 1, 2, 'fa-inbox', '#0d6efd'),
(2, 'Quality Control', 'qc', 'Items undergoing quality checks', 2, 3, 'fa-check-double', '#6610f2'),
(3, 'Description', 'description', 'Items awaiting descriptive cataloguing', 3, 10, 'fa-pen', '#6f42c1'),
(4, 'Rights', 'rights', 'Items requiring rights assessment', 4, 10, 'fa-balance-scale', '#d63384'),
(5, 'Publish', 'publish', 'Items ready for publication review', 5, 3, 'fa-globe', '#28a745'),
(6, 'Requests', 'requests', 'External access and reproduction requests', 6, 5, 'fa-envelope-open', '#fd7e14'),
(7, 'Movement', 'movement', 'Physical movement tracking', 7, 2, 'fa-truck', '#20c997'),
(8, 'Preservation', 'preservation', 'Preservation actions and monitoring', 8, 14, 'fa-shield-alt', '#17a2b8');

-- ============================================================
-- V2.0: Dropdown taxonomy seeds for workflow columns
-- These will be loaded via ahg_dropdown (Dropdown Manager)
-- ============================================================

-- Workflow history action types (22 codes)
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `icon`, `sort_order`, `is_active`, `taxonomy_section`) VALUES
('workflow_history_action', 'Workflow History Action', 'started', 'Started', '#0d6efd', 'fa-play', 1, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'claimed', 'Claimed', '#0dcaf0', 'fa-hand-paper', 2, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'released', 'Released', '#6c757d', 'fa-hand-rock', 3, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'approved', 'Approved', '#198754', 'fa-check-circle', 4, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'rejected', 'Rejected', '#dc3545', 'fa-times-circle', 5, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'returned', 'Returned', '#ffc107', 'fa-undo', 6, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'escalated', 'Escalated', '#fd7e14', 'fa-level-up-alt', 7, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'cancelled', 'Cancelled', '#6c757d', 'fa-ban', 8, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'completed', 'Completed', '#198754', 'fa-flag-checkered', 9, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'comment', 'Comment', '#0d6efd', 'fa-comment', 10, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'reassigned', 'Reassigned', '#6610f2', 'fa-exchange-alt', 11, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'note_added', 'Note Added', '#0dcaf0', 'fa-sticky-note', 12, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'attachment_added', 'Attachment Added', '#20c997', 'fa-paperclip', 13, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'attachment_removed', 'Attachment Removed', '#dc3545', 'fa-unlink', 14, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'rights_decision', 'Rights Decision', '#d63384', 'fa-balance-scale', 15, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'publish', 'Published', '#198754', 'fa-globe', 16, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'unpublish', 'Unpublished', '#ffc107', 'fa-eye-slash', 17, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'priority_changed', 'Priority Changed', '#fd7e14', 'fa-sort', 18, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'due_date_changed', 'Due Date Changed', '#0d6efd', 'fa-calendar-alt', 19, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'sla_breach', 'SLA Breach', '#dc3545', 'fa-exclamation-triangle', 20, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'sla_policy_applied', 'SLA Policy Applied', '#0d6efd', 'fa-clock', 21, 1, 'workflow'),
('workflow_history_action', 'Workflow History Action', 'sla_due_overridden', 'SLA Due Date Overridden', '#fd7e14', 'fa-calendar-check', 22, 1, 'workflow');

-- Workflow priority values
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `icon`, `sort_order`, `is_active`, `taxonomy_section`) VALUES
('workflow_priority', 'Workflow Priority', 'low', 'Low', '#0dcaf0', 'fa-arrow-down', 1, 1, 'workflow'),
('workflow_priority', 'Workflow Priority', 'normal', 'Normal', '#6c757d', 'fa-minus', 2, 1, 'workflow'),
('workflow_priority', 'Workflow Priority', 'high', 'High', '#fd7e14', 'fa-arrow-up', 3, 1, 'workflow'),
('workflow_priority', 'Workflow Priority', 'urgent', 'Urgent', '#dc3545', 'fa-exclamation-circle', 4, 1, 'workflow');

-- Workflow decision values
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `icon`, `sort_order`, `is_active`, `taxonomy_section`) VALUES
('workflow_decision', 'Workflow Decision', 'pending', 'Pending', '#ffc107', 'fa-hourglass-half', 1, 1, 'workflow'),
('workflow_decision', 'Workflow Decision', 'approved', 'Approved', '#198754', 'fa-check', 2, 1, 'workflow'),
('workflow_decision', 'Workflow Decision', 'rejected', 'Rejected', '#dc3545', 'fa-times', 3, 1, 'workflow'),
('workflow_decision', 'Workflow Decision', 'returned', 'Returned', '#fd7e14', 'fa-undo', 4, 1, 'workflow');

-- Workflow notification types (12 codes)
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `icon`, `sort_order`, `is_active`, `taxonomy_section`) VALUES
('workflow_notification_type', 'Workflow Notification Type', 'task_assigned', 'Task Assigned', '#0d6efd', 'fa-user-check', 1, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'task_claimed', 'Task Claimed', '#0dcaf0', 'fa-hand-paper', 2, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'task_approved', 'Task Approved', '#198754', 'fa-check-circle', 3, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'task_rejected', 'Task Rejected', '#dc3545', 'fa-times-circle', 4, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'task_returned', 'Task Returned', '#ffc107', 'fa-undo', 5, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'task_escalated', 'Task Escalated', '#fd7e14', 'fa-level-up-alt', 6, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'task_due_soon', 'Task Due Soon', '#ffc107', 'fa-clock', 7, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'task_overdue', 'Task Overdue', '#dc3545', 'fa-exclamation-triangle', 8, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'workflow_completed', 'Workflow Completed', '#198754', 'fa-flag-checkered', 9, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'sla_warning', 'SLA Warning', '#ffc107', 'fa-bell', 10, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'sla_breach', 'SLA Breach', '#dc3545', 'fa-exclamation-circle', 11, 1, 'workflow'),
('workflow_notification_type', 'Workflow Notification Type', 'bulk_complete', 'Bulk Operation Complete', '#0d6efd', 'fa-layer-group', 12, 1, 'workflow');

-- Workflow escalation actions
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `icon`, `sort_order`, `is_active`, `taxonomy_section`) VALUES
('workflow_escalation_action', 'Workflow Escalation Action', 'notify_lead', 'Notify Team Lead', '#ffc107', 'fa-bell', 1, 1, 'workflow'),
('workflow_escalation_action', 'Workflow Escalation Action', 'notify_admin', 'Notify Administrator', '#fd7e14', 'fa-user-shield', 2, 1, 'workflow'),
('workflow_escalation_action', 'Workflow Escalation Action', 'auto_reassign', 'Auto-Reassign', '#dc3545', 'fa-exchange-alt', 3, 1, 'workflow');






SET FOREIGN_KEY_CHECKS = 1;
