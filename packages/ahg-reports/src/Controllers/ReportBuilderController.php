<?php

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
