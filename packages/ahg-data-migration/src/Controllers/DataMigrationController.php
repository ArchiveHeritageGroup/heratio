<?php

namespace AhgDataMigration\Controllers;

use AhgDataMigration\Services\DataMigrationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataMigrationController extends Controller
{
    private DataMigrationService $service;

    public function __construct(DataMigrationService $service)
    {
        $this->service = $service;
    }

    /**
     * Dashboard: show saved mappings and recent jobs.
     */
    public function index()
    {
        $mappings = $this->service->getSavedMappings();
        $jobs = $this->service->getJobs(20);

        return view('ahg-data-migration::index', [
            'mappings' => $mappings,
            'jobs'     => $jobs,
        ]);
    }

    /**
     * GET: show upload form. POST: store file and redirect to map.
     */
    public function upload(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'file'        => 'required|file|mimes:csv,txt,xml|max:102400',
                'target_type' => 'required|in:informationObject,actor,accession,repository',
                'import_type' => 'required|in:create,update,replace',
            ]);

            $file = $request->file('file');
            $storagePath = storage_path('app/data-migration');
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move($storagePath, $filename);
            $fullPath = $storagePath . '/' . $filename;

            session([
                'dm_file_path'   => $fullPath,
                'dm_file_name'   => $file->getClientOriginalName(),
                'dm_target_type' => $request->input('target_type'),
                'dm_import_type' => $request->input('import_type'),
            ]);

            return redirect()->route('data-migration.map');
        }

        return view('ahg-data-migration::upload');
    }

    /**
     * Show field mapping UI.
     */
    public function map(Request $request)
    {
        $filePath = session('dm_file_path');
        $targetType = session('dm_target_type', 'informationObject');

        if (!$filePath || !file_exists($filePath)) {
            return redirect()->route('data-migration.upload')
                ->with('error', 'No file uploaded. Please upload a CSV file first.');
        }

        $csvData = $this->service->parseCSV($filePath, 5);
        $targetFields = $this->service->getTargetFields($targetType);
        $savedMappings = $this->service->getSavedMappings();

        // Filter saved mappings to only show ones for this target type
        $relevantMappings = array_filter($savedMappings, fn ($m) => $m['target_type'] === $targetType);

        return view('ahg-data-migration::map', [
            'sourceColumns'    => $csvData['headers'],
            'previewRows'      => $csvData['rows'],
            'totalRows'        => $csvData['totalRows'],
            'targetFields'     => $targetFields,
            'targetType'       => $targetType,
            'savedMappings'    => array_values($relevantMappings),
            'fileName'         => session('dm_file_name', ''),
        ]);
    }

    /**
     * AJAX: save a mapping to the database.
     */
    public function saveMapping(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'target_type'    => 'required|string|max:100',
            'category'       => 'nullable|string|max:100',
            'field_mappings' => 'required|array',
        ]);

        $id = $this->service->saveMapping([
            'id'             => $request->input('mapping_id'),
            'name'           => $request->input('name'),
            'target_type'    => $request->input('target_type'),
            'category'       => $request->input('category', 'Custom'),
            'field_mappings' => $request->input('field_mappings'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'id' => $id]);
        }

        return redirect()->route('data-migration.index')
            ->with('success', 'Mapping saved successfully.');
    }

    /**
     * AJAX: delete a mapping.
     */
    public function deleteMapping(int $id)
    {
        $this->service->deleteMapping($id);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('data-migration.index')
            ->with('success', 'Mapping deleted.');
    }

    /**
     * Show transformed data preview with mapping applied.
     */
    public function preview(Request $request)
    {
        $filePath = session('dm_file_path');
        $targetType = session('dm_target_type', 'informationObject');

        if (!$filePath || !file_exists($filePath)) {
            return redirect()->route('data-migration.upload')
                ->with('error', 'No file uploaded.');
        }

        $mappingJson = $request->input('mapping', '{}');
        $mapping = is_string($mappingJson) ? json_decode($mappingJson, true) : $mappingJson;
        if (!$mapping) {
            $mapping = [];
        }

        $csvData = $this->service->parseCSV($filePath, 10);
        $targetFields = $this->service->getTargetFields($targetType);

        // Apply mapping to preview rows
        $transformedRows = [];
        foreach ($csvData['rows'] as $row) {
            $transformed = [];
            foreach ($mapping as $sourceCol => $targetField) {
                if (!empty($targetField) && isset($row[$sourceCol])) {
                    $label = $targetFields[$targetField] ?? $targetField;
                    $transformed[$label] = $row[$sourceCol];
                }
            }
            if (!empty($transformed)) {
                $transformedRows[] = $transformed;
            }
        }

        // Get target column headers from the transformed rows
        $targetHeaders = [];
        if (!empty($transformedRows)) {
            $targetHeaders = array_keys($transformedRows[0]);
        }

        return view('ahg-data-migration::preview', [
            'transformedRows' => $transformedRows,
            'targetHeaders'   => $targetHeaders,
            'totalRows'       => $csvData['totalRows'],
            'mapping'         => $mapping,
            'targetType'      => $targetType,
        ]);
    }

    /**
     * POST: create job, execute import, redirect to job status.
     */
    public function execute(Request $request)
    {
        $filePath = session('dm_file_path');
        $targetType = session('dm_target_type', 'informationObject');
        $importType = session('dm_import_type', 'create');

        if (!$filePath || !file_exists($filePath)) {
            return redirect()->route('data-migration.upload')
                ->with('error', 'No file uploaded.');
        }

        $mappingJson = $request->input('mapping', '{}');
        $mapping = is_string($mappingJson) ? json_decode($mappingJson, true) : $mappingJson;
        if (!$mapping) {
            return redirect()->route('data-migration.map')
                ->with('error', 'No field mapping provided.');
        }

        $csvData = $this->service->parseCSV($filePath, 0);

        $jobId = $this->service->createJob([
            'name'             => $request->input('name', 'CSV Import ' . now()->format('Y-m-d H:i:s')),
            'target_type'      => $targetType,
            'source_file'      => $filePath,
            'source_format'    => 'csv',
            'mapping_id'       => $request->input('mapping_id'),
            'mapping_snapshot' => $mapping,
            'import_options'   => [
                'import_type' => $importType,
                'culture'     => app()->getLocale(),
            ],
            'total_records'    => $csvData['totalRows'],
        ]);

        $result = $this->service->executeImport($jobId);

        session([
            'dm_import_result' => $result,
            'dm_job_id'        => $jobId,
        ]);

        return redirect()->route('data-migration.import-results');
    }

    /**
     * List all migration jobs.
     */
    public function jobs()
    {
        $jobs = $this->service->getJobs(100);

        return view('ahg-data-migration::jobs', [
            'jobs' => $jobs,
        ]);
    }

    /**
     * Show single job with progress details.
     */
    public function jobStatus(int $id)
    {
        $job = $this->service->getJob($id);

        if (!$job) {
            abort(404);
        }

        $progressPercent = 0;
        if ($job['total_records'] > 0) {
            $progressPercent = min(100, round(($job['processed_records'] / $job['total_records']) * 100));
        }

        return view('ahg-data-migration::job-status', [
            'job'             => $job,
            'progressPercent' => $progressPercent,
        ]);
    }

    /**
     * GET: show export form. With ?export=csv: stream CSV.
     */
    public function batchExport(Request $request)
    {
        if ($request->has('export') && $request->input('export') === 'csv') {
            $request->validate([
                'entity_type' => 'required|in:informationObject,actor,repository,accession,donor,physicalObject',
            ]);

            $filters = [];
            if ($request->filled('date_from')) {
                $filters['date_from'] = $request->input('date_from');
            }
            if ($request->filled('date_to')) {
                $filters['date_to'] = $request->input('date_to');
            }

            return $this->service->batchExportCsv(
                $request->input('entity_type'),
                $filters
            );
        }

        // Show export form with record counts
        $culture = app()->getLocale();
        $counts = [
            'informationObject' => DB::table('information_object')->where('id', '>', 1)->count(),
            'actor'             => DB::table('object')->where('class_name', 'QubitActor')->count(),
            'repository'        => DB::table('repository')->count(),
            'accession'         => DB::table('accession')->count(),
            'donor'             => DB::table('donor')->count(),
            'physicalObject'    => DB::table('physical_object')->count(),
        ];

        return view('ahg-data-migration::batch-export', [
            'counts' => $counts,
        ]);
    }

    /**
     * Show import results from session.
     */
    public function importResults()
    {
        $result = session('dm_import_result', []);
        $jobId = session('dm_job_id');
        $job = $jobId ? $this->service->getJob($jobId) : null;

        return view('ahg-data-migration::import-results', [
            'result' => $result,
            'job'    => $job,
        ]);
    }
}
