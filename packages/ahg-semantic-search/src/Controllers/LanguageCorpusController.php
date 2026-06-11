<?php

/**
 * LanguageCorpusController - language-revival corpus surface (north-star
 * heratio#1208: a culture you can talk to - corpus-grounded history + language
 * revival).
 *
 * A PUBLIC, read-only revival resource for heritage / endangered languages,
 * plus an admin-moderated community glossary and a public contribution form:
 *
 *   PUBLIC (web):
 *     GET  /language-corpus            index    - directory of languages in the collection
 *     GET  /language-corpus/{culture}  show     - one language: holdings IN/ABOUT it,
 *                                                  terms, transcriptions, approved glossary
 *     POST /language-corpus/{culture}/contribute  contribute - lodge a glossary entry (-> pending)
 *     POST /language-corpus/{culture}/translate   translate  - optional gateway MT of a snippet
 *
 *   ADMIN (auth + admin):
 *     GET  /language-corpus-admin/glossary         moderate     - moderation queue
 *     POST /language-corpus-admin/glossary/{id}    moderateSet  - approve / reject one entry
 *
 * Every write goes ONLY to the new language_revival_glossary table via
 * LanguageCorpusService. The corpus itself is read READ-ONLY from the existing
 * catalogue. No existing table is written or ALTERed. Forms have full validation;
 * every screen has an empty-state and never 500s. Any machine translation is
 * clearly labelled as unofficial. Respectful, jurisdiction-neutral framing.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgSemanticSearch\Controllers;

use AhgSemanticSearch\Services\LanguageCorpusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LanguageCorpusController extends Controller
{
    protected LanguageCorpusService $service;

    public function __construct()
    {
        $this->service = new LanguageCorpusService;
    }

    /**
     * Public directory of every language the collection holds, with how much is
     * described in it and how many terms carry a label in it. Never 500s: any
     * failure renders the grounded empty-state.
     */
    public function index(Request $request)
    {
        $languages = [];
        try {
            $languages = $this->service->languages();
        } catch (\Throwable $e) {
            Log::info('[language-corpus] index failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::language-corpus.index', [
            'languages' => $languages,
            'disclaimer' => LanguageCorpusService::DISCLAIMER,
        ]);
    }

    /**
     * Public view of one language / culture: the records described in it, the
     * place-names and subject terms that carry a label in it, any transcriptions,
     * and the approved community glossary. Read-only. Never 500s.
     */
    public function show(Request $request, string $culture)
    {
        $base = $this->service->sanitiseCulture($culture);
        if ($base === null) {
            abort(404);
        }

        $records = [];
        $terms = [];
        $transcriptions = [];
        $glossary = [];
        try {
            $records = $this->service->describedRecords($base);
            $terms = $this->service->terms($base);
            $transcriptions = $this->service->transcriptions($base);
            $glossary = $this->service->glossary($base);
        } catch (\Throwable $e) {
            Log::info('[language-corpus] show failed for '.$base.': '.$e->getMessage());
        }

        // Split terms into place-names vs subject terms for a clearer word-list.
        $places = array_values(array_filter($terms, fn ($t) => ($t['kind'] ?? '') === 'place'));
        $subjects = array_values(array_filter($terms, fn ($t) => ($t['kind'] ?? '') === 'subject'));

        return view('ahg-semantic-search::language-corpus.show', [
            'culture' => $base,
            'label' => $this->service->label($base),
            'records' => $records,
            'places' => $places,
            'subjects' => $subjects,
            'transcriptions' => $transcriptions,
            'glossary' => $glossary,
            'glossaryAvailable' => $this->service->glossaryAvailable(),
            'disclaimer' => LanguageCorpusService::DISCLAIMER,
            'mtLabel' => LanguageCorpusService::MT_LABEL,
            'hasAnything' => ! empty($records) || ! empty($terms) || ! empty($transcriptions) || ! empty($glossary),
        ]);
    }

    /**
     * Lodge a new community glossary entry for one culture. Full validation. The
     * entry lands as 'pending' and is NOT shown publicly until an admin approves
     * it. Redirects back to the language page with a clear confirmation.
     */
    public function contribute(Request $request, string $culture)
    {
        $base = $this->service->sanitiseCulture($culture);
        if ($base === null) {
            abort(404);
        }

        $validated = $request->validate([
            'term' => 'required|string|max:512',
            'meaning' => 'required|string|max:20000',
            'usage_example' => 'nullable|string|max:20000',
            'source' => 'nullable|string|max:512',
            'contributor_name' => 'nullable|string|max:255',
        ]);
        $validated['culture'] = $base;

        $id = $this->service->contribute($validated, $this->userId($request));

        if ($id === null) {
            return back()
                ->withInput()
                ->with('error', __('Your contribution could not be saved. Please check the details and try again.'));
        }

        return redirect()
            ->route('language-corpus.show', ['culture' => $base])
            ->with('success', __('Thank you. Your contribution has been submitted and will appear once a reviewer has approved it.'));
    }

    /**
     * Optional on-demand machine translation of a snippet, via the AHG gateway
     * (through LlmService). The response always carries the unofficial-translation
     * label. Returns JSON for an inline fetch; degrades to a clear "unavailable"
     * payload (HTTP 200) rather than an error so the page never breaks.
     */
    public function translate(Request $request, string $culture)
    {
        $base = $this->service->sanitiseCulture($culture);
        if ($base === null) {
            abort(404);
        }

        $validated = $request->validate([
            'text' => 'required|string|max:5000',
            'target' => 'nullable|string|max:16',
        ]);

        $target = trim((string) ($validated['target'] ?? '')) !== '' ? $validated['target'] : 'en';

        $result = null;
        try {
            $result = $this->service->translateSnippet($validated['text'], $target, $base);
        } catch (\Throwable $e) {
            Log::info('[language-corpus] translate endpoint failed for '.$base.': '.$e->getMessage());
        }

        if ($result === null) {
            return response()->json([
                'ok' => false,
                'message' => __('Machine translation is not available right now. The text is shown in its original language.'),
                'label' => LanguageCorpusService::MT_LABEL,
            ]);
        }

        return response()->json([
            'ok' => true,
            'translation' => $result['text'],
            'label' => $result['label'],
        ]);
    }

    /**
     * Admin moderation queue for the community glossary. Lists entries in one
     * moderation state (default 'pending'), with approve / reject actions. Never
     * 500s: a missing table renders the empty-state.
     */
    public function moderate(Request $request)
    {
        $status = trim((string) $request->query('status', 'pending'));

        $entries = [];
        $counts = [];
        try {
            $entries = $this->service->moderationQueue($status);
            $counts = $this->service->moderationCounts();
        } catch (\Throwable $e) {
            Log::info('[language-corpus] moderate queue failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::language-corpus.glossary-moderate', [
            'entries' => $entries,
            'counts' => $counts,
            'statuses' => LanguageCorpusService::MODERATION_STATUSES,
            'statusFilter' => array_key_exists($status, LanguageCorpusService::MODERATION_STATUSES) ? $status : 'pending',
            'available' => $this->service->glossaryAvailable(),
        ]);
    }

    /**
     * Approve or reject one glossary entry (admin action from the moderation
     * queue). Full validation; redirects back with a confirmation.
     */
    public function moderateSet(Request $request, $id)
    {
        $validated = $request->validate([
            'moderation_status' => 'required|string|max:32',
        ]);

        $ok = $this->service->moderate((int) $id, $validated['moderation_status'], $this->userId($request));

        return back()->with($ok ? 'success' : 'error', $ok
            ? __('Glossary entry updated.')
            : __('The entry could not be updated.'));
    }

    /**
     * Current user id, when authenticated.
     */
    protected function userId(Request $request): ?int
    {
        try {
            $user = $request->user();

            return $user ? (int) $user->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
