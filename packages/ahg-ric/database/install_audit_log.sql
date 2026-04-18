-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- SPDX-License-Identifier: AGPL-3.0-or-later
--
-- Audit log for write operations on RiC entities. Populated by the
-- LinkedDataApiController after every successful create / update / delete.
-- Read via `GET /api/ric/v1/{type}/{id}/revisions`.

CREATE TABLE IF NOT EXISTS openric_audit_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action          ENUM('create','update','delete') NOT NULL,
    entity_type     VARCHAR(32)  NOT NULL,
    entity_id       INT          NOT NULL,
    api_key_id      INT          NULL,
    user_id         INT          NULL,
    requester_ip    VARCHAR(64)  NULL,
    user_agent      VARCHAR(255) NULL,
    payload_json    JSON         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id, created_at),
    INDEX idx_time   (created_at),
    INDEX idx_actor  (api_key_id, user_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
