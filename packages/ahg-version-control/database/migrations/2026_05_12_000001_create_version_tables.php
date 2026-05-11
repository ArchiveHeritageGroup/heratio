<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase A — create information_object_version + actor_version.
 *
 * Runs the package install.sql, which is the source of truth and byte-equivalent
 * to /usr/share/nginx/archive/atom-ahg-plugins/ahgVersionControlPlugin/database/install.sql
 * (modulo the header line). Mirror schemas across surfaces.
 */
return new class extends Migration {
    public function up(): void
    {
        $sql = file_get_contents(__DIR__ . '/../install.sql');
        if ($sql === false) {
            throw new \RuntimeException('Cannot read ahg-version-control install.sql');
        }

        // DB::unprepared executes the SQL as-is via PDO::exec, which supports
        // multiple statements and comments. Cleaner than hand-splitting on ';'.
        DB::unprepared($sql);
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['actor_version', 'information_object_version'] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
