-- ============================================================================
-- ahg-federation - Federated GLAM network: public "Join the network" requests
-- install schema - #1203 join-request slice
-- ============================================================================
-- One table, idempotent (CREATE TABLE IF NOT EXISTS):
--
--   federation_join_request  a moderated queue of institutions that have asked
--                            (via the public /federation/join form) to join the
--                            federated network. Lands as status='pending'. An
--                            admin reviews and marks it reviewing / approved /
--                            declined. Approval is a label only - it does NOT
--                            auto-create a federation_member; actual member
--                            creation stays the admin's deliberate action via
--                            the existing member registry.
--
-- International / jurisdiction-neutral. VARCHAR not ENUM for status (the
-- Dropdown-Manager rule: never use MySQL ENUM). No foreign keys, no ALTER
-- against existing tables. The only writes are public INSERTs into THIS table
-- and admin moderation UPDATEs of THIS table.
-- ============================================================================

CREATE TABLE IF NOT EXISTS federation_join_request (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_name VARCHAR(255) NOT NULL
        COMMENT 'name of the institution requesting to join',
    contact_name VARCHAR(255) NULL
        COMMENT 'person submitting the request',
    contact_email VARCHAR(255) NULL
        COMMENT 'contact email for follow-up',
    base_url VARCHAR(1024) NULL
        COMMENT 'public catalogue / site URL for the institution',
    what_they_share TEXT NULL
        COMMENT 'free-text description of the collections they would contribute',
    notes TEXT NULL
        COMMENT 'optional extra notes from the requester or internal review notes',
    status VARCHAR(32) NOT NULL DEFAULT 'pending'
        COMMENT 'pending | reviewing | approved | declined (VARCHAR, never ENUM)',
    reviewed_by VARCHAR(255) NULL
        COMMENT 'name/email of the admin who last actioned the request',
    reviewed_at DATETIME NULL
        COMMENT 'when the request was last actioned',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_fjr_status (status),
    INDEX idx_fjr_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
