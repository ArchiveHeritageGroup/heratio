-- ============================================================================
-- ahg-core — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgCorePlugin/database/install.sql
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

-- ahgCorePlugin Installation SQL
-- This plugin doesn't require its own tables, but we need to register it

-- Register plugin in atom_plugin table (if not exists)
INSERT IGNORE INTO atom_plugin (name, class_name, version, description, category, is_enabled, is_core, is_locked, load_order, created_at)
VALUES (
    'ahgCorePlugin',
    'ahgCorePluginConfiguration',
    '1.0.0',
    'Core utilities and shared services for AHG plugins',
    'core',
    1,
    1,
    1,
    1,
    NOW()
);

-- Update if already exists
UPDATE atom_plugin SET
    version = '1.0.0',
    description = 'Core utilities and shared services for AHG plugins',
    category = 'core',
    is_enabled = 1,
    is_core = 1,
    is_locked = 1,
    load_order = 1,
    updated_at = NOW()
WHERE name = 'ahgCorePlugin';

-- ============================================================
-- AHG Dropdown Table
-- Plugin-specific controlled vocabulary system
-- Replaces hardcoded dropdown values with database-driven terms
-- ============================================================

CREATE TABLE IF NOT EXISTS `ahg_dropdown` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `taxonomy` VARCHAR(100) NOT NULL COMMENT 'Taxonomy code e.g. loan_status',
    `taxonomy_label` VARCHAR(255) NOT NULL COMMENT 'Display name e.g. Loan Status',
    `code` VARCHAR(100) NOT NULL COMMENT 'Term code e.g. draft',
    `label` VARCHAR(255) NOT NULL COMMENT 'Term display name',
    `color` VARCHAR(7) NULL COMMENT 'Hex color e.g. #4caf50',
    `icon` VARCHAR(50) NULL COMMENT 'Icon class e.g. fa-check',
    `sort_order` INT DEFAULT 0,
    `is_default` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `taxonomy_section` VARCHAR(50) NULL COMMENT 'UI section grouping',
    `metadata` JSON NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_taxonomy_code` (`taxonomy`, `code`),
    INDEX `idx_taxonomy` (`taxonomy`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_section` (`taxonomy_section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA: Exhibition Types
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`) VALUES
('exhibition_type', 'Exhibition Type', 'permanent', 'Permanent Exhibition', 10),
('exhibition_type', 'Exhibition Type', 'temporary', 'Temporary Exhibition', 20),
('exhibition_type', 'Exhibition Type', 'traveling', 'Traveling Exhibition', 30),
('exhibition_type', 'Exhibition Type', 'online', 'Online/Virtual Exhibition', 40),
('exhibition_type', 'Exhibition Type', 'pop_up', 'Pop-up Exhibition', 50);

-- ============================================================
-- SEED DATA: Exhibition Status (with colors)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('exhibition_status', 'Exhibition Status', 'concept', 'Concept', '#9e9e9e', 10, 1),
('exhibition_status', 'Exhibition Status', 'planning', 'Planning', '#2196f3', 20, 0),
('exhibition_status', 'Exhibition Status', 'preparation', 'Preparation', '#ff9800', 30, 0),
('exhibition_status', 'Exhibition Status', 'installation', 'Installation', '#9c27b0', 40, 0),
('exhibition_status', 'Exhibition Status', 'open', 'Open', '#4caf50', 50, 0),
('exhibition_status', 'Exhibition Status', 'closing', 'Closing', '#ff5722', 60, 0),
('exhibition_status', 'Exhibition Status', 'closed', 'Closed', '#795548', 70, 0),
('exhibition_status', 'Exhibition Status', 'archived', 'Archived', '#607d8b', 80, 0),
('exhibition_status', 'Exhibition Status', 'canceled', 'Canceled', '#f44336', 90, 0);

-- ============================================================
-- SEED DATA: Exhibition Object Status
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('exhibition_object_status', 'Exhibition Object Status', 'proposed', 'Proposed', 10, 1),
('exhibition_object_status', 'Exhibition Object Status', 'confirmed', 'Confirmed', 20, 0),
('exhibition_object_status', 'Exhibition Object Status', 'on_loan_request', 'Loan Requested', 30, 0),
('exhibition_object_status', 'Exhibition Object Status', 'installed', 'Installed', 40, 0),
('exhibition_object_status', 'Exhibition Object Status', 'removed', 'Removed', 50, 0),
('exhibition_object_status', 'Exhibition Object Status', 'returned', 'Returned', 60, 0);

-- ============================================================
-- SEED DATA: Request to Publish Status
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('rtp_status', 'Request to Publish Status', 'in_review', 'In Review', '#ff9800', 10, 1),
('rtp_status', 'Request to Publish Status', 'rejected', 'Rejected', '#f44336', 20, 0),
('rtp_status', 'Request to Publish Status', 'approved', 'Approved', '#4caf50', 30, 0);

-- ============================================================
-- SEED DATA: Workflow Status (with colors)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('workflow_status', 'Workflow Status', 'not_started', 'Not Started', '#9e9e9e', 10, 1),
('workflow_status', 'Workflow Status', 'in_progress', 'In Progress', '#2196f3', 20, 0),
('workflow_status', 'Workflow Status', 'pending_review', 'Pending Review', '#ff9800', 30, 0),
('workflow_status', 'Workflow Status', 'pending_approval', 'Pending Approval', '#ff9800', 35, 0),
('workflow_status', 'Workflow Status', 'approved', 'Approved', '#8bc34a', 40, 0),
('workflow_status', 'Workflow Status', 'completed', 'Completed', '#4caf50', 50, 0),
('workflow_status', 'Workflow Status', 'on_hold', 'On Hold', '#607d8b', 60, 0),
('workflow_status', 'Workflow Status', 'cancelled', 'Cancelled', '#f44336', 70, 0),
('workflow_status', 'Workflow Status', 'overdue', 'Overdue', '#e91e63', 80, 0);

-- ============================================================
-- SEED DATA: Link Status (Getty/vocabulary links)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('link_status', 'Link Status', 'pending', 'Pending', 10, 1),
('link_status', 'Link Status', 'suggested', 'Suggested', 20, 0),
('link_status', 'Link Status', 'confirmed', 'Confirmed', 30, 0),
('link_status', 'Link Status', 'rejected', 'Rejected', 40, 0);

-- ============================================================
-- SEED DATA: Loan Status (with colors)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('loan_status', 'Loan Status', 'draft', 'Draft', '#9e9e9e', 10, 1),
('loan_status', 'Loan Status', 'pending_approval', 'Pending Approval', '#ff9800', 20, 0),
('loan_status', 'Loan Status', 'approved', 'Approved', '#8bc34a', 30, 0),
('loan_status', 'Loan Status', 'active', 'Active', '#4caf50', 40, 0),
('loan_status', 'Loan Status', 'in_transit', 'In Transit', '#2196f3', 50, 0),
('loan_status', 'Loan Status', 'overdue', 'Overdue', '#e91e63', 60, 0),
('loan_status', 'Loan Status', 'returned', 'Returned', '#607d8b', 70, 0),
('loan_status', 'Loan Status', 'cancelled', 'Cancelled', '#f44336', 80, 0);

