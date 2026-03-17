<?php

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

        return view('ahg-reports::dashboard', compact('stats', 'enabledPlugins', 'hasPlugin'));
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
                ['ID', 'Identifier', 'Title', 'Level', 'Status', 'Created', 'Updated'],
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
            ->leftJoin('repository_i18n as ri', function ($join) use ($culture) {
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
}
