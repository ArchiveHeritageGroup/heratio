<?php

/**
 * LanguageTranscriptionController - community TRANSCRIPTION / CORRECTION /
 * TRANSLATION contributions for the language-revival corpus (north-star
 * heratio#1208: a culture you can talk to - corpus-grounded history + language
 * revival).
 *
 * The next slice on top of the read-only language-revival corpus and its
 * moderated community glossary. A community member, viewing a PUBLISHED item in
 * a heritage language, can submit a transcription, a correction, a translation
 * or a note about its text. The contribution lands MODERATED ('pending') and is
 * shown publicly only once an admin approves it - exactly the glossary flow:
 *
 *   PUBLIC (web):
 *     GET  /language-transcribe/{item}            form       - context + submit form
 *     POST /language-transcribe/{item}            contribute - lodge a contribution (-> pending)
 *
 *   ADMIN (auth + admin):
 *     GET  /language-corpus-admin/transcriptions       moderate    - moderation queue
 *     POST /language-corpus-admin/transcriptions/{id}  moderateSet - approve / reject one entry
 *
 * Every write goes ONLY to the new language_transcription_contribution table via
 * LanguageTranscriptionService. The item context is read READ-ONLY from the
 * existing catalogue. No existing table is written or ALTERed. Forms have full
 * validation; every screen has an empty-state and never 500s. Respectful,
 * jurisdiction-neutral framing; contributors are credited only on consent.
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

use AhgSemanticSearch\Services\LanguageTranscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LanguageTranscriptionController extends Controller
{
    protected LanguageTranscriptionService $service;

    public function __construct()
    {
        $this->service = new LanguageTranscriptionService;
    }

    /**
     * Public contribution form for one PUBLISHED catalogue item: shows the item's
     * read-only context (title, its in-language text), the approved community
     * contributions already on it, and the submit form. Never 500s: a missing /
     * unpublished item renders a clear "not available" state.
     */
    public function form(Request $request, $item)
    {
        $itemId = (int) $item;

        $context = null;
        $approved = [];
        try {
            $context = $this->service->resolveItem($itemId);
            if ($context !== null) {
                $approved = $this->service->approvedForItem($itemId);
            }
        } catch (\Throwable $e) {
            Log::info('[language-transcription] form failed for '.$itemId.': '.$e->getMessage());
        }

        return view('ahg-semantic-search::language-transcription.form', [
            'itemId' => $itemId,
            'context' => $context,
            'approved' => $approved,
            'types' => LanguageTranscriptionService::CONTRIBUTION_TYPES,
            'disclaimer' => LanguageTranscriptionService::DISCLAIMER,
            'available' => $this->service->available(),
        ]);
    }

    /**
     * Lodge a new contribution against one PUBLISHED item. Full validation. The
     * contribution lands 'pending' and is NOT shown publicly until an admin
     * approves it. Redirects back to the item form with a clear confirmation.
     */
    public function contribute(Request $request, $item)
    {
        $itemId = (int) $item;

        $validated = $request->validate([
            'contribution_type' => 'required|string|max:32',
            'body' => 'required|string|max:60000',
            'culture' => 'nullable|string|max:16',
            'source' => 'nullable|string|max:512',
            'contributor_name' => 'nullable|string|max:255',
            'credit_consent' => 'nullable',
        ]);
        $validated['item_ref'] = $itemId;
        $validated['credit_consent'] = $request->boolean('credit_consent');

        $id = $this->service->contribute($validated, $this->userId($request));

        if ($id === null) {
            return back()
                ->withInput()
                ->with('error', __('Your contribution could not be saved. The item may not be available, or some details may be missing. Please check and try again.'));
        }

        return redirect()
            ->route('language-transcribe.form', ['item' => $itemId])
            ->with('success', __('Thank you. Your contribution has been submitted and will appear once a reviewer has approved it.'));
    }

    /**
     * Admin moderation queue for community contributions. Lists entries in one
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
            Log::info('[language-transcription] moderate queue failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::language-transcription.moderate', [
            'entries' => $entries,
            'counts' => $counts,
            'statuses' => LanguageTranscriptionService::MODERATION_STATUSES,
            'types' => LanguageTranscriptionService::CONTRIBUTION_TYPES,
            'statusFilter' => array_key_exists($status, LanguageTranscriptionService::MODERATION_STATUSES) ? $status : 'pending',
            'available' => $this->service->available(),
        ]);
    }

    /**
     * Approve or reject one contribution (admin action from the moderation queue).
     * Full validation; redirects back with a confirmation.
     */
    public function moderateSet(Request $request, $id)
    {
        $validated = $request->validate([
            'moderation_status' => 'required|string|max:32',
        ]);

        $ok = $this->service->moderate((int) $id, $validated['moderation_status'], $this->userId($request));

        return back()->with($ok ? 'success' : 'error', $ok
            ? __('Contribution updated.')
            : __('The contribution could not be updated.'));
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
