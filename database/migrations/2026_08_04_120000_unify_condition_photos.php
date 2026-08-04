<?php

/**
 * #1452/#1453 - unify condition photos into one rich store (spectrum_condition_photo).
 *
 * Decision (Johan): one rich photo store, keep both condition systems. Add a
 * polymorphic source link (source_type report|check, source_id) so BOTH the
 * io-manage Condition Report (condition_report) and the Spectrum Condition Check
 * (spectrum_condition_check) hang their photos off the same rich table. The 7
 * thin condition_image rows are migrated in as source_type='report'. Idempotent
 * and non-destructive (condition_image is left in place until the code cutover
 * is confirmed).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spectrum_condition_photo')) {
            return;
        }

        // 1. Polymorphic source columns.
        if (! Schema::hasColumn('spectrum_condition_photo', 'source_type')) {
            DB::statement("ALTER TABLE spectrum_condition_photo ADD COLUMN source_type VARCHAR(16) NOT NULL DEFAULT 'check' AFTER condition_check_id");
        }
        if (! Schema::hasColumn('spectrum_condition_photo', 'source_id')) {
            DB::statement('ALTER TABLE spectrum_condition_photo ADD COLUMN source_id INT NULL AFTER source_type');
            DB::statement('ALTER TABLE spectrum_condition_photo ADD INDEX idx_scp_source (source_type, source_id)');
        }

        // 2. condition_check_id becomes nullable - report-sourced photos have none.
        try {
            DB::statement('ALTER TABLE spectrum_condition_photo MODIFY condition_check_id INT NULL');
        } catch (\Throwable $e) {
            // already nullable
        }

        // 3. Backfill the source link for the existing (check-sourced) rows.
        DB::statement("UPDATE spectrum_condition_photo SET source_type='check', source_id=condition_check_id WHERE (source_id IS NULL OR source_id = 0) AND condition_check_id IS NOT NULL");

        // 4. Migrate the thin condition_image rows in as report-sourced photos.
        if (Schema::hasTable('condition_image')) {
            $map = ['general' => 'detail', 'raking' => 'detail', 'uv' => 'detail'];
            foreach (DB::table('condition_image')->get() as $ci) {
                $already = DB::table('spectrum_condition_photo')
                    ->where('source_type', 'report')
                    ->where('source_id', $ci->condition_report_id)
                    ->where('file_path', $ci->file_path)
                    ->exists();
                if ($already) {
                    continue;
                }
                DB::table('spectrum_condition_photo')->insert([
                    'condition_check_id' => null,
                    'source_type'        => 'report',
                    'source_id'          => (int) $ci->condition_report_id,
                    'digital_object_id'  => $ci->digital_object_id,
                    'photo_type'         => $map[$ci->image_type] ?? ($ci->image_type ?: 'detail'),
                    'caption'            => $ci->caption,
                    'file_path'          => $ci->file_path,
                    'filename'           => $ci->file_path ? basename($ci->file_path) : null,
                    'annotations'        => $ci->annotations,
                    'created_at'         => $ci->created_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: the added columns and migrated rows are left in place.
    }
};
