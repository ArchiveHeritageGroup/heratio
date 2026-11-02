<?php

/**
 * term_i18n.name_sort - the first 191 characters of the name as a virtual
 * column with an index on (culture, name_sort, id), so the alphabetical term
 * list reads one page in index order instead of sorting every term. name is
 * varchar(1024) and its index stores a 191-character prefix, which MySQL
 * cannot use for ORDER BY: /term/browse took 6-10 s a page at ANC's 558,000
 * terms (heratio#1573). Virtual, so adding it does not rebuild the table.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('term_i18n') || Schema::hasColumn('term_i18n', 'name_sort')) {
            return;
        }
        DB::statement('ALTER TABLE term_i18n ADD COLUMN name_sort VARCHAR(191) GENERATED ALWAYS AS (LEFT(name, 191)) VIRTUAL, ADD INDEX idx_term_i18n_name_sort (culture, name_sort, id)');
    }

    public function down(): void
    {
        if (Schema::hasColumn('term_i18n', 'name_sort')) {
            DB::statement('ALTER TABLE term_i18n DROP INDEX idx_term_i18n_name_sort, DROP COLUMN name_sort');
        }
    }
};
