<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1730: the ICIP community region (state_territory) options were a
 * list of Australian states hardcoded in IcipController. They now come from
 * the Dropdown Manager taxonomy icip_region. Seed it with the same values so
 * existing communities keep their labels; an installation elsewhere replaces
 * them with its own regions. Existing rows are left alone.
 */
return new class extends Migration
{
    private const VALUES = [
        'NSW' => 'New South Wales', 'VIC' => 'Victoria', 'QLD' => 'Queensland',
        'WA' => 'Western Australia', 'SA' => 'South Australia', 'TAS' => 'Tasmania',
        'NT' => 'Northern Territory', 'ACT' => 'Australian Capital Territory',
        'External' => 'External Territories',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ahg_dropdown')) {
            return;
        }
        $i = 0;
        foreach (self::VALUES as $code => $label) {
            $i++;
            if (DB::table('ahg_dropdown')->where('taxonomy', 'icip_region')->where('code', $code)->exists()) {
                continue;
            }
            DB::table('ahg_dropdown')->insert([
                'taxonomy' => 'icip_region', 'taxonomy_label' => 'ICIP community region', 'code' => $code, 'label' => $label,
                'sort_order' => $i, 'is_default' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ahg_dropdown')->where('taxonomy', 'icip_region')->delete();
    }
};
