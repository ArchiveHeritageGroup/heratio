<?php

/**
 * ui_string_review - native-speaker review state per (locale, UI string)
 * (heratio#1445).
 *
 * One row per string a reviewer has seen, or per held machine translation
 * staged for review. A row with status 'accepted' or 'corrected' is reviewed:
 * the machine fill commands skip it, so a later run never overwrites a
 * reviewer's choice. A 'pending' row holds a machine translation that has not
 * reached the lang file or ui_string yet (the zu/xh/ts hold rule): it only
 * goes live once a reviewer accepts or corrects it.
 *
 * Status values are listed in ahg_dropdown (taxonomy ui_string_review_status).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ui_string_review')) {
            // Raw SQL: the key needs utf8mb4_bin (case-distinct keys, as in
            // ui_string) and a 255-character prefix on the unique index.
            DB::statement(<<<'SQL'
CREATE TABLE `ui_string_review` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `culture` VARCHAR(16) NOT NULL,
  `key` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `key_id` CHAR(10) NOT NULL,
  `machine_text` LONGTEXT NULL,
  `reviewed_text` LONGTEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `held` TINYINT(1) NOT NULL DEFAULT 0,
  `source` VARCHAR(255) NULL,
  `reviewer_name` VARCHAR(255) NULL,
  `reviewed_by_user_id` INT NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ui_string_review_culture_key` (`culture`, `key`(255)),
  KEY `idx_culture_status` (`culture`, `status`),
  KEY `idx_key_id` (`key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        if (Schema::hasTable('ahg_dropdown')) {
            $rows = [
                ['pending', 'Awaiting review', 10, 1],
                ['accepted', 'Accepted as machine translated', 20, 0],
                ['corrected', 'Corrected by reviewer', 30, 0],
            ];
            foreach ($rows as [$code, $label, $sort, $default]) {
                DB::table('ahg_dropdown')->insertOrIgnore([
                    'taxonomy' => 'ui_string_review_status',
                    'taxonomy_label' => 'UI string review status',
                    'taxonomy_section' => 'core',
                    'code' => $code,
                    'label' => $label,
                    'sort_order' => $sort,
                    'is_default' => $default,
                    'is_active' => 1,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_string_review');
        if (Schema::hasTable('ahg_dropdown')) {
            DB::table('ahg_dropdown')->where('taxonomy', 'ui_string_review_status')->delete();
        }
    }
};
