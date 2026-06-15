<?php

/**
 * heratio#1281 (PSIS parity, HIGH) - Library interoperability & acquisitions backbone.
 *
 * Ports the 11 PSIS ahgLibraryPlugin tables heratio lacked + the library_item MARC/FRBR
 * columns. Schema is copied faithfully from the archive plugin DDL, with two project-rule
 * adaptations: MySQL ENUM columns become VARCHAR (Dropdown Manager rule), and every step is
 * idempotent (CREATE TABLE IF NOT EXISTS / Schema::hasColumn) so re-runs and fresh installs
 * are safe. Foreign-key targets (library_item / library_patron, both BIGINT UNSIGNED) verified
 * present before keys are declared.
 *
 * These create the PSIS-named interop tables (Z39.50/SRU server, SUSHI/COUNTER, KBART vendor
 * feeds, bindery, ILL history, fund accounting, FRBR override, usage events). heratio's own
 * earlier library_counter_log / library_sushi_audit_log remain; the ported plugin code (later
 * batches of #1281) targets these standard-named tables.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Z39.50 / SRU server mode (heratio only had library_z3950_target = client side) ---
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_z3950_server_config (
              id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              option_key   VARCHAR(64)   NOT NULL UNIQUE COMMENT 'host, port, timeout, max_result_set, enabled, default_element_set',
              option_value TEXT          NULL,
              category     VARCHAR(32)   NOT NULL DEFAULT 'server' COMMENT 'server | bib1 | limits',
              created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
              updated_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX idx_z3950srv_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_z3950_server_request (
              id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              client_addr    VARCHAR(45)   NOT NULL DEFAULT '' COMMENT 'IPv4/IPv6 address of client',
              apdu_type      VARCHAR(32)   NOT NULL DEFAULT '' COMMENT 'init_request, search_request, present_request, close, delete_result_set, unknown, error',
              bytes_received INT UNSIGNED  NOT NULL DEFAULT 0,
              result_count   INT UNSIGNED  NULL COMMENT 'For search APDUs: hit count',
              elapsed_ms     INT UNSIGNED  NULL COMMENT 'APDU processing time in milliseconds',
              error_detail   TEXT          NULL,
              created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_z3950req_client (client_addr),
              INDEX idx_z3950req_type (apdu_type),
              INDEX idx_z3950req_time (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_sru_log (
              id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              query           TEXT            NULL,
              cql_query       TEXT            NULL  COMMENT 'The parsed/converted CQL query',
              result_count    INT UNSIGNED    DEFAULT 0,
              duration_ms     DECIMAL(10,1)   NULL,
              error           TEXT            NULL,
              remote_addr     VARCHAR(45)     NULL,
              api_key_hint    VARCHAR(64)     NULL  COMMENT 'SHA-256 prefix of API key used (not the key itself)',
              created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_created_at (created_at),
              INDEX idx_result_count (result_count)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // --- SUSHI / COUNTER metering (event_type ENUM -> VARCHAR per dropdown rule) ---
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_usage_event (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                library_item_id BIGINT UNSIGNED NULL,
                patron_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(20) NOT NULL COMMENT 'opac_view, link_click, ir_access, search, export',
                metadata JSON DEFAULT NULL COMMENT 'e.g. {"search_terms":"...","result_position":1,"format":"pdf"}',
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                session_id VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_item (library_item_id),
                INDEX idx_patron (patron_id),
                INDEX idx_created (created_at),
                INDEX idx_type_date (event_type, created_at),
                CONSTRAINT fk_usage_item FOREIGN KEY (library_item_id) REFERENCES library_item(id)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_usage_patron FOREIGN KEY (patron_id) REFERENCES library_patron(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_counter_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_sushi_access_log (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                requestor_id    VARCHAR(255) NULL COMMENT 'SUSHI X-Requestor-Id header',
                customer_id     VARCHAR(255) NULL COMMENT 'SUSHI X-Customer-Id header',
                report_type     VARCHAR(20)  NOT NULL COMMENT 'TR_J1, DR, PR, IR, TR_J3',
                period_begin    DATE         NULL,
                period_end      DATE         NULL,
                status_code     SMALLINT    NULL COMMENT 'HTTP status code returned',
                records_returned INT UNSIGNED DEFAULT 0 COMMENT 'Number of usage records in response',
                ip_address      VARCHAR(45) NULL,
                user_agent      VARCHAR(500) NULL,
                created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sushi_log_report   (report_type),
                INDEX idx_sushi_log_customer (customer_id),
                INDEX idx_sushi_log_created  (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // --- KBART vendor feed registry ---
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_kbart_vendor (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL COMMENT 'Human-readable vendor name',
                feed_url VARCHAR(1000) NOT NULL COMMENT 'URL to the KBART TSV feed',
                active TINYINT(1) NOT NULL DEFAULT 1,
                last_fetch_at DATETIME NULL,
                last_row_count INT UNSIGNED NULL,
                last_error TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_feed_url (feed_url(255)),   -- prefix length: feed_url is VARCHAR(1000); a full utf8mb4 index (4000 bytes) exceeds InnoDB's 3072-byte key limit
                INDEX idx_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // --- Serials bindery batch management ---
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_bindery_batch (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              batch_number  VARCHAR(40) NOT NULL,
              vendor_id     BIGINT UNSIGNED DEFAULT NULL COMMENT 'bindery vendor (ahg_vendors.id)',
              status        VARCHAR(20) NOT NULL DEFAULT 'sent' COMMENT 'sent, returned, cancelled',
              sent_date     DATE DEFAULT NULL,
              returned_date DATE DEFAULT NULL,
              item_count    INT UNSIGNED NOT NULL DEFAULT 0,
              notes         TEXT DEFAULT NULL,
              created_by    INT DEFAULT NULL,
              created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uk_bindery_batch_number (batch_number),
              KEY idx_bindery_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // --- ILL status transition history ---
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_ill_status_history (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              ill_request_id BIGINT UNSIGNED NOT NULL,
              from_status    VARCHAR(30) DEFAULT NULL,
              to_status      VARCHAR(30) NOT NULL,
              notes          TEXT DEFAULT NULL,
              created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_illh_request (ill_request_id),
              KEY idx_illh_to (to_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // --- Acquisitions fund splitting per order line ---
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_order_line_fund (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              order_line_id BIGINT UNSIGNED NOT NULL,
              fund_code     VARCHAR(50) NOT NULL,
              amount        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_olf_line (order_line_id),
              KEY idx_olf_fund (fund_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // --- Manual FRBR work-clustering override ---
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_item_frbr_override (
                id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                library_item_id  BIGINT UNSIGNED NOT NULL,
                target_work_key  VARCHAR(64)  NULL COMMENT 'force_group: merge this item INTO the target work key',
                forced_split     TINYINT(1)   DEFAULT 0 COMMENT 'force_split: do NOT cluster this item with any other',
                reason           VARCHAR(500) NULL,
                created_by       BIGINT UNSIGNED NULL,
                created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (library_item_id) REFERENCES library_item(id) ON DELETE CASCADE,
                INDEX idx_target_work_key (target_work_key),
                INDEX idx_library_item_id (library_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // --- library_item MARC control fields + FRBR clustering keys (idempotent, no
        //     positional ->after(): the live PSIS-shaped table may lack the anchor column). ---
        if (Schema::hasTable('library_item')) {
            Schema::table('library_item', function (Blueprint $table) {
                if (! Schema::hasColumn('library_item', 'marc_leader')) {
                    $table->string('marc_leader', 24)->nullable()->comment('Preserved MARC leader');
                }
                if (! Schema::hasColumn('library_item', 'marc_005')) {
                    $table->string('marc_005', 16)->nullable()->comment('Preserved MARC 005 (last transaction date/time)');
                }
                if (! Schema::hasColumn('library_item', 'marc_008')) {
                    $table->string('marc_008', 40)->nullable()->comment('Preserved MARC 008 (fixed-length data elements)');
                }
                if (! Schema::hasColumn('library_item', 'frbr_work_key')) {
                    $table->string('frbr_work_key', 64)->nullable()->comment('SHA-256 work identifier, first 20 chars');
                }
                if (! Schema::hasColumn('library_item', 'frbr_override_type')) {
                    $table->string('frbr_override_type', 20)->default('none')->comment('none, force_group, force_split');
                }
                if (! Schema::hasColumn('library_item', 'description')) {
                    $table->text('description')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'library_item_frbr_override', 'library_order_line_fund', 'library_ill_status_history',
            'library_bindery_batch', 'library_kbart_vendor', 'library_sushi_access_log',
            'library_counter_settings', 'library_usage_event', 'library_sru_log',
            'library_z3950_server_request', 'library_z3950_server_config',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        if (Schema::hasTable('library_item')) {
            Schema::table('library_item', function (Blueprint $table) {
                foreach (['marc_leader', 'marc_005', 'marc_008', 'frbr_work_key', 'frbr_override_type', 'description'] as $c) {
                    if (Schema::hasColumn('library_item', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
