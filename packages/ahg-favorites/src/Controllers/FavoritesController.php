<?php

namespace AhgFavorites\Controllers;

use AhgFavorites\Services\FavoritesService;
use AhgFavorites\Services\FolderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoritesController extends Controller
{
    private FavoritesService $favoritesService;
    private FolderService $folderService;

    public function __construct(FavoritesService $favoritesService, FolderService $folderService)
    {
        $this->favoritesService = $favoritesService;
        $this->folderService = $folderService;
    }

    public function browse(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return redirect()->route('login');
        }

        $params = $request->only(['page', 'limit', 'sort', 'sortDir', 'query', 'folder_id', 'unfiled']);
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
        if (!$userId) {
            return redirect()->route('login');
        }

        $culture = app()->getLocale();
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            return redirect()->back()->with('error', 'Item not found.');
        }

        $title = DB::table('information_object_i18n')
            ->where('id', $slugRow->object_id)
            ->where('culture', $culture)
            ->value('title');

        if (!$title) {
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
        if (!$userId) {
            return response()->json(['error' => 'Login required'], 401);
        }

        $slug = $request->input('slug');
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
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
        if (!$userId) {
            return response()->json(['favorited' => false]);
        }

        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
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
                $count = $this->favoritesService->moveToFolder($userId, $ids, $folderId ?: null);
                return redirect()->route('favorites.browse')->with('success', "Moved {$count} items.");
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
                ->with('success', 'Share link: ' . url('/favorites/shared/' . $token));
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
        if (!$folder) {
            abort(404, 'Shared folder not found or expired.');
        }

        $items = $this->folderService->getSharedFolderItems($folder->id, $folder->user_id);

        return view('ahg-favorites::shared', compact('folder', 'items', 'token'));
    }

    // Export
    public function exportCsv(Request $request)
    {
        return $this->favoritesService->exportCsv(Auth::id(), $request->get('folder_id'));
    }

    public function exportJson(Request $request)
    {
        return $this->favoritesService->exportJson(Auth::id(), $request->get('folder_id'));
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
}
