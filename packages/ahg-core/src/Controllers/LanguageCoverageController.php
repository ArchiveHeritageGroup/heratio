<?php

/**
 * LanguageCoverageController - Heratio ahg-core
 *
 * heratio#1211 north-star ("every museum for everyone - universal multilingual
 * access"), NEXT public slice. Two public, read-only surfaces:
 *
 *   GET  /language-coverage           A visitor-facing LANGUAGE-COVERAGE dashboard:
 *                                     how many published descriptions (and authority
 *                                     records, and vocabulary terms) can be read in
 *                                     each language, as counts + simple CSS bars,
 *                                     framed as an open invitation ("help us reach
 *                                     more readers"). All figures are cheap aggregate
 *                                     COUNTs from LanguageCoverageService.
 *
 *   POST /language-coverage/translate On-demand MACHINE translation of ONE published
 *                                     record's key metadata into a target language,
 *                                     for DISPLAY ONLY. Delegated to the existing
 *                                     MultilingualRecordService, which routes through
 *                                     the SANCTIONED AHG AI gateway client
 *                                     (https://ai.theahg.co.za/ai/v1) - never a node
 *                                     port. The original text is always returned
 *                                     alongside and is always authoritative; the
 *                                     output is labelled "machine translation via the
 *                                     AHG gateway - not an official translation" and
 *                                     is never written back to the catalogue.
 *
 * Both surfaces are PUBLIC (no auth) and perform NO DB writes. The dashboard makes
 * NO AI calls; the translate endpoint makes at most one cached gateway call per
 * field-set and degrades gracefully (returns the original, flagged) when AI is
 * unavailable, so analytics keep working even with the gateway down.
 *
 * The translate route is registered BEFORE the dashboard's deeper paths and uses a
 * MULTI-SEGMENT path, so it can never collide with the single-segment /{slug}
 * archival-record catch-all. Drafts 404 for anonymous visitors (no leak).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\LanguageCoverageService;
use AhgCore\Services\MultilingualRecordService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class LanguageCoverageController extends Controller
{
    public function __construct(
        private LanguageCoverageService $coverage,
        private MultilingualRecordService $translator,
    ) {
    }

    /**
     * The public language-coverage dashboard. Every figure comes from the read-only
     * service (already null-safe). A zero total (or a service error) renders the calm
     * "still being catalogued" empty-state in the view - never a 500.
     *
     * We surface whether the per-record translate surface exists (Route::has), so the
     * view can invite visitors to read a record in their language only when that
     * feature is actually wired up.
     */
    public function index()
    {
        $data = $this->coverage->snapshot();

        // Is the per-record "read this record in your language" surface available?
        // (Built in the earlier slice of this same north-star.) Used only to show or
        // hide the invitation CTA - the dashboard never depends on it.
        $hasRecordTranslate = Route::has('record.translate');

        return view('ahg-core::language-coverage.index', [
            'total'              => (int) ($data['total_published'] ?? 0),
            'primary'            => $data['primary'] ?? null,
            'languageCount'      => (int) ($data['language_count'] ?? 0),
            'descriptions'       => $data['descriptions'] ?? [],
            'actors'             => $data['actors'] ?? [],
            'terms'              => $data['terms'] ?? [],
            'generatedAt'        => $data['generated_at'] ?? null,
            'hasError'           => (bool) ($data['error'] ?? false),
            'hasRecordTranslate' => $hasRecordTranslate,
        ]);
    }

    /**
     * On-demand metadata translation for ONE published record into a target language.
     *
     * Returns JSON ALWAYS (the service never throws). The contract mirrors
     * MultilingualRecordService::translate(): { lang, language, source, provider,
     * authoritative, notice, fields[] }, here wrapped with an explicit machine-
     * translation disclaimer the UI must show verbatim. Publication status is
     * enforced: a draft 404s for an anonymous visitor (no leak). When AI is
     * unavailable the service returns each field's ORIGINAL with is_translated=false
     * and provider='original', so this endpoint degrades to "analytics only" rather
     * than erroring.
     */
    public function translate(Request $request)
    {
        $data = $request->validate([
            'object_id' => 'required|integer|min:1',
            'lang'      => 'required|string|max:16',
        ]);

        $objectId = (int) $data['object_id'];

        // Publication gate - a draft must never be exposed to the public.
        if (! $this->translator->isPublished($objectId) && ! Auth::check()) {
            abort(404);
        }

        $result = $this->translator->translate($objectId, trim((string) $data['lang']));

        // The mandatory, non-negotiable label. Surfaced as a structured field so the
        // UI cannot accidentally drop it, and echoed in plain text for any non-HTML
        // consumer. NOT an official translation; the original stays authoritative.
        $result['disclaimer'] = __('Machine translation via the AHG gateway - not an official translation. The original text remains authoritative.');
        $result['ai_available'] = (($result['provider'] ?? 'original') !== 'original');

        return response()->json($result);
    }
}
