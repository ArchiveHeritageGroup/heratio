<?php

/**
 * FavoritesController - Controller for Heratio
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

namespace AhgFavorites\Controllers;

use AhgFavorites\Services\FavoritesExportService;
use AhgFavorites\Services\FavoritesService;
use AhgFavorites\Services\FolderService;
use AhgFavorites\Services\ResearchBridgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoritesController extends Controller
{
    private FavoritesService $favoritesService;

    private FolderService $folderService;

    private ResearchBridgeService $researchBridge;

    private FavoritesExportService $exportService;

    public function __construct(
        FavoritesService $favoritesService,
        FolderService $folderService,
        ResearchBridgeService $researchBridge,
        FavoritesExportService $exportService
    ) {
        $this->favoritesService = $favoritesService;
        $this->folderService = $folderService;
        $this->researchBridge = $researchBridge;
        $this->exportService = $exportService;
    }

    public function browse(Request $request)
    {
        $userId = Auth::id();
        if (! $userId) {
            return redirect()->route('login');
        }

        $params = $request->only(['page', 'limit', 'sort', 'sortDir', 'query', 'folder_id', 'unfiled', 'view']);
        $data = $this->favoritesService->browse($userId, $params);
        $folders = $this->folderService->getUserFolders($userId);
        $unfiledCount = $this->folderService->getUnfiledCount($userId);
        $totalCount = $this->favoritesService->getCount($userId);

        return view('ahg-favorites::browse', array_merge($data, [
            'params' => $params,
            'folders' => $folders,
            'unfiledCount' => $unfiledCount,
            'totalCount' => $totalCount,
        ]));
    }

    public function add(Request $request, string $slug)
    {
        $userId = Auth::id();
        if (! $userId) {
            return redirect()->route('login');
        }

        $culture = app()->getLocale();
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (! $slugRow) {
            return redirect()->back()->with('error', 'Item not found.');
        }

        $title = DB::table('information_object_i18n')
            ->where('id', $slugRow->object_id)
            ->where('culture', $culture)
            ->value('title');

        if (! $title) {
            $title = DB::table('actor_i18n')
                ->where('id', $slugRow->object_id)
                ->where('culture', $culture)
                ->value('authorized_form_of_name') ?? $slug;
        }

        $referenceCode = DB::table('information_object')
            ->where('id', $slugRow->object_id)
            ->value('identifier');

        $added = $this->favoritesService->addToFavorites($userId, $slugRow->object_id, $title, $slug, 'information_object', $referenceCode);

        if ($added) {
            return redirect()->back()->with('success', 'Added to favorites.');
        }

        return redirect()->back()->with('info', 'Already in favorites.');
    }

    public function remove(Request $request, int $id)
    {
        $this->favoritesService->removeFromFavorites(Auth::id(), $id);

        return redirect()->route('favorites.browse')->with('success', 'Removed from favorites.');
    }

    public function clear(Request $request)
    {
        $count = $this->favoritesService->clearAll(Auth::id());

        return redirect()->route('favorites.browse')->with('success', "Cleared {$count} favorites.");
    }

    public function ajaxToggle(Request $request)
    {
        $userId = Auth::id();
        if (! $userId) {
            return response()->json(['error' => 'Login required'], 401);
        }

        $slug = $request->input('slug');
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (! $slugRow) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $culture = app()->getLocale();
        $title = DB::table('information_object_i18n')
            ->where('id', $slugRow->object_id)
            ->where('culture', $culture)
            ->value('title') ?? $slug;

        $isFavorited = $this->favoritesService->toggle($userId, $slugRow->object_id, $title, $slug);

        return response()->json(['favorited' => $isFavorited, 'count' => $this->favoritesService->getCount($userId)]);
    }

    public function ajaxStatus(Request $request, string $slug)
    {
        $userId = Auth::id();
        if (! $userId) {
            return response()->json(['favorited' => false]);
        }

        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (! $slugRow) {
            return response()->json(['favorited' => false]);
        }

        return response()->json([
            'favorited' => $this->favoritesService->isFavorited($userId, $slugRow->object_id),
        ]);
    }

    public function bulk(Request $request)
    {
        $userId = Auth::id();
        $action = $request->input('action');
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return redirect()->route('favorites.browse')->with('error', 'No items selected.');
        }

        switch ($action) {
            case 'remove':
                $count = $this->favoritesService->bulkRemove($userId, $ids);

                return redirect()->route('favorites.browse')->with('success', "Removed {$count} items.");
            case 'move':
                $folderId = $request->input('move_folder_id');
                $result = $this->favoritesService->moveToFolder($userId, $ids, $folderId ? (int) $folderId : null);

                return redirect()->route('favorites.browse')
                    ->with($result['success'] ? 'success' : 'error', $result['message']);
        }

        return redirect()->route('favorites.browse');
    }

    public function updateNotes(Request $request, int $id)
    {
        $this->favoritesService->updateNotes(Auth::id(), $id, $request->input('notes', ''));

        return response()->json(['success' => true]);
    }

    // Folder management
    public function folderCreate(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $id = $this->folderService->createFolder(Auth::id(), $request->input('name'), $request->input('description'), $request->input('color'));

        return redirect()->route('favorites.browse', ['folder_id' => $id])->with('success', 'Folder created.');
    }

    public function folderEdit(Request $request, int $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $this->folderService->updateFolder(Auth::id(), $id, $request->only(['name', 'description', 'color']));

        return redirect()->route('favorites.browse', ['folder_id' => $id])->with('success', 'Folder updated.');
    }

    public function folderDelete(int $id)
    {
        $this->folderService->deleteFolder(Auth::id(), $id);

        return redirect()->route('favorites.browse')->with('success', 'Folder deleted. Items moved to unfiled.');
    }

    public function shareFolder(Request $request, int $id)
    {
        $token = $this->folderService->shareFolder(Auth::id(), $id);
        if ($token) {
            return redirect()->route('favorites.browse', ['folder_id' => $id])
                ->with('success', 'Share link: '.url('/favorites/shared/'.$token));
        }

        return redirect()->back()->with('error', 'Could not share folder.');
    }

    public function revokeSharing(int $id)
    {
        $this->folderService->revokeSharing(Auth::id(), $id);

        return redirect()->route('favorites.browse', ['folder_id' => $id])->with('success', 'Sharing revoked.');
    }

    public function viewShared(string $token)
    {
        $folder = $this->folderService->getSharedFolder($token);
        if (! $folder) {
            abort(404, 'Shared folder not found or expired.');
        }

        $items = $this->folderService->getSharedFolderItems($folder->id, $folder->user_id);

        return view('ahg-favorites::shared', compact('folder', 'items', 'token'));
    }

    // Export
    public function exportCsv(Request $request)
    {
        return $this->exportService->streamCsv(Auth::id(), $request->get('folder_id'));
    }

    public function exportJson(Request $request)
    {
        return $this->exportService->streamJson(Auth::id(), $request->get('folder_id'));
    }

    public function importFavorites(Request $request)
    {
        $content = '';
        if ($request->hasFile('file')) {
            $content = file_get_contents($request->file('file')->getRealPath());
        } elseif ($request->input('slugs')) {
            $content = $request->input('slugs');
        }

        $count = $this->favoritesService->importFromCsv(Auth::id(), $content);

        return redirect()->route('favorites.browse')->with('success', "Imported {$count} items.");
    }

    // ------------------------------------------------------------------
    // PSIS-parity endpoints
    // ------------------------------------------------------------------

    /**
     * Redirect /favorites/folder/{id} to the browse view filtered by folder.
     * Mirrors PSIS favoritesFolderViewAction.
     */
    public function folderView(int $id)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return redirect()->route('favorites.browse', ['folder_id' => $id]);
    }

    /**
     * AJAX folder list - returns JSON for the folder-picker dropdowns used
     * by move-to-folder + new-folder modals.
     */
    public function ajaxFolders()
    {
        if (! Auth::check()) {
            return response()->json(['success' => false, 'folders' => []], 401);
        }

        $folders = $this->folderService->getUserFolders(Auth::id());

        return response()->json([
            'success' => true,
            'folders' => $folders->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'color' => $f->color,
                'icon' => $f->icon,
                'item_count' => $f->item_count,
                'visibility' => $f->visibility,
            ])->values(),
        ]);
    }

    /**
     * AJAX search inside the user's favourites. Returns the PSIS-shape
     * { success, hits, total, page, limit } so existing widgets can be
     * reused verbatim.
     */
    public function ajaxSearch(Request $request)
    {
        if (! Auth::check()) {
            return response()->json(['success' => false, 'message' => __('Not authenticated')], 401);
        }

        $params = [
            'query' => $request->input('query', ''),
            'page' => (int) $request->input('page', 1),
            'limit' => (int) $request->input('limit', 25),
            'sort' => $request->input('sort', 'created_at'),
            'sortDir' => $request->input('sortDir', 'desc'),
        ];

        if ($request->filled('folder_id')) {
            $params['folder_id'] = (int) $request->input('folder_id');
        }

        $result = $this->favoritesService->browse(Auth::id(), $params);

        return response()->json([
            'success' => true,
            'hits' => $result['hits'],
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
        ]);
    }

    /**
     * AJAX toggle for non-information_object entities (research project,
     * collection, custom external URL, etc.). Caller supplies object_id +
     * object_type + title + optional URL.
     */
    public function ajaxToggleCustom(Request $request)
    {
        if (! Auth::check()) {
            return response()->json(['success' => false, 'message' => __('Not authenticated')], 401);
        }

        $data = $request->validate([
            'object_id' => 'required|integer|min:1',
            'object_type' => 'required|string|max:50',
            'title' => 'required|string|max:1024',
            'url' => 'nullable|string|max:1024',
            'folder_id' => 'nullable|integer|min:1',
        ]);

        $result = $this->favoritesService->toggleCustom(
            Auth::id(),
            (int) $data['object_id'],
            $data['object_type'],
            $data['title'],
            $data['url'] ?? null,
            isset($data['folder_id']) ? (int) $data['folder_id'] : null
        );

        return response()->json($result);
    }

    /**
     * Dedicated move-to-folder endpoint (sister of bulk action=move).
     * Returns JSON when called via XHR, otherwise redirects with flash.
     */
    public function moveToFolder(Request $request)
    {
        if (! Auth::check()) {
            return response()->json(['success' => false, 'message' => __('Not authenticated')], 401);
        }

        $ids = (array) $request->input('ids', []);
        $folderId = $request->input('folder_id');

        $result = $this->favoritesService->moveToFolder(
            Auth::id(),
            $ids,
            $folderId !== null && $folderId !== '' ? (int) $folderId : null
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return redirect()->route('favorites.browse')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    // ------------------------------------------------------------------
    // Send to ... (collection / project / bibliography)
    // ------------------------------------------------------------------

    public function sendToCollection(Request $request)
    {
        if (! Auth::check()) {
            return response()->json(['success' => false, 'message' => __('Not authenticated')], 401);
        }

        $userId = Auth::id();

        // List mode for the picker modal
        if ($request->boolean('list')) {
            return response()->json(['collections' => $this->researchBridge->getResearcherCollections($userId)]);
        }

        $ids = (array) $request->input('ids', []);
        $collectionId = (int) $request->input('collection_id');
        $includeNotes = $request->boolean('include_notes', true);

        if (empty($ids) || ! $collectionId) {
            $msg = __('Missing parameters.');
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg]);
            }

            return redirect()->route('favorites.browse')->with('error', $msg);
        }

        $result = $this->researchBridge->sendToCollection($userId, $ids, $collectionId, $includeNotes);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return redirect()->route('favorites.browse')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function sendToProject(Request $request)
    {
        if (! Auth::check()) {
            return response()->json(['success' => false, 'message' => __('Not authenticated')], 401);
        }

        $userId = Auth::id();

        if ($request->boolean('list')) {
            return response()->json(['projects' => $this->researchBridge->getResearcherProjects($userId)]);
        }

        $ids = (array) $request->input('ids', []);
        $projectId = (int) $request->input('project_id');

        if (empty($ids) || ! $projectId) {
            $msg = __('Missing parameters.');
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg]);
            }

            return redirect()->route('favorites.browse')->with('error', $msg);
        }

        $result = $this->researchBridge->sendToProject($userId, $ids, $projectId);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return redirect()->route('favorites.browse')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function sendToBibliography(Request $request)
    {
        if (! Auth::check()) {
            return response()->json(['success' => false, 'message' => __('Not authenticated')], 401);
        }

        $userId = Auth::id();

        if ($request->boolean('list')) {
            return response()->json(['bibliographies' => $this->researchBridge->getResearcherBibliographies($userId)]);
        }

        $ids = (array) $request->input('ids', []);
        $bibliographyId = (int) $request->input('bibliography_id');
        $style = $request->input('style', 'chicago');

        if (empty($ids) || ! $bibliographyId) {
            $msg = __('Missing parameters.');
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg]);
            }

            return redirect()->route('favorites.browse')->with('error', $msg);
        }

        $result = $this->researchBridge->sendToBibliography($userId, $ids, $bibliographyId, $style);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return redirect()->route('favorites.browse')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    // ------------------------------------------------------------------
    // Per-folder export (multi-format)
    // ------------------------------------------------------------------

    /**
     * Export a specific folder in the chosen format. Confirms the caller
     * owns the folder before streaming.
     */
    public function exportFolder(Request $request, int $id)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $userId = Auth::id();
        $folder = DB::table('favorites_folder')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $folder) {
            return redirect()->route('favorites.browse')->with('error', __('Folder not found.'));
        }

        $format = strtolower($request->get('format', 'csv'));
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder->name);

        return match ($format) {
            'csv' => $this->exportService->streamCsv($userId, $id, "favorites_{$safeName}.csv"),
            'json' => $this->exportService->streamJson($userId, $id, "favorites_{$safeName}.json"),
            'bibtex' => $this->exportService->streamBibTeX($userId, $id, "favorites_{$safeName}.bib"),
            'ris' => $this->exportService->streamRis($userId, $id, "favorites_{$safeName}.ris"),
            'ead' => $this->exportService->streamEad($userId, $id, "favorites_{$safeName}.xml"),
            'print' => response($this->exportService->printHtml($userId, $id), 200, ['Content-Type' => 'text/html']),
            default => redirect()->route('favorites.browse', ['folder_id' => $id])
                ->with('error', __('Unsupported export format.')),
        };
    }
}
