<?php

/**
 * heratio#1570 - the company is The Archive and Heritage Digital Commons Group
 * (Pty) Ltd. Correct the old form "The Archive and Heritage Group" where it is
 * stored as configuration or site text: dropdown labels, settings, plugin and
 * extension authors, landing blocks, hero slides, static pages, help text, the
 * AI client record and journal publishers.
 *
 * Record data is left as catalogued (museum cataloguing institution, notes,
 * provenance agents, preservation originators, job output, IIIF caches):
 * changing what a record says is an editorial decision, not a label fix.
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
        'ahg_dropdown' => ['label'],
        'ahg_dropdown_i18n' => ['label'],
        'ahg_settings' => ['setting_value'],
        'atom_extension' => ['author'],
        'atom_plugin' => ['author'],
        'atom_landing_page_block' => ['title'],
        'heritage_hero_slide' => ['title'],
        'static_page_i18n' => ['content'],
        'help_article' => ['body_html', 'body_markdown', 'body_text', 'toc_json'],
        'help_section' => ['heading', 'body_text'],
        'ahg_ai_service_client' => ['organization'],
        'research_journal' => ['publisher'],
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
