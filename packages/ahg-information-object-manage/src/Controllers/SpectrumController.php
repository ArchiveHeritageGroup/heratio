<?php

/**
 * SpectrumController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgSpectrumPlugin/
 * and /usr/share/nginx/archive/atom-ahg-plugins/ahgHeritageAccountingPlugin/
 */
class SpectrumController extends Controller
{
    /**
     * Spectrum data for an IO: condition checks, valuations, and locations.
     */
    public function index(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Fetch spectrum condition checks
        try {
            $conditionChecks = DB::table('spectrum_condition_check')
                ->where('object_id', $io->id)
                ->select(
                    'id',
                    'object_id',
                    'condition_reference',
                    'check_date',
                    'check_reason',
                    'checked_by',
                    'overall_condition',
                    'condition_note',
                    'completeness_note',
                    'hazard_note',
                    'technical_assessment',
                    'recommended_treatment',
                    'treatment_priority',
                    'next_check_date',
                    'environment_recommendation',
                    'handling_recommendation',
                    'display_recommendation',
                    'storage_recommendation',
                    'packing_recommendation',
                    'image_reference',
                    'photo_count',
                    'created_at',
                    'created_by',
                    'condition_check_reference',
                    'completeness',
                    'condition_description',
                    'hazards_noted',
                    'recommendations',
                    'workflow_state',
                    'condition_rating',
                    'condition_notes',
                    'template_id',
                    'material_type',
                    'template_data'
                )
                ->orderBy('check_date', 'desc')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            $conditionChecks = collect();
        }

        // Fetch spectrum valuations
        try {
            $valuations = DB::table('spectrum_valuation')
                ->where('object_id', $io->id)
                ->select(
                    'id',
                    'object_id',
                    'valuation_reference',
                    'valuation_date',
                    'valuation_type',
                    'valuation_amount',
                    'valuation_currency',
                    'valuer_name',
                    'valuer_organization',
                    'valuation_note',
                    'renewal_date',
                    'is_current',
                    'created_at',
                    'created_by',
                    'workflow_state',
                    'renewal_cycle_months',
                    'valuer',
                    'currency'
                )
                ->orderBy('valuation_date', 'desc')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            $valuations = collect();
        }

        // Fetch spectrum locations
        try {
            $locations = DB::table('spectrum_location')
                ->where('object_id', $io->id)
                ->select(
                    'id',
                    'object_id',
                    'location_type',
                    'location_name',
                    'location_building',
                    'location_floor',
                    'location_room',
                    'location_unit',
                    'location_shelf',
                    'location_box',
                    'location_note',
                    'fitness_for_purpose',
                    'security_note',
                    'environment_note',
                    'is_current',
                    'created_at',
                    'updated_at',
                    'created_by',
                    'location_coordinates',
                    'security_level',
                    'workflow_state'
                )
                ->orderBy('is_current', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            $locations = collect();
        }

        return view('ahg-io-manage::spectrum.index', [
            'io'              => $io,
            'conditionChecks' => $conditionChecks,
            'valuations'      => $valuations,
            'locations'        => $locations,
        ]);
    }

    /**
     * Heritage asset accounting for an IO.
     * Fetches spectrum valuations and GRAP heritage asset data.
     */
    public function heritage(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Fetch heritage asset record for this IO
        $asset = null;
        try {
            $asset = DB::table('heritage_asset as ha')
                ->leftJoin('heritage_accounting_standard as std', 'std.id', '=', 'ha.accounting_standard_id')
                ->leftJoin('heritage_asset_class as cls', 'cls.id', '=', 'ha.asset_class_id')
                ->where('ha.information_object_id', $io->id)
                ->select('ha.*', 'std.code as standard_code', 'std.name as standard_name', 'cls.name as class_name')
                ->first();
        } catch (\Exception $e) {
            $asset = null;
        }

        $valuations = collect();
        $impairments = collect();
        $movements = collect();
        $journals = collect();

        if ($asset) {
            try { $valuations = DB::table('heritage_asset_valuation')->where('heritage_asset_id', $asset->id)->orderByDesc('valuation_date')->get(); } catch (\Exception $e) {}
            try { $impairments = DB::table('heritage_asset_impairment')->where('heritage_asset_id', $asset->id)->orderByDesc('assessment_date')->get(); } catch (\Exception $e) {}
            try { $movements = DB::table('heritage_asset_movement')->where('heritage_asset_id', $asset->id)->orderByDesc('movement_date')->get(); } catch (\Exception $e) {}
            try { $journals = DB::table('heritage_asset_journal')->where('heritage_asset_id', $asset->id)->orderByDesc('journal_date')->get(); } catch (\Exception $e) {}
        }

        // If no asset, load standards/classes for the add form
        $standards = collect();
        $classes = collect();
        if (!$asset) {
            try { if (\Illuminate\Support\Facades\Schema::hasTable('heritage_accounting_standard')) { $standards = DB::table('heritage_accounting_standard')->orderBy('code')->get(); } } catch (\Exception $e) {}
            try { if (\Illuminate\Support\Facades\Schema::hasTable('heritage_asset_class')) { $classes = DB::table('heritage_asset_class')->orderBy('name')->get(); } } catch (\Exception $e) {}
        }

        return view('ahg-io-manage::spectrum.heritage', compact('io', 'asset', 'valuations', 'impairments', 'movements', 'journals', 'standards', 'classes'));
    }

    /**
     * Fetch an IO by slug with i18n data.
     */
    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->first();
    }
}
