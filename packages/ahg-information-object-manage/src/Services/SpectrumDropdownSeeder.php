<?php

/**
 * SpectrumDropdownSeeder - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgInformationObjectManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent first-boot seed for the SPECTRUM / GRAP heritage-asset
 * controlled vocabularies, moving the formerly hardcoded <option> lists
 * in the heritage form into admin-managed ahg_dropdown taxonomies.
 *
 * The stored code values are unchanged - only the labels become
 * admin-curatable. An $existing guard means a taxonomy an operator has
 * already touched is never re-merged.
 */
class SpectrumDropdownSeeder
{
    public static function seed(): void
    {
        if (!Schema::hasTable('ahg_dropdown')) {
            return;
        }

        // taxonomy => [taxonomy_label, [code => label, ...]]
        $vocabularies = [
            'spectrum_recognition_status' => ['Recognition Status', [
                'pending'        => 'Pending',
                'recognised'     => 'Recognised',
                'not_recognised' => 'Not Recognised',
            ]],
            'spectrum_measurement_basis' => ['Measurement Basis', [
                'cost'            => 'Cost',
                'fair_value'      => 'Fair Value',
                'nominal'         => 'Nominal',
                'not_practicable' => 'Not Practicable',
            ]],
            'spectrum_acquisition_method' => ['Acquisition Method', [
                'purchase' => 'Purchase',
                'donation' => 'Donation',
                'bequest'  => 'Bequest',
                'transfer' => 'Transfer',
                'found'    => 'Found',
                'exchange' => 'Exchange',
                'other'    => 'Other',
            ]],
            'spectrum_heritage_significance' => ['Heritage Significance', [
                'exceptional' => 'Exceptional',
                'high'        => 'High',
                'medium'      => 'Medium',
                'low'         => 'Low',
            ]],
            'spectrum_condition_rating' => ['Condition Rating', [
                'excellent' => 'Excellent',
                'good'      => 'Good',
                'fair'      => 'Fair',
                'poor'      => 'Poor',
                'critical'  => 'Critical',
            ]],
        ];

        // Never re-merge a taxonomy an admin has already curated.
        $existing = DB::table('ahg_dropdown')
            ->whereIn('taxonomy', array_keys($vocabularies))
            ->pluck('taxonomy')
            ->unique()
            ->all();

        $rows = [];
        foreach ($vocabularies as $taxonomy => [$taxonomyLabel, $options]) {
            if (in_array($taxonomy, $existing, true)) {
                continue;
            }

            $sortOrder = 10;
            $first = true;
            foreach ($options as $code => $label) {
                $rows[] = [
                    'taxonomy'         => $taxonomy,
                    'taxonomy_label'   => $taxonomyLabel,
                    'taxonomy_section' => 'spectrum',
                    'code'             => $code,
                    'label'            => $label,
                    'sort_order'       => $sortOrder,
                    'is_default'       => $first ? 1 : 0,
                    'is_active'        => 1,
                ];
                $sortOrder += 10;
                $first = false;
            }
        }

        if (!empty($rows)) {
            DB::table('ahg_dropdown')->insertOrIgnore($rows);
        }
    }
}
