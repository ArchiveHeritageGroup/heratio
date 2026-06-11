<?php

/**
 * MultilingualController - Heratio ahg-core
 *
 * heratio#1211 north-star, first public slice: "every museum for everyone -
 * universal multilingual access". A SEPARATE public surface (not a picker bolted
 * onto the locked record show page) that lets any visitor read a catalogue
 * record's key metadata in their own language, on demand.
 *
 * This surface is PUBLIC (no auth) and READ-ONLY. It performs at most one cached
 * gateway translate call per field-set per language, so it stays cheap and
 * rate-aware. The original text is always shown and is always authoritative; the
 * machine translation is clearly labelled and is never persisted to the catalogue.
 *
 * Routes use a multi-segment path (/record/{idOrSlug}/translate and
 * /record/translate) so they never collide with the single-segment /{slug}
 * archival-record catch-all in ahg-information-object-manage.
 *
 * Publication status is enforced: an unpublished record 404s for the public and
 * never leaks a draft's text.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Controllers\ReadingLanguageController;
use AhgCore\Services\MultilingualRecordService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class MultilingualController extends Controller
{
    public function __construct(private MultilingualRecordService $service) {}

    /**
     * Public "Read this record in your language" page. Renders the original
     * metadata with a prominent language picker. If ?lang= is supplied it is
     * resolved server-side too, so the page is shareable / refresh-safe and
     * works without JavaScript.
     */
    public function show(string $idOrSlug, Request $request)
    {
        $record = $this->service->resolve($idOrSlug);

        // Unknown record, or unpublished + anonymous viewer -> 404 (no leak).
        if ($record === null || (! $record['published'] && ! Auth::check())) {
            abort(404);
        }

        $objectId = $record['id'];
        $fields = $this->service->fields($objectId);
        $languages = $this->service->languages($record['culture']);

        // The persisted reading-language preference (1-year cookie + session),
        // validated against the supported set for THIS record's source culture.
        // Empty string when none is set or the stored value is no longer
        // supported, in which case the page behaves exactly as before.
        $preferred = ReadingLanguageController::current($request, $this->service, $record['culture']);

        // Resolve which language (if any) to pre-select + pre-translate on load.
        // Precedence: an EXPLICIT ?lang= in the URL (shareable deep link / no-JS
        // submit) always wins so it stays refresh-safe and shareable. When no
        // ?lang= is given, fall back to the remembered preference so a returning
        // visitor's record opens already translated into their language. No
        // preference and no ?lang= -> original-only, unchanged behaviour.
        $explicit = $request->query('lang');
        $hasExplicit = is_string($explicit) && trim($explicit) !== '';
        $selected = $hasExplicit ? trim($explicit) : $preferred;

        // Server-side pre-translation (no-JS / shareable deep link / remembered
        // preference). The service validates the target and degrades to the
        // original on any gateway failure, so this never 500s.
        $translation = null;
        if ($selected !== '') {
            $translation = $this->service->translate($objectId, $selected);
        }

        return view('ahg-core::multilingual-record', [
            'objectId'      => $objectId,
            'idOrSlug'      => $idOrSlug,
            'sourceLang'    => $record['culture'],
            'fields'        => $fields,
            'languages'     => $languages,
            'translation'   => $translation,
            'selectedLang'  => $selected,
            'preferredLang' => $preferred,
        ]);
    }

    /**
     * JSON translate endpoint used by the picker's async fetch (also usable
     * directly). Validates the object id + target lang, enforces the
     * publication gate, runs one cached gateway call per field-set, and returns
     * the {lang, language, source, provider, fields} contract. The service
     * never throws; on gateway failure each field falls back to its original
     * with is_translated=false.
     */
    public function translateAjax(Request $request)
    {
        $data = $request->validate([
            'object_id' => 'required|integer|min:1',
            'lang'      => 'required|string|max:16',
        ]);

        $objectId = (int) $data['object_id'];

        // Publication gate - a draft must never be exposed to the public.
        if (! $this->service->isPublished($objectId) && ! Auth::check()) {
            abort(404);
        }

        return response()->json($this->service->translate($objectId, trim($data['lang'])));
    }
}
