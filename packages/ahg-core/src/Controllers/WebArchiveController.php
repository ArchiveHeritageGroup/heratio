<?php

/**
 * WebArchiveController - Heratio ahg-core
 *
 * heratio#1244 (WARC web-archiving slice): the admin surface that lets the catalogue
 * web-archive its OWN published record pages into valid WARC 1.1 (ISO 28500) files.
 *
 * Routes (all admin-gated by the route group's `auth` middleware, matching the rest of
 * the /admin/* ahg-core surface, and all MULTI-SEGMENT so they can never collide with
 * the single-segment /{slug} archival-record catch-all):
 *
 *   GET  /admin/web-archive                 list captures + a "capture a record" form
 *   POST /admin/web-archive/capture         snapshot a published record's own page (by id)
 *   GET  /admin/web-archive/{id}/download   stream the stored .warc (application/warc)
 *
 * Every write is confined to the NEW warc_capture table (via WarcCaptureService) + the
 * .warc files on disk under the configured storage path; no AtoM/Qubit base table is
 * written, no ALTER, no AI call. The capture takes a record ID (never a raw URL): the
 * service derives the record's OWN canonical url() and SSRF-validates it before any
 * fetch. The capture now also archives the page's DIRECT same-host subresources (CSS /
 * JS / images / icons / inline-style url()) into the SAME WARC - off-host assets are
 * dropped honestly, and the bounded subresource count is surfaced in the result message
 * + the captures list. Every action is resilient: a missing table, an unreachable /
 * oversize page, a failing subresource, or a non-own-host target degrades to a clean
 * message + a `failed` row, never a 500.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\WarcCaptureService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class WebArchiveController extends Controller
{
    public function __construct(private WarcCaptureService $service) {}

    /** Landing page: the list of captures + the "capture a record" form. */
    public function index()
    {
        return view('ahg-core::web-archive.index', [
            'available' => $this->service->isAvailable(),
            'captures' => $this->service->listCaptures(),
        ]);
    }

    /**
     * Snapshot a published record's own public page into a WARC 1.1 file. Takes a
     * record id (NOT a URL); the service derives + SSRF-validates the record's own
     * canonical url() before fetching. Always redirects back with a clean flash.
     */
    public function capture(Request $request)
    {
        $data = $request->validate([
            'information_object_id' => ['required', 'integer', 'min:2'],
        ]);

        if (! $this->service->isAvailable()) {
            return redirect()->route('web-archive.index')
                ->with('error', __('The web-archive table is not installed yet. Please try again shortly.'));
        }

        $result = $this->service->capture((int) $data['information_object_id'], Auth::id());

        if (! empty($result['ok'])) {
            $subCount = (int) ($result['subresource_count'] ?? 0);
            $subNote = $subCount > 0
                ? ' '.trans_choice(
                    '{1}+ :count same-host subresource.|[2,*]+ :count same-host subresources.',
                    $subCount,
                    ['count' => $subCount]
                )
                : ' '.__('(page only; no same-host subresources).');

            return redirect()->route('web-archive.index')
                ->with('success', __('Page captured to a WARC file.').' ('.($result['byte_size'] ?? 0).' '.__('bytes').')'.$subNote);
        }

        return redirect()->route('web-archive.index')
            ->with('error', $result['message'] ?? __('The page could not be captured.'));
    }

    /** Stream a stored .warc file for download (Content-Type application/warc). */
    public function download(int $id)
    {
        $file = $this->service->fileForDownload($id);
        if ($file === null) {
            return redirect()->route('web-archive.index')
                ->with('error', __('That capture file could not be found.'));
        }

        return response()->download($file['path'], $file['name'], [
            'Content-Type' => 'application/warc',
        ]);
    }
}
