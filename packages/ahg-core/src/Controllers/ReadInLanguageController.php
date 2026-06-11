<?php

/**
 * ReadInLanguageController - Heratio ahg-core
 *
 * heratio#1211 north-star ("every museum for everyone - universal multilingual
 * access"), NEXT public slice: a standalone, shareable "READ THIS RECORD IN YOUR
 * LANGUAGE" page that offers on-demand access to a PUBLISHED record's metadata in
 * the visitor's chosen language.
 *
 * The distinguishing policy of THIS surface (vs the side-by-side MT page at
 * /record/{idOrSlug}/translate) is that it PREFERS a real, human-authored
 * translation over machine translation:
 *
 *   - If the catalogue already holds an information_object_i18n row for the chosen
 *     culture, that text is shown and labelled "OFFICIAL TRANSLATION" (no gateway
 *     call, authoritative).
 *   - Otherwise the page calls the SANCTIONED AHG AI gateway (via
 *     MultilingualRecordService::translate -> AhgAiServices\Services\LlmService
 *     -> https://ai.theahg.co.za/ai/v1, never a node port) and labels the result
 *     "Machine translation via the AHG gateway - not an official translation. The
 *     original remains authoritative."
 *
 * The original catalogue text is always shown first and is always authoritative.
 * Nothing is ever written back to the catalogue. Publication status is enforced -
 * an unknown or draft record 404s for the public (no leak). When the gateway is
 * unavailable the page degrades to the original + a calm notice and never 500s;
 * the language picker still works.
 *
 * Routes:
 *   GET  /read/{idOrSlug}            (2-segment - safe from the /{slug} catch-all)
 *   POST /read/{idOrSlug}/translate  (multi-segment - safe from the catch-all)
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\MultilingualRecordService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ReadInLanguageController extends Controller
{
    /** The verbatim, mandatory machine-translation disclaimer. */
    public const MT_DISCLAIMER_KEY = 'Machine translation via the AHG gateway - not an official translation. The original remains authoritative.';

    public function __construct(private MultilingualRecordService $service)
    {
    }

    /**
     * GET /read/{idOrSlug} - the public "read this record in your language" page.
     *
     * Resolves a PUBLISHED record (draft/unknown -> 404), shows its original
     * title + descriptive metadata, and a language picker that distinguishes
     * cultures with an OFFICIAL existing translation from those available only via
     * machine translation. An optional ?lang= pre-selects + pre-renders the chosen
     * language server-side, so the page is shareable, refresh-safe, and works
     * without JavaScript. Never throws, never 500s.
     */
    public function show(string $idOrSlug, Request $request)
    {
        $record = $this->service->resolve($idOrSlug);

        // Unknown record, or a draft viewed by an anonymous visitor -> 404 (no leak).
        if ($record === null || (! $record['published'] && ! Auth::check())) {
            abort(404);
        }

        $objectId = $record['id'];
        $sourceLang = $record['culture'];

        $fields = $this->service->fields($objectId);
        $languages = $this->service->languages($sourceLang);

        // Which cultures have a REAL, human-authored translation already (so the
        // picker can mark them "official" and prefer them over MT). Always read-only.
        $officialCultures = $this->service->availableCultures($objectId);

        // Title for the page header (first 'title' field, if any).
        $title = '';
        foreach ($fields as $f) {
            if ($f['key'] === 'title') {
                $title = $f['original'];
                break;
            }
        }

        // Server-side pre-render of an explicit ?lang= (no-JS / shareable deep link).
        // read() prefers an official i18n row over MT and degrades gracefully.
        $explicit = $request->query('lang');
        $selected = is_string($explicit) ? trim($explicit) : '';
        $rendering = null;
        if ($selected !== '' && $selected !== $sourceLang) {
            $rendering = $this->decorate($this->service->read($objectId, $selected));
        }

        return view('ahg-core::read-in-language', [
            'objectId'         => $objectId,
            'idOrSlug'         => $idOrSlug,
            'title'            => $title,
            'sourceLang'       => $sourceLang,
            'fields'           => $fields,
            'languages'        => $languages,
            'officialCultures' => $officialCultures,
            'rendering'        => $rendering,
            'selectedLang'     => $selected,
            'mtDisclaimer'     => __(self::MT_DISCLAIMER_KEY),
        ]);
    }

    /**
     * POST /read/{idOrSlug}/translate - drive the on-demand rendering.
     *
     * Resolves + publication-gates the record, then returns JSON describing the
     * chosen language: an OFFICIAL existing translation when the catalogue holds
     * one (source='official'), otherwise the gateway machine translation
     * (source='machine-translation'). The service never throws; on gateway failure
     * each field falls back to its original with is_translated=false, so this
     * endpoint degrades rather than erroring. Always returns the mandatory
     * disclaimer + an is_official flag the UI uses to pick the right banner.
     */
    public function translate(string $idOrSlug, Request $request)
    {
        $record = $this->service->resolve($idOrSlug);
        if ($record === null || (! $record['published'] && ! Auth::check())) {
            abort(404);
        }

        $data = $request->validate([
            'lang' => 'required|string|max:16',
        ]);

        $result = $this->service->read($record['id'], trim((string) $data['lang']));

        return response()->json($this->decorate($result));
    }

    /**
     * Attach the UI-facing flags every renderer needs: whether this is an OFFICIAL
     * (human-authored) translation vs machine translation, whether the gateway
     * actually produced anything, and the mandatory verbatim disclaimer (only
     * meaningful for the MT case, but always present so the UI cannot drop it).
     *
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function decorate(array $result): array
    {
        $source = (string) ($result['source'] ?? 'machine-translation');
        $provider = (string) ($result['provider'] ?? 'original');

        $result['is_official'] = ($source === 'official');
        // For MT, "available" means the gateway round-tripped a real translation;
        // 'original'/'catalogue' providers mean no live MT call was needed/made.
        $result['ai_available'] = ($provider === 'ahg-gateway');
        $result['disclaimer'] = __(self::MT_DISCLAIMER_KEY);

        return $result;
    }
}
