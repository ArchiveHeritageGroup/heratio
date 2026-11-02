<?php

/**
 * heratio#1570, part 2 - correct the old company name "The Archive and
 * Heritage Group" to The Archive and Heritage Digital Commons Group in record
 * data as well, on Johan's instruction (2 Nov 2026): museum cataloguing
 * institution, notes, provenance agents, preservation package originators,
 * journal publishers, job output and cached IIIF manifests.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD = 'Archive and Heritage Group';

    private const NEW = 'Archive and Heritage Digital Commons Group';

    private const COLUMNS = [
        'museum_metadata' => ['cataloging_institution'],
        'museum_metadata_i18n' => ['cataloging_institution'],
        'note_i18n' => ['content'],
        'provenance_agent' => ['name'],
        'provenance_agent_retired' => ['name'],
        'preservation_package' => ['originator'],
        'job' => ['output'],
        'iiif_manifest_cache' => ['manifest_json'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::table($table)->where($column, 'like', '%'.self::OLD.'%')
                        ->update([$column => DB::raw("REPLACE(`$column`, '".self::OLD."', '".self::NEW."')")]);
                }
            }
        }
    }

    public function down(): void
    {
        // A correction of a wrong name has nothing to roll back to.
    }
};