-- ============================================================
-- SEED DATA: Loan Type
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`) VALUES
('loan_type', 'Loan Type', 'incoming', 'Incoming Loan', 10),
('loan_type', 'Loan Type', 'outgoing', 'Outgoing Loan', 20),
('loan_type', 'Loan Type', 'exhibition', 'Exhibition Loan', 30),
('loan_type', 'Loan Type', 'research', 'Research Loan', 40),
('loan_type', 'Loan Type', 'conservation', 'Conservation Loan', 50);

-- ============================================================
-- SEED DATA: Spectrum Procedure Status
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('spectrum_procedure_status', 'Spectrum Procedure Status', 'not_started', 'Not Started', '#9e9e9e', 10, 1),
('spectrum_procedure_status', 'Spectrum Procedure Status', 'in_progress', 'In Progress', '#2196f3', 20, 0),
('spectrum_procedure_status', 'Spectrum Procedure Status', 'completed', 'Completed', '#4caf50', 30, 0),
('spectrum_procedure_status', 'Spectrum Procedure Status', 'on_hold', 'On Hold', '#ff9800', 40, 0);

-- ============================================================
-- SEED DATA: Rights Basis
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('rights_basis', 'Rights Basis', 'copyright', 'Copyright', 10, 1),
('rights_basis', 'Rights Basis', 'license', 'License', 20, 0),
('rights_basis', 'Rights Basis', 'statute', 'Statute', 30, 0),
('rights_basis', 'Rights Basis', 'donor', 'Donor Agreement', 40, 0),
('rights_basis', 'Rights Basis', 'policy', 'Institutional Policy', 50, 0),
('rights_basis', 'Rights Basis', 'other', 'Other', 60, 0);

-- ============================================================
-- SEED DATA: Copyright Status
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('copyright_status', 'Copyright Status', 'copyrighted', 'In Copyright', '#f44336', 10, 0),
('copyright_status', 'Copyright Status', 'public_domain', 'Public Domain', '#4caf50', 20, 0),
('copyright_status', 'Copyright Status', 'unknown', 'Unknown', '#9e9e9e', 30, 1);

-- ============================================================
-- SEED DATA: Act Type (Rights Actions)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`) VALUES
('act_type', 'Act Type', 'render', 'Render / Display', 10),
('act_type', 'Act Type', 'disseminate', 'Disseminate / Distribute', 20),
('act_type', 'Act Type', 'replicate', 'Replicate / Copy', 30),
('act_type', 'Act Type', 'migrate', 'Migrate / Transform', 40),
('act_type', 'Act Type', 'modify', 'Modify / Edit', 50),
('act_type', 'Act Type', 'delete', 'Delete', 60),
('act_type', 'Act Type', 'print', 'Print', 70),
('act_type', 'Act Type', 'publish', 'Publish', 80),
('act_type', 'Act Type', 'use', 'Use', 90),
('act_type', 'Act Type', 'excerpt', 'Excerpt', 100),
('act_type', 'Act Type', 'annotate', 'Annotate', 110);

-- ============================================================
-- SEED DATA: Restriction Type
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('restriction_type', 'Restriction Type', 'allow', 'Allow', '#4caf50', 10, 1),
('restriction_type', 'Restriction Type', 'disallow', 'Disallow', '#f44336', 20, 0),
('restriction_type', 'Restriction Type', 'conditional', 'Conditional', '#ff9800', 30, 0);

-- ============================================================
-- SEED DATA: Embargo Type
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('embargo_type', 'Embargo Type', 'full', 'Full Embargo', '#f44336', 10, 0),
('embargo_type', 'Embargo Type', 'metadata_only', 'Metadata Only (No Digital)', '#ff9800', 20, 0),
('embargo_type', 'Embargo Type', 'digital_only', 'Digital Only (Metadata Visible)', '#2196f3', 30, 0),
('embargo_type', 'Embargo Type', 'partial', 'Partial', '#9c27b0', 40, 0);

-- ============================================================
-- SEED DATA: Embargo Reason
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('embargo_reason', 'Embargo Reason', 'donor_restriction', 'Donor Restriction', 10, 1),
('embargo_reason', 'Embargo Reason', 'copyright', 'Copyright', 20, 0),
('embargo_reason', 'Embargo Reason', 'privacy', 'Privacy', 30, 0),
('embargo_reason', 'Embargo Reason', 'legal', 'Legal Hold', 40, 0),
('embargo_reason', 'Embargo Reason', 'commercial', 'Commercial Sensitivity', 50, 0),
('embargo_reason', 'Embargo Reason', 'research', 'Research Embargo', 60, 0),
('embargo_reason', 'Embargo Reason', 'cultural', 'Cultural Sensitivity', 70, 0),
('embargo_reason', 'Embargo Reason', 'security', 'Security Classification', 80, 0),
('embargo_reason', 'Embargo Reason', 'other', 'Other', 90, 0);

-- ============================================================
-- SEED DATA: Work Type (Copyright)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`) VALUES
('work_type', 'Work Type', 'literary', 'Literary Work', 10),
('work_type', 'Work Type', 'dramatic', 'Dramatic Work', 20),
('work_type', 'Work Type', 'musical', 'Musical Work', 30),
('work_type', 'Work Type', 'artistic', 'Artistic Work', 40),
('work_type', 'Work Type', 'film', 'Film', 50),
('work_type', 'Work Type', 'sound_recording', 'Sound Recording', 60),
('work_type', 'Work Type', 'broadcast', 'Broadcast', 70),
('work_type', 'Work Type', 'photograph', 'Photograph', 80),
('work_type', 'Work Type', 'database', 'Database', 90),
('work_type', 'Work Type', 'other', 'Other', 100);

-- ============================================================
-- SEED DATA: Source Type (Rights Research)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`) VALUES
('source_type', 'Source Type', 'database', 'Database/Registry', 10),
('source_type', 'Source Type', 'registry', 'Copyright Registry', 20),
('source_type', 'Source Type', 'publisher', 'Publisher', 30),
('source_type', 'Source Type', 'author_society', 'Author/Rights Society', 40),
('source_type', 'Source Type', 'archive', 'Archive/Library', 50),
('source_type', 'Source Type', 'library', 'Library Catalog', 60),
('source_type', 'Source Type', 'internet', 'Internet Search', 70),
('source_type', 'Source Type', 'newspaper', 'Newspaper/Publication', 80),
('source_type', 'Source Type', 'other', 'Other', 90);

-- ============================================================
-- SEED DATA: Agreement Status (Donor Agreements)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('agreement_status', 'Agreement Status', 'draft', 'Draft', '#9e9e9e', 10, 1),
('agreement_status', 'Agreement Status', 'pending_review', 'Pending Review', '#ff9800', 20, 0),
('agreement_status', 'Agreement Status', 'pending_signature', 'Pending Signature', '#2196f3', 30, 0),
('agreement_status', 'Agreement Status', 'pending_approval', 'Pending Approval', '#ff9800', 35, 0),
('agreement_status', 'Agreement Status', 'active', 'Active', '#4caf50', 40, 0),
('agreement_status', 'Agreement Status', 'suspended', 'Suspended', '#9c27b0', 50, 0),
('agreement_status', 'Agreement Status', 'expired', 'Expired', '#795548', 60, 0),
('agreement_status', 'Agreement Status', 'terminated', 'Terminated', '#f44336', 70, 0),
('agreement_status', 'Agreement Status', 'renewed', 'Renewed', '#8bc34a', 80, 0);

-- ============================================================
-- SEED DATA: Condition Grade
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('condition_grade', 'Condition Grade', 'excellent', 'Excellent', '#4caf50', 10, 0),
('condition_grade', 'Condition Grade', 'good', 'Good', '#8bc34a', 20, 1),
('condition_grade', 'Condition Grade', 'fair', 'Fair', '#ff9800', 30, 0),
('condition_grade', 'Condition Grade', 'poor', 'Poor', '#ff5722', 40, 0),
('condition_grade', 'Condition Grade', 'unacceptable', 'Unacceptable', '#f44336', 50, 0);

