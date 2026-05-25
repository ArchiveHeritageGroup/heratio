<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Run the package install.sql to create the seven SharePoint tables.
 *
 * The install.sql is the source of truth and is byte-equivalent to
 * /usr/share/nginx/archive/atom-ahg-plugins/ahgSharePointPlugin/database/install.sql
 * (modulo the header). Running raw SQL keeps the schema mirror tight.
 *
 * heratio#130: the previous implementation split install.sql on `;\s*\n` and
 * dropped any chunk beginning with a comment line - which silently skipped
 * every CREATE TABLE (each is preceded by a `-- N. table` header), yet Laravel
 * still recorded the migration as Ran. The whole file is now executed in one
 * batch with DB::unprepared(), and a post-run check fails the migration loudly
 * if any expected table is missing.
 */
return new class extends Migration
{
    /** Tables install.sql is expected to create. */
    private const TABLES = [
        'sharepoint_tenant',
        'sharepoint_drive',
        'sharepoint_mapping',
        'sharepoint_sync_state',
        'sharepoint_subscription',
        'sharepoint_event',
        'sharepoint_user_mapping',
    ];

    public function up(): void
    {
        $sql = file_get_contents(__DIR__.'/../install.sql');
        if ($sql === false) {
            throw new \RuntimeException('Cannot read ahg-sharepoint install.sql');
        }

        // install.sql is hand-curated, multi-statement, with comment headers
        // and a SET FOREIGN_KEY_CHECKS wrapper. DB::unprepared() runs it
        // verbatim as one batch - the same pattern ahg-provenance-ai uses for
        // its install.sql - instead of a fragile per-statement split.
        DB::unprepared($sql);

        // Fail loudly if anything is missing, so a half-applied migration is
        // never silently recorded as Ran (heratio#130).
        $missing = array_values(array_filter(
            self::TABLES,
            static fn ($table) => ! Schema::hasTable($table)
        ));
        if ($missing !== []) {
            throw new \RuntimeException(
                'ahg-sharepoint install.sql did not create: '.implode(', ', $missing)
            );
        }
    }

    public function down(): void
    {
        // Defensive: drop in reverse FK dependency order. sharepoint_user_mapping
        // has no FK into the others, so its position is not significant.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'sharepoint_event',
            'sharepoint_subscription',
            'sharepoint_sync_state',
            'sharepoint_mapping',
            'sharepoint_drive',
            'sharepoint_tenant',
            'sharepoint_user_mapping',
        ] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
