<?php

/**
 * AltTextController - Heratio ahg-core
 *
 * heratio#1211 ("every museum for everyone"), alt-text curation slice. The
 * accessibility coverage report surfaced that published image surrogates carry
 * essentially no genuine alternative text (the catalogue has no dedicated alt-text
 * column). This controller drives the curation surface that ACTS ON that gap: an
 * admin worklist of PUBLISHED image digital objects MISSING alt text, an inline
 * add/edit form, and a coverage figure - all backed by AltTextService and the NEW
 * image_alt_text side table.
 *
 * Admin-gated via the route group's `auth` middleware (matching the rest of the
 * /admin/* ahg-core surface, including the /admin/accessibility report this slice
 * complements). The ONLY write is the upsert in store(), confined entirely to the
 * image_alt_text table through the service; no AtoM base table is written, no ALTER
 * runs, and no AI call is made (alt text here is human-authored). The two-segment
 * /admin/alt-text paths keep this clear of the single-segment /{slug} archival-record
 * catch-all (that route only ever matches ONE path segment). Every action fails safe:
 * a missing table / empty catalogue renders a calm empty state, never a 500.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\AltTextService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AltTextController extends Controller
{
    public function __construct(private AltTextService $service) {}

    /**
     * The alt-text curation worklist: published image surrogates missing alt text in
     * the working language, paginated, with a coverage figure. The service never
     * throws; on any failure we render a clean, honest empty state rather than a 500.
     */
    public function index(Request $request)
    {
        $lang = $this->service->normalizeLang((string) $request->query('lang', AltTextService::DEFAULT_LANG));
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 25);

        try {
            $available = $this->service->isAvailable();
            $coverage = $this->service->coverage($lang);
            $worklist = $this->service->worklist($lang, $page, $perPage);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] alt-text dashboard failed: '.$e->getMessage());
            $available = false;
            $coverage = ['total' => 0, 'with' => 0, 'pct' => 0.0, 'lang' => $lang];
            $worklist = ['rows' => [], 'page' => 1, 'per_page' => $perPage, 'total_missing' => 0, 'last_page' => 1, 'lang' => $lang];
        }

        return view('ahg-core::alt-text.index', [
            'available'  => $available,
            'coverage'   => $coverage,
            'worklist'   => $worklist,
            'lang'       => $lang,
            'maxAltLen'  => AltTextService::MAX_ALT_LEN,
        ]);
    }

    /**
     * Upsert one image's alt text. The only write path; it goes through the service
     * to the image_alt_text table only. Redirects back to the worklist (preserving
     * the working language + page) with a one-line flash.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'digital_object_id' => ['required', 'integer', 'min:1'],
            'alt_text'          => ['nullable', 'string', 'max:'.AltTextService::MAX_ALT_LEN],
            'lang'              => ['nullable', 'string', 'max:16'],
            'page'              => ['nullable', 'integer', 'min:1'],
        ]);

        $lang = $this->service->normalizeLang($data['lang'] ?? AltTextService::DEFAULT_LANG);
        $altText = (string) ($data['alt_text'] ?? '');

        $ok = $this->service->save(
            (int) $data['digital_object_id'],
            $altText,
            $lang,
            Auth::id(),
        );

        $cleared = trim($altText) === '';
        $message = $ok
            ? ($cleared ? __('Alternative text cleared.') : __('Alternative text saved.'))
            : __('That image could not be updated right now.');

        $params = ['lang' => $lang];
        if (! empty($data['page'])) {
            $params['page'] = (int) $data['page'];
        }

        return redirect()
            ->route('alt-text.index', $params)
            ->with($ok ? 'status' : 'error', $message);
    }
}
