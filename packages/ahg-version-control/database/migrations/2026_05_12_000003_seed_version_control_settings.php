<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase M — seed default retention settings.
 *
 * Three keys in ahg_settings under setting_group='version_control':
 *   retain_count            (integer, default 0 = unlimited)
 *   retain_days             (integer, default 0 = unlimited)
 *   skip_on_minor_edit      (boolean, default 0 — reserved for future use)
 */
return new class extends Migration {
    private const SEEDS = [
        [
            'setting_key'   => 'version_control.retain_count',
            'setting_value' => '0',
            'setting_type'  => 'integer',
            'description'   => 'How many recent versions to keep per entity. 0 = unlimited. v1 baseline is always kept.',
        ],
        [
            'setting_key'   => 'version_control.retain_days',
            'setting_value' => '0',
            'setting_type'  => 'integer',
            'description'   => 'Keep versions newer than N days. 0 = unlimited. v1 baseline is always kept; recent-N (per retain_count) always kept.',
        ],
        [
            'setting_key'   => 'version_control.skip_on_minor_edit',
            'setting_value' => '0',
            'setting_type'  => 'boolean',
            'description'   => 'Reserved — if 1, the save listener skips capture when changed_fields is empty. Currently unused.',
        ],
    ];

    public function up(): void
    {
        foreach (self::SEEDS as $row) {
            $exists = DB::table('ahg_settings')->where('setting_key', $row['setting_key'])->exists();
            if (!$exists) {
                DB::table('ahg_settings')->insert($row + [
                    'setting_group' => 'version_control',
                    'is_sensitive'  => 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('ahg_settings')->where('setting_group', 'version_control')->delete();
    }
};
