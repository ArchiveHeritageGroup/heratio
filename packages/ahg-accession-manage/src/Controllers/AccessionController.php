<?php

namespace AhgAccessionManage\Controllers;

use AhgAccessionManage\Services\AccessionBrowseService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccessionController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new AccessionBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'lastUpdated'),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-accession-manage::browse', [
            'pager' => $pager,
            'sortOptions' => [
                'alphabetic' => 'Title',
                'identifier' => 'Identifier',
                'date' => 'Accession date',
                'lastUpdated' => 'Date modified',
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $accession = DB::table('accession')
            ->join('slug', 'accession.id', '=', 'slug.object_id')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->join('object', 'accession.id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('accession_i18n.culture', $culture)
            ->select([
                'accession.id',
                'accession.identifier',
                'accession.date',
                'accession.acquisition_type_id',
                'accession.processing_priority_id',
                'accession.processing_status_id',
                'accession.resource_type_id',
                'accession_i18n.title',
                'accession_i18n.scope_and_content',
                'accession_i18n.appraisal',
                'accession_i18n.archival_history',
                'accession_i18n.location_information',
                'accession_i18n.physical_characteristics',
                'accession_i18n.processing_notes',
                'accession_i18n.received_extent_units',
                'accession_i18n.source_of_acquisition',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$accession) {
            abort(404);
        }

        // Resolve term names for type/priority/status IDs
        $termIds = array_filter([
            $accession->acquisition_type_id,
            $accession->processing_priority_id,
            $accession->processing_status_id,
            $accession->resource_type_id,
        ]);

        $termNames = [];
        if (!empty($termIds)) {
            $termNames = DB::table('term_i18n')
                ->whereIn('id', $termIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Get donor via relation table (type_id = 167 "Accession" relation)
        $donor = DB::table('relation')
            ->join('actor_i18n', 'relation.subject_id', '=', 'actor_i18n.id')
            ->join('slug', 'relation.subject_id', '=', 'slug.object_id')
            ->where('relation.object_id', $accession->id)
            ->where('relation.type_id', 167)
            ->where('actor_i18n.culture', $culture)
            ->select([
                'relation.subject_id as id',
                'actor_i18n.authorized_form_of_name as name',
                'slug.slug',
            ])
            ->first();

        // Get deaccessions
        $deaccessions = DB::table('deaccession')
            ->join('deaccession_i18n', 'deaccession.id', '=', 'deaccession_i18n.id')
            ->where('deaccession.accession_id', $accession->id)
            ->where('deaccession_i18n.culture', $culture)
            ->select([
                'deaccession.id',
                'deaccession.identifier',
                'deaccession.date',
                'deaccession.scope_id',
                'deaccession_i18n.description',
                'deaccession_i18n.extent',
                'deaccession_i18n.reason',
            ])
            ->get();

        // Resolve deaccession scope term names
        $scopeIds = $deaccessions->pluck('scope_id')->filter()->unique()->values()->toArray();
        $scopeNames = [];
        if (!empty($scopeIds)) {
            $scopeNames = DB::table('term_i18n')
                ->whereIn('id', $scopeIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        return view('ahg-accession-manage::show', [
            'accession' => $accession,
            'termNames' => $termNames,
            'donor' => $donor,
            'deaccessions' => $deaccessions,
            'scopeNames' => $scopeNames,
        ]);
    }
}
