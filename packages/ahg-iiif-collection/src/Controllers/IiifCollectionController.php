<?php

namespace AhgIiifCollection\Controllers;

use App\Http\Controllers\Controller;
use AhgIiifCollection\Services\IiifCollectionService;
use Illuminate\Http\Request;

/**
 * IIIF Collection Management Controller.
 * Migrated from ahgIiifPlugin/modules/iiifCollection/actions/actions.class.php
 */
class IiifCollectionController extends Controller
{
    protected IiifCollectionService $service;

    public function __construct(IiifCollectionService $service)
    {
        $this->service = $service;
    }

    /**
     * List all collections.
     */
    public function index(Request $request)
    {
        $parentId = $request->input('parent_id') ? (int) $request->input('parent_id') : null;
        $collections = $this->service->getAllCollections($parentId);

        $parentCollection = null;
        if ($parentId) {
            $parentCollection = $this->service->getCollection($parentId);
        }

        return view('ahg-iiif-collection::iiif-collection.index', compact(
            'collections',
            'parentId',
            'parentCollection'
        ));
    }

    /**
     * View a single collection.
     */
    public function view($id)
    {
        $collection = $this->service->getCollection($id);

        if (!$collection) {
            abort(404);
        }

        $breadcrumbs = $this->service->getBreadcrumbs($collection);

        return view('ahg-iiif-collection::iiif-collection.view', compact(
            'collection',
            'breadcrumbs'
        ));
    }

    /**
     * Create new collection form.
     */
    public function create(Request $request)
    {
        $parentId = $request->input('parent_id');
        $allCollections = $this->service->getAllCollections();

        return view('ahg-iiif-collection::iiif-collection.create', compact(
            'parentId',
            'allCollections'
        ));
    }

    /**
     * Store new collection.
     */
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $id = $this->service->createCollection([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'attribution' => $request->input('attribution'),
            'viewing_hint' => $request->input('viewing_hint', 'individuals'),
            'parent_id' => $request->input('parent_id') ?: null,
            'is_public' => $request->input('is_public', 1),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('iiif-collection.view', $id);
    }

    /**
     * Edit collection form.
     */
    public function edit($id)
    {
        $collection = $this->service->getCollection($id);

        if (!$collection) {
            abort(404);
        }

        $allCollections = $this->service->getAllCollections();

        return view('ahg-iiif-collection::iiif-collection.edit', compact(
            'collection',
            'allCollections'
        ));
    }

    /**
     * Update collection.
     */
    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $this->service->updateCollection($id, [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'attribution' => $request->input('attribution'),
            'viewing_hint' => $request->input('viewing_hint'),
            'parent_id' => $request->input('parent_id') ?: null,
            'is_public' => $request->input('is_public', 0),
        ]);

        return redirect()->route('iiif-collection.view', $id);
    }

    /**
     * Delete collection.
     */
    public function destroy($id)
    {
        $collection = $this->service->getCollection($id);
        $parentId = $collection ? $collection->parent_id : null;

        $this->service->deleteCollection($id);

        if ($parentId) {
            return redirect()->route('iiif-collection.view', $parentId);
        }

        return redirect()->route('iiif-collection.index');
    }

    /**
     * Add items form.
     */
    public function addItems(Request $request, $id)
    {
        $collection = $this->service->getCollection($id);

        if (!$collection) {
            abort(404);
        }

        if ($request->isMethod('post')) {
            $objectIds = $request->input('object_ids', []);
            $includeChildren = $request->input('include_children', []);
            $manifestUri = $request->input('manifest_uri');

            if ($manifestUri) {
                $this->service->addItem($id, [
                    'manifest_uri' => $manifestUri,
                    'label' => $request->input('label'),
                    'item_type' => $request->input('item_type', 'manifest'),
                ]);
            }

            if (is_array($objectIds)) {
                foreach ($objectIds as $objectId) {
                    $objectId = (int) $objectId;
                    $this->service->addItem($id, ['object_id' => $objectId]);

                    if (in_array($objectId, (array) $includeChildren)) {
                        $this->service->addChildrenToCollection($id, $objectId);
                    }
                }
            }

            return redirect()->route('iiif-collection.view', $id);
        }

        $searchQuery = $request->input('q', '');
        $searchResults = [];
        if ($searchQuery) {
            $searchResults = $this->service->searchObjects($searchQuery);
        }

        return view('ahg-iiif-collection::iiif-collection.add-items', compact(
            'collection',
            'searchQuery',
            'searchResults'
        ));
    }

    /**
     * Remove item from collection.
     */
    public function removeItem(Request $request)
    {
        $itemId = $request->input('item_id');
        $collectionId = $request->input('collection_id');

        $this->service->removeItem($itemId);

        return redirect()->route('iiif-collection.view', $collectionId);
    }

    /**
     * Reorder items (AJAX).
     */
    public function reorder(Request $request)
    {
        $collectionId = $request->input('collection_id');
        $itemIds = $request->input('item_ids', []);

        $this->service->reorderItems($collectionId, $itemIds);

        return response()->json(['success' => true]);
    }

    /**
     * Output IIIF Collection JSON.
     */
    public function manifest($slug)
    {
        $collection = $this->service->getCollection($slug);

        if (!$collection || (!$collection->is_public && !auth()->check())) {
            return response()->json(['error' => 'Collection not found'], 404);
        }

        $json = $this->service->generateCollectionJson($collection->id);

        return response()->json($json, 200, [
            'Content-Type' => 'application/ld+json',
            'Access-Control-Allow-Origin' => '*',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * AJAX autocomplete for objects.
     */
    public function autocomplete(Request $request)
    {
        $query = $request->input('q', '');
        $results = $this->service->autocompleteObjects($query);

        return response()->json(['results' => $results]);
    }
}
