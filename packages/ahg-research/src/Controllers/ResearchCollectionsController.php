<?php

/**
 * ResearchCollectionsController - Controller for Heratio
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
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchCollectionsController - Researcher Collections (Evidence Sets).
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Covers the full Collections / Evidence Sets surface: the
 * collections index + viewer (legacy POST-action handlers), the dedicated
 * REST-ish CRUD route methods, the item add/remove route methods, and the two
 * AJAX endpoints (addToCollection / createCollectionAjax) that are also invoked
 * from Blade/JS via the named routes research.addToCollection and
 * research.createCollectionAjax (see _request-button.blade.php). Every method is
 * auth-gated and scoped to the current researcher's own collections via the
 * research_collection / research_collection_item tables. No cross-calls to
 * other ResearchController methods existed - the bodies use only the shared
 * trait helper (getSidebarData) and the injected ResearchService
 * (getResearcherByUserId / getCollection / getCollections / createCollection /
 * addToCollection / removeFromCollection), so the move is a verbatim lift.
 */
class ResearchCollectionsController extends Controller
{
    use LogsResearchActivity;
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    // =========================================================================
    // COLLECTIONS (Evidence Sets)
    // =========================================================================

    public function collections(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post') && $request->input('do') === 'create') {
            $id = $this->service->createCollection($researcher->id, [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
            ]);
            return redirect()->route('research.viewCollection', $id);
        }

        $collections = $this->service->getCollections($researcher->id);

