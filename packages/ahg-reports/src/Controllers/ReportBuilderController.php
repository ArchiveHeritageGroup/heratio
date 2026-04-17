<?php

/**
 * ReportBuilderController - Controller for Heratio
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



namespace AhgReports\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportBuilderController extends Controller
{
    /**
     * Report Builder dashboard — lists all custom reports grouped by category.
     */
    public function index()
    {
        $reports = collect();
        $statistics = ['total_reports' => 0, 'by_source' => []];
        $groupedReports = [];

        try {
            if (Schema::hasTable('ahg_report')) {
                $reports = DB::table('ahg_report')
                    ->orderBy('category')
                    ->orderByDesc('updated_at')
                    ->get();

                $statistics['total_reports'] = $reports->count();
                $statistics['by_source'] = $reports->groupBy('data_source')
                    ->map->count()
                    ->toArray();

                $groupedReports = $reports->groupBy('category')->toArray();
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        return view('ahg-reports::report-builder.index', compact('reports', 'statistics', 'groupedReports'));
    }

    /**
     * Create report form.
     */
    public function create()
    {
        return view('ahg-reports::report-builder.create');
    }

    /**
     * Store a new report.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'data_source' => 'required|string',
        ]);

        try {
            if (Schema::hasTable('ahg_report')) {
                DB::table('ahg_report')->insert([
                    'name' => $request->input('name'),
                    'description' => $request->input('description'),
                    'data_source' => $request->input('data_source'),
                    'category' => $request->input('category', 'General'),
                    'status' => 'draft',
                    'is_public' => $request->input('visibility') === 'public' ? 1 : 0,
                    'is_shared' => $request->input('visibility') === 'shared' ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            return redirect()->route('reports.builder.index')
                ->with('error', 'Failed to create report: ' . $e->getMessage());
        }

        return redirect()->route('reports.builder.index')
            ->with('success', 'Report created successfully.');
    }

    /**
     * Preview a report.
     */
    public function preview(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.preview', compact('report'));
    }

    /**
     * Edit a report.
     */
    public function edit(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.edit', compact('report'));
    }

    /**
     * Update a report.
     */
    public function update(Request $request, int $id)
    {
        $request->validate(['name' => 'required|string|max:255']);

        try {
            if (Schema::hasTable('ahg_report')) {
                DB::table('ahg_report')->where('id', $id)->update([
                    'name' => $request->input('name'),
                    'description' => $request->input('description'),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // ignore
        }

        return redirect()->route('reports.builder.edit', $id)
            ->with('success', 'Report updated successfully.');
    }

    /**
     * View a report.
     */
    public function view(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.view', compact('report'));
    }

    /**
     * Query builder for a report.
     */
    public function query(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.query', compact('report'));
    }

    /**
     * Schedule a report.
     */
    public function schedule(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.schedule', compact('report'));
    }

    /**
     * Share a report.
     */
    public function share(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.share', compact('report'));
    }

    /**
     * Report execution history.
     */
    public function history(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.history', compact('report'));
    }

    /**
     * Report widget configuration.
     */
    public function widget(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.widget', compact('report'));
    }

    /**
     * Report templates listing.
     */
    public function templates()
    {
        $report = null;
        return view('ahg-reports::report-builder.templates', compact('report'));
    }

    /**
     * Archived reports listing.
     */
    public function archive()
    {
        $report = null;
        return view('ahg-reports::report-builder.archive', compact('report'));
    }

    /**
     * Edit a report template.
     */
    public function editTemplate(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.edit-template', compact('report'));
    }

    /**
     * Preview a report template.
     */
    public function previewTemplate(int $id)
    {
        $report = $this->getReport($id);
        return view('ahg-reports::report-builder.preview-template', compact('report'));
    }

    /**
     * Delete a report template.
     */
    public function deleteTemplate(int $id)
    {
        try {
            if (Schema::hasTable('ahg_report')) {
                DB::table('ahg_report')->where('id', $id)->delete();
            }
        } catch (\Exception $e) {
            // ignore
        }

        return redirect()->route('reports.builder.templates')
            ->with('success', 'Template deleted successfully.');
    }

    // ── Report Builder API Actions (AJAX) ─────────────────────────

    /**
     * API: Delete a report.
     */
    public function apiDelete(Request $request, int $id)
    {
        try {
            if (Schema::hasTable('ahg_report')) {
                DB::table('ahg_report')->where('id', $id)->delete();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Save report (create or update).
     */
    public function apiSave(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        try {
            if (!Schema::hasTable('ahg_report')) {
                return response()->json(['success' => false, 'error' => 'Report table not available']);
            }

            $data = [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'data_source' => $request->input('data_source'),
                'category' => $request->input('category', 'General'),
                'query_definition' => $request->input('query_definition'),
                'layout_config' => $request->input('layout_config'),
                'chart_config' => $request->input('chart_config'),
                'filters' => $request->input('filters'),
                'is_public' => $request->boolean('is_public') ? 1 : 0,
                'is_shared' => $request->boolean('is_shared') ? 1 : 0,
                'updated_at' => now(),
            ];

            $id = $request->input('id');
            if ($id) {
                DB::table('ahg_report')->where('id', $id)->update($data);
            } else {
                $data['status'] = 'draft';
                $data['created_at'] = now();
                $data['created_by'] = auth()->id();
                $id = DB::table('ahg_report')->insertGetId($data);
            }

            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get report data for rendering.
     */
    public function apiData(Request $request, int $id)
    {
        $report = $this->getReport($id);
        if (!$report) {
            return response()->json(['success' => false, 'error' => 'Report not found']);
        }

        $data = [];
        try {
            $queryDef = json_decode($report->query_definition ?? '{}', true);
            if (!empty($queryDef['table'])) {
                $query = DB::table($queryDef['table']);
                if (!empty($queryDef['columns'])) {
                    $query->select($queryDef['columns']);
                }
                if (!empty($queryDef['where'])) {
                    foreach ($queryDef['where'] as $condition) {
                        $query->where($condition['column'], $condition['operator'] ?? '=', $condition['value']);
                    }
                }
                $limit = $queryDef['limit'] ?? 1000;
                $data = $query->limit($limit)->get()->toArray();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'data' => $data, 'count' => count($data)]);
    }

    /**
     * API: Get available columns for a data source.
     */
    public function apiColumns(Request $request)
    {
        $table = $request->input('table');
        if (!$table) {
            return response()->json(['success' => false, 'error' => 'Table name required']);
        }

        try {
            if (!Schema::hasTable($table)) {
                return response()->json(['success' => false, 'error' => 'Table not found']);
            }
            $columns = Schema::getColumnListing($table);
            return response()->json(['success' => true, 'columns' => $columns]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get available tables for query builder.
     */
    public function apiQueryTables(Request $request)
    {
        $allowedTables = [
            'information_object', 'information_object_i18n', 'actor', 'actor_i18n',
            'accession', 'accession_i18n', 'repository', 'repository_i18n',
            'donor', 'term', 'term_i18n', 'physical_object', 'physical_object_i18n',
            'digital_object', 'event', 'relation', 'status', 'note', 'note_i18n',
            'property', 'property_i18n', 'contact_information',
        ];

        return response()->json(['success' => true, 'tables' => $allowedTables]);
    }

    /**
     * API: Validate a query definition.
     */
    public function apiQueryValidate(Request $request)
    {
        $queryDef = $request->input('query');
        if (!$queryDef) {
            return response()->json(['valid' => false, 'error' => 'No query provided']);
        }

        $parsed = is_string($queryDef) ? json_decode($queryDef, true) : $queryDef;
        if (!$parsed || !isset($parsed['table'])) {
            return response()->json(['valid' => false, 'error' => 'Invalid query format - table required']);
        }

        if (!Schema::hasTable($parsed['table'])) {
            return response()->json(['valid' => false, 'error' => 'Table does not exist: ' . $parsed['table']]);
        }

        return response()->json(['valid' => true]);
    }

    /**
     * API: Execute a query and return results.
     */
    public function apiQueryExecute(Request $request)
    {
        $queryDef = $request->input('query');
        $parsed = is_string($queryDef) ? json_decode($queryDef, true) : $queryDef;

        if (!$parsed || !isset($parsed['table'])) {
            return response()->json(['success' => false, 'error' => 'Invalid query']);
        }

        try {
            $query = DB::table($parsed['table']);
            if (!empty($parsed['columns'])) {
                $query->select($parsed['columns']);
            }
            if (!empty($parsed['joins'])) {
                foreach ($parsed['joins'] as $join) {
                    $query->leftJoin($join['table'], $join['first'], '=', $join['second']);
                }
            }
            if (!empty($parsed['where'])) {
                foreach ($parsed['where'] as $condition) {
                    $query->where($condition['column'], $condition['operator'] ?? '=', $condition['value']);
                }
            }
            if (!empty($parsed['orderBy'])) {
                $query->orderBy($parsed['orderBy'], $parsed['orderDir'] ?? 'asc');
            }

            $limit = min($parsed['limit'] ?? 100, 10000);
            $results = $query->limit($limit)->get();

            return response()->json(['success' => true, 'data' => $results, 'count' => $results->count()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get query column definitions with relationships.
     */
    public function apiQueryColumns(Request $request)
    {
        $table = $request->input('table');
        if (!$table || !Schema::hasTable($table)) {
            return response()->json(['success' => false, 'error' => 'Table not found']);
        }

        try {
            $columns = Schema::getColumnListing($table);
            $details = [];
            foreach ($columns as $col) {
                $details[] = ['name' => $col, 'type' => Schema::getColumnType($table, $col)];
            }
            return response()->json(['success' => true, 'columns' => $details]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get query relationships between tables.
     */
    public function apiQueryRelationships(Request $request)
    {
        // Standard AtoM relationships
        $relationships = [
            ['from' => 'information_object', 'to' => 'information_object_i18n', 'key' => 'id'],
            ['from' => 'actor', 'to' => 'actor_i18n', 'key' => 'id'],
            ['from' => 'accession', 'to' => 'accession_i18n', 'key' => 'id'],
            ['from' => 'repository', 'to' => 'repository_i18n', 'key' => 'id'],
            ['from' => 'term', 'to' => 'term_i18n', 'key' => 'id'],
            ['from' => 'information_object', 'to' => 'digital_object', 'key' => 'information_object_id'],
            ['from' => 'information_object', 'to' => 'event', 'key' => 'information_object_id'],
            ['from' => 'information_object', 'to' => 'status', 'key' => 'object_id'],
            ['from' => 'object', 'to' => 'slug', 'key' => 'object_id'],
        ];

        return response()->json(['success' => true, 'relationships' => $relationships]);
    }

    /**
     * API: Save a query for a report.
     */
    public function apiQuerySave(Request $request, int $id)
    {
        try {
            if (Schema::hasTable('ahg_report')) {
                DB::table('ahg_report')->where('id', $id)->update([
                    'query_definition' => json_encode($request->input('query')),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Change report status.
     */
    public function apiStatusChange(Request $request, int $id)
    {
        $status = $request->input('status');
        $validStatuses = ['draft', 'active', 'archived', 'published'];

        if (!in_array($status, $validStatuses)) {
            return response()->json(['success' => false, 'error' => 'Invalid status']);
        }

        try {
            if (Schema::hasTable('ahg_report')) {
                DB::table('ahg_report')->where('id', $id)->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Get chart data for a report.
     */
    public function apiChartData(Request $request, int $id)
    {
        $report = $this->getReport($id);
        if (!$report) {
            return response()->json(['success' => false, 'error' => 'Report not found']);
        }

        $chartConfig = json_decode($report->chart_config ?? '{}', true);
        $queryDef = json_decode($report->query_definition ?? '{}', true);

        $data = [];
        try {
            if (!empty($queryDef['table'])) {
                $query = DB::table($queryDef['table']);
                if (!empty($chartConfig['groupBy'])) {
                    $query->select(DB::raw($chartConfig['groupBy'] . ' as label, COUNT(*) as value'))
                          ->groupBy($chartConfig['groupBy']);
                }
                $data = $query->limit(100)->get()->toArray();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'chartData' => $data]);
    }

    /**
     * API: Search entities for linking.
     */
    public function apiEntitySearch(Request $request)
    {
        $query = $request->input('query', '');
        $type = $request->input('type', 'information_object');

        $results = [];
        if (strlen($query) < 2) {
            return response()->json(['success' => true, 'results' => $results]);
        }

        try {
            $culture = app()->getLocale();
            if ($type === 'information_object') {
                $results = DB::table('information_object as io')
                    ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                        $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                    })
                    ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                    ->where(function ($q) use ($query) {
                        $q->where('io.identifier', 'LIKE', "%{$query}%")
                          ->orWhere('ioi.title', 'LIKE', "%{$query}%");
                    })
                    ->select(['io.id', 'io.identifier', 'ioi.title', 's.slug'])
                    ->limit(20)
                    ->get()
                    ->toArray();
            } elseif ($type === 'actor') {
                $results = DB::table('actor as a')
                    ->leftJoin('actor_i18n as ai', function ($join) use ($culture) {
                        $join->on('a.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
                    })
                    ->where('ai.authorized_form_of_name', 'LIKE', "%{$query}%")
                    ->select(['a.id', 'ai.authorized_form_of_name as name'])
                    ->limit(20)
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * API: Save report section.
     */
    public function apiSectionSave(Request $request, int $id)
    {
        try {
            if (Schema::hasTable('ahg_report_section')) {
                $sectionId = $request->input('section_id');
                $data = [
                    'report_id' => $id,
                    'title' => $request->input('title'),
                    'section_type' => $request->input('section_type', 'text'),
                    'content' => $request->input('content'),
                    'sort_order' => (int) $request->input('sort_order', 0),
                    'config' => json_encode($request->input('config', [])),
                    'updated_at' => now(),
                ];

                if ($sectionId) {
                    DB::table('ahg_report_section')->where('id', $sectionId)->update($data);
                } else {
                    $data['created_at'] = now();
                    $sectionId = DB::table('ahg_report_section')->insertGetId($data);
                }

                return response()->json(['success' => true, 'section_id' => $sectionId]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Section table not available']);
    }

    /**
     * API: Delete report section.
     */
    public function apiSectionDelete(Request $request, int $id, int $sectionId)
    {
        try {
            if (Schema::hasTable('ahg_report_section')) {
                DB::table('ahg_report_section')
                    ->where('id', $sectionId)
                    ->where('report_id', $id)
                    ->delete();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Reorder report sections.
     */
    public function apiSectionReorder(Request $request, int $id)
    {
        $order = $request->input('order', []);

        try {
            if (Schema::hasTable('ahg_report_section')) {
                foreach ($order as $i => $sectionId) {
                    DB::table('ahg_report_section')
                        ->where('id', $sectionId)
                        ->where('report_id', $id)
                        ->update(['sort_order' => $i]);
                }
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Create a snapshot of a report.
     */
    public function apiSnapshot(Request $request, int $id)
    {
        try {
            $report = $this->getReport($id);
            if (!$report) {
                return response()->json(['success' => false, 'error' => 'Report not found']);
            }

            if (Schema::hasTable('ahg_report_snapshot')) {
                $snapshotId = DB::table('ahg_report_snapshot')->insertGetId([
                    'report_id' => $id,
                    'snapshot_data' => json_encode($report),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                ]);

                return response()->json(['success' => true, 'snapshot_id' => $snapshotId]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Snapshot table not available']);
    }

    /**
     * API: Get report versions.
     */
    public function apiVersions(Request $request, int $id)
    {
        $versions = [];
        try {
            if (Schema::hasTable('ahg_report_version')) {
                $versions = DB::table('ahg_report_version')
                    ->where('report_id', $id)
                    ->orderByDesc('created_at')
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            // ignore
        }

        return response()->json(['success' => true, 'versions' => $versions]);
    }

    /**
     * API: Create a new version of a report.
     */
    public function apiVersionCreate(Request $request, int $id)
    {
        try {
            $report = $this->getReport($id);
            if (!$report) {
                return response()->json(['success' => false, 'error' => 'Report not found']);
            }

            if (Schema::hasTable('ahg_report_version')) {
                $versionId = DB::table('ahg_report_version')->insertGetId([
                    'report_id' => $id,
                    'version_data' => json_encode($report),
                    'version_label' => $request->input('label', 'v' . now()->format('Ymd-His')),
                    'notes' => $request->input('notes'),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                ]);

                return response()->json(['success' => true, 'version_id' => $versionId]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Version table not available']);
    }

    /**
     * API: Restore a report version.
     */
    public function apiVersionRestore(Request $request, int $id, int $versionId)
    {
        try {
            if (Schema::hasTable('ahg_report_version')) {
                $version = DB::table('ahg_report_version')
                    ->where('id', $versionId)
                    ->where('report_id', $id)
                    ->first();

                if (!$version) {
                    return response()->json(['success' => false, 'error' => 'Version not found']);
                }

                $versionData = json_decode($version->version_data, true);
                if ($versionData) {
                    $updateData = array_intersect_key($versionData, array_flip([
                        'name', 'description', 'data_source', 'category',
                        'query_definition', 'layout_config', 'chart_config', 'filters',
                    ]));
                    $updateData['updated_at'] = now();

                    DB::table('ahg_report')->where('id', $id)->update($updateData);
                }

                return response()->json(['success' => true]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Version table not available']);
    }

    /**
     * API: Create share link for a report.
     */
    public function apiShareCreate(Request $request, int $id)
    {
        try {
            if (Schema::hasTable('ahg_report_share')) {
                $token = \Str::random(32);
                $shareId = DB::table('ahg_report_share')->insertGetId([
                    'report_id' => $id,
                    'token' => $token,
                    'expires_at' => $request->input('expires_at'),
                    'is_active' => 1,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'share_id' => $shareId,
                    'token' => $token,
                    'url' => url('/admin/reports/builder/shared/' . $token),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Share table not available']);
    }

    /**
     * API: Deactivate a share link.
     */
    public function apiShareDeactivate(Request $request, int $id, int $shareId)
    {
        try {
            if (Schema::hasTable('ahg_report_share')) {
                DB::table('ahg_report_share')
                    ->where('id', $shareId)
                    ->where('report_id', $id)
                    ->update(['is_active' => 0]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Save a report template.
     */
    public function apiTemplateSave(Request $request)
    {
        try {
            if (Schema::hasTable('ahg_report_template')) {
                $data = [
                    'name' => $request->input('name'),
                    'description' => $request->input('description'),
                    'template_data' => json_encode($request->input('template_data', [])),
                    'category' => $request->input('category', 'General'),
                    'updated_at' => now(),
                ];

                $templateId = $request->input('template_id');
                if ($templateId) {
                    DB::table('ahg_report_template')->where('id', $templateId)->update($data);
                } else {
                    $data['created_at'] = now();
                    $data['created_by'] = auth()->id();
                    $templateId = DB::table('ahg_report_template')->insertGetId($data);
                }

                return response()->json(['success' => true, 'template_id' => $templateId]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Template table not available']);
    }

    /**
     * API: Delete a report template.
     */
    public function apiTemplateDelete(Request $request, int $templateId)
    {
        try {
            if (Schema::hasTable('ahg_report_template')) {
                DB::table('ahg_report_template')->where('id', $templateId)->delete();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Apply a template to a report.
     */
    public function apiTemplateApply(Request $request, int $id)
    {
        $templateId = $request->input('template_id');

        try {
            if (Schema::hasTable('ahg_report_template') && Schema::hasTable('ahg_report')) {
                $template = DB::table('ahg_report_template')->where('id', $templateId)->first();
                if (!$template) {
                    return response()->json(['success' => false, 'error' => 'Template not found']);
                }

                $templateData = json_decode($template->template_data, true) ?? [];
                $updateData = array_intersect_key($templateData, array_flip([
                    'layout_config', 'chart_config', 'filters',
                ]));
                $updateData['updated_at'] = now();

                DB::table('ahg_report')->where('id', $id)->update($updateData);

                return response()->json(['success' => true]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Tables not available']);
    }

    /**
     * API: Get widgets for a report.
     */
    public function apiWidgets(Request $request, int $id)
    {
        $widgets = [];
        try {
            if (Schema::hasTable('ahg_report_widget')) {
                $widgets = DB::table('ahg_report_widget')
                    ->where('report_id', $id)
                    ->orderBy('sort_order')
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            // ignore
        }

        return response()->json(['success' => true, 'widgets' => $widgets]);
    }

    /**
     * API: Save a widget.
     */
    public function apiWidgetSave(Request $request, int $id)
    {
        try {
            if (Schema::hasTable('ahg_report_widget')) {
                $data = [
                    'report_id' => $id,
                    'widget_type' => $request->input('widget_type', 'chart'),
                    'title' => $request->input('title'),
                    'config' => json_encode($request->input('config', [])),
                    'sort_order' => (int) $request->input('sort_order', 0),
                    'updated_at' => now(),
                ];

                $widgetId = $request->input('widget_id');
                if ($widgetId) {
                    DB::table('ahg_report_widget')->where('id', $widgetId)->update($data);
                } else {
                    $data['created_at'] = now();
                    $widgetId = DB::table('ahg_report_widget')->insertGetId($data);
                }

                return response()->json(['success' => true, 'widget_id' => $widgetId]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Widget table not available']);
    }

    /**
     * API: Delete a widget.
     */
    public function apiWidgetDelete(Request $request, int $id, int $widgetId)
    {
        try {
            if (Schema::hasTable('ahg_report_widget')) {
                DB::table('ahg_report_widget')
                    ->where('id', $widgetId)
                    ->where('report_id', $id)
                    ->delete();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Add a comment to a report.
     */
    public function apiComment(Request $request, int $id)
    {
        try {
            if (Schema::hasTable('ahg_report_comment')) {
                $commentId = DB::table('ahg_report_comment')->insertGetId([
                    'report_id' => $id,
                    'user_id' => auth()->id(),
                    'comment' => $request->input('comment'),
                    'created_at' => now(),
                ]);

                return response()->json(['success' => true, 'comment_id' => $commentId]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Comment table not available']);
    }

    /**
     * API: Upload attachment for a report.
     */
    public function apiAttachmentUpload(Request $request, int $id)
    {
        $request->validate(['file' => 'required|file|max:10240']);

        try {
            $file = $request->file('file');
            $path = $file->store('report-attachments/' . $id, 'public');

            if (Schema::hasTable('ahg_report_attachment')) {
                $attachmentId = DB::table('ahg_report_attachment')->insertGetId([
                    'report_id' => $id,
                    'filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => auth()->id(),
                    'created_at' => now(),
                ]);

                return response()->json(['success' => true, 'attachment_id' => $attachmentId, 'path' => $path]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Attachment table not available']);
    }

    /**
     * API: Get attachments for a report.
     */
    public function apiAttachments(Request $request, int $id)
    {
        $attachments = [];
        try {
            if (Schema::hasTable('ahg_report_attachment')) {
                $attachments = DB::table('ahg_report_attachment')
                    ->where('report_id', $id)
                    ->orderByDesc('created_at')
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            // ignore
        }

        return response()->json(['success' => true, 'attachments' => $attachments]);
    }

    /**
     * API: Delete an attachment.
     */
    public function apiAttachmentDelete(Request $request, int $id, int $attachmentId)
    {
        try {
            if (Schema::hasTable('ahg_report_attachment')) {
                $attachment = DB::table('ahg_report_attachment')
                    ->where('id', $attachmentId)
                    ->where('report_id', $id)
                    ->first();

                if ($attachment && \Storage::disk('public')->exists($attachment->file_path)) {
                    \Storage::disk('public')->delete($attachment->file_path);
                }

                DB::table('ahg_report_attachment')
                    ->where('id', $attachmentId)
                    ->where('report_id', $id)
                    ->delete();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Save a link (bookmark / reference) for a report.
     */
    public function apiLinkSave(Request $request, int $id)
    {
        try {
            if (Schema::hasTable('ahg_report_link')) {
                $linkId = DB::table('ahg_report_link')->insertGetId([
                    'report_id' => $id,
                    'url' => $request->input('url'),
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'created_at' => now(),
                ]);

                return response()->json(['success' => true, 'link_id' => $linkId]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'error' => 'Link table not available']);
    }

    /**
     * API: Delete a link.
     */
    public function apiLinkDelete(Request $request, int $id, int $linkId)
    {
        try {
            if (Schema::hasTable('ahg_report_link')) {
                DB::table('ahg_report_link')
                    ->where('id', $linkId)
                    ->where('report_id', $id)
                    ->delete();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * API: Fetch Open Graph data for a URL.
     */
    public function apiOgFetch(Request $request)
    {
        $url = $request->input('url');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'error' => 'Invalid URL']);
        }

        try {
            $html = @file_get_contents($url);
            if (!$html) {
                return response()->json(['success' => false, 'error' => 'Unable to fetch URL']);
            }

            $title = '';
            $description = '';
            $image = '';

            if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
                $title = html_entity_decode($m[1]);
            }
            if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
                $title = $m[1];
            }
            if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
                $description = $m[1];
            }
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
                $image = $m[1];
            }

            return response()->json([
                'success' => true,
                'title' => $title,
                'description' => $description,
                'image' => $image,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * View a shared report (public access via token).
     */
    public function sharedView(string $token)
    {
        $share = null;
        $report = null;

        try {
            if (Schema::hasTable('ahg_report_share')) {
                $share = DB::table('ahg_report_share')
                    ->where('token', $token)
                    ->where('is_active', 1)
                    ->first();

                if ($share) {
                    if ($share->expires_at && now()->gt($share->expires_at)) {
                        abort(410, 'This shared link has expired.');
                    }
                    $report = $this->getReport($share->report_id);
                }
            }
        } catch (\Exception $e) {
            // ignore
        }

        if (!$report) {
            abort(404, 'Report not found');
        }

        return view('ahg-reports::report-builder.shared-view', compact('report', 'share'));
    }

    /**
     * Clone an existing report.
     */
    public function cloneReport(int $id)
    {
        $report = $this->getReport($id);
        if (!$report) {
            abort(404, 'Report not found');
        }

        try {
            if (Schema::hasTable('ahg_report')) {
                $newId = DB::table('ahg_report')->insertGetId([
                    'name' => $report->name . ' (Copy)',
                    'description' => $report->description,
                    'data_source' => $report->data_source,
                    'category' => $report->category,
                    'query_definition' => $report->query_definition,
                    'layout_config' => $report->layout_config,
                    'chart_config' => $report->chart_config,
                    'filters' => $report->filters,
                    'status' => 'draft',
                    'is_public' => 0,
                    'is_shared' => 0,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return redirect()->route('reports.builder.edit', $newId)
                    ->with('success', 'Report cloned successfully.');
            }
        } catch (\Exception $e) {
            return redirect()->route('reports.builder.index')
                ->with('error', 'Failed to clone report: ' . $e->getMessage());
        }

        return redirect()->route('reports.builder.index');
    }

    /**
     * Export a report (download).
     */
    public function export(Request $request, int $id)
    {
        $report = $this->getReport($id);
        if (!$report) {
            abort(404, 'Report not found');
        }

        $format = $request->input('format', 'json');

        if ($format === 'json') {
            return response()->json($report)
                ->header('Content-Disposition', 'attachment; filename="report-' . $id . '.json"');
        }

        // CSV export
        $queryDef = json_decode($report->query_definition ?? '{}', true);
        $data = [];
        try {
            if (!empty($queryDef['table'])) {
                $query = DB::table($queryDef['table']);
                if (!empty($queryDef['columns'])) {
                    $query->select($queryDef['columns']);
                }
                $data = $query->limit(10000)->get()->toArray();
            }
        } catch (\Exception $e) {
            abort(500, 'Error generating export: ' . $e->getMessage());
        }

        if (empty($data)) {
            abort(404, 'No data to export');
        }

        $headers = array_keys((array) $data[0]);
        $csv = implode(',', $headers) . "\n";
        foreach ($data as $row) {
            $values = array_map(function ($v) {
                return '"' . str_replace('"', '""', $v ?? '') . '"';
            }, array_values((array) $row));
            $csv .= implode(',', $values) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="report-' . $id . '.csv"',
        ]);
    }

    /**
     * Schedule management — delete a schedule entry.
     */
    public function scheduleDelete(Request $request, int $id)
    {
        try {
            if (Schema::hasTable('ahg_report_schedule')) {
                DB::table('ahg_report_schedule')
                    ->where('report_id', $id)
                    ->delete();
            }
        } catch (\Exception $e) {
            return redirect()->route('reports.builder.schedule', $id)
                ->with('error', 'Failed to delete schedule: ' . $e->getMessage());
        }

        return redirect()->route('reports.builder.schedule', $id)
            ->with('success', 'Schedule removed.');
    }

    /**
     * Store/update a schedule for a report (POST).
     */
    public function scheduleStore(Request $request, int $id)
    {
        $request->validate([
            'frequency' => 'required|string|in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'time' => 'required|string',
            'email_recipients' => 'nullable|string',
            'format' => 'nullable|string|in:csv,pdf,xlsx',
        ]);

        try {
            if (Schema::hasTable('ahg_report_schedule')) {
                $data = [
                    'report_id' => $id,
                    'frequency' => $request->input('frequency'),
                    'day_of_week' => $request->input('day_of_week'),
                    'day_of_month' => $request->input('day_of_month'),
                    'time' => $request->input('time'),
                    'email_recipients' => $request->input('email_recipients'),
                    'format' => $request->input('format', 'csv'),
                    'is_active' => 1,
                    'updated_at' => now(),
                ];

                $existing = DB::table('ahg_report_schedule')->where('report_id', $id)->first();
                if ($existing) {
                    DB::table('ahg_report_schedule')->where('report_id', $id)->update($data);
                } else {
                    $data['created_at'] = now();
                    DB::table('ahg_report_schedule')->insert($data);
                }
            }
        } catch (\Exception $e) {
            return redirect()->route('reports.builder.schedule', $id)
                ->with('error', 'Failed to save schedule: ' . $e->getMessage());
        }

        return redirect()->route('reports.builder.schedule', $id)
            ->with('success', 'Schedule saved.');
    }

    /**
     * Get a single report by ID.
     */
    private function getReport(int $id): ?object
    {
        try {
            if (Schema::hasTable('ahg_report')) {
                return DB::table('ahg_report')->where('id', $id)->first();
            }
        } catch (\Exception $e) {
            // ignore
        }
        return null;
    }
}
