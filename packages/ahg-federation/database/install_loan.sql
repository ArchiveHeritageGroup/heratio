-- ============================================================================
-- ahg-federation - Federated GLAM network (inter-institution loan requests)
-- install schema - #1203 loan slice
-- ============================================================================
-- One table, idempotent (CREATE TABLE IF NOT EXISTS):
--
--   federation_loan_request   one row per inter-institution loan request. A
--                             requesting member asks a holding member to loan
--                             a specific item (information_object id / slug,
--                             held as a soft reference - NO foreign key). The
--                             request moves through a small status workflow
--                             (requested -> approved | declined -> in_transit
--                             -> returned, with cancelled as an early exit).
--
-- Both requesting_member_id and holding_member_id are soft references into
-- federation_member (read-only reuse of the union-catalogue member registry).
-- No FK constraints are declared so a member row can be removed without an
-- ALTER-time dependency, and so this slice never has to touch the existing
-- federation_member definition.
--
-- status is VARCHAR (not ENUM), per the Dropdown Manager / no-ENUM rule.
-- International / jurisdiction-neutral. The only writes against live data are
-- INSERT / UPDATE on THIS new table; no INSERT/UPDATE/ALTER elsewhere.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS federation_loan_request (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    requesting_member_id INT NOT NULL
        COMMENT 'federation_member.id asking to borrow (soft ref, no FK)',
    holding_member_id INT NOT NULL
        COMMENT 'federation_member.id that holds the item (soft ref, no FK)',
    item_ref VARCHAR(255) NULL
        COMMENT 'requested item: local information_object id or slug at the holder (soft ref, no FK)',
    item_title VARCHAR(1024) NULL
        COMMENT 'free-text label for the requested item, captured at request time',
    purpose VARCHAR(2048) NULL
        COMMENT 'why the loan is requested (exhibition, research, conservation, etc.)',
    status VARCHAR(32) NOT NULL DEFAULT 'requested'
        COMMENT 'requested|approved|declined|in_transit|returned|cancelled - VARCHAR not ENUM',
    needed_from DATE NULL
        COMMENT 'start of the requested loan window',
    needed_to DATE NULL
        COMMENT 'end of the requested loan window',
    notes TEXT NULL
        COMMENT 'free-text notes / correspondence summary',
    decided_by VARCHAR(255) NULL
        COMMENT 'name / id of the user who last transitioned the status',
    decided_at DATETIME NULL
        COMMENT 'when the status was last transitioned',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_flr_status (status),
    INDEX idx_flr_requesting (requesting_member_id),
    INDEX idx_flr_holding (holding_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