        return view('research::research.collections', array_merge(
            $this->getSidebarData('collections'),
            compact('researcher', 'collections')
        ));
    }

    public function viewCollection(Request $request, ?int $id = null)
    {
        $id = $id ?: (int) ($request->input('id') ?: $request->getQueryString());
        if (!$id) abort(404);

        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($id);
        if (!$collection) abort(404, 'Not found');
        if ($collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        if ($request->isMethod('post')) {
            $action = $request->input('booking_action');

            if ($action === 'remove') {
                $this->service->removeFromCollection($id, (int) $request->input('object_id'));
                return redirect()->route('research.viewCollection', $id)->with('success', 'Item removed from collection');
            }

            if ($action === 'add_item') {
                $objectId = (int) $request->input('object_id');
                $notes = trim($request->input('notes', ''));
                $includeDescendants = $request->input('include_descendants') ? true : false;
                if ($objectId > 0) {
                    $addedCount = 0;
                    $objectsToAdd = [$objectId];
                    if ($includeDescendants) {
                        $item = DB::table('information_object')->where('id', $objectId)->first();
                        if ($item) {
                            $descendants = DB::table('information_object')
                                ->where('lft', '>', $item->lft)
                                ->where('rgt', '<', $item->rgt)
                                ->pluck('id')->toArray();
                            $objectsToAdd = array_merge($objectsToAdd, $descendants);
                        }
                    }
                    foreach ($objectsToAdd as $oid) {
                        $exists = DB::table('research_collection_item')
                            ->where('collection_id', $id)
                            ->where('object_id', $oid)->exists();
                        if (!$exists) {
                            DB::table('research_collection_item')->insert([
                                'collection_id' => $id,
                                'object_id' => $oid,
                                'notes' => ($oid == $objectId) ? $notes : '',
                                'created_at' => date('Y-m-d H:i:s'),
                            ]);
                            $addedCount++;
                        }
                    }
                    $msg = $addedCount > 0 ? "$addedCount item(s) added to collection" : 'Item(s) already in collection';
                    $type = $addedCount > 0 ? 'success' : 'error';
                    return redirect()->route('research.viewCollection', $id)->with($type, $msg);
                }
            }

            if ($action === 'update_notes') {
                DB::table('research_collection_item')
                    ->where('collection_id', $id)
                    ->where('object_id', (int) $request->input('object_id'))
                    ->update(['notes' => trim($request->input('notes'))]);
                return redirect()->route('research.viewCollection', $id)->with('success', 'Notes updated');
            }

            if ($action === 'update') {
                $name = trim($request->input('name'));
                if ($name) {
                    DB::table('research_collection')->where('id', $id)->update([
                        'name' => $name,
                        'description' => trim($request->input('description')),
                        'is_public' => $request->input('is_public') ? 1 : 0,
                    ]);
                    return redirect()->route('research.viewCollection', $id)->with('success', 'Collection updated');
                }
            }

            if ($action === 'delete') {
                DB::table('research_collection_item')->where('collection_id', $id)->delete();
                DB::table('research_collection')->where('id', $id)->delete();
                return redirect()->route('research.collections')->with('success', 'Collection deleted');
            }
        }

        return view('research::research.view-collection', array_merge(
            $this->getSidebarData('collections'),
            compact('researcher', 'collection')
        ));
    }

    // =========================================================================
    // AJAX: ADD TO COLLECTION
    // =========================================================================

    public function addToCollection(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Not authenticated']);
        }

        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher || $researcher->status !== 'approved') {
            return response()->json(['success' => false, 'error' => 'Not an approved researcher']);
        }

        $collectionId = (int) $request->input('collection_id');
        $objectId = (int) $request->input('object_id');
        $notes = $request->input('notes', '');

        $collection = DB::table('research_collection')
            ->where('id', $collectionId)
            ->where('researcher_id', $researcher->id)
            ->first();
        if (!$collection) {
            return response()->json(['success' => false, 'error' => 'Collection not found']);
        }

        $exists = DB::table('research_collection_item')
            ->where('collection_id', $collectionId)
            ->where('object_id', $objectId)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'error' => 'Item already in collection']);
        }

        $this->service->addToCollection($collectionId, $objectId, $notes);
        return response()->json(['success' => true, 'message' => 'Item added to collection']);
    }

    public function createCollectionAjax(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Not authenticated']);
        }

        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher || $researcher->status !== 'approved') {
            return response()->json(['success' => false, 'error' => 'Not an approved researcher']);
        }

        $name = trim($request->input('name'));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => 'Collection name is required']);
        }

        $collectionId = $this->service->createCollection($researcher->id, [
            'name' => $name,
            'description' => trim($request->input('description', '')),
            'is_public' => $request->input('is_public') ? 1 : 0,
        ]);

        $objectId = (int) $request->input('object_id');
        if ($objectId > 0) {
            $this->service->addToCollection($collectionId, $objectId);
        }

        $this->logResearchActivity('create', 'collection', (int) $collectionId, $name, ['method' => 'ResearchCollectionsController@createCollectionAjax']);

        return response()->json([
            'success' => true,
            'message' => 'Collection created',
            'collection_id' => $collectionId,
        ]);
    }

    // =========================================================================
    // DEDICATED ROUTE METHODS (Collections CRUD)
    // =========================================================================

    public function createCollection()
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collections = $this->service->getCollections($researcher->id);

        return view('research::research.collections', array_merge(
            $this->getSidebarData('collections'),
            compact('researcher', 'collections'),
            ['showCreateForm' => true]
        ));
    }

    public function storeCollection(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $id = $this->service->createCollection($researcher->id, [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        $this->logResearchActivity('create', 'collection', (int) $id, $request->input('name'), ['method' => 'ResearchCollectionsController@storeCollection']);

        return redirect()->route('research.viewCollection', $id)
            ->with('success', 'Collection created');
    }

    public function updateCollection(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($id);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        $name = trim($request->input('name'));
        if ($name) {
            DB::table('research_collection')->where('id', $id)->update([
                'name' => $name,
                'description' => trim($request->input('description')),
                'is_public' => $request->input('is_public') ? 1 : 0,
            ]);
            $this->logResearchActivity('update', 'collection', (int) $id, $name, ['method' => 'ResearchCollectionsController@updateCollection']);
            return redirect()->route('research.viewCollection', $id)->with('success', 'Collection updated');
        }

        return redirect()->route('research.viewCollection', $id)->with('error', 'Name is required');
    }

    public function destroyCollection(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($id);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        DB::table('research_collection_item')->where('collection_id', $id)->delete();
        DB::table('research_collection')->where('id', $id)->delete();

        $this->logResearchActivity('delete', 'collection', (int) $id, $collection->name ?? null, ['method' => 'ResearchCollectionsController@destroyCollection']);

        return redirect()->route('research.collections')->with('success', 'Collection deleted');
    }

    public function addItemToCollection(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($id);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        $objectId = (int) $request->input('object_id');
        $notes = trim($request->input('notes', ''));
        $includeDescendants = $request->input('include_descendants') ? true : false;

        if ($objectId > 0) {
            $addedCount = 0;
            $objectsToAdd = [$objectId];
            if ($includeDescendants) {
                $item = DB::table('information_object')->where('id', $objectId)->first();
                if ($item) {
                    $descendants = DB::table('information_object')
                        ->where('lft', '>', $item->lft)
                        ->where('rgt', '<', $item->rgt)
                        ->pluck('id')->toArray();
                    $objectsToAdd = array_merge($objectsToAdd, $descendants);
                }
            }
            foreach ($objectsToAdd as $oid) {
                $exists = DB::table('research_collection_item')
                    ->where('collection_id', $id)
                    ->where('object_id', $oid)->exists();
                if (!$exists) {
                    DB::table('research_collection_item')->insert([
                        'collection_id' => $id,
                        'object_id' => $oid,
                        'notes' => ($oid == $objectId) ? $notes : '',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $addedCount++;
                }
            }
            $msg = $addedCount > 0 ? "$addedCount item(s) added to collection" : 'Item(s) already in collection';
            $type = $addedCount > 0 ? 'success' : 'error';
            return redirect()->route('research.viewCollection', $id)->with($type, $msg);
        }

        return redirect()->route('research.viewCollection', $id)->with('error', 'No item selected');
    }

    public function removeItemFromCollection(int $collectionId, int $itemId)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collection = $this->service->getCollection($collectionId);
        if (!$collection || $collection->researcher_id != $researcher->id) {
            return redirect()->route('research.collections')->with('error', 'Access denied');
        }

        $this->service->removeFromCollection($collectionId, $itemId);

        return redirect()->route('research.viewCollection', $collectionId)->with('success', 'Item removed from collection');
    }
}
