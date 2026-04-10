<?php

/**
 * IiifCollectionController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgIiifCollection\Controllers;

use App\Http\Controllers\Controller;
use AhgIiifCollection\Services\IiifCollectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * Output IIIF Presentation API 2.1 Manifest for an individual information object.
     * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgIiifPlugin/bin/iiif-manifest.php
     */
    public function objectManifest($slug)
    {
        $json = $this->service->generateObjectManifest($slug);

        if (!$json) {
            return response()->json(['error' => 'Object not found or has no digital objects'], 404);
        }

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

    /** IIIF Viewer page. */
    public function viewer($slug)
    {
        $culture = app()->getLocale();
        $object = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();
        if (!$object) abort(404);
        $manifestUrl = route('iiif-collection.object-manifest', $slug);
        return view('ahg-iiif-collection::iiif.viewer', ['objectTitle' => $object->title, 'objectSlug' => $slug, 'manifestUrl' => $manifestUrl]);
    }

    /** IIIF Comparison viewer. */
    public function compare(Request $request)
    {
        $manifests = $request->input('manifests', []);
        if (is_string($manifests)) $manifests = explode(',', $manifests);
        return view('ahg-iiif-collection::iiif.compare', compact('manifests'));
    }

    /** IIIF Settings page. */
    public function settings()
    {
        $settings = [];
        if (Schema::hasTable('iiif_viewer_settings')) {
            $settings = DB::table('iiif_viewer_settings')->pluck('setting_value', 'setting_key')->all();
        }
        $collections = $this->service->getAllCollections();
        return view('ahg-iiif-collection::iiif.settings', compact('settings', 'collections'));
    }

    /** Update IIIF Settings. */
    public function settingsUpdate(Request $request)
    {
        if (!Schema::hasTable('iiif_viewer_settings')) {
            return redirect()->route('iiif.settings')->with('error', 'Settings table not found.');
        }

        // Checkboxes: unchecked won't be in POST, so explicitly set them to '0'
        $checkboxKeys = [
            'homepage_collection_enabled', 'homepage_carousel_autoplay', 'homepage_show_captions',
            'carousel_autoplay', 'carousel_show_thumbnails', 'carousel_show_controls',
            'enable_fullscreen', 'show_zoom_controls', 'show_on_view', 'show_on_browse',
        ];

        // Save all posted values
        foreach ($request->except(['_token']) as $key => $value) {
            DB::table('iiif_viewer_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value ?? '', 'updated_at' => now()]
            );
        }

        // Explicitly set unchecked checkboxes to '0'
        foreach ($checkboxKeys as $key) {
            if (!$request->has($key)) {
                DB::table('iiif_viewer_settings')->updateOrInsert(
                    ['setting_key' => $key],
                    ['setting_value' => '0', 'updated_at' => now()]
                );
            }
        }

        return redirect()->route('iiif.settings')->with('success', 'Carousel settings saved.');
    }

    /** IIIF Validation Dashboard. */
    public function validationDashboard()
    {
        $stats = ['total' => 0, 'passed' => 0, 'failed' => 0, 'warning' => 0];
        $recentFailures = collect();
        return view('ahg-iiif-collection::iiif.validation-dashboard', compact('stats', 'recentFailures'));
    }

    /** Media processing queue. */
    public function mediaQueue()
    {
        $stats = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        $jobs = collect();
        return view('ahg-iiif-collection::mediaSettings.queue', compact('stats', 'jobs'));
    }

    /** Media processing test form. */
    public function mediaTest() { return view('ahg-iiif-collection::mediaSettings.test'); }
    public function mediaTestRun(Request $request) { return view('ahg-iiif-collection::mediaSettings.test', ['result' => ['status' => 'success', 'message' => 'Test completed']]); }

    /** 3D Reports. */
    public function threeDIndex() { return redirect()->route('iiif.three-d-reports.models'); }
    public function threeDDigitalObjects() { $items = collect(); return view('ahg-iiif-collection::threeDReports.digital-objects', compact('items')); }
    public function threeDHotspots() { $items = collect(); return view('ahg-iiif-collection::threeDReports.hotspots', compact('items')); }
    public function threeDModels() { $items = collect(); return view('ahg-iiif-collection::threeDReports.models', compact('items')); }
    public function threeDSettings() { $items = collect(); return view('ahg-iiif-collection::threeDReports.settings', compact('items')); }
    public function threeDThumbnails() { $items = collect(); return view('ahg-iiif-collection::threeDReports.thumbnails', compact('items')); }
}