-- ============================================================
-- SEED DATA: Damage Type
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `metadata`) VALUES
('damage_type', 'Damage Type', 'abrasion', 'Abrasion/Scratches', 10, '{"category": "physical"}'),
('damage_type', 'Damage Type', 'crack', 'Crack', 20, '{"category": "physical"}'),
('damage_type', 'Damage Type', 'break', 'Break/Fracture', 30, '{"category": "physical"}'),
('damage_type', 'Damage Type', 'chip', 'Chip/Loss', 40, '{"category": "physical"}'),
('damage_type', 'Damage Type', 'dent', 'Dent/Deformation', 50, '{"category": "physical"}'),
('damage_type', 'Damage Type', 'tear', 'Tear', 60, '{"category": "physical"}'),
('damage_type', 'Damage Type', 'hole', 'Hole/Puncture', 70, '{"category": "physical"}'),
('damage_type', 'Damage Type', 'missing_part', 'Missing Part', 80, '{"category": "physical"}'),
('damage_type', 'Damage Type', 'stain', 'Stain', 90, '{"category": "surface"}'),
('damage_type', 'Damage Type', 'discoloration', 'Discoloration', 100, '{"category": "surface"}'),
('damage_type', 'Damage Type', 'fading', 'Fading', 110, '{"category": "surface"}'),
('damage_type', 'Damage Type', 'foxing', 'Foxing', 120, '{"category": "surface"}'),
('damage_type', 'Damage Type', 'accretion', 'Accretion/Deposit', 130, '{"category": "surface"}'),
('damage_type', 'Damage Type', 'corrosion', 'Corrosion/Rust', 140, '{"category": "surface"}'),
('damage_type', 'Damage Type', 'tarnish', 'Tarnish', 150, '{"category": "surface"}'),
('damage_type', 'Damage Type', 'delamination', 'Delamination', 160, '{"category": "structural"}'),
('damage_type', 'Damage Type', 'flaking', 'Flaking/Lifting', 170, '{"category": "structural"}'),
('damage_type', 'Damage Type', 'warping', 'Warping', 180, '{"category": "structural"}'),
('damage_type', 'Damage Type', 'cupping', 'Cupping', 190, '{"category": "structural"}'),
('damage_type', 'Damage Type', 'splitting', 'Splitting', 200, '{"category": "structural"}'),
('damage_type', 'Damage Type', 'loose_joint', 'Loose Joint', 210, '{"category": "structural"}');

-- ============================================================
-- SEED DATA: Shipment Type
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('shipment_type', 'Shipment Type', 'outbound', 'Outbound (To Borrower)', 10, 1),
('shipment_type', 'Shipment Type', 'return', 'Return (To Lender)', 20, 0);

-- ============================================================
-- SEED DATA: Shipment Status
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('shipment_status', 'Shipment Status', 'planned', 'Planned', '#9e9e9e', 10, 1),
('shipment_status', 'Shipment Status', 'picked_up', 'Picked Up', '#2196f3', 20, 0),
('shipment_status', 'Shipment Status', 'in_transit', 'In Transit', '#ff9800', 30, 0),
('shipment_status', 'Shipment Status', 'customs', 'In Customs', '#9c27b0', 40, 0),
('shipment_status', 'Shipment Status', 'out_for_delivery', 'Out for Delivery', '#00bcd4', 50, 0),
('shipment_status', 'Shipment Status', 'delivered', 'Delivered', '#4caf50', 60, 0),
('shipment_status', 'Shipment Status', 'failed', 'Delivery Failed', '#f44336', 70, 0),
('shipment_status', 'Shipment Status', 'returned', 'Returned to Sender', '#795548', 80, 0);

-- ============================================================
-- SEED DATA: Cost Type (Loan Costs)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`) VALUES
('cost_type', 'Cost Type', 'transport', 'Transport/Shipping', 10),
('cost_type', 'Cost Type', 'insurance', 'Insurance', 20),
('cost_type', 'Cost Type', 'conservation', 'Conservation', 30),
('cost_type', 'Cost Type', 'framing', 'Framing/Mounting', 40),
('cost_type', 'Cost Type', 'crating', 'Crating/Packing', 50),
('cost_type', 'Cost Type', 'customs', 'Customs/Duties', 60),
('cost_type', 'Cost Type', 'courier_fee', 'Courier Fee', 70),
('cost_type', 'Cost Type', 'handling', 'Handling', 80),
('cost_type', 'Cost Type', 'photography', 'Photography', 90),
('cost_type', 'Cost Type', 'other', 'Other', 100);

-- ============================================================
-- SEED DATA: Report Type (Condition Reports)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('report_type', 'Report Type', 'incoming', 'Incoming', 10, 1),
('report_type', 'Report Type', 'outgoing', 'Outgoing', 20, 0),
('report_type', 'Report Type', 'periodic', 'Periodic', 30, 0),
('report_type', 'Report Type', 'damage', 'Damage', 40, 0);

-- ============================================================
-- SEED DATA: Image Type (Condition Photos)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('image_type', 'Image Type', 'overall', 'Overall', 10, 1),
('image_type', 'Image Type', 'detail', 'Detail', 20, 0),
('image_type', 'Image Type', 'damage', 'Damage', 30, 0),
('image_type', 'Image Type', 'before', 'Before', 40, 0),
('image_type', 'Image Type', 'after', 'After', 50, 0);

-- ============================================================
-- SEED DATA: Embargo Status
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('embargo_status', 'Embargo Status', 'active', 'Active', '#f44336', 10, 1),
('embargo_status', 'Embargo Status', 'pending', 'Pending', '#ff9800', 20, 0),
('embargo_status', 'Embargo Status', 'extended', 'Extended', '#9c27b0', 30, 0),
('embargo_status', 'Embargo Status', 'expired', 'Expired', '#9e9e9e', 40, 0),
('embargo_status', 'Embargo Status', 'lifted', 'Lifted', '#4caf50', 50, 0);

-- ============================================================
-- SEED DATA: ID Type (Research/Visitor Registration)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('id_type', 'ID Type', 'passport', 'Passport', 10, 0),
('id_type', 'ID Type', 'national_id', 'National ID', 20, 1),
('id_type', 'ID Type', 'drivers_license', 'Driver''s License', 30, 0),
('id_type', 'ID Type', 'student_card', 'Student Card', 40, 0),
('id_type', 'ID Type', 'employee_id', 'Employee ID', 50, 0),
('id_type', 'ID Type', 'other', 'Other', 90, 0);

-- ============================================================
-- SEED DATA: Organization Type (Research/Visitor)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('organization_type', 'Organization Type', 'independent', 'Independent Researcher', 10, 0),
('organization_type', 'Organization Type', 'academic', 'Academic Institution', 20, 1),
('organization_type', 'Organization Type', 'government', 'Government', 30, 0),
('organization_type', 'Organization Type', 'private', 'Private Company', 40, 0),
('organization_type', 'Organization Type', 'nonprofit', 'Non-Profit Organization', 50, 0),
('organization_type', 'Organization Type', 'student', 'Student', 60, 0),
('organization_type', 'Organization Type', 'media', 'Media/Press', 70, 0),
('organization_type', 'Organization Type', 'other', 'Other', 90, 0);

-- ============================================================
-- SEED DATA: Equipment Type (Reading Room)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`) VALUES
('equipment_type', 'Equipment Type', 'microfilm_reader', 'Microfilm Reader', 10),
('equipment_type', 'Equipment Type', 'microfiche_reader', 'Microfiche Reader', 20),
('equipment_type', 'Equipment Type', 'scanner', 'Scanner', 30),
('equipment_type', 'Equipment Type', 'computer', 'Computer Workstation', 40),
('equipment_type', 'Equipment Type', 'laptop', 'Laptop', 50),
('equipment_type', 'Equipment Type', 'magnifier', 'Magnifier/Loupe', 60),
('equipment_type', 'Equipment Type', 'light_box', 'Light Box', 70),
('equipment_type', 'Equipment Type', 'camera_stand', 'Camera Stand', 80),
('equipment_type', 'Equipment Type', 'projector', 'Projector', 90),
('equipment_type', 'Equipment Type', 'audio_player', 'Audio Player', 100),
('equipment_type', 'Equipment Type', 'video_player', 'Video Player', 110),
('equipment_type', 'Equipment Type', 'other', 'Other', 200);

-- ============================================================
-- SEED DATA: Equipment Condition
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('equipment_condition', 'Equipment Condition', 'excellent', 'Excellent', '#4caf50', 10, 0),
('equipment_condition', 'Equipment Condition', 'good', 'Good', '#8bc34a', 20, 1),
('equipment_condition', 'Equipment Condition', 'fair', 'Fair', '#ff9800', 30, 0),
('equipment_condition', 'Equipment Condition', 'needs_repair', 'Needs Repair', '#ff5722', 40, 0),
('equipment_condition', 'Equipment Condition', 'out_of_service', 'Out of Service', '#f44336', 50, 0);

