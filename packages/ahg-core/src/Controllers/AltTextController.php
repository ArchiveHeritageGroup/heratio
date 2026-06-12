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
 * image_alt_text table through the service; no AtoM base table is written and no
 * ALTER runs. The stored alt text is always HUMAN-AUTHORED.
 *
 * suggest() adds an OPTIONAL AI assist: a DRAFT description from the sanctioned AHG
 * AI gateway vision model (AltTextSuggestionService -> https://ai.theahg.co.za/ai/v1,
 * never a node port). The draft is a SUGGESTION ONLY - it is returned as JSON for the
 * curator to review and edit, and is NEVER saved by this endpoint. The human store()
 * path remains the sole write. When the gateway is unavailable / unconfigured the
 * assist degrades to a calm "unavailable" message and the manual curation is
 * untouched.
 *
 * The two-segment /admin/alt-text paths keep this clear of the single-segment /{slug}
 * archival-record catch-all (that route only ever matches ONE path segment). Every
 * action fails safe: a missing table / empty catalogue / gateway outage renders a
 * calm state, never a 500.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\AltTextService;
use AhgCore\Services\AltTextSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AltTextController extends Controller
{
    public function __construct(
        private AltTextService $service,
        private AltTextSuggestionService $suggestions,
    ) {}

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

        // Whether to offer the optional "Suggest alt text" AI assist. It only shows
        // when the curation store exists AND a gateway endpoint + key are configured;
        // a missing / mis-configured gateway simply hides the button - the manual
        // curation path is unaffected. Never throws.
        try {
            $aiEnabled = $this->suggestions->isEnabled();
        } catch (\Throwable $e) {
            $aiEnabled = false;
        }

        return view('ahg-core::alt-text.index', [
            'available'  => $available,
            'coverage'   => $coverage,
            'worklist'   => $worklist,
            'lang'       => $lang,
            'maxAltLen'  => AltTextService::MAX_ALT_LEN,
            'aiEnabled'  => $aiEnabled,
        ]);
    }

    /**
     * Optional AI ASSIST: return a DRAFT alt-text description for one published image
     * surrogate, via the sanctioned AHG AI gateway vision model. This is a SUGGESTION
     * ONLY - it is never saved. The curator reviews and edits the draft, then saves it
     * through the existing human store() path above (the only write path).
     *
     * Returns JSON so the worklist can drop the draft into the textarea without a page
     * reload. Every failure (no image, gateway down, no vision model, package missing)
     * degrades to { ok:false } with a calm message and HTTP 200 - the endpoint never
     * 500s and the manual curation stays usable. The two-segment /admin/alt-text/suggest
     * path keeps this clear of the single-segment /{slug} archival-record catch-all.
     */
    public function suggest(Request $request)
    {
        $data = $request->validate([
            'digital_object_id' => ['required', 'integer', 'min:1'],
            'lang'              => ['nullable', 'string', 'max:16'],
        ]);

        $lang = $this->service->normalizeLang($data['lang'] ?? AltTextService::DEFAULT_LANG);

        try {
            $result = $this->suggestions->suggest((int) $data['digital_object_id'], $lang);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] alt-text suggest endpoint failed: '.$e->getMessage());
            $result = [
                'ok'     => false,
                'draft'  => null,
                'reason' => __('The suggestion service is unavailable right now.'),
                'lang'   => $lang,
            ];
        }

        return response()->json([
            'ok'     => (bool) ($result['ok'] ?? false),
            'draft'  => $result['draft'] ?? null,
            'reason' => $result['reason'] ?? null,
            'lang'   => $result['lang'] ?? $lang,
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
