<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Run the package install.sql to create the six SharePoint tables.
 *
 * The install.sql is the source of truth and is byte-equivalent to
 * /usr/share/nginx/archive/atom-ahg-plugins/ahgSharePointPlugin/database/install.sql
 * (modulo the header). Running raw SQL keeps the schema mirror tight.
 */
return new class extends Migration {
    public function up(): void
    {
        $sql = file_get_contents(__DIR__ . '/../install.sql');
        if ($sql === false) {
            throw new \RuntimeException('Cannot read ahg-sharepoint install.sql');
        }

        // Split on semicolons that terminate a statement on its own line.
        // Naive split is fine here because install.sql is hand-curated and
        // contains no embedded ';' inside string literals.
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*\n/', $sql)),
            fn ($s) => $s !== '' && !str_starts_with($s, '--')
        );

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        // Defensive: drop in reverse FK dependency order.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'sharepoint_event',
            'sharepoint_subscription',
            'sharepoint_sync_state',
            'sharepoint_mapping',
            'sharepoint_drive',
            'sharepoint_tenant',
        ] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
