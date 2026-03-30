<?php

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

        // Fetch current valuation
        try {
            $currentValuation = DB::table('spectrum_valuation')
                ->where('object_id', $io->id)
                ->where('is_current', 1)
                ->first();
        } catch (\Illuminate\Database\QueryException $e) {
            $currentValuation = null;
        }

        // Fetch valuation history
        try {
            $valuationHistory = DB::table('spectrum_valuation')
                ->where('object_id', $io->id)
                ->orderBy('valuation_date', 'desc')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            $valuationHistory = collect();
        }

        // Fetch GRAP heritage asset data if exists
        try {
            $grapAsset = DB::table('grap_heritage_asset')
                ->where('object_id', $io->id)
                ->first();
        } catch (\Illuminate\Database\QueryException $e) {
            $grapAsset = null;
        }

        // Fetch GRAP data record for this object (links depreciation/revaluation)
        try {
            $grapData = DB::table('spectrum_grap_data')
                ->where('information_object_id', $io->id)
                ->first();
        } catch (\Illuminate\Database\QueryException $e) {
            $grapData = null;
        }

        // Fetch GRAP depreciation schedule if GRAP data exists
        try {
            $grapDepreciation = $grapData
                ? DB::table('spectrum_grap_depreciation_schedule')
                    ->where('grap_data_id', $grapData->id)
                    ->orderBy('fiscal_year', 'desc')
                    ->get()
                : collect();
        } catch (\Illuminate\Database\QueryException $e) {
            $grapDepreciation = collect();
        }

        // Fetch GRAP revaluation history
        try {
            $grapRevaluations = $grapData
                ? DB::table('spectrum_grap_revaluation_history')
                    ->where('grap_data_id', $grapData->id)
                    ->orderBy('revaluation_date', 'desc')
                    ->get()
                : collect();
        } catch (\Illuminate\Database\QueryException $e) {
            $grapRevaluations = collect();
        }

        return view('ahg-io-manage::spectrum.heritage', [
            'io'                => $io,
            'currentValuation'  => $currentValuation,
            'valuationHistory'  => $valuationHistory,
            'grapAsset'         => $grapAsset,
            'grapData'          => $grapData ?? null,
            'grapDepreciation'  => $grapDepreciation,
            'grapRevaluations'  => $grapRevaluations,
        ]);
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
