<?php

/**
 * AskCollectionController - Heratio ahg-core
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

/**
 * heratio#1208 - public "Ask the collection" page. A member of the public asks a plain-language
 * question; the answer is grounded ONLY in matching PUBLISHED catalogue records and cites them by
 * number with links. Two-segment paths (/ask/collection, /ask/collection/answer) keep this clear
 * of the single-segment /{slug} archival-record catch-all.
 */
class AskCollectionController extends Controller
{
    public function __construct(private AskCollectionService $service) {}

    /** The public search-box page. */
    public function index(Request $request)
    {
        $question = trim((string) $request->query('q', ''));
        $result = null;
        if ($question !== '') {
            $result = $this->service->ask($question);
        }

        return view('ahg-core::ask-collection', [
            'question' => $question,
            'result' => $result,
        ]);
    }

    /** JSON answer endpoint (used by the page's async submit; also usable directly). */
    public function answer(Request $request)
    {
        $data = $request->validate([
            'q' => 'required|string|max:500',
        ]);

        return response()->json($this->service->ask(trim($data['q'])));
    }
}
