<?php

/**
 * ResearchNotebooksController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;

/**
 * ResearchNotebooksController - Private researcher notebook (scratchpad).
 *
 * Extracted from ResearchController as stage 4 of the monolith decomposition
 * (issue #1253 / #1269). All four endpoints are auth-gated and operate on the
 * current researcher's own notebooks via NotebookService. The promote action
 * converts a notebook into a public research project.
 */
class ResearchNotebooksController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function notebooks(Request $request)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        $svc = app(\AhgResearch\Services\NotebookService::class);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            if ($action === 'create') {
                $id = $svc->create((int) $researcher->id, [
                    'title'   => $request->input('title', 'Untitled notebook'),
                    'summary' => $request->input('summary'),
                ]);
                return redirect()->route('research.notebookShow', $id)->with('success', 'Notebook created.');
            }
        }

        $notebooks = $svc->listForResearcher((int) $researcher->id);

        return view('research::research.notebooks', array_merge(
            $this->getSidebarData('notebooks'),
            compact('notebooks', 'researcher')
        ));
    }

    public function notebookShow(Request $request, int $id)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        $svc = app(\AhgResearch\Services\NotebookService::class);
        $notebook = $svc->get($id);
        if (!$notebook || (int) $notebook->researcher_id !== (int) $researcher->id) abort(404);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            if ($action === 'update') {
                $svc->update($id, [
                    'title'   => $request->input('title'),
                    'summary' => $request->input('summary'),
                ]);
                return redirect()->route('research.notebookShow', $id)->with('success', 'Notebook updated.');
            }
            if ($action === 'add_item') {
                $svc->addItem($id, [
                    'item_type'        => $request->input('item_type', 'note'),
                    'title'            => $request->input('item_title'),
                    'body'             => $request->input('item_body'),
                    'source_object_id' => $request->input('source_object_id') ?: null,
                    'saved_search_id'  => $request->input('saved_search_id') ?: null,
                    'pinned'           => (bool) $request->input('pinned'),
                ]);
                return redirect()->route('research.notebookShow', $id)->with('success', 'Note added.');
            }
            if ($action === 'remove_item') {
                $svc->removeItem((int) $request->input('item_id'));
                return redirect()->route('research.notebookShow', $id)->with('success', 'Note removed.');
            }
            if ($action === 'pin_item') {
                $itemId = (int) $request->input('item_id');
                $svc->updateItem($itemId, ['pinned' => (bool) $request->input('pinned')]);
                return redirect()->route('research.notebookShow', $id);
            }
        }

        $items = $svc->getItems($id);
        $itemTypes = \AhgResearch\Services\NotebookService::ITEM_TYPES;

        return view('research::research.notebook-show', array_merge(
            $this->getSidebarData('notebooks'),
            compact('notebook', 'items', 'researcher', 'itemTypes')
        ));
    }

    public function notebookDelete(Request $request, int $id)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        $svc = app(\AhgResearch\Services\NotebookService::class);
        $notebook = $svc->get($id);
        if (!$notebook || (int) $notebook->researcher_id !== (int) $researcher->id) abort(404);

        $svc->delete($id);
        return redirect()->route('research.notebooks')->with('success', 'Notebook deleted.');
    }

    public function notebookPromote(Request $request, int $id)
    {
        $researcher = $this->getResearcherOrRedirect();
        if (!is_object($researcher)) return $researcher;

        $svc = app(\AhgResearch\Services\NotebookService::class);
        $projectId = $svc->promoteToProject($id, (int) $researcher->id);

        if (!$projectId) {
            return redirect()->route('research.notebookShow', $id)->with('error', 'Could not promote notebook.');
        }

        return redirect()->route('research.viewProject', $projectId)
            ->with('success', 'Notebook promoted to project.');
    }
}