-- ============================================================
-- SEED DATA: Workspace Privacy (Research Workspaces)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('workspace_privacy', 'Workspace Privacy', 'private', 'Private (Only Me)', 10, 1),
('workspace_privacy', 'Workspace Privacy', 'members', 'Members Only', 20, 0),
('workspace_privacy', 'Workspace Privacy', 'public', 'Public', 30, 0);

-- ============================================================
-- SEED DATA: Creator Role (Library/Bibliographic)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('creator_role', 'Creator Role', 'author', 'Author', 10, 1),
('creator_role', 'Creator Role', 'editor', 'Editor', 20, 0),
('creator_role', 'Creator Role', 'translator', 'Translator', 30, 0),
('creator_role', 'Creator Role', 'illustrator', 'Illustrator', 40, 0),
('creator_role', 'Creator Role', 'compiler', 'Compiler', 50, 0),
('creator_role', 'Creator Role', 'contributor', 'Contributor', 60, 0),
('creator_role', 'Creator Role', 'photographer', 'Photographer', 70, 0),
('creator_role', 'Creator Role', 'composer', 'Composer', 80, 0),
('creator_role', 'Creator Role', 'director', 'Director', 90, 0),
('creator_role', 'Creator Role', 'producer', 'Producer', 100, 0),
('creator_role', 'Creator Role', 'narrator', 'Narrator', 110, 0),
('creator_role', 'Creator Role', 'other', 'Other', 200, 0);

-- ============================================================
-- SEED DATA: Document Type (Donor Agreements)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('document_type', 'Document Type', 'signed_agreement', 'Signed Agreement', 10, 1),
('document_type', 'Document Type', 'draft', 'Draft', 20, 0),
('document_type', 'Document Type', 'amendment', 'Amendment', 30, 0),
('document_type', 'Document Type', 'addendum', 'Addendum', 40, 0),
('document_type', 'Document Type', 'correspondence', 'Correspondence', 50, 0),
('document_type', 'Document Type', 'inventory', 'Inventory List', 60, 0),
('document_type', 'Document Type', 'provenance_evidence', 'Provenance Evidence', 70, 0),
('document_type', 'Document Type', 'appraisal', 'Appraisal Report', 80, 0),
('document_type', 'Document Type', 'receipt', 'Receipt', 90, 0),
('document_type', 'Document Type', 'other', 'Other', 200, 0);

-- ============================================================
-- SEED DATA: Reminder Type (Agreements/Loans)
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('reminder_type', 'Reminder Type', 'review_due', 'Review Due', 10, 1),
('reminder_type', 'Reminder Type', 'expiry_warning', 'Expiry Warning', 20, 0),
('reminder_type', 'Reminder Type', 'renewal_required', 'Renewal Required', 30, 0),
('reminder_type', 'Reminder Type', 'donor_contact', 'Donor Contact', 40, 0),
('reminder_type', 'Reminder Type', 'follow_up', 'Follow Up', 50, 0),
('reminder_type', 'Reminder Type', 'custom', 'Custom', 90, 0);

-- ============================================================
-- SEED DATA: RDF Export Format
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('rdf_format', 'RDF Export Format', 'jsonld', 'JSON-LD', 10, 1),
('rdf_format', 'RDF Export Format', 'turtle', 'Turtle (.ttl)', 20, 0),
('rdf_format', 'RDF Export Format', 'rdfxml', 'RDF/XML', 30, 0),
('rdf_format', 'RDF Export Format', 'ntriples', 'N-Triples', 40, 0),
('rdf_format', 'RDF Export Format', 'n3', 'Notation3 (N3)', 50, 0);

-- ============================================================
-- SEED DATA: Federation Sync Direction
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('federation_sync_direction', 'Federation Sync Direction', 'pull', 'Pull (from remote)', 10, 1),
('federation_sync_direction', 'Federation Sync Direction', 'push', 'Push (to remote)', 20, 0),
('federation_sync_direction', 'Federation Sync Direction', 'bidirectional', 'Bidirectional', 30, 0);

-- ============================================================
-- SEED DATA: Federation Conflict Resolution
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_default`) VALUES
('federation_conflict_resolution', 'Federation Conflict Resolution', 'prefer_local', 'Prefer Local', 10, 0),
('federation_conflict_resolution', 'Federation Conflict Resolution', 'prefer_remote', 'Prefer Remote', 20, 0),
('federation_conflict_resolution', 'Federation Conflict Resolution', 'skip', 'Skip Conflicts', 30, 1),
('federation_conflict_resolution', 'Federation Conflict Resolution', 'merge', 'Merge', 40, 0);

-- ============================================================
-- SEED DATA: Federation Harvest Action
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`) VALUES
('federation_harvest_action', 'Federation Harvest Action', 'created', 'Created', '#4caf50', 10),
('federation_harvest_action', 'Federation Harvest Action', 'updated', 'Updated', '#2196f3', 20),
('federation_harvest_action', 'Federation Harvest Action', 'deleted', 'Deleted', '#f44336', 30);

-- ============================================================
-- SEED DATA: Federation Session Status
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('federation_session_status', 'Federation Session Status', 'running', 'Running', '#2196f3', 10, 1),
('federation_session_status', 'Federation Session Status', 'completed', 'Completed', '#4caf50', 20, 0),
('federation_session_status', 'Federation Session Status', 'failed', 'Failed', '#f44336', 30, 0),
('federation_session_status', 'Federation Session Status', 'cancelled', 'Cancelled', '#9e9e9e', 40, 0);

-- ============================================================
-- SEED DATA: Federation Mapping Status
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('federation_mapping_status', 'Federation Mapping Status', 'matched', 'Matched', '#4caf50', 10, 1),
('federation_mapping_status', 'Federation Mapping Status', 'created', 'Created', '#2196f3', 20, 0),
('federation_mapping_status', 'Federation Mapping Status', 'conflict', 'Conflict', '#ff9800', 30, 0),
('federation_mapping_status', 'Federation Mapping Status', 'skipped', 'Skipped', '#9e9e9e', 40, 0);

