<?php

/**
 * AuthorityHierarchyController - edit form, broader/narrower management and
 * external vocabulary sync for authority records (heratio#1475 item 3).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgCore\Support\DropdownOptions;
use AhgLibrary\Services\AuthorityControlService;
use AhgLibrary\Services\AuthorityHierarchyService;
use AhgLibrary\Services\AuthorityRecordService;
use AhgLibrary\Services\AuthorityVocabularySyncService;
use AhgLibrary\Services\LcshVocabularyClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AuthorityHierarchyController extends Controller
{
    public function __construct(
        private AuthorityControlService $authorities,
        private AuthorityRecordService $records,
        private AuthorityHierarchyService $hierarchy,
    ) {
    }

    public function edit(int $id)
    {
        $record = $this->authorities->find($id);
        abort_unless($record, 404);
        $typing = $this->records->available() ? $this->records->get($id) : null;

        return view('ahg-library::authority.edit', [
            'record' => $record,
            'recordType' => $this->records->typeFor($record, $typing),
            'recordTypes' => DropdownOptions::resolve('library_authority_record_type', []),
            'subjectTypes' => DropdownOptions::resolve('library_authority_subject_type', []),
            'sources' => DropdownOptions::resolve('library_authority_source', []),
        ]);
    }

    public function addRelation(Request $request, int $id)
    {
        abort_unless($this->authorities->find($id), 404);
        $validated = $request->validate([
            'direction' => 'required|in:broader,narrower',
            'term' => 'required|string|max:500',
        ]);

        $otherId = $this->hierarchy->resolveReference($validated['term']);
        if (! $otherId) {
            return back()->with('error', __('No single authority record matches that heading or ID.'));
        }

        try {
            $validated['direction'] === 'broader'
                ? $this->hierarchy->addRelation($otherId, $id)
                : $this->hierarchy->addRelation($id, $otherId);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', __($e->getMessage()));
        }

        return redirect()->route('library.authority-view', $id)->with('success', __('Relation added.'));
    }

    public function removeRelation(Request $request, int $relationId)
    {
        $validated = $request->validate(['authority_id' => 'required|integer']);
        $relation = $this->hierarchy->removeRelation($relationId);
        abort_unless($relation, 404);

        $back = (int) $validated['authority_id'];
        if (! in_array($back, [(int) $relation->broader_id, (int) $relation->narrower_id], true)) {
            $back = (int) $relation->broader_id;
        }

        return redirect()->route('library.authority-view', $back)
            ->with('success', __('Relation removed.'));
    }

    public function sync(Request $request, int $id, AuthorityVocabularySyncService $sync)
    {
        abort_unless($this->authorities->find($id), 404);
        $validated = $request->validate(['external_id' => 'nullable|string|max:20']);
        $externalId = trim((string) ($validated['external_id'] ?? ''));

        if ($externalId !== '') {
            if (! LcshVocabularyClient::validId($externalId)) {
                return back()->with('error', __('Enter a valid LCSH identifier, for example sh85034652.'));
            }
            if ($this->records->externalIdTaken(LcshVocabularyClient::SCHEME, $externalId, $id)) {
                return back()->with('error', __('Another authority record is already linked to that identifier.'));
            }
        }

        $result = $sync->sync($id, $externalId !== '' ? $externalId : null);
        if (! $result['ok']) {
            return redirect()->route('library.authority-view', $id)
                ->with('error', __('Sync failed: :reason', ['reason' => $result['error']]));
        }

        return redirect()->route('library.authority-view', $id)->with('success', __(
            'Synced: :relations relations, :created new records, :skipped skipped.',
            ['relations' => $result['relations'], 'created' => $result['created'], 'skipped' => $result['skipped']]
        ));
    }

    public function clearOverrides(int $id)
    {
        abort_unless($this->authorities->find($id), 404);
        $this->records->clearOverrides($id);

        return redirect()->route('library.authority-view', $id)
            ->with('success', __('Local overrides cleared. The next sync will update these fields.'));
    }
}
