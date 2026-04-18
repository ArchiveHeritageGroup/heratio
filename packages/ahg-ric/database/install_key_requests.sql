-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- SPDX-License-Identifier: AGPL-3.0-or-later
--
-- Self-service API key requests. Populated by public POST /keys/request;
-- reviewed by an admin via `php artisan openric:issue-key {id}` which
-- issues the key into ahg_api_key and emails it to the requester.

CREATE TABLE IF NOT EXISTS openric_key_request (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    email             VARCHAR(255) NOT NULL,
    organization      VARCHAR(255) NULL,
    intended_use      TEXT         NOT NULL,
    requested_scopes  VARCHAR(255) NOT NULL DEFAULT 'read,write',
    status            ENUM('pending','approved','denied','revoked') NOT NULL DEFAULT 'pending',
    -- When approved, this points to the issued key row; null otherwise.
    api_key_id        INT NULL,
    -- Bookkeeping
    requester_ip      VARCHAR(64)  NULL,
    user_agent        VARCHAR(255) NULL,
    review_notes      TEXT         NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at       DATETIME     NULL,
    INDEX idx_status (status),
    INDEX idx_email  (email),
    CONSTRAINT fk_okr_apikey FOREIGN KEY (api_key_id) REFERENCES ahg_api_key(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
