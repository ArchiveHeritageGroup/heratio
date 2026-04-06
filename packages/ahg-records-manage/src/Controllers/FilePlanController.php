<?php

namespace AhgRecordsManage\Controllers;

use AhgRecordsManage\Services\FilePlanService;
use AhgRecordsManage\Services\FilePlanImportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FilePlanController extends Controller
{
    private FilePlanService $filePlanService;
    private FilePlanImportService $importService;

    public function __construct(FilePlanService $filePlanService, FilePlanImportService $importService)
    {
        $this->filePlanService = $filePlanService;
        $this->importService = $importService;
    }

    /**
     * Show interactive file plan tree.
     */
    public function index()
    {
        $tree = $this->filePlanService->getTree();
        $stats = $this->filePlanService->getStats();

        return view('ahg-records::fileplan.index', compact('tree', 'stats'));
    }

    /**
     * Return JSON tree for AJAX rendering.
     */
    public function treeJson()
    {
        $tree = $this->filePlanService->getTree();
        return response()->json($tree);
    }

    /**
     * Show create node form.
     */
    public function create(Request $request)
    {
        $parentNodes = $this->filePlanService->getNodesForDropdown();
        $parentId = $request->query('parent_id');

        return view('ahg-records::fileplan.create', compact('parentNodes', 'parentId'));
    }

    /**
     * Store a new node.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:rm_fileplan_node,id',
            'node_type' => 'required|string|in:plan,series,sub_series,file_group,volume',
            'code' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'disposal_class_id' => 'nullable|integer',
            'retention_period' => 'nullable|string|max:100',
            'disposal_action' => 'nullable|string|max:30',
            'status' => 'nullable|string|in:active,closed,deprecated',
        ]);

        // Check for duplicate code
        $existing = $this->filePlanService->getNodeByCode($validated['code']);
        if ($existing) {
            return redirect()->back()->withInput()->with('error', 'A node with code "' . $validated['code'] . '" already exists.');
        }

        $validated['created_by'] = Auth::id() ?? 1;

        // Calculate depth from parent
        if (!empty($validated['parent_id'])) {
            $parent = $this->filePlanService->getNode($validated['parent_id']);
            $validated['depth'] = $parent ? $parent->depth + 1 : 0;
        }

        $id = $this->filePlanService->createNode($validated);

        return redirect()->route('records.fileplan.show', $id)->with('success', 'File plan node created.');
    }

    /**
     * Show node detail with children and linked records.
     */
    public function show(int $id, Request $request)
    {
        $node = $this->filePlanService->getNode($id);
        if (!$node) {
            abort(404, 'File plan node not found.');
        }

        $breadcrumb = $this->filePlanService->getBreadcrumb($id);
        $children = $this->filePlanService->getChildren($id);
        $page = (int) $request->query('page', 1);
        $records = $this->filePlanService->getRecordsInNode($id, $page);

        return view('ahg-records::fileplan.show', compact('node', 'breadcrumb', 'children', 'records'));
    }

    /**
     * Show edit form.
     */
    public function edit(int $id)
    {
        $node = $this->filePlanService->getNode($id);
        if (!$node) {
            abort(404, 'File plan node not found.');
        }

        $parentNodes = $this->filePlanService->getNodesForDropdown();

        return view('ahg-records::fileplan.edit', compact('node', 'parentNodes'));
    }

    /**
     * Update a node.
     */
    public function update(Request $request, int $id)
    {
        $node = $this->filePlanService->getNode($id);
        if (!$node) {
            abort(404, 'File plan node not found.');
        }

        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:rm_fileplan_node,id',
            'node_type' => 'required|string|in:plan,series,sub_series,file_group,volume',
            'code' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'disposal_class_id' => 'nullable|integer',
            'retention_period' => 'nullable|string|max:100',
            'disposal_action' => 'nullable|string|max:30',
            'status' => 'nullable|string|in:active,closed,deprecated',
        ]);

        // Check for duplicate code (exclude self)
        $existing = $this->filePlanService->getNodeByCode($validated['code']);
        if ($existing && $existing->id !== $id) {
            return redirect()->back()->withInput()->with('error', 'A node with code "' . $validated['code'] . '" already exists.');
        }

        $this->filePlanService->updateNode($id, $validated);

        return redirect()->route('records.fileplan.show', $id)->with('success', 'File plan node updated.');
    }

    /**
     * Delete a node (only if empty).
     */
    public function destroy(int $id)
    {
        $node = $this->filePlanService->getNode($id);
        if (!$node) {
            abort(404, 'File plan node not found.');
        }

        $deleted = $this->filePlanService->deleteNode($id);
        if (!$deleted) {
            return redirect()->route('records.fileplan.show', $id)
                ->with('error', 'Cannot delete node: it has child nodes or linked records.');
        }

        return redirect()->route('records.fileplan.index')->with('success', 'File plan node deleted.');
    }

    /**
     * Move a node to a new parent.
     */
    public function move(Request $request, int $id)
    {
        $request->validate([
            'new_parent_id' => 'required|integer|exists:rm_fileplan_node,id',
        ]);

        $moved = $this->filePlanService->moveNode($id, $request->input('new_parent_id'));
        if (!$moved) {
            return redirect()->route('records.fileplan.show', $id)
                ->with('error', 'Cannot move node to the specified parent.');
        }

        return redirect()->route('records.fileplan.show', $id)->with('success', 'Node moved successfully.');
    }

    /**
     * Step 1: Import form — upload file.
     */
    public function importForm()
    {
        $sessions = $this->importService->getImportSessions(1, 10);

        return view('ahg-records::fileplan.import', compact('sessions'));
    }

    /**
     * Handle file upload and detect columns.
     */
    public function importUpload(Request $request)
    {
        $request->validate([
            'source_type' => 'required|string|in:spreadsheet,directory,xml',
            'department' => 'nullable|string|max:255',
            'agency_code' => 'nullable|string|max:50',
        ]);

        $sourceType = $request->input('source_type');
        $department = $request->input('department');
        $agencyCode = $request->input('agency_code');

        if ($sourceType === 'spreadsheet') {
            $request->validate([
                'import_file' => 'required|file|mimes:xlsx,xls,csv,ods|max:51200',
            ]);

            $file = $request->file('import_file');
            $storedPath = $file->store('fileplan-imports', 'local');
            $fullPath = storage_path('app/private/' . $storedPath);

            // Detect columns
            $spreadsheet = IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            $headers = $rows[1] ?? [];
            $sampleRows = array_slice($rows, 1, 5, true);

            $detectedMapping = $this->importService->detectColumnMapping($headers);

            return view('ahg-records::fileplan.import-map', [
                'headers' => $headers,
                'sampleRows' => $sampleRows,
                'detectedMapping' => $detectedMapping,
                'filePath' => $storedPath,
                'department' => $department,
                'agencyCode' => $agencyCode,
                'sourceType' => $sourceType,
            ]);
        }

        if ($sourceType === 'directory') {
            $request->validate([
                'directory_path' => 'required|string',
            ]);

            $dirPath = $request->input('directory_path');
            if (!is_dir($dirPath)) {
                return redirect()->back()->withInput()->with('error', 'Directory not found: ' . $dirPath);
            }

            $userId = Auth::id() ?? 1;
            $result = $this->importService->importFromDirectory($dirPath, $department, $agencyCode, $userId);

            return redirect()->route('records.fileplan.import.status', $result['session_id'])
                ->with('success', 'Directory import completed.');
        }

        if ($sourceType === 'xml') {
            $request->validate([
                'import_file' => 'required|file|mimes:xml|max:51200',
                'xml_format' => 'required|string|in:generic,ead',
            ]);

            $file = $request->file('import_file');
            $storedPath = $file->store('fileplan-imports', 'local');
            $fullPath = storage_path('app/private/' . $storedPath);

            $userId = Auth::id() ?? 1;
            $format = $request->input('xml_format', 'generic');
            $result = $this->importService->importFromXml($fullPath, $format, $department, $agencyCode, $userId);

            return redirect()->route('records.fileplan.import.status', $result['session_id'])
                ->with('success', 'XML import completed.');
        }

        return redirect()->back()->with('error', 'Unsupported source type.');
    }

    /**
     * Step 2: Show column mapping form.
     */
    public function importMap(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'department' => 'nullable|string',
            'agency_code' => 'nullable|string',
            'mapping' => 'required|array',
        ]);

        $filePath = $request->input('file_path');
        $fullPath = storage_path('app/private/' . $filePath);
        $mapping = $request->input('mapping');
        $department = $request->input('department');
        $agencyCode = $request->input('agency_code');

        // Load file for validation preview
        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $headers = array_shift($rows);
        $dataRows = array_values($rows);

        // Validate
        $validationErrors = $this->importService->validateImport($dataRows, $mapping);

        // Build preview tree (first 50 nodes)
        $previewNodes = [];
        $allCodes = [];
        foreach (array_slice($dataRows, 0, 50) as $row) {
            $code = isset($mapping['code']) ? trim((string) ($row[$mapping['code']] ?? '')) : '';
            $title = isset($mapping['title']) ? trim((string) ($row[$mapping['title']] ?? '')) : '';
            if (!empty($code)) {
                $allCodes[] = $code;
                $previewNodes[] = [
                    'code' => $code,
                    'title' => $title ?: $code,
                    'depth' => substr_count($code, $this->importService->detectSeparator([$code])),
                ];
            }
        }

        $separator = $this->importService->detectSeparator($allCodes);
        $maxDepth = 0;
        foreach ($previewNodes as &$pn) {
            $pn['depth'] = substr_count($pn['code'], $separator);
            $maxDepth = max($maxDepth, $pn['depth']);
        }

        return view('ahg-records::fileplan.import-preview', [
            'validationErrors' => $validationErrors,
            'previewNodes' => $previewNodes,
            'totalRows' => count($dataRows),
            'maxDepth' => $maxDepth,
            'filePath' => $filePath,
            'department' => $department,
            'agencyCode' => $agencyCode,
            'mapping' => $mapping,
        ]);
    }

    /**
     * Step 3: Preview (same as importMap but rendered differently if needed).
     */
    public function importPreview(Request $request)
    {
        return $this->importMap($request);
    }

    /**
     * Step 4: Execute the import.
     */
    public function importCommit(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'mapping' => 'required|array',
            'department' => 'nullable|string',
            'agency_code' => 'nullable|string',
        ]);

        $filePath = $request->input('file_path');
        $fullPath = storage_path('app/private/' . $filePath);
        $mapping = $request->input('mapping');
        $department = $request->input('department');
        $agencyCode = $request->input('agency_code');
        $userId = Auth::id() ?? 1;

        $result = $this->importService->importFromSpreadsheet(
            $fullPath,
            $mapping,
            $department,
            $agencyCode,
            $userId
        );

        if (!empty($result['errors'])) {
            return redirect()->route('records.fileplan.import.status', $result['session_id'])
                ->with('warning', 'Import completed with ' . count($result['errors']) . ' warning(s).');
        }

        return redirect()->route('records.fileplan.import.status', $result['session_id'])
            ->with('success', 'Import completed: ' . $result['imported'] . ' nodes imported.');
    }

    /**
     * Show import session results.
     */
    public function importStatus(int $sessionId)
    {
        $session = $this->importService->getImportSession($sessionId);
        if (!$session) {
            abort(404, 'Import session not found.');
        }

        $errors = $session->errors_json ? json_decode($session->errors_json, true) : [];

        return view('ahg-records::fileplan.import-result', compact('session', 'errors'));
    }

    /**
     * Link records to file plan nodes from an import session.
     */
    public function linkRecords(int $sessionId)
    {
        $session = $this->importService->getImportSession($sessionId);
        if (!$session) {
            abort(404, 'Import session not found.');
        }

        $linked = $this->importService->linkRecordsToFilePlan($sessionId);

        return redirect()->route('records.fileplan.import.status', $sessionId)
            ->with('success', "Linked {$linked} record(s) to file plan nodes.");
    }
}
