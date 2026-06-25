<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgAiServices\Controllers;

use AhgAiServices\Services\FsScotlandIndexerService;
use AhgAiServices\Support\FsDataSafeCsv;
use AhgAiServices\Support\FsKeyingRules;
use AhgAiServices\Support\FsScotlandProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * FS-Scotland indexer UI/API (heratio FS-metadata-capture): point at a DGS
 * folder, run the trained-HTR extraction + keying-rule normalisation, preview
 * the Data Safe rows, and download the Data Safe CSV. Admin-gated (route group).
 */
class FsIndexerController extends Controller
{
    public function __construct(private FsScotlandIndexerService $indexer)
    {
    }

    /** Minimal driver page. */
    public function page()
    {
        return view('ahg-ai-services::htr.fs-index', [
            'columns' => FsScotlandProfile::COLUMNS,
        ]);
    }

    /** Run the indexer over a folder and return a JSON preview. */
    public function run(Request $request)
    {
        $folder = (string) $request->input('folder', '');
        if ($folder === '' || ! is_dir($folder)) {
            return response()->json(['success' => false, 'error' => 'Folder not found'], 422);
        }

        // Instant: one row per image, event fields blank. The grid then fills
        // per-image on demand (imageFields) - a synchronous full-folder HTR
        // sweep blocked ~70s on a 49-image folder and timed the request out.
        $rows = $this->indexer->listImages($folder, $this->project($request));

        return response()->json([
            'success' => true,
            'columns' => array_keys(FsScotlandProfile::COLUMNS),
            'rows'    => $rows,
            'total'   => count($rows),
            'pending' => [],
        ]);
    }

    /** Run the indexer and stream the Data Safe CSV as a download. */
    public function csv(Request $request)
    {
        $folder = (string) $request->input('folder', '');
        if ($folder === '' || ! is_dir($folder)) {
            return response()->json(['success' => false, 'error' => 'Folder not found'], 422);
        }

        $result = $this->indexer->indexFolder($folder, $this->project($request));
        $dgs = basename(rtrim($folder, '/'));
        $name = 'fs-scotland-'.preg_replace('/[^A-Za-z0-9_-]/', '', $dgs).'.csv';

        return response($result['csv'], 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /** Serve a source image from within the indexed folder (review-grid preview). */
    public function image(Request $request)
    {
        $folder = (string) $request->query('folder', '');
        $fname = basename((string) $request->query('fname', '')); // strip any traversal
        if ($folder === '' || $fname === '' || ! is_dir($folder)) {
            abort(404);
        }
        $path = rtrim($folder, '/').'/'.$fname;
        $real = realpath($path);
        $base = realpath($folder);
        if ($real === false || $base === false || ! str_starts_with($real, $base.'/')) {
            abort(403);
        }
        if (! preg_match('/\.(jpe?g|png|tiff?)$/i', $real)) {
            abort(415);
        }

        return response()->file($real);
    }

    /**
     * Per-image model fields with bbox + Data Safe target, for the review-grid
     * overlay (draw/drag boxes on the image, per-box recognise). Also returns
     * the image URL + absolute path (the latter feeds the reused recognise API).
     */
    public function imageFields(Request $request)
    {
        $folder = (string) $request->input('folder', '');
        $fname = basename((string) $request->input('fname', ''));
        $eventType = (string) $request->input('event_type', '');
        if ($folder === '' || $fname === '' || ! is_dir($folder)) {
            return response()->json(['success' => false, 'error' => 'Folder/file not found'], 422);
        }
        $real = realpath(rtrim($folder, '/').'/'.$fname);
        $base = realpath($folder);
        if ($real === false || $base === false || ! str_starts_with($real, $base.'/')) {
            return response()->json(['success' => false, 'error' => 'Access denied'], 403);
        }

        $review = $this->indexer->reviewImage($real, $eventType, $this->project($request));

        return response()->json([
            'success'    => true,
            'image_url'  => route('admin.ai.htr.fsIndexImage').'?folder='.urlencode($folder).'&fname='.urlencode($fname),
            'image_path' => $real,
            'fields'     => $review['fields'], // overlay boxes
            'row'        => $review['row'],    // assembled Data Safe row for this image
        ]);
    }

    /** Build the Data Safe CSV from human-corrected rows (review grid). */
    public function csvFromRows(Request $request)
    {
        $rows = $request->input('rows', []);
        if (! is_array($rows)) {
            return response()->json(['success' => false, 'error' => 'rows must be an array'], 422);
        }
        $dgs = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->input('dgs', 'export'));

        // FS Overlay export sends raw box values keyed by Data Safe system name;
        // normalise (month/day/year/sex/name keying rules) before writing.
        if ($request->boolean('normalize')) {
            $rows = array_map(
                static fn ($r) => is_array($r) ? FsKeyingRules::normalizeRecord($r) : $r,
                $rows
            );
        }

        return response(FsDataSafeCsv::toString($rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fs-scotland-'.($dgs ?: 'export').'.csv"',
        ]);
    }

    /**
     * Persist human-corrected rows (the reviewed artifact + future fine-tune
     * corpus). Written to www-data-writable storage, NOT the source folder.
     */
    public function saveCorrections(Request $request)
    {
        $rows = $request->input('rows', []);
        if (! is_array($rows) || $rows === []) {
            return response()->json(['success' => false, 'error' => 'No rows to save'], 422);
        }
        $dgs = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->input('dgs', 'export')) ?: 'export';
        $dir = storage_path('app/fs-corrections');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/'.$dgs.'.json';
        $payload = [
            'dgs'        => $dgs,
            'folder'     => (string) $request->input('folder', ''),
            'saved_at'   => now()->toIso8601String(),
            'row_count'  => count($rows),
            'rows'       => array_values($rows),
        ];
        $ok = file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;

        return response()->json(['success' => $ok, 'file' => $ok ? $file : null, 'rows' => count($rows)]);
    }

    /**
     * Project constants for the run (Collection/PPQ supplied by FamilySearch;
     * doc_type selects the trained model).
     *
     * @return array{collection_id:string,ppq_id:string,doc_type:string}
     */
    private function project(Request $request): array
    {
        return [
            'collection_id' => (string) $request->input('collection_id', ''),
            'ppq_id'        => (string) $request->input('ppq_id', ''),
            // Event type picks the HTR doc_type (type_a birth / type_b death /
            // type_c marriage) AND shapes the Data Safe mapping. doc_type can be
            // overridden explicitly; blank => derived from event_type.
            'event_type'    => (string) $request->input('event_type', ''),
            'doc_type'      => (string) $request->input('doc_type', ''),
        ];
    }
}
