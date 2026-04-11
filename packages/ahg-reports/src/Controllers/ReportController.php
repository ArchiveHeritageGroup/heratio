<?php

/**
 * ReportController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgReports\Controllers;

use AhgReports\Services\ReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private ReportService $service;

    public function __construct(ReportService $service)
    {
        $this->service = $service;
    }

    public function dashboard()
    {
        $stats = $this->service->getReportStats();

        // Plugin detection
        $enabledPlugins = [];
        if (Schema::hasTable('atom_plugin')) {
            $enabledPlugins = DB::table('atom_plugin')
                ->where('is_enabled', 1)
                ->pluck('name')
                ->flip()
                ->toArray();
        }
        $hasPlugin = fn($name) => isset($enabledPlugins[$name]);

        // AI Condition stats
        $aiConditionStats = null;
        if ($hasPlugin('ahgAiConditionPlugin')) {
            try {
                if (Schema::hasTable('ahg_ai_condition_assessment')) {
                    $aiConditionStats = [
                        'total' => DB::table('ahg_ai_condition_assessment')->count(),
                        'confirmed' => DB::table('ahg_ai_condition_assessment')->where('is_confirmed', 1)->count(),
                        'avg_score' => round(DB::table('ahg_ai_condition_assessment')->avg('overall_score') ?? 0, 1),
                        'by_grade' => DB::table('ahg_ai_condition_assessment')
                            ->select('condition_grade', DB::raw('COUNT(*) as cnt'))
                            ->groupBy('condition_grade')
                            ->pluck('cnt', 'condition_grade')
                            ->all(),
                    ];
                    $aiConditionStats['pending'] = $aiConditionStats['total'] - $aiConditionStats['confirmed'];
                }
            } catch (\Exception $e) {
                $aiConditionStats = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'avg_score' => 0, 'by_grade' => []];
            }
        }

        return view('ahg-reports::dashboard', compact('stats', 'enabledPlugins', 'hasPlugin', 'aiConditionStats'));
    }

    public function accessions(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'limit', 'page']);
        $data = $this->service->reportAccessions($params);
        $cultures = $this->service->getAvailableCultures();

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Identifier', 'Title', 'Scope', 'Created', 'Updated'],
                'accession-report.csv'
            );
        }

        return view('ahg-reports::report-accessions', array_merge($data, [
            'params' => $params, 'cultures' => $cultures,
        ]));
    }

    public function descriptions(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'level', 'publicationStatus', 'limit', 'page']);
        $data = $this->service->reportDescriptions($params);
        $levels = $this->service->getLevelsOfDescription();

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['Identifier', 'Title', 'Alternate Title', 'Extent And Medium',
                 'Archival History', 'Acquisition', 'Scope And Content',
                 'Appraisal', 'Accruals', 'Arrangement', 'Access Conditions',
                 'Reproduction Conditions', 'Physical Characteristics', 'Finding Aids',
                 'Location Of Originals', 'Location Of Copies', 'Related Units',
                 'Institution Responsible', 'Rules', 'Sources', 'Revision History',
                 'Culture', 'Repository', 'Created'],
                'description-report.csv'
            );
        }

        return view('ahg-reports::report-descriptions', array_merge($data, [
            'params' => $params, 'levels' => $levels,
        ]));
    }

    public function authorities(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'entityType', 'limit', 'page']);
        $data = $this->service->reportAuthorities($params);
        $entityTypes = $this->service->getEntityTypes();

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Name', 'Entity Type', 'Dates', 'Created', 'Updated'],
                'authority-report.csv'
            );
        }

        return view('ahg-reports::report-authorities', array_merge($data, [
            'params' => $params, 'entityTypes' => $entityTypes,
        ]));
    }

    public function donors(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'limit', 'page']);
        $data = $this->service->reportDonors($params);

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Name', 'Email', 'Phone', 'City', 'Created', 'Updated'],
                'donor-report.csv'
            );
        }

        return view('ahg-reports::report-donors', array_merge($data, ['params' => $params]));
    }

    public function repositories(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'limit', 'page']);
        $data = $this->service->reportRepositories($params);

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Identifier', 'Name', 'Holdings', 'Created', 'Updated'],
                'repository-report.csv'
            );
        }

        return view('ahg-reports::report-repositories', array_merge($data, ['params' => $params]));
    }

    public function storage(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'limit', 'page']);
        $data = $this->service->reportPhysicalStorage($params);

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Name', 'Type', 'Location', 'Created', 'Updated'],
                'physical-storage-report.csv'
            );
        }

        return view('ahg-reports::report-storage', array_merge($data, ['params' => $params]));
    }

    public function activity(Request $request)
    {
        $params = $request->only(['dateStart', 'dateEnd', 'actionUser', 'userAction', 'limit', 'page']);
        $data = $this->service->reportUserActivity($params);
        $users = $this->service->getAuditUsers();

        return view('ahg-reports::report-activity', array_merge($data, [
            'params' => $params, 'users' => $users,
        ]));
    }

    public function recent(Request $request)
    {
        $params = $request->only(['dateStart', 'dateEnd', 'className', 'limit', 'page']);
        $data = $this->service->reportUpdates($params);

        return view('ahg-reports::report-recent', array_merge($data, ['params' => $params]));
    }

    public function taxonomy(Request $request)
    {
        $params = $request->only(['dateStart', 'dateEnd', 'sort', 'limit', 'page']);
        $data = $this->service->reportTaxonomies($params);

        return view('ahg-reports::report-taxonomy', array_merge($data, ['params' => $params]));
    }

    public function spatialAnalysis(Request $request)
    {
        $culture = $request->input('culture', 'en');
        $place = $request->input('place', []);
        $level = $request->input('level');
        $subjects = $request->input('subjects', '');
        $topLevelOnly = $request->boolean('topLevelOnly');
        $requireCoordinates = $request->boolean('requireCoordinates', true);
        $export = $request->input('export');

        // Build query for records with coordinate data
        $query = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('property as plat', function ($join) {
                $join->on('io.id', '=', 'plat.object_id')
                    ->where('plat.name', 'like', '%latitude%');
            })
            ->leftJoin('property as plng', function ($join) {
                $join->on('io.id', '=', 'plng.object_id')
                    ->where('plng.name', 'like', '%longitude%');
            })
            ->leftJoin('term_i18n as lod', function ($join) use ($culture) {
                $join->on('io.level_of_description_id', '=', 'lod.id')
                    ->where('lod.culture', '=', $culture);
            })
            ->leftJoin('actor_i18n as ri', function ($join) use ($culture) {
                $join->on('io.repository_id', '=', 'ri.id')
                    ->where('ri.culture', '=', $culture);
            })
            ->select([
                'io.id',
                'io.identifier',
                'ioi.title',
                DB::raw('plat.source_culture as latitude'),
                DB::raw('plng.source_culture as longitude'),
                'lod.name as level_of_description',
                'ri.authorized_form_of_name as repository',
            ]);

        if ($requireCoordinates) {
            $query->whereNotNull('plat.id')->whereNotNull('plng.id');
        }

        if ($topLevelOnly) {
            $query->whereNull('io.parent_id');
        }

        // Filter by level of description (taxonomy 34)
        if ($level) {
            $query->where('io.level_of_description_id', $level);
        }

        // Filter by place (taxonomy 42)
        if (!empty($place)) {
            $placeIds = is_array($place) ? $place : [$place];
            $query->whereExists(function ($sub) use ($placeIds) {
                $sub->select(DB::raw(1))
                    ->from('object_term_relation as otr')
                    ->join('term as t', 'otr.term_id', '=', 't.id')
                    ->whereColumn('otr.object_id', 'io.id')
                    ->where('t.taxonomy_id', 42)
                    ->whereIn('otr.term_id', $placeIds);
            });
        }

        // Filter by subject terms (freetext, comma-separated)
        if ($subjects) {
            $subjectList = array_filter(array_map('trim', explode(',', $subjects)));
            if (!empty($subjectList)) {
                $query->whereExists(function ($sub) use ($subjectList, $culture) {
                    $sub->select(DB::raw(1))
                        ->from('object_term_relation as otr')
                        ->join('term_i18n as sti', function ($join) use ($culture) {
                            $join->on('otr.term_id', '=', 'sti.id')
                                ->where('sti.culture', '=', $culture);
                        })
                        ->whereColumn('otr.object_id', 'io.id')
                        ->where(function ($q) use ($subjectList) {
                            foreach ($subjectList as $term) {
                                $q->orWhere('sti.name', 'like', '%' . $term . '%');
                            }
                        });
                });
            }
        }

        // Place terms for multiselect
        $placeTerms = DB::table('term')
            ->join('term_i18n', function ($join) use ($culture) {
                $join->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', 42)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Levels of description for dropdown
        $levels = DB::table('term')
            ->join('term_i18n', function ($join) use ($culture) {
                $join->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', 34)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Handle exports
        if ($request->isMethod('post') && $export === 'csv') {
            $results = $query->get();
            return new StreamedResponse(function () use ($results) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['ID', 'Identifier', 'Title', 'Latitude', 'Longitude', 'Level', 'Repository', 'Place']);
                foreach ($results as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->identifier,
                        $row->title,
                        $row->latitude,
                        $row->longitude,
                        $row->level_of_description,
                        $row->repository,
                        '',
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="spatial-analysis-export.csv"',
            ]);
        }

        if ($request->isMethod('post') && $export === 'json') {
            $results = $query->get();
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => $results->map(function ($row) {
                    return [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'identifier' => $row->identifier,
                            'title' => $row->title,
                            'level' => $row->level_of_description,
                            'repository' => $row->repository,
                        ],
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [(float) $row->longitude, (float) $row->latitude],
                        ],
                    ];
                })->values(),
            ]);
        }

        // Preview: limit to 10 records
        $preview = $query->limit(10)->get();
        $totalCount = (clone $query)->getCountForPagination();

        $params = $request->only(['culture', 'place', 'level', 'subjects', 'topLevelOnly', 'requireCoordinates']);

        return view('ahg-reports::report-spatial', [
            'preview' => $preview,
            'totalCount' => $totalCount,
            'params' => $params,
            'placeTerms' => $placeTerms,
            'levels' => $levels,
        ]);
    }

    /**
     * Reports index page.
     */
    public function index()
    {
        return view('ahg-reports::index');
    }

    /**
     * Browse reports with strong room / location filters.
     */
    public function browse(Request $request)
    {
        $strongrooms = [];
        $locations = [];

        try {
            if (Schema::hasTable('physical_object_i18n')) {
                // Strong rooms from physical object names (boxes/vaults)
                $strongrooms = DB::table('physical_object_i18n')
                    ->select('name')
                    ->whereNotNull('name')
                    ->where('name', '!=', '')
                    ->distinct()
                    ->orderBy('name')
                    ->pluck('name')
                    ->toArray();

                // Locations from the location field
                $locations = DB::table('physical_object_i18n')
                    ->select('location')
                    ->whereNotNull('location')
                    ->where('location', '!=', '')
                    ->distinct()
                    ->orderBy('location')
                    ->pluck('location')
                    ->toArray();
            }
        } catch (\Exception $e) {
            // ignore
        }

        return view('ahg-reports::browse', compact('strongrooms', 'locations'));
    }

    /**
     * Browse / publish preservation items.
     */
    public function browsePublish(Request $request)
    {
        $items = collect();

        return view('ahg-reports::browse-publish', compact('items'));
    }

    /**
     * Report type selector.
     */
    public function reportSelect(Request $request)
    {
        $objectType = $request->input('objectType');

        if ($objectType) {
            $routeMap = [
                'accession' => 'reports.report-accession',
                'informationObject' => 'reports.report-information-object',
                'authorityRecord' => 'reports.report-authority-record',
                'donor' => 'reports.report-donor',
                'physical_storage' => 'reports.report-physical-storage',
                'repository' => 'reports.report-repository',
            ];

            if (isset($routeMap[$objectType])) {
                return redirect()->route($routeMap[$objectType]);
            }
        }

        return view('ahg-reports::report-select');
    }

    /**
     * Generic report viewer.
     */
    public function report(Request $request)
    {
        $reportName = $request->input('name', 'Report');
        $results = [];
        $summary = [];

        return view('ahg-reports::report', compact('reportName', 'results', 'summary'));
    }

    /**
     * Access report.
     */
    public function reportAccess(Request $request)
    {
        $results = collect();
        $columns = ['Identifier', 'Title', 'Refusal', 'Sensitive', 'Publish', 'Classification', 'Restriction', 'Date'];

        return view('ahg-reports::report-access', compact('results', 'columns'));
    }

    /**
     * Accession report (audit-style).
     */
    public function reportAccession(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'limit']);
        $results = collect();
        $columns = ['ID', 'Identifier', 'Title', 'Scope', 'Created', 'Updated'];

        try {
            $query = DB::table('accession')
                ->leftJoin('accession_i18n', function ($j) {
                    $j->on('accession.id', '=', 'accession_i18n.id')
                      ->where('accession_i18n.culture', '=', $params['culture'] ?? 'en');
                })
                ->select('accession.id', 'accession.identifier', 'accession_i18n.title', 'accession_i18n.scope_and_content as scope', 'accession.created_at', 'accession.updated_at');

            if (!empty($params['dateStart'])) {
                $query->where('accession.created_at', '>=', $params['dateStart']);
            }
            if (!empty($params['dateEnd'])) {
                $query->where('accession.created_at', '<=', $params['dateEnd']);
            }

            $results = $query->orderByDesc('accession.created_at')->paginate((int) ($params['limit'] ?? 25));
        } catch (\Exception $e) {
            $results = collect();
        }

        return view('ahg-reports::report-accession', compact('results', 'columns'));
    }

    /**
     * Authority record report.
     */
    public function reportAuthorityRecord(Request $request)
    {
        $results = collect();
        $columns = ['ID', 'Identifier', 'Name', 'Entity Type', 'Created', 'Updated'];

        try {
            $results = DB::table('actor')
                ->leftJoin('actor_i18n', function ($j) {
                    $j->on('actor.id', '=', 'actor_i18n.id')
                      ->where('actor_i18n.culture', '=', $request->input('culture', 'en'));
                })
                ->select('actor.id', 'actor.description_identifier as identifier', 'actor_i18n.authorized_form_of_name as name', 'actor.entity_type_id', 'actor.created_at', 'actor.updated_at')
                ->where('actor.id', '!=', 1)
                ->orderByDesc('actor.created_at')
                ->paginate(25);
        } catch (\Exception $e) {
            // ignore
        }

        return view('ahg-reports::report-authority-record', compact('results', 'columns'));
    }

    /**
     * Donor report.
     */
    public function reportDonor(Request $request)
    {
        $results = collect();
        $columns = ['ID', 'Name', 'Created', 'Updated'];

        try {
            $results = DB::table('donor')
                ->join('actor', 'donor.id', '=', 'actor.id')
                ->leftJoin('actor_i18n', function ($j) {
                    $j->on('actor.id', '=', 'actor_i18n.id')
                      ->where('actor_i18n.culture', '=', $request->input('culture', 'en'));
                })
                ->select('donor.id', 'actor_i18n.authorized_form_of_name as name', 'actor.created_at', 'actor.updated_at')
                ->orderByDesc('actor.created_at')
                ->paginate(25);
        } catch (\Exception $e) {
            // ignore
        }

        return view('ahg-reports::report-donor', compact('results', 'columns'));
    }

    /**
     * Information object report.
     */
    public function reportInformationObject(Request $request)
    {
        $results = collect();
        $columns = ['ID', 'Identifier', 'Title', 'Level', 'Status', 'Created', 'Updated'];

        return view('ahg-reports::report-information-object', compact('results', 'columns'));
    }

    /**
     * Physical storage report.
     */
    public function reportPhysicalStorage(Request $request)
    {
        $results = collect();
        $columns = ['ID', 'Name', 'Location', 'Type', 'Created'];

        return view('ahg-reports::report-physical-storage', compact('results', 'columns'));
    }

    /**
     * Repository report.
     */
    public function reportRepository(Request $request)
    {
        $results = collect();
        $columns = ['ID', 'Identifier', 'Name', 'Created', 'Updated'];

        return view('ahg-reports::report-repository', compact('results', 'columns'));
    }

    /**
     * Spatial analysis report.
     */
    public function reportSpatialAnalysis(Request $request)
    {
        $results = collect();
        $columns = ['ID', 'Identifier', 'Title', 'Latitude', 'Longitude'];

        return view('ahg-reports::report-spatial-analysis', compact('results', 'columns'));
    }

    /**
     * Taxonomy report.
     */
    public function reportTaxonomyAudit(Request $request)
    {
        $results = collect();
        $columns = ['ID', 'Taxonomy', 'Term', 'Created', 'Updated'];

        return view('ahg-reports::report-taxonomy-audit', compact('results', 'columns'));
    }

    /**
     * Updates report.
     */
    public function reportUpdates(Request $request)
    {
        $results = collect();
        $columns = ['ID', 'Entity', 'Title', 'Action', 'User', 'Date'];

        return view('ahg-reports::report-updates', compact('results', 'columns'));
    }

    /**
     * User activity report.
     */
    public function reportUser(Request $request)
    {
        $records = collect();
        $columns = ['User', 'Action', 'Date', 'Identifier', 'Title', 'Repository', 'Area'];

        try {
            if (Schema::hasTable('ahg_audit_log')) {
                $query = DB::table('ahg_audit_log')
                    ->select('username as User', 'action as Action', 'created_at as Date', 'entity_id as Identifier', 'entity_type as Title', DB::raw("'' as Repository"), 'action as Area');

                if ($request->filled('dateStart')) {
                    $query->where('created_at', '>=', $request->input('dateStart'));
                }
                if ($request->filled('dateEnd')) {
                    $query->where('created_at', '<=', $request->input('dateEnd'));
                }

                $records = $query->orderByDesc('created_at')->paginate(25);
            }
        } catch (\Exception $e) {
            // ignore
        }

        return view('ahg-reports::report-user', compact('records', 'columns'));
    }

    /**
     * Audit: Actor records.
     */
    public function auditActor(Request $request)
    {
        $records = $this->getAuditRecords($request, ['actor', 'actor_i18n']);
        return view('ahg-reports::audit-actor', compact('records'));
    }

    /**
     * Audit: Archival descriptions.
     */
    public function auditDescription(Request $request)
    {
        $records = $this->getAuditRecords($request, ['information_object', 'information_object_i18n']);
        return view('ahg-reports::audit-archival-description', compact('records'));
    }

    /**
     * Audit: Donors.
     */
    public function auditDonor(Request $request)
    {
        $records = $this->getAuditRecords($request, ['donor']);
        return view('ahg-reports::audit-donor', compact('records'));
    }

    /**
     * Audit: Permissions.
     */
    public function auditPermissions(Request $request)
    {
        $records = $this->getAuditRecords($request, ['acl_permission', 'acl_group', 'acl_user_group']);
        return view('ahg-reports::audit-permissions', compact('records'));
    }

    /**
     * Audit: Physical storage.
     */
    public function auditPhysicalStorage(Request $request)
    {
        $records = $this->getAuditRecords($request, ['physical_object', 'physical_object_i18n']);
        return view('ahg-reports::audit-physical-storage', compact('records'));
    }

    /**
     * Audit: Repository.
     */
    public function auditRepository(Request $request)
    {
        $records = $this->getAuditRecords($request, ['repository', 'repository_i18n']);
        return view('ahg-reports::audit-repository', compact('records'));
    }

    /**
     * Audit: Taxonomy.
     */
    public function auditTaxonomy(Request $request)
    {
        $records = $this->getAuditRecords($request, ['term', 'term_i18n', 'taxonomy']);
        return view('ahg-reports::audit-taxonomy', compact('records'));
    }

    /**
     * Helper: get audit trail records for given DB tables.
     */
    private function getAuditRecords(Request $request, array $tables)
    {
        try {
            if (!Schema::hasTable('ahg_audit_log')) {
                return collect();
            }

            $query = DB::table('ahg_audit_log')
                ->whereIn('entity_type', $tables)
                ->select(
                    'id',
                    'username',
                    'action',
                    'created_at as action_date_time',
                    'entity_id as record_id',
                    'entity_type as db_table',
                    'metadata as db_query'
                );

            if ($request->filled('dateStart')) {
                $query->where('created_at', '>=', $request->input('dateStart'));
            }
            if ($request->filled('dateEnd')) {
                $query->where('created_at', '<=', $request->input('dateEnd'));
            }

            return $query->orderByDesc('created_at')->paginate((int) $request->input('limit', 25));
        } catch (\Exception $e) {
            return collect();
        }
    }
}
