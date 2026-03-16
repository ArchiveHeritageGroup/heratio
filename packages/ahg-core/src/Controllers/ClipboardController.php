<?php

namespace AhgCore\Controllers;

use AhgCore\Services\ClipboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClipboardController extends Controller
{
    protected ClipboardService $service;

    public function __construct()
    {
        $this->service = new ClipboardService();
    }

    /**
     * Show clipboard items.
     * GET /clipboard
     */
    public function index(Request $request)
    {
        $culture = app()->getLocale();
        $type = $request->get('type', 'informationObject');
        $items = $this->service->getItems($this->getUserId());
        $details = $this->service->getItemDetails($items, $culture);

        // Filter details by type if requested
        if ($type !== 'all') {
            $details = array_filter($details, fn($item) => $item->type === $type);
            $details = array_values($details);
        }

        $counts = [
            'informationObject' => count($items['informationObject'] ?? []),
            'actor'             => count($items['actor'] ?? []),
            'repository'        => count($items['repository'] ?? []),
        ];
        $totalCount = array_sum($counts);

        $uiLabels = [
            'informationObject' => 'Archival descriptions',
            'actor'             => 'Authority records',
            'repository'        => 'Archival institutions',
        ];

        return view('ahg-core::clipboard.index', compact(
            'details', 'type', 'counts', 'totalCount', 'uiLabels', 'items'
        ));
    }

    /**
     * Add item to clipboard (AJAX).
     * POST /clipboard/add
     */
    public function add(Request $request)
    {
        $slug = $request->input('slug');
        $type = $request->input('type', 'informationObject');

        if (!$slug) {
            return response()->json(['error' => 'Slug is required.'], 400);
        }

        $this->service->addItem($slug, $type, $this->getUserId());

        return response()->json([
            'success' => true,
            'count'   => $this->service->count($this->getUserId()),
        ]);
    }

    /**
     * Remove item from clipboard (AJAX).
     * DELETE /clipboard/remove
     */
    public function remove(Request $request)
    {
        $slug = $request->input('slug');
        $type = $request->input('type', 'informationObject');

        if (!$slug) {
            return response()->json(['error' => 'Slug is required.'], 400);
        }

        $this->service->removeItem($slug, $type, $this->getUserId());

        return response()->json([
            'success' => true,
            'count'   => $this->service->count($this->getUserId()),
        ]);
    }

    /**
     * Clear clipboard (AJAX).
     * POST /clipboard/clear
     */
    public function clear(Request $request)
    {
        $type = $request->input('type');
        $this->service->clearAll($this->getUserId(), $type);

        return response()->json([
            'success' => true,
            'count'   => 0,
        ]);
    }

    /**
     * Sync clipboard from client localStorage (AJAX).
     * POST /clipboard/sync
     */
    public function sync(Request $request)
    {
        $items = $request->input('items', []);
        $this->service->syncFromClient($items);

        return response()->json([
            'success' => true,
            'count'   => $this->service->count($this->getUserId()),
        ]);
    }

    /**
     * Save clipboard to DB with password.
     * POST /clipboard/save
     */
    public function save(Request $request)
    {
        $slugs = $request->input('slugs', []);

        if (empty($slugs)) {
            // Fall back to session items
            $slugs = $this->service->getItems($this->getUserId());
        }

        $result = $this->service->save($slugs, $this->getUserId());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json($result);
    }

    /**
     * Load clipboard form page.
     * GET /clipboard/load
     */
    public function loadForm()
    {
        return view('ahg-core::clipboard.load');
    }

    /**
     * Load clipboard by password (AJAX).
     * POST /clipboard/load
     */
    public function load(Request $request)
    {
        $password = $request->input('clipboardPassword', $request->input('password', ''));
        $mode = $request->input('mode', 'merge');

        if (empty($password)) {
            return response()->json(['error' => 'Clipboard ID is required.'], 400);
        }

        $result = $this->service->load($password, $mode);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 404);
        }

        return response()->json($result);
    }

    /**
     * Export clipboard as CSV download.
     * GET /clipboard/export/csv
     */
    public function exportCsv(Request $request)
    {
        $culture = app()->getLocale();
        $items = $this->service->getItems($this->getUserId());

        if (empty($items)) {
            return redirect()->route('clipboard.index')
                ->with('warning', 'No items in clipboard to export.');
        }

        $csv = $this->service->exportCsv($items, $culture);

        $filename = 'clipboard_export_' . date('Y-m-d_His') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Get clipboard count (AJAX).
     * GET /clipboard/count
     */
    public function count()
    {
        return response()->json([
            'count' => $this->service->count($this->getUserId()),
        ]);
    }

    /**
     * Get authenticated user ID or null.
     */
    protected function getUserId(): ?int
    {
        return Auth::id();
    }
}
