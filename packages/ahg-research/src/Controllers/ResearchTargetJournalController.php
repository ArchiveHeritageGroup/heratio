<?php

/**
 * ResearchTargetJournalController - target-journal directory UI + CRUD (#1107).
 *
 * Journals to publish TO (scope + submission rules), seeded for the SA market
 * from the DHET accredited list. Feeds the #1105 manuscript builder. Routes are
 * under the auth'd /research group.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\ResearchTargetJournalService;
use Illuminate\Http\Request;

class ResearchTargetJournalController extends Controller
{
    use LogsResearchActivity;

    public function __construct(private ResearchTargetJournalService $service)
    {
    }

    public function index(Request $request)
    {
        return view('research::target-journals.index', [
            'isAdmin' => $this->isAdmin(),
            'journals' => $this->service->list([
                'q'      => $request->query('q'),
                'market' => $request->query('market'),
            ]),
            'q'      => (string) $request->query('q', ''),
            'market' => (string) $request->query('market', ''),
        ]);
    }

    public function create()
    {
        $this->requireAdmin();
        return view('research::target-journals.builder', ['journal' => null, 'styles' => ResearchTargetJournalService::REFERENCE_STYLES]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $id = $this->service->create($this->validateData($request));

        $this->logResearchActivity('create', 'target_journal', (int) $id, $request->input('title'), ['method' => 'ResearchTargetJournalController@store']);

        return redirect()->route('research.target-journal.show', $id)->with('success', __('Journal added to the directory.'));
    }

    public function show(int $id)
    {
        $journal = $this->service->get($id);
        abort_if(! $journal, 404);

        return view('research::target-journals.show', ['journal' => $journal, 'isAdmin' => $this->isAdmin()]);
    }

    public function edit(int $id)
    {
        $this->requireAdmin();
        $journal = $this->service->get($id);
        abort_if(! $journal, 404);

        return view('research::target-journals.builder', ['journal' => $journal, 'styles' => ResearchTargetJournalService::REFERENCE_STYLES]);
    }

    public function update(int $id, Request $request)
    {
        $this->requireAdmin();
        abort_if(! $this->service->get($id), 404);
        $this->service->update($id, $this->validateData($request));

        $this->logResearchActivity('update', 'target_journal', $id, $request->input('title'), ['method' => 'ResearchTargetJournalController@update']);

        return redirect()->route('research.target-journal.show', $id)->with('success', __('Journal updated.'));
    }

    public function destroy(int $id)
    {
        $this->requireAdmin();
        $this->service->delete($id);

        $this->logResearchActivity('delete', 'target_journal', $id, null, ['method' => 'ResearchTargetJournalController@destroy']);

        return redirect()->route('research.target-journal.index')->with('success', __('Journal removed from the directory.'));
    }

    public function seedDhet()
    {
        $this->requireAdmin();
        $n = $this->service->seedDhetStarter();

        $this->logResearchActivity('create', 'target_journal', null, null, ['method' => 'ResearchTargetJournalController@seedDhet', 'seeded' => $n]);

        return redirect()->route('research.target-journal.index')
            ->with('success', __(':n DHET-accredited journals seeded/updated (South-African accreditation module).', ['n' => $n]));
    }

    /** The directory is shared by every researcher; only admins change it. */
    private function isAdmin(): bool
    {
        return \Illuminate\Support\Facades\Auth::check() && \AhgCore\Services\AclService::canAdmin((int) \Illuminate\Support\Facades\Auth::id());
    }

    private function requireAdmin(): void
    {
        abort_unless($this->isAdmin(), 403);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title'                => 'required|string|max:300',
            'subtitle'             => 'nullable|string|max:255',
            'issn'                 => 'nullable|string|max:20',
            'eissn'                => 'nullable|string|max:20',
            'publisher'            => 'nullable|string|max:255',
            'homepage_url'         => ['nullable', 'string', 'max:1000', 'url:http,https'],
            'submission_url'       => ['nullable', 'string', 'max:1000', 'url:http,https'],
            'languages'            => 'nullable|string|max:120',
            'subject_scope'        => 'nullable|string',
            'article_types'        => 'nullable|string|max:255',
            'accreditation'        => 'nullable|string|max:255',
            'accreditation_market' => 'nullable|string|max:8',
            'reference_style'      => 'nullable|string|max:40',
            'structure_notes'      => 'nullable|string',
            'max_words'            => 'nullable|integer|min:0',
            'abstract_max_words'   => 'nullable|integer|min:0',
            'peer_review'          => 'nullable|string|max:40',
            'open_access'          => 'nullable|boolean',
            'apc_amount'           => 'nullable|string|max:60',
            'turnaround'           => 'nullable|string|max:120',
            'notes'                => 'nullable|string',
            'status'               => 'nullable|in:active,discontinued',
        ]);
    }
}
