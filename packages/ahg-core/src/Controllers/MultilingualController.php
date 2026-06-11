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

        // Optional server-side pre-translation (no-JS / shareable deep link).
        $selected = $request->query('lang');
        $translation = null;
        if (is_string($selected) && trim($selected) !== '') {
            $translation = $this->service->translate($objectId, trim($selected));
        }

        return view('ahg-core::multilingual-record', [
            'objectId'     => $objectId,
            'idOrSlug'     => $idOrSlug,
            'sourceLang'   => $record['culture'],
            'fields'       => $fields,
            'languages'    => $languages,
            'translation'  => $translation,
            'selectedLang' => is_string($selected) ? trim($selected) : '',
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
