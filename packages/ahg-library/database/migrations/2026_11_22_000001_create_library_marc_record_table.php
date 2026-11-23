<?php

/**
 * Source MARC record history for library items (heratio#1476).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_item')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS library_marc_record (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              library_item_id BIGINT UNSIGNED NOT NULL,
              version INT UNSIGNED NOT NULL,
              event VARCHAR(32) NOT NULL COMMENT 'Dropdown: library_marc_record_event',
              source_format VARCHAR(32) NOT NULL COMMENT 'Dropdown: library_marc_source_format',
              is_verbatim TINYINT(1) NOT NULL DEFAULT 0,
              marcxml LONGTEXT NULL,
              source_binary LONGBLOB NULL,
              content_sha256 CHAR(64) NOT NULL,
              byte_length INT UNSIGNED NOT NULL,
              created_by BIGINT UNSIGNED NULL,
              created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_marc_record_version (library_item_id, version),
              KEY idx_marc_record_event (library_item_id, event),
              CONSTRAINT fk_marc_record_item FOREIGN KEY (library_item_id) REFERENCES library_item(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        if (Schema::hasTable('ahg_dropdown')) {
            DB::unprepared(file_get_contents(__DIR__ . '/../seed_dropdowns.sql'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_marc_record');
    }
};
