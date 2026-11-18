<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1780: four taxonomies the heritage and IPSAS forms read had no rows
 * in ahg_dropdown, so their options came from lists hardcoded in the code and
 * could not be changed in the Dropdown Manager. Seed them with those same
 * values. Existing rows are left alone, so an administrator's edits survive.
 */
return new class extends Migration
{
    private const TAXONOMIES = [
        'heritage_standard_valuation_method' => ['Heritage valuation method', [
            'cost' => 'Historical Cost', 'fair_value' => 'Fair Value', 'deemed_cost' => 'Deemed Cost',
            'revaluation' => 'Revaluation Model', 'valuation' => 'Valuation', 'nominal' => 'Nominal Value',
            'insurance' => 'Insurance Value', 'replacement' => 'Replacement Cost',
        ]],
        'heritage_oci_movement_type' => ['OCI movement type', [
            'revaluation_up' => 'Revaluation increase', 'revaluation_down' => 'Revaluation decrease',
            'impairment' => 'Impairment', 'reversal' => 'Impairment reversal', 'disposal' => 'Disposal',
        ]],
        'ipsas_valuation_method' => ['IPSAS valuation method', [
            'market_comparison' => 'Market Comparison', 'income_approach' => 'Income Approach',
            'cost_approach' => 'Cost Approach', 'expert_opinion' => 'Expert Opinion',
        ]],
        'ipsas_accounting_standard' => ['IPSAS accounting standard', [
            'IPSAS' => 'IPSAS', 'GRAP' => 'GRAP', 'IFRS' => 'IFRS', 'GAAP' => 'GAAP',
        ]],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ahg_dropdown')) {
            return;
        }
        foreach (self::TAXONOMIES as $taxonomy => [$taxonomyLabel, $values]) {
            $i = 0;
            foreach ($values as $code => $label) {
                $i++;
                if (DB::table('ahg_dropdown')->where('taxonomy', $taxonomy)->where('code', $code)->exists()) {
                    continue;
                }
                DB::table('ahg_dropdown')->insert([
                    'taxonomy' => $taxonomy, 'taxonomy_label' => $taxonomyLabel, 'code' => $code, 'label' => $label,
                    'sort_order' => $i, 'is_default' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('ahg_dropdown')->whereIn('taxonomy', array_keys(self::TAXONOMIES))->delete();
    }
};
