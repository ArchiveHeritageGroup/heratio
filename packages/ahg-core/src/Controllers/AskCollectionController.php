<?php

/**
 * AskCollectionController - Heratio ahg-core
 *
 * heratio#1208 north-star, first public slice: "Ask the collection". The public,
 * collection-wide, anonymous cousin of the room-scoped exhibition docent. A member
 * of the public asks a plain-language question; the answer is grounded in the
 * institution's own corpus via the KM (knowledge-management RAG) service, with
 * cited sources, and says so honestly when the corpus does not cover the question.
 *
 * This surface is PUBLIC (no auth) and READ-ONLY - it performs exactly one KM call
 * per ask with a short timeout, so it stays cheap and rate-aware. Routes use a
 * multi-segment path (/ask-the-collection ...) so they never collide with the
 * single-segment /{slug} archival-record catch-all.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\AskCollectionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AskCollectionController extends Controller
{
    public function __construct(private AskCollectionService $service) {}

    /**
     * The public "Ask the collection" page. If a question is supplied as ?q=, it is
     * answered server-side so the page is shareable / refresh-safe and works without
     * JavaScript; otherwise the empty search box renders.
     */
    public function index(Request $request)
    {
        $question = trim((string) $request->query('q', ''));
        // Optional ?lang override; otherwise the answer is localized to the visitor's
        // current site locale (SetLocale middleware / culture switcher). #1208/#1211.
        $locale = trim((string) $request->query('lang', '')) ?: null;
        $result = null;
        if ($question !== '') {
            $result = $this->service->ask(mb_substr($question, 0, 500), $locale);
        }

        return view('ahg-core::ask-collection', [
            'question' => $question,
            'result' => $result,
        ]);
    }

    /**
     * JSON answer endpoint used by the page's async fetch (also usable directly).
     * Validates `q`, runs a single grounded KM call, and returns the well-formed
     * {answer, sources, grounded} contract. The service never throws.
     */
    public function ask(Request $request)
    {
        $data = $request->validate([
            'q' => 'required|string|max:500',
            'lang' => 'nullable|string|max:12',
        ]);

        return response()->json($this->service->ask(trim($data['q']), $data['lang'] ?? null));
    }
}
