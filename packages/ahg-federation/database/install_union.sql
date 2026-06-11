-- ============================================================================
-- ahg-federation - Federated GLAM network (opt-in union catalogue)
-- install schema - #1203 first slice
-- ============================================================================
-- Three tables, all idempotent (CREATE TABLE IF NOT EXISTS):
--
--   federation_member        participating institutions / peers. The local
--                            institution is the self-member (is_self=1).
--   federation_share_setting single-row (id=1) opt-in sharing config. Default
--                            OFF (share_enabled=0). Gates what this institution
--                            publishes into the union index.
--   federation_union_record  the union index itself - one row per shared
--                            discovery record, keyed by (member_id, record_ref)
--                            so the publish pass can upsert idempotently.
--
-- International / jurisdiction-neutral. Read-only over catalogue data; opt-in
-- defaults OFF. No INSERT/UPDATE/ALTER against live data here - the share
-- setting row is created lazily by the application with share_enabled=0.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Participating institutions / peers in the federated network.
CREATE TABLE IF NOT EXISTS federation_member (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    base_url VARCHAR(1024) NULL
        COMMENT 'public catalogue / union endpoint base for this member',
    contact VARCHAR(255) NULL
        COMMENT 'contact email or person for the member institution',
    share_scope TEXT NULL
        COMMENT 'free-text notes on what this member shares / agreement terms',
    is_self TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = this local institution (the self-member)',
    is_enabled TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = included in union searches; opt-in default OFF',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_fm_enabled (is_enabled),
    INDEX idx_fm_self (is_self)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-row opt-in sharing config for this institution (id=1).
-- Default OFF. The publish pass refuses to share anything unless
-- share_enabled=1.
CREATE TABLE IF NOT EXISTS federation_share_setting (
    id INT PRIMARY KEY AUTO_INCREMENT,
    share_enabled TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'master opt-in switch; default OFF',
    published_only TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'only publish records with publication status Published',
    min_level_id INT NULL
        COMMENT 'optional minimum level-of-description term id gate; NULL = no level gate',
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The union index. Portable, queryable without a second ES cluster.
-- Upsert key is the unique (member_id, record_ref) pair.
CREATE TABLE IF NOT EXISTS federation_union_record (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL
        COMMENT 'federation_member.id that contributed this record',
    record_ref VARCHAR(255) NOT NULL
        COMMENT 'source-stable reference (local IO id or slug) within the member',
    title VARCHAR(1024) NULL,
    level VARCHAR(255) NULL
        COMMENT 'level of description label',
    dates VARCHAR(255) NULL
        COMMENT 'display date string',
    repository VARCHAR(512) NULL
        COMMENT 'holding repository / institution name',
    url VARCHAR(1024) NULL
        COMMENT 'permalink to the source record at the member',
    indexed_at DATETIME NULL,
    UNIQUE KEY uq_fur_member_ref (member_id, record_ref),
    INDEX idx_fur_member (member_id),
    INDEX idx_fur_title (title(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
