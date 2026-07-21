<?php

/**
 * GraphEditorController - tree-view graph editor for BIBFRAME records.
 *
 * Renders the Work -> Instance -> Item -> Contribution -> Subject tree for a
 * library_item row and lets cataloguers edit the most common predicates
 * inline. Backs onto library_item, library_item_creator, and the
 * object_term_relation -> term tables - i.e. the actual Heratio catalogue,
 * which is also what the BIBFRAME export reads (see BiblioWorkRepository).
 *
 * Routes (registered in routes/web.php):
 *   GET  /bibframe/editor/{libraryItemId}
 *   POST /bibframe/editor/{libraryItemId}/work
 *   POST /bibframe/editor/{libraryItemId}/contributor
 *   POST /bibframe/editor/{libraryItemId}/contributor/{creatorId}/delete
 *   POST /bibframe/editor/{libraryItemId}/subject
 *   POST /bibframe/editor/{libraryItemId}/subject/{termId}/delete
 *
 * Issue: heratio#760 (final acceptance criterion)
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgBiblioBf\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class GraphEditorController extends Controller
{
    public function show(int $libraryItemId)
    {
        $row = $this->loadRow($libraryItemId);
        if (!$row) abort(404);

        $contributors = DB::table('library_item_creator')
            ->where('library_item_id', $libraryItemId)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'role', 'actor_id', 'authority_uri', 'sort_order']);

        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'term_i18n.id', '=', 'object_term_relation.term_id')
            ->where('object_term_relation.object_id', $row->information_object_id)
            ->where('term_i18n.culture', app()->getLocale())
            ->orderBy('term_i18n.name')
            ->get([
                'object_term_relation.term_id',
                'term_i18n.name',
            ]);

        return view('ahg-biblio-bf::graph-editor', [
            'item'         => $row,
            'contributors' => $contributors,
            'subjects'     => $subjects,
        ]);
    }

    /**
     * Update the Work + Instance level predicates (title, ISBN, publisher, date, language).
     */
    public function updateWork(Request $request, int $libraryItemId): RedirectResponse
    {
        $row = $this->loadRow($libraryItemId);
        if (!$row) abort(404);

        $data = $request->validate([
            'title'            => 'required|string|max:500',
            'subtitle'         => 'nullable|string|max:500',
            'isbn'             => 'nullable|string|max:32',
            'issn'             => 'nullable|string|max:20',
            'publisher'        => 'nullable|string|max:500',
            'publication_date' => 'nullable|string|max:50',
            'publication_place'=> 'nullable|string|max:200',
            'language'         => 'nullable|string|max:50',
            'edition'          => 'nullable|string|max:200',
        ]);

        // Update library_item-level columns (Instance / Item predicates).
        DB::table('library_item')->where('id', $libraryItemId)->update([
            'subtitle'         => $data['subtitle'] ?? null,
            'isbn'             => $data['isbn'] ?? null,
            'issn'             => $data['issn'] ?? null,
            'publisher'        => $data['publisher'] ?? null,
            'publication_date' => $data['publication_date'] ?? null,
            'publication_place'=> $data['publication_place'] ?? null,
            'language'         => $data['language'] ?? null,
            'edition'          => $data['edition'] ?? null,
            'updated_at'       => now(),
        ]);

        // Update Work-level title (lives on information_object_i18n).
        DB::table('information_object_i18n')
            ->where('id', $row->information_object_id)
            ->where('culture', app()->getLocale())
            ->update(['title' => $data['title']]);

        // Recompute work_key since title and creator can affect clustering.
        try {
            app(\AhgBiblioFrbr\Services\WorkKeyService::class)->backfillOne($libraryItemId);
        } catch (\Throwable) {
            // WorkKeyService not loaded if ahg-biblio-frbr is absent - skip.
        }

        return redirect()->route('bibframe.editor.show', $libraryItemId)
            ->with('success', __('Work properties saved.'));
    }

    public function addContributor(Request $request, int $libraryItemId): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:300',
            'role'           => 'nullable|string|max:60',
            'authority_uri'  => 'nullable|url|max:500',
        ]);
        $row = $this->loadRow($libraryItemId);
        if (!$row) abort(404);

        $maxSort = (int) DB::table('library_item_creator')
            ->where('library_item_id', $libraryItemId)
            ->max('sort_order');

        DB::table('library_item_creator')->insert([
            'library_item_id' => $libraryItemId,
            'name'            => $data['name'],
            'role'            => $data['role'] ?? null,
            'authority_uri'   => $data['authority_uri'] ?? null,
            'sort_order'      => $maxSort + 1,
            'created_at'      => now(),
        ]);

        return redirect()->route('bibframe.editor.show', $libraryItemId)
            ->with('success', __('Contributor added.'));
    }

    public function deleteContributor(int $libraryItemId, int $creatorId): RedirectResponse
    {
        DB::table('library_item_creator')
            ->where('id', $creatorId)
            ->where('library_item_id', $libraryItemId)
            ->delete();

        return redirect()->route('bibframe.editor.show', $libraryItemId)
            ->with('success', __('Contributor removed.'));
    }

    public function addSubject(Request $request, int $libraryItemId): RedirectResponse
    {
        $data = $request->validate([
            'subject_name' => 'required|string|max:300',
        ]);
        $row = $this->loadRow($libraryItemId);
        if (!$row) abort(404);

        $termId = DB::table('term')
            ->join('term_i18n', 'term_i18n.id', '=', 'term.id')
            ->where('term_i18n.name', $data['subject_name'])
            ->value('term.id');

        if (!$termId) {
            return redirect()->route('bibframe.editor.show', $libraryItemId)
                ->with('error', __('No matching subject term found in the taxonomy.'));
        }

        DB::table('object_term_relation')->insertOrIgnore([
            'object_id' => $row->information_object_id,
            'term_id'   => $termId,
        ]);

        return redirect()->route('bibframe.editor.show', $libraryItemId)
            ->with('success', __('Subject added.'));
    }

    public function deleteSubject(int $libraryItemId, int $termId): RedirectResponse
    {
        $row = $this->loadRow($libraryItemId);
        if (!$row) abort(404);
        DB::table('object_term_relation')
            ->where('object_id', $row->information_object_id)
            ->where('term_id', $termId)
            ->delete();

        return redirect()->route('bibframe.editor.show', $libraryItemId)
            ->with('success', __('Subject removed.'));
    }

    private function loadRow(int $libraryItemId): ?object
    {
        return DB::table('library_item')
            ->where('library_item.id', $libraryItemId)
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object_i18n.id', '=', 'library_item.information_object_id')
                  ->where('information_object_i18n.culture', '=', app()->getLocale());
            })
            ->select(
                'library_item.id',
                'library_item.information_object_id',
                'library_item.subtitle',
                'library_item.isbn',
                'library_item.issn',
                'library_item.publisher',
                'library_item.publication_date',
                'library_item.publication_place',
                'library_item.language',
                'library_item.edition',
                'library_item.work_key',
                'information_object_i18n.title'
            )
            ->first();
    }
}