-- ============================================================
-- SEED DATA: Federation Change Type
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`) VALUES
('federation_change_type', 'Federation Change Type', 'term_added', 'Term Added', 10),
('federation_change_type', 'Federation Change Type', 'term_updated', 'Term Updated', 20),
('federation_change_type', 'Federation Change Type', 'term_deleted', 'Term Deleted', 30),
('federation_change_type', 'Federation Change Type', 'term_moved', 'Term Moved', 40),
('federation_change_type', 'Federation Change Type', 'relation_added', 'Relation Added', 50),
('federation_change_type', 'Federation Change Type', 'relation_removed', 'Relation Removed', 60);

-- ============================================================
-- SEED DATA: Federation Search Status
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('federation_search_status', 'Federation Search Status', 'success', 'Success', '#4caf50', 10, 1),
('federation_search_status', 'Federation Search Status', 'timeout', 'Timeout', '#ff9800', 20, 0),
('federation_search_status', 'Federation Search Status', 'error', 'Error', '#f44336', 30, 0);

-- ============================================================
-- SEED DATA: Restriction Code (Access Restrictions)
-- Base set of access restriction vocabularies for any institution.
-- Institutions add their own codes via Admin > Dropdown Manager.
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
('restriction_code', 'Access Restriction', 'open', 'Open / Unrestricted', '#4caf50', 10, 1),
('restriction_code', 'Access Restriction', 'closed', 'Closed', '#f44336', 20, 0),
('restriction_code', 'Access Restriction', 'restricted_time', 'Time-based Restriction', '#ff9800', 30, 0),
('restriction_code', 'Access Restriction', 'restricted_permission', 'Permission Required', '#2196f3', 40, 0),
('restriction_code', 'Access Restriction', 'restricted_privacy', 'Privacy Restriction', '#9c27b0', 50, 0),
('restriction_code', 'Access Restriction', 'restricted_legal', 'Legal Hold', '#795548', 60, 0),
('restriction_code', 'Access Restriction', 'restricted_cultural', 'Cultural Protocol', '#607d8b', 70, 0),
('restriction_code', 'Access Restriction', 'restricted_security', 'Security Classification', '#e91e63', 80, 0),
('restriction_code', 'Access Restriction', 'restricted_donor', 'Donor Restriction', '#ff5722', 90, 0);

-- ============================================================
-- ENUM TO DROPDOWN MIGRATION DATA
-- These values replace hardcoded ENUM columns across all AHG plugins
-- Additional migrations: enum_to_dropdown_migration_phase2*.sql
-- ============================================================

-- JOB/TASK STATUS (used by: ahg_ai_batch, ahg_ai_job, ahg_dedupe_scan, etc.)
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('job_status', 'Job Status', 'pending', 'Pending', '#6c757d', 10, 1, NOW()),
('job_status', 'Job Status', 'queued', 'Queued', '#17a2b8', 20, 1, NOW()),
('job_status', 'Job Status', 'running', 'Running', '#007bff', 30, 1, NOW()),
('job_status', 'Job Status', 'paused', 'Paused', '#ffc107', 40, 1, NOW()),
('job_status', 'Job Status', 'completed', 'Completed', '#28a745', 50, 1, NOW()),
('job_status', 'Job Status', 'failed', 'Failed', '#dc3545', 60, 1, NOW()),
('job_status', 'Job Status', 'cancelled', 'Cancelled', '#6c757d', 70, 1, NOW()),
('job_status', 'Job Status', 'skipped', 'Skipped', '#868e96', 80, 1, NOW());

-- APPROVAL STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('approval_status', 'Approval Status', 'pending', 'Pending', '#ffc107', 10, 1, NOW()),
('approval_status', 'Approval Status', 'approved', 'Approved', '#28a745', 20, 1, NOW()),
('approval_status', 'Approval Status', 'rejected', 'Rejected', '#dc3545', 30, 1, NOW()),
('approval_status', 'Approval Status', 'returned', 'Returned', '#fd7e14', 40, 1, NOW()),
('approval_status', 'Approval Status', 'escalated', 'Escalated', '#e83e8c', 50, 1, NOW()),
('approval_status', 'Approval Status', 'edited', 'Edited', '#17a2b8', 60, 1, NOW());

-- PRIORITY LEVELS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('priority_level', 'Priority Level', 'low', 'Low', '#28a745', 10, 1, NOW()),
('priority_level', 'Priority Level', 'normal', 'Normal', '#007bff', 20, 1, NOW()),
('priority_level', 'Priority Level', 'high', 'High', '#fd7e14', 30, 1, NOW()),
('priority_level', 'Priority Level', 'urgent', 'Urgent', '#dc3545', 40, 1, NOW());

-- RISK/SEVERITY LEVELS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('risk_level', 'Risk Level', 'low', 'Low', '#28a745', 10, 1, NOW()),
('risk_level', 'Risk Level', 'medium', 'Medium', '#ffc107', 20, 1, NOW()),
('risk_level', 'Risk Level', 'high', 'High', '#fd7e14', 30, 1, NOW()),
('risk_level', 'Risk Level', 'critical', 'Critical', '#dc3545', 40, 1, NOW());

-- VENDOR STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('vendor_status', 'Vendor Status', 'active', 'Active', '#28a745', 10, 1, NOW()),
('vendor_status', 'Vendor Status', 'inactive', 'Inactive', '#6c757d', 20, 1, NOW()),
('vendor_status', 'Vendor Status', 'suspended', 'Suspended', '#dc3545', 30, 1, NOW()),
('vendor_status', 'Vendor Status', 'pending_approval', 'Pending Approval', '#ffc107', 40, 1, NOW());

-- VENDOR TYPE
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('vendor_type', 'Vendor Type', 'company', 'Company', '#007bff', 10, 1, NOW()),
('vendor_type', 'Vendor Type', 'individual', 'Individual', '#28a745', 20, 1, NOW()),
('vendor_type', 'Vendor Type', 'institution', 'Institution', '#6f42c1', 30, 1, NOW()),
('vendor_type', 'Vendor Type', 'government', 'Government', '#fd7e14', 40, 1, NOW());

-- CONTRACT STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('contract_status', 'Contract Status', 'draft', 'Draft', '#6c757d', 10, 1, NOW()),
('contract_status', 'Contract Status', 'pending_review', 'Pending Review', '#ffc107', 20, 1, NOW()),
('contract_status', 'Contract Status', 'pending_signature', 'Pending Signature', '#17a2b8', 30, 1, NOW()),
('contract_status', 'Contract Status', 'active', 'Active', '#28a745', 40, 1, NOW()),
('contract_status', 'Contract Status', 'suspended', 'Suspended', '#fd7e14', 50, 1, NOW()),
('contract_status', 'Contract Status', 'expired', 'Expired', '#dc3545', 60, 1, NOW()),
('contract_status', 'Contract Status', 'terminated', 'Terminated', '#343a40', 70, 1, NOW()),
('contract_status', 'Contract Status', 'renewed', 'Renewed', '#007bff', 80, 1, NOW()),
('contract_status', 'Contract Status', 'superseded', 'Superseded', '#868e96', 90, 1, NOW());

-- COUNTERPARTY TYPE
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('counterparty_type', 'Counterparty Type', 'vendor', 'Vendor/Supplier', '#007bff', 10, 1, NOW()),
('counterparty_type', 'Counterparty Type', 'institution', 'Institution', '#6f42c1', 20, 1, NOW()),
('counterparty_type', 'Counterparty Type', 'individual', 'Individual', '#28a745', 30, 1, NOW()),
('counterparty_type', 'Counterparty Type', 'government', 'Government', '#fd7e14', 40, 1, NOW()),
('counterparty_type', 'Counterparty Type', 'other', 'Other', '#6c757d', 50, 1, NOW());

-- PAYMENT FREQUENCY
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('payment_frequency', 'Payment Frequency', 'once', 'Once', '#6c757d', 10, 1, NOW()),
('payment_frequency', 'Payment Frequency', 'monthly', 'Monthly', '#007bff', 20, 1, NOW()),
('payment_frequency', 'Payment Frequency', 'quarterly', 'Quarterly', '#17a2b8', 30, 1, NOW()),
('payment_frequency', 'Payment Frequency', 'annually', 'Annually', '#28a745', 40, 1, NOW()),
('payment_frequency', 'Payment Frequency', 'on_delivery', 'On Delivery', '#fd7e14', 50, 1, NOW());

-- RECURRENCE PATTERN
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('recurrence_pattern', 'Recurrence Pattern', 'daily', 'Daily', '#dc3545', 10, 1, NOW()),
('recurrence_pattern', 'Recurrence Pattern', 'weekly', 'Weekly', '#fd7e14', 20, 1, NOW()),
('recurrence_pattern', 'Recurrence Pattern', 'monthly', 'Monthly', '#ffc107', 30, 1, NOW()),
('recurrence_pattern', 'Recurrence Pattern', 'quarterly', 'Quarterly', '#28a745', 40, 1, NOW()),
('recurrence_pattern', 'Recurrence Pattern', 'yearly', 'Yearly', '#007bff', 50, 1, NOW());

-- GLAM SECTOR
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('glam_sector', 'GLAM Sector', 'archive', 'Archive', '#007bff', 10, 1, NOW()),
('glam_sector', 'GLAM Sector', 'library', 'Library', '#28a745', 20, 1, NOW()),
('glam_sector', 'GLAM Sector', 'museum', 'Museum', '#6f42c1', 30, 1, NOW()),
('glam_sector', 'GLAM Sector', 'gallery', 'Gallery', '#fd7e14', 40, 1, NOW()),
('glam_sector', 'GLAM Sector', 'dam', 'Digital Asset Management', '#17a2b8', 50, 1, NOW());

-- NOTIFICATION STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('notification_status', 'Notification Status', 'pending', 'Pending', '#ffc107', 10, 1, NOW()),
('notification_status', 'Notification Status', 'sent', 'Sent', '#28a745', 20, 1, NOW()),
('notification_status', 'Notification Status', 'failed', 'Failed', '#dc3545', 30, 1, NOW()),
('notification_status', 'Notification Status', 'bounced', 'Bounced', '#fd7e14', 40, 1, NOW()),
('notification_status', 'Notification Status', 'cancelled', 'Cancelled', '#6c757d', 50, 1, NOW());

-- SETTING TYPE
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('setting_type', 'Setting Type', 'string', 'String', '#007bff', 10, 1, NOW()),
('setting_type', 'Setting Type', 'integer', 'Integer', '#28a745', 20, 1, NOW()),
('setting_type', 'Setting Type', 'float', 'Float', '#17a2b8', 30, 1, NOW()),
('setting_type', 'Setting Type', 'boolean', 'Boolean', '#ffc107', 40, 1, NOW()),
('setting_type', 'Setting Type', 'json', 'JSON', '#6f42c1', 50, 1, NOW()),
('setting_type', 'Setting Type', 'array', 'Array', '#fd7e14', 60, 1, NOW());

-- DUPLICATE DETECTION STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('duplicate_status', 'Duplicate Status', 'pending', 'Pending Review', '#ffc107', 10, 1, NOW()),
('duplicate_status', 'Duplicate Status', 'confirmed', 'Confirmed', '#dc3545', 20, 1, NOW()),
('duplicate_status', 'Duplicate Status', 'dismissed', 'Dismissed', '#6c757d', 30, 1, NOW()),
('duplicate_status', 'Duplicate Status', 'merged', 'Merged', '#28a745', 40, 1, NOW());

-- DOI STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('doi_status', 'DOI Status', 'draft', 'Draft', '#6c757d', 10, 1, NOW()),
('doi_status', 'DOI Status', 'registered', 'Registered', '#17a2b8', 20, 1, NOW()),
('doi_status', 'DOI Status', 'findable', 'Findable', '#28a745', 30, 1, NOW()),
('doi_status', 'DOI Status', 'failed', 'Failed', '#dc3545', 40, 1, NOW()),
('doi_status', 'DOI Status', 'deleted', 'Deleted', '#343a40', 50, 1, NOW());

-- WEBHOOK STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('webhook_status', 'Webhook Status', 'pending', 'Pending', '#ffc107', 10, 1, NOW()),
('webhook_status', 'Webhook Status', 'success', 'Success', '#28a745', 20, 1, NOW()),
('webhook_status', 'Webhook Status', 'failed', 'Failed', '#dc3545', 30, 1, NOW()),
('webhook_status', 'Webhook Status', 'retrying', 'Retrying', '#fd7e14', 40, 1, NOW());

-- NER ENTITY TYPES
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('ner_correction_type', 'NER Correction Type', 'none', 'None', '#6c757d', 10, 1, NOW()),
('ner_correction_type', 'NER Correction Type', 'value_edit', 'Value Edited', '#17a2b8', 20, 1, NOW()),
('ner_correction_type', 'NER Correction Type', 'type_change', 'Type Changed', '#fd7e14', 30, 1, NOW()),
('ner_correction_type', 'NER Correction Type', 'both', 'Both Changed', '#6f42c1', 40, 1, NOW()),
('ner_correction_type', 'NER Correction Type', 'rejected', 'Rejected', '#dc3545', 50, 1, NOW()),
('ner_correction_type', 'NER Correction Type', 'approved', 'Approved', '#28a745', 60, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('ner_link_type', 'NER Link Type', 'exact', 'Exact Match', '#28a745', 10, 1, NOW()),
('ner_link_type', 'NER Link Type', 'fuzzy', 'Fuzzy Match', '#ffc107', 20, 1, NOW()),
('ner_link_type', 'NER Link Type', 'manual', 'Manual', '#007bff', 30, 1, NOW());

-- SPELLCHECK/TRANSLATION STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('spellcheck_status', 'Spellcheck Status', 'pending', 'Pending', '#ffc107', 10, 1, NOW()),
('spellcheck_status', 'Spellcheck Status', 'reviewed', 'Reviewed', '#28a745', 20, 1, NOW()),
('spellcheck_status', 'Spellcheck Status', 'ignored', 'Ignored', '#6c757d', 30, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('translation_status', 'Translation Status', 'draft', 'Draft', '#6c757d', 10, 1, NOW()),
('translation_status', 'Translation Status', 'applied', 'Applied', '#28a745', 20, 1, NOW()),
('translation_status', 'Translation Status', 'rejected', 'Rejected', '#dc3545', 30, 1, NOW());

-- ORDER/PAYMENT STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('order_status', 'Order Status', 'pending', 'Pending', '#ffc107', 10, 1, NOW()),
('order_status', 'Order Status', 'paid', 'Paid', '#28a745', 20, 1, NOW()),
('order_status', 'Order Status', 'processing', 'Processing', '#007bff', 30, 1, NOW()),
('order_status', 'Order Status', 'completed', 'Completed', '#20c997', 40, 1, NOW()),
('order_status', 'Order Status', 'cancelled', 'Cancelled', '#6c757d', 50, 1, NOW()),
('order_status', 'Order Status', 'refunded', 'Refunded', '#dc3545', 60, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('payment_status', 'Payment Status', 'pending', 'Pending', '#ffc107', 10, 1, NOW()),
('payment_status', 'Payment Status', 'processing', 'Processing', '#17a2b8', 20, 1, NOW()),
('payment_status', 'Payment Status', 'completed', 'Completed', '#28a745', 30, 1, NOW()),
('payment_status', 'Payment Status', 'failed', 'Failed', '#dc3545', 40, 1, NOW()),
('payment_status', 'Payment Status', 'refunded', 'Refunded', '#fd7e14', 50, 1, NOW()),
('payment_status', 'Payment Status', 'not_invoiced', 'Not Invoiced', '#6c757d', 60, 1, NOW()),
('payment_status', 'Payment Status', 'invoiced', 'Invoiced', '#007bff', 70, 1, NOW()),
('payment_status', 'Payment Status', 'disputed', 'Disputed', '#e83e8c', 80, 1, NOW());

-- WORKFLOW TYPES
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('workflow_task_status', 'Workflow Task Status', 'pending', 'Pending', '#6c757d', 10, 1, NOW()),
('workflow_task_status', 'Workflow Task Status', 'claimed', 'Claimed', '#17a2b8', 20, 1, NOW()),
('workflow_task_status', 'Workflow Task Status', 'in_progress', 'In Progress', '#007bff', 30, 1, NOW()),
('workflow_task_status', 'Workflow Task Status', 'approved', 'Approved', '#28a745', 40, 1, NOW()),
('workflow_task_status', 'Workflow Task Status', 'rejected', 'Rejected', '#dc3545', 50, 1, NOW()),
('workflow_task_status', 'Workflow Task Status', 'returned', 'Returned', '#fd7e14', 60, 1, NOW()),
('workflow_task_status', 'Workflow Task Status', 'escalated', 'Escalated', '#e83e8c', 70, 1, NOW()),
('workflow_task_status', 'Workflow Task Status', 'cancelled', 'Cancelled', '#6c757d', 80, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('workflow_trigger', 'Workflow Trigger', 'create', 'On Create', '#28a745', 10, 1, NOW()),
('workflow_trigger', 'Workflow Trigger', 'update', 'On Update', '#007bff', 20, 1, NOW()),
('workflow_trigger', 'Workflow Trigger', 'submit', 'On Submit', '#17a2b8', 30, 1, NOW()),
('workflow_trigger', 'Workflow Trigger', 'publish', 'On Publish', '#6f42c1', 40, 1, NOW()),
('workflow_trigger', 'Workflow Trigger', 'manual', 'Manual', '#6c757d', 50, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('workflow_scope', 'Workflow Scope', 'global', 'Global', '#dc3545', 10, 1, NOW()),
('workflow_scope', 'Workflow Scope', 'repository', 'Repository', '#007bff', 20, 1, NOW()),
('workflow_scope', 'Workflow Scope', 'collection', 'Collection', '#28a745', 30, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('workflow_applies_to', 'Workflow Applies To', 'information_object', 'Information Object', '#007bff', 10, 1, NOW()),
('workflow_applies_to', 'Workflow Applies To', 'actor', 'Actor', '#28a745', 20, 1, NOW()),
('workflow_applies_to', 'Workflow Applies To', 'accession', 'Accession', '#6f42c1', 30, 1, NOW()),
('workflow_applies_to', 'Workflow Applies To', 'digital_object', 'Digital Object', '#fd7e14', 40, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('workflow_step_type', 'Workflow Step Type', 'review', 'Review', '#007bff', 10, 1, NOW()),
('workflow_step_type', 'Workflow Step Type', 'approve', 'Approve', '#28a745', 20, 1, NOW()),
('workflow_step_type', 'Workflow Step Type', 'edit', 'Edit', '#ffc107', 30, 1, NOW()),
('workflow_step_type', 'Workflow Step Type', 'verify', 'Verify', '#17a2b8', 40, 1, NOW()),
('workflow_step_type', 'Workflow Step Type', 'sign_off', 'Sign Off', '#6f42c1', 50, 1, NOW()),
('workflow_step_type', 'Workflow Step Type', 'custom', 'Custom', '#6c757d', 60, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('workflow_action', 'Workflow Action', 'approve', 'Approve', '#28a745', 10, 1, NOW()),
('workflow_action', 'Workflow Action', 'reject', 'Reject', '#dc3545', 20, 1, NOW()),
('workflow_action', 'Workflow Action', 'approve_reject', 'Approve/Reject', '#ffc107', 30, 1, NOW()),
('workflow_action', 'Workflow Action', 'complete', 'Complete', '#007bff', 40, 1, NOW()),
('workflow_action', 'Workflow Action', 'submit', 'Submit', '#17a2b8', 50, 1, NOW());

-- FORM FIELD TYPES
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('form_field_type', 'Form Field Type', 'text', 'Text', '#007bff', 10, 1, NOW()),
('form_field_type', 'Form Field Type', 'textarea', 'Textarea', '#28a745', 20, 1, NOW()),
('form_field_type', 'Form Field Type', 'richtext', 'Rich Text', '#6f42c1', 30, 1, NOW()),
('form_field_type', 'Form Field Type', 'date', 'Date', '#fd7e14', 40, 1, NOW()),
('form_field_type', 'Form Field Type', 'daterange', 'Date Range', '#ffc107', 50, 1, NOW()),
('form_field_type', 'Form Field Type', 'select', 'Select', '#17a2b8', 60, 1, NOW()),
('form_field_type', 'Form Field Type', 'multiselect', 'Multi-select', '#20c997', 70, 1, NOW()),
('form_field_type', 'Form Field Type', 'autocomplete', 'Autocomplete', '#e83e8c', 80, 1, NOW()),
('form_field_type', 'Form Field Type', 'checkbox', 'Checkbox', '#343a40', 90, 1, NOW()),
('form_field_type', 'Form Field Type', 'radio', 'Radio', '#6c757d', 100, 1, NOW()),
('form_field_type', 'Form Field Type', 'file', 'File Upload', '#dc3545', 110, 1, NOW()),
('form_field_type', 'Form Field Type', 'hidden', 'Hidden', '#868e96', 120, 1, NOW()),
('form_field_type', 'Form Field Type', 'heading', 'Heading', '#495057', 130, 1, NOW()),
('form_field_type', 'Form Field Type', 'divider', 'Divider', '#adb5bd', 140, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('form_field_width', 'Form Field Width', 'full', 'Full Width', '#007bff', 10, 1, NOW()),
('form_field_width', 'Form Field Width', 'half', 'Half Width', '#28a745', 20, 1, NOW()),
('form_field_width', 'Form Field Width', 'third', 'One Third', '#ffc107', 30, 1, NOW()),
('form_field_width', 'Form Field Width', 'quarter', 'One Quarter', '#fd7e14', 40, 1, NOW());

-- LOAN OBJECT STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('loan_object_status', 'Loan Object Status', 'pending', 'Pending', '#6c757d', 10, 1, NOW()),
('loan_object_status', 'Loan Object Status', 'approved', 'Approved', '#28a745', 20, 1, NOW()),
('loan_object_status', 'Loan Object Status', 'prepared', 'Prepared', '#17a2b8', 30, 1, NOW()),
('loan_object_status', 'Loan Object Status', 'dispatched', 'Dispatched', '#007bff', 40, 1, NOW()),
('loan_object_status', 'Loan Object Status', 'received', 'Received', '#20c997', 50, 1, NOW()),
('loan_object_status', 'Loan Object Status', 'on_display', 'On Display', '#6f42c1', 60, 1, NOW()),
('loan_object_status', 'Loan Object Status', 'packed', 'Packed', '#fd7e14', 70, 1, NOW()),
('loan_object_status', 'Loan Object Status', 'returned', 'Returned', '#343a40', 80, 1, NOW());

-- LOAN INSURANCE TYPE
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('loan_insurance_type', 'Loan Insurance Type', 'borrower', 'Borrower', '#007bff', 10, 1, NOW()),
('loan_insurance_type', 'Loan Insurance Type', 'lender', 'Lender', '#28a745', 20, 1, NOW()),
('loan_insurance_type', 'Loan Insurance Type', 'shared', 'Shared', '#6f42c1', 30, 1, NOW()),
('loan_insurance_type', 'Loan Insurance Type', 'government', 'Government', '#fd7e14', 40, 1, NOW()),
('loan_insurance_type', 'Loan Insurance Type', 'self', 'Self-Insured', '#ffc107', 50, 1, NOW()),
('loan_insurance_type', 'Loan Insurance Type', 'none', 'None', '#dc3545', 60, 1, NOW());

-- VENDOR TRANSACTION STATUS
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, code, label, color, sort_order, is_active, created_at) VALUES
('vendor_transaction_status', 'Vendor Transaction Status', 'pending_approval', 'Pending Approval', '#ffc107', 10, 1, NOW()),
('vendor_transaction_status', 'Vendor Transaction Status', 'approved', 'Approved', '#28a745', 20, 1, NOW()),
('vendor_transaction_status', 'Vendor Transaction Status', 'dispatched', 'Dispatched', '#007bff', 30, 1, NOW()),
('vendor_transaction_status', 'Vendor Transaction Status', 'received_by_vendor', 'Received by Vendor', '#17a2b8', 40, 1, NOW()),
('vendor_transaction_status', 'Vendor Transaction Status', 'in_progress', 'In Progress', '#6f42c1', 50, 1, NOW()),
('vendor_transaction_status', 'Vendor Transaction Status', 'completed', 'Completed', '#20c997', 60, 1, NOW()),
('vendor_transaction_status', 'Vendor Transaction Status', 'ready_for_collection', 'Ready for Collection', '#fd7e14', 70, 1, NOW()),
('vendor_transaction_status', 'Vendor Transaction Status', 'returned', 'Returned', '#343a40', 80, 1, NOW()),
('vendor_transaction_status', 'Vendor Transaction Status', 'cancelled', 'Cancelled', '#dc3545', 90, 1, NOW());

-- ============================================================
-- NOTE: Additional ENUM migrations are in separate files:
-- - enum_to_dropdown_migration_phase2.sql (Access, DAM, Display, Donor, Exhibition, Gallery types)
-- - enum_to_dropdown_migration_phase2b.sql (Heritage, ICIP, IIIF types)
-- - enum_to_dropdown_migration_phase2c.sql (NAZ, NMMZ, OAIS, Preservation types)
-- - enum_to_dropdown_migration_phase2d.sql (Privacy, Provenance, RIC, Rights types)
-- - enum_to_dropdown_migration_phase2e.sql (Research plugin types)
-- Run these separately for full ENUM coverage.
-- ============================================================

-- ============================================================
-- TTS (Text-to-Speech) Settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_tts_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sector` VARCHAR(50) NOT NULL DEFAULT 'all' COMMENT 'all, archive, library, museum, gallery, dam',
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_sector_key` (`sector`, `setting_key`),
    INDEX `idx_sector` (`sector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default TTS settings
