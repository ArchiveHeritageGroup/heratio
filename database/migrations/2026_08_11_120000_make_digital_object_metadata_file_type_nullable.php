<?php

/**
 * digital_object_metadata.file_type must be NULLABLE.
 *
 * The core schema has long declared this column nullable, and says why:
 * "NULL when metadata is written without a file classification, e.g. EXIF-only
 * updates". Databases created from the current core schema (e.g. sasa) have it
 * right. Older instances predate the correction and still carry NOT NULL with
 * no default, so every EXIF-only write fails with
 *
 *     SQLSTATE[HY000]: General error: 1364 Field 'file_type' doesn't have a
 *     default value
 *
 * On the affected instances digital_object_metadata was empty - the feature has
 * never once succeeded there. Surfaced by EmbeddedMetadataApiTest (8 errors)
 * once the test suite was made runnable again.
 *
 * Widening NOT NULL -> NULL cannot invalidate existing rows and cannot break
 * callers that always supply a value, so this is safe to run everywhere. The
 * guard makes it a no-op where the column is already correct.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMMENT = 'image, pdf, office, video, audio, other (NULL when metadata is written without a file classification, e.g. EXIF-only updates)';

    public function up(): void
    {
        if (! Schema::hasTable('digital_object_metadata')
            || ! Schema::hasColumn('digital_object_metadata', 'file_type')) {
            return;
        }

        if ($this->isNullable()) {
            return; // already correct (fresh installs from the core schema)
        }

        DB::statement(
            'ALTER TABLE `digital_object_metadata` MODIFY `file_type` '
            ."varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '".self::COMMENT."'"
        );
    }

    public function down(): void
    {
        // Deliberately not reverted. Going back to NOT NULL would fail on any
        // row written since (exactly the rows this migration exists to allow),
        // and the nullable form is what the core schema declares.
    }

    private function isNullable(): bool
    {
        $row = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS'
            .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['digital_object_metadata', 'file_type']
        );

        return $row !== null && strtoupper((string) $row->IS_NULLABLE) === 'YES';
    }
};
