<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1 Phase A — create information_object_share_token + _share_access.
 *
 * Runs the package install.sql via DB::unprepared (handles multi-statement
 * + comments natively). This is the source-of-truth + mirror approach we
 * established in F2 Phase A.
 */
return new class extends Migration {
    public function up(): void
    {
        $sql = file_get_contents(__DIR__ . '/../install.sql');
        if ($sql === false) {
            throw new \RuntimeException('Cannot read ahg-share-link install.sql');
        }
        DB::unprepared($sql);
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['information_object_share_access', 'information_object_share_token'] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