INSERT IGNORE INTO `ahg_tts_settings` (`sector`, `setting_key`, `setting_value`) VALUES
('all', 'enabled', '1'),
('all', 'default_rate', '1.0'),
('all', 'read_labels', '1'),
('all', 'keyboard_shortcuts', '1'),
('archive', 'fields_to_read', '["title","scopeAndContent","arrangement"]'),
('library', 'fields_to_read', '["title","scopeAndContent","abstract"]'),
('museum', 'fields_to_read', '["title","scopeAndContent","physicalDescription"]'),
('gallery', 'fields_to_read', '["title","scopeAndContent","medium"]'),
('dam', 'fields_to_read', '["title","scopeAndContent","technicalNotes"]');

-- ============================================================================
-- EMAIL SETTINGS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `email_setting` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `setting_type` VARCHAR(20) DEFAULT 'text',
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`),
    KEY `idx_key` (`setting_key`),
    KEY `idx_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- SMTP settings (values configured per instance via Admin > Settings > Email)
INSERT IGNORE INTO `email_setting` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`) VALUES
('smtp_enabled', '0', 'boolean', 'smtp', 'Enable email sending'),
('smtp_host', '', 'text', 'smtp', 'SMTP server hostname'),
('smtp_port', '587', 'number', 'smtp', 'SMTP server port'),
('smtp_encryption', 'tls', 'text', 'smtp', 'Encryption type (tls, ssl, or empty)'),
('smtp_username', '', 'text', 'smtp', 'SMTP username'),
('smtp_password', '', 'password', 'smtp', 'SMTP password'),
('smtp_from_email', '', 'email', 'smtp', 'From email address'),
('smtp_from_name', 'AtoM Archive', 'text', 'smtp', 'From name'),
('notify_new_researcher', '', 'email', 'notifications', 'Email to notify of new researcher registrations'),
('notify_new_booking', '', 'email', 'notifications', 'Email to notify of new booking requests'),
('notify_errors', '', 'email', 'notifications', 'Admin email address to receive system error alerts'),
('email_researcher_pending_subject', 'Registration Received - Pending Approval', 'text', 'templates', 'Subject for pending registration email'),
('email_researcher_pending_body', 'Dear {name},\n\nThank you for registering as a researcher. Your application is being reviewed.\n\nYou will receive an email once your account has been approved.\n\nRegards,\nThe Archive Team', 'textarea', 'templates', 'Body for pending registration email'),
('email_researcher_approved_subject', 'Registration Approved', 'text', 'templates', 'Subject for approved registration email'),
('email_researcher_approved_body', 'Dear {name},\n\nYour researcher registration has been approved!\n\nYou can now log in and book reading room visits at:\n{login_url}\n\nRegards,\nThe Archive Team', 'textarea', 'templates', 'Body for approved registration email'),
('email_researcher_rejected_subject', 'Registration Not Approved', 'text', 'templates', 'Subject for rejected registration email'),
('email_researcher_rejected_body', 'Dear {name},\n\nUnfortunately, your researcher registration was not approved.\n\nReason: {reason}\n\nIf you have questions, please contact us.\n\nRegards,\nThe Archive Team', 'textarea', 'templates', 'Body for rejected registration email'),
('email_password_reset_subject', 'Password Reset Request', 'text', 'templates', 'Subject for password reset email'),
('email_password_reset_body', 'Dear {name},\n\nA password reset was requested for your account.\n\nClick the link below to reset your password:\n{reset_url}\n\nThis link expires in 2 hours.\n\nIf you did not request this, please ignore this email.\n\nRegards,\nThe Archive Team', 'textarea', 'templates', 'Body for password reset email'),
('email_booking_confirmed_subject', 'Booking Confirmed', 'text', 'templates', 'Subject for booking confirmation email'),
('email_booking_confirmed_body', 'Dear {name},\n\nYour reading room booking has been confirmed:\n\nDate: {date}\nTime: {time}\nRoom: {room}\n\nPlease bring valid identification.\n\nRegards,\nThe Archive Team', 'textarea', 'templates', 'Body for booking confirmation email'),
('email_admin_new_researcher_subject', 'New Researcher Registration', 'text', 'templates', 'Subject for admin notification of new researcher'),
('email_admin_new_researcher_body', 'A new researcher has registered:\n\nName: {name}\nEmail: {email}\nInstitution: {institution}\n\nReview at: {review_url}', 'textarea', 'templates', 'Body for admin notification of new researcher'),
('email_error_alert_subject', 'System Error Alert - {hostname}', 'text', 'templates', 'Subject line for error notification emails'),
('email_error_alert_body', 'System Error Alert\n==================\n\nTime: {timestamp}\nHost: {hostname}\nURL: {url}\n\nError: {message}\nFile: {file}\nLine: {line}\n\nStack Trace:\n{trace}', 'textarea', 'templates', 'Body template for error notification emails'),
('email_booking_cancelled_subject', 'Booking Cancelled', 'text', 'templates', 'Subject for booking cancellation email'),
('email_booking_cancelled_body', 'Dear {name},\n\nYour reading room booking for {date} ({time}) in {room} has been cancelled.\n\nIf you have questions, please contact us.\n\nBest regards,\nThe Archive Team', 'textarea', 'templates', 'Body for booking cancellation email. Placeholders: {name}, {date}, {time}, {room}'),
('email_search_alert_subject', 'New results for your saved search: {search_name}', 'text', 'templates', 'Subject for search alert email'),
('email_search_alert_body', 'Dear {name},\n\nYour saved search \"{search_name}\" has {result_count} new result(s).\n\nSearch query: {search_query}\n\nView your saved searches at: {saved_searches_url}\n\nYou can manage your alert settings from your researcher workspace.\n\nBest regards,\nThe Archive Team', 'textarea', 'templates', 'Body for search alert email. Placeholders: {name}, {search_name}, {result_count}, {search_query}, {saved_searches_url}'),
('email_collaborator_invite_subject', 'You have been invited to collaborate on a research project', 'text', 'templates', 'Subject for collaborator invitation email'),
('email_collaborator_invite_body', 'Dear {name},\n\n{inviter_name} has invited you to collaborate on the research project \"{project_title}\" as a {role}.\n\nView the project and accept the invitation:\n{project_url}\n\nBest regards,\nThe Archive Team', 'textarea', 'templates', 'Body for collaborator invitation email. Placeholders: {name}, {inviter_name}, {project_title}, {role}, {project_url}'),
('email_collaborator_external_subject', 'You have been invited to collaborate on a research project', 'text', 'templates', 'Subject for external collaborator invitation email'),
('email_collaborator_external_body', 'Dear Colleague,\n\n{inviter_name} has invited you to collaborate on the research project \"{project_title}\" as a {role}.\n\nTo accept this invitation, you first need to register as a researcher:\n{register_url}\n\nAfter registration and approval, you will be able to join the project.\n\nBest regards,\nThe Archive Team', 'textarea', 'templates', 'Body for external collaborator invitation email. Placeholders: {inviter_name}, {project_title}, {role}, {register_url}'),
('email_peer_review_request_subject', 'Peer Review Request: {report_title}', 'text', 'templates', 'Subject for peer review request email'),
('email_peer_review_request_body', 'Dear {name},\n\n{requester_name} has requested your peer review of the report \"{report_title}\".\n\nPlease review the report at:\n{review_url}\n\nBest regards,\nThe Archive Team', 'textarea', 'templates', 'Body for peer review request email. Placeholders: {name}, {requester_name}, {report_title}, {review_url}');

SET FOREIGN_KEY_CHECKS = 1;
