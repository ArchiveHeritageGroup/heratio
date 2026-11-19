<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * "Explore this collection": the settings the collection explorer reads, so an
 * administrator can tune them under Settings > Discovery. Values are the code
 * defaults; existing rows are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_settings')) {
            return;
        }
        foreach ([
            'explorer_collection_limit' => ['2000', 'Most records "Explore this collection" draws (100 to 5000).'],
            'explorer_min_mentions' => ['2', 'Leave names that occur on fewer records than this off the collection explorer.'],
            'explorer_hide_words' => ['', 'Comma-separated general words kept off the explorer\'s starting list and map (still searchable).'],
            'explorer_ner_min_confidence' => ['0', 'Also use names read from the text that are not yet reviewed, at or above this confidence (0 to 1). 0 uses reviewed names only.'],
        ] as $key => [$value, $desc]) {
            if (! DB::table('ahg_settings')->where('setting_key', $key)->exists()) {
                $row = ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'discovery'];
                if (Schema::hasColumn('ahg_settings', 'description')) {
                    $row['description'] = $desc;
                }
                DB::table('ahg_settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        // Additive only.
    }
};
