<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\OnixIngestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ONIX ingestion UI + API (heratio#1094).
 *
 * Upload/paste an EDItEUR ONIX message -> staged review queue -> commit to
 * catalogue + acquisitions order. All work delegates to OnixIngestService.
 */
class OnixIngestController extends Controller
{
    public function __construct(private OnixIngestService $onix) {}

    /** GET /library-manage/onix - upload form + ingest history. */
    public function index(): View
    {
        return view('ahg-library::onix.index', [
            'ingests' => $this->onix->listIngests(),
        ]);
    }

    /** POST /library-manage/onix - parse an uploaded file or pasted XML. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'onix_file' => ['nullable', 'file', 'max:20480', 'mimes:xml,txt,onx'],
            'onix_xml'  => ['nullable', 'string'],
        ]);

        [$xml, $filename, $source] = $this->resolvePayload($request);
        if ($xml === null) {
            return back()->with('error', 'Provide an ONIX file or paste ONIX XML.');
        }

        try {
            $result = $this->onix->ingest($xml, $filename, $source, $request->user()?->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'ONIX parse failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('library.onix-show', $result['ingest_id'])
            ->with('success', sprintf(
                'Parsed %d record(s): %d valid, %d with errors. Review and commit below.',
                $result['record_count'],
                $result['valid_count'],
                $result['error_count'],
            ));
    }

    /** GET /library-manage/onix/{id} - review queue for one ingest. */
    public function show(int $id): View|RedirectResponse
    {
        $ingest = $this->onix->getIngest($id);
        if (!$ingest) {
            return redirect()->route('library.onix-index')->with('error', "ONIX ingest #{$id} not found.");
        }

        return view('ahg-library::onix.show', [
            'ingest' => $ingest,
            'lines'  => $this->onix->getLines($id),
        ]);
    }

    /** POST /library-manage/onix/{id}/commit - import valid lines. */
    public function commit(int $id, Request $request): RedirectResponse
    {
        try {
            $r = $this->onix->commit($id, $request->user()?->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Commit failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('library.onix-show', $id)
            ->with('success', sprintf(
                'Committed: %d imported, %d skipped, %d failed.%s',
                $r['imported'],
                $r['skipped'],
                $r['failed'],
                $r['order_id'] ? ' Acquisitions order #' . $r['order_id'] . ' created/updated.' : '',
            ));
    }

    /** DELETE /library-manage/onix/{id} - drop an ingest log (keeps committed bibs). */
    public function destroy(int $id): RedirectResponse
    {
        $this->onix->deleteIngest($id);

        return redirect()->route('library.onix-index')->with('success', "ONIX ingest #{$id} deleted.");
    }

    /** POST /library-manage/onix/line/{lineId}/status - skip/include a review-queue line. */
    public function lineStatus(int $lineId, Request $request): RedirectResponse
    {
        $status = (string) $request->input('status', '');
        $ok = $this->onix->updateLineStatus($lineId, $status);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? "Line marked '{$status}'." : 'Could not update line (already committed or invalid status).',
        );
    }

    /**
     * POST /api/library/ingest/onix - accept an ONIX message as a raw body, a
     * JSON {"onix": "..."} field, or a multipart file upload. Returns a JSON
     * summary. Pass ?commit=1 to parse and commit in one call.
     *
     * Session-authenticated + CSRF-exempt; API-key hardening lands with the
     * #1100 JSON:API layer.
     */
    public function apiIngest(Request $request): JsonResponse
    {
        $xml = null;
        $filename = null;

        if ($request->hasFile('onix_file')) {
            $xml = $request->file('onix_file')->get();
            $filename = $request->file('onix_file')->getClientOriginalName();
        } elseif ($request->filled('onix')) {
            $xml = (string) $request->input('onix');
        } else {
            $raw = $request->getContent();
            if (trim($raw) !== '') {
                $xml = $raw;
            }
        }

        if ($xml === null || trim($xml) === '') {
            return response()->json(['error' => 'No ONIX payload (raw body, onix field, or onix_file).'], 422);
        }

        try {
            $result = $this->onix->ingest($xml, $filename, 'api', $request->user()?->id);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Parse failed: ' . $e->getMessage()], 422);
        }

        if ($request->boolean('commit')) {
            $commit = $this->onix->commit($result['ingest_id'], $request->user()?->id);
            $result['commit'] = $commit;
        }

        return response()->json($result, 201);
    }

    /**
     * Resolve the inbound payload to [xml, filename, source].
     *
     * @return array{0: ?string, 1: ?string, 2: string}
     */
    private function resolvePayload(Request $request): array
    {
        if ($request->hasFile('onix_file')) {
            $file = $request->file('onix_file');
            return [$file->get(), $file->getClientOriginalName(), 'file'];
        }
        if ($request->filled('onix_xml')) {
            return [(string) $request->input('onix_xml'), null, 'paste'];
        }
        return [null, null, 'file'];
    }
}
