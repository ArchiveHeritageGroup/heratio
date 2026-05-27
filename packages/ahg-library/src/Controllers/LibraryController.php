<?php

/**
 * LibraryController - Controller for Heratio
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



namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryIllService;
use AhgLibrary\Services\LibraryService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryController extends Controller
{
    protected LibraryService $service;
    protected \AhgLibrary\Services\LibraryCirculationService $circ;
    protected \AhgLibrary\Services\LibraryPatronService $patrons;
    protected \AhgLibrary\Services\LibraryOpacService $opac;
    protected \AhgLibrary\Services\LibraryIllService $ill;
    protected \AhgLibrary\Services\LibrarySerialService $serial;
    protected \AhgLibrary\Services\LibraryAcquisitionService $acq;
    protected \AhgLibrary\Services\LibraryIsbnProviderService $isbnProvider;

    public function __construct()
    {
        $this->service = new LibraryService(app()->getLocale());
        $this->circ = new \AhgLibrary\Services\LibraryCirculationService();
        $this->patrons = new \AhgLibrary\Services\LibraryPatronService();
        $this->opac = new \AhgLibrary\Services\LibraryOpacService();
        $this->ill = new \AhgLibrary\Services\LibraryIllService();
        $this->serial = new \AhgLibrary\Services\LibrarySerialService();
        $this->acq = new \AhgLibrary\Services\LibraryAcquisitionService();
        $this->isbnProvider = new \AhgLibrary\Services\LibraryIsbnProviderService();
    }

    // ── ILL helpers ──────────────────────────────────────────────────────
    private function countBorrowActive(array $status_counts): int
    {
        $active = [LibraryIllService::STATUS_PENDING, LibraryIllService::STATUS_REQUESTED,
                   LibraryIllService::STATUS_SHIPPED, LibraryIllService::STATUS_RECEIVED,
                   LibraryIllService::STATUS_OVERDUE];
        return array_sum(array_intersect_key($status_counts, array_flip($active)));
    }

    private function countLendActive(array $status_counts): int
    {
        $active = [LibraryIllService::STATUS_PENDING, LibraryIllService::STATUS_SHIPPED,
                   LibraryIllService::STATUS_RECEIVED, LibraryIllService::STATUS_OVERDUE];
        return array_sum(array_intersect_key($status_counts, array_flip($active)));
    }

    public function browse(Request $request)
    {
        $result = $this->service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'lastUpdated'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
            'material_type' => $request->get('material_type', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-library::library.browse', [
            'pager' => $pager,
            'sortOptions' => [
                'alphabetic' => 'Title',
                'identifier' => 'Identifier',
                'materialType' => 'Material type',
                'lastUpdated' => 'Date modified',
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $item = $this->service->getBySlug($slug);
        if (!$item) {
            abort(404);
        }

        $levelName = $this->service->getTermName($item->level_of_description_id);

        $creators = collect();
        $subjects = collect();
        if ($item->library_item_id) {
            $creators = $this->service->getCreators($item->library_item_id);
            $subjects = $this->service->getSubjects($item->library_item_id);
        }

        // Parent item for "Related records" sidebar card
        $parentItem = $this->service->getParentItem($item->id);

        // Child record count
        $childCount = $this->service->getChildCount($item->id);

        // Digital objects (master, reference, thumbnail)
        $digitalObjects = ['master' => null, 'reference' => null, 'thumbnail' => null];
        $doRows = DB::table('digital_object')
            ->where('object_id', $item->id)
            ->select('id', 'object_id', 'usage_id', 'media_type_id', 'mime_type', 'name', 'path', 'byte_size', 'checksum', 'sequence')
            ->orderBy('id')
            ->get();
        foreach ($doRows as $doRow) {
            $usageId = (int) ($doRow->usage_id ?? 0);
            if ($usageId === 140 || (!$usageId && !$digitalObjects['master'])) {
                $digitalObjects['master'] = $doRow;
            } elseif ($usageId === 141) {
                $digitalObjects['reference'] = $doRow;
            } elseif ($usageId === 142) {
                $digitalObjects['thumbnail'] = $doRow;
            }
        }

        // Repository name
        $repository = null;
        if ($item->repository_id) {
            $repository = DB::table('repository')
                ->join('actor_i18n', function ($j) {
                    $j->on('repository.id', '=', 'actor_i18n.id')
                       ->where('actor_i18n.culture', '=', app()->getLocale());
                })
                ->where('repository.id', $item->repository_id)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
                ->first();
        }

        // Physical objects
        $physicalObjects = DB::table('relation')
            ->join('physical_object', 'relation.subject_id', '=', 'physical_object.id')
            ->leftJoin('physical_object_i18n', function ($j) {
                $j->on('physical_object.id', '=', 'physical_object_i18n.id')
                   ->where('physical_object_i18n.culture', '=', app()->getLocale());
            })
            ->where('relation.object_id', $item->id)
            ->where('relation.type_id', 131)
            ->select('physical_object.id', 'physical_object.type_id', 'physical_object_i18n.name', 'physical_object_i18n.location')
            ->get();

        // Item physical location (single row from information_object_physical_location)
        $itemLocation = $this->service->getItemLocation($item->id);

        return view('ahg-library::library.show', [
            'item' => $item,
            'levelName' => $levelName,
            'creators' => $creators,
            'subjects' => $subjects,
            'parentItem' => $parentItem,
            'childCount' => $childCount,
            'digitalObjects' => $digitalObjects,
            'repository' => $repository,
            'physicalObjects' => $physicalObjects,
            'itemLocation' => $itemLocation,
        ]);
    }

    public function create()
    {
        $formChoices = $this->service->getFormChoices();

        return view('ahg-library::library.edit', [
            'item' => null,
            'formChoices' => $formChoices,
            'creators' => collect(),
            'subjects' => collect(),
            'itemLocation' => [],
        ]);
    }

    public function edit(string $slug)
    {
        $item = $this->service->getBySlug($slug);
        if (!$item) {
            abort(404);
        }

        $formChoices = $this->service->getFormChoices();

        $creators = collect();
        $subjects = collect();
        if ($item->library_item_id) {
            $creators = $this->service->getCreators($item->library_item_id);
            $subjects = $this->service->getSubjects($item->library_item_id);
        }

        // Item physical location (from item_physical_location table if exists)
        $itemLocation = $this->service->getItemLocation($item->id);

        return view('ahg-library::library.edit', [
            'item' => $item,
            'formChoices' => $formChoices,
            'creators' => $creators,
            'subjects' => $subjects,
            'itemLocation' => $itemLocation,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:1024',
            'identifier' => 'nullable|string|max:255',
            'level_of_description_id' => 'nullable|integer|exists:term,id',
            'repository_id' => 'nullable|integer|exists:repository,id',
            'material_type' => 'nullable|string|max:50',
            'subtitle' => 'nullable|string|max:1024',
            'responsibility_statement' => 'nullable|string|max:1024',
            'call_number' => 'nullable|string|max:255',
            'classification_scheme' => 'nullable|string|max:50',
            'classification_number' => 'nullable|string|max:255',
            'dewey_decimal' => 'nullable|string|max:50',
            'cutter_number' => 'nullable|string|max:50',
            'shelf_location' => 'nullable|string|max:255',
            'copy_number' => 'nullable|string|max:50',
            'volume_designation' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:20',
            'issn' => 'nullable|string|max:20',
            'lccn' => 'nullable|string|max:50',
            'oclc_number' => 'nullable|string|max:50',
            'openlibrary_id' => 'nullable|string|max:50',
            'goodreads_id' => 'nullable|string|max:50',
            'librarything_id' => 'nullable|string|max:50',
            'openlibrary_url' => 'nullable|url|max:500',
            'ebook_preview_url' => 'nullable|url|max:500',
            'cover_url' => 'nullable|url|max:500',
            'cover_url_original' => 'nullable|url|max:500',
            'doi' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'edition' => 'nullable|string|max:255',
            'edition_statement' => 'nullable|string|max:1024',
            'publisher' => 'nullable|string|max:500',
            'publication_place' => 'nullable|string|max:500',
            'publication_date' => 'nullable|string|max:255',
            'series_title' => 'nullable|string|max:500',
            'series_number' => 'nullable|string|max:50',
            'pagination' => 'nullable|string|max:100',
            'dimensions' => 'nullable|string|max:255',
            'physical_details' => 'nullable|string|max:1024',
            'scope_and_content' => 'nullable|string',
            'contents_note' => 'nullable|string',
            'general_note' => 'nullable|string',
            'bibliography_note' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'parent' => 'nullable|string|max:255',
            'creators' => 'nullable|array',
            'creators.*.name' => 'nullable|string|max:500',
            'creators.*.role' => 'nullable|string|max:50',
            'creators.*.authority_uri' => 'nullable|string|max:500',
            'subjects' => 'nullable|array',
            'subjects.*.heading' => 'nullable|string|max:500',
            'item_physical_object_id' => 'nullable|integer',
            'item_barcode' => 'nullable|string|max:100',
            'item_box_number' => 'nullable|string|max:50',
            'item_folder_number' => 'nullable|string|max:50',
            'item_shelf' => 'nullable|string|max:50',
            'item_row' => 'nullable|string|max:50',
            'item_position' => 'nullable|string|max:50',
            'item_item_number' => 'nullable|string|max:50',
            'item_extent_value' => 'nullable|numeric',
            'item_extent_unit' => 'nullable|string|max:50',
            'item_condition_status' => 'nullable|string|max:43',
            'item_access_status' => 'nullable|string|max:53',
            'item_condition_notes' => 'nullable|string',
            'item_location_notes' => 'nullable|string',
        ]);

        $data = $request->only([
            'title', 'identifier', 'level_of_description_id', 'repository_id',
            'material_type', 'subtitle', 'responsibility_statement',
            'call_number', 'classification_scheme', 'classification_number',
            'dewey_decimal', 'cutter_number', 'shelf_location', 'copy_number',
            'volume_designation', 'isbn', 'issn', 'lccn', 'oclc_number',
            'openlibrary_id', 'goodreads_id', 'librarything_id', 'openlibrary_url',
            'ebook_preview_url', 'cover_url', 'cover_url_original', 'doi', 'barcode',
            'edition', 'edition_statement', 'publisher', 'publication_place',
            'publication_date', 'series_title', 'series_number', 'pagination',
            'dimensions', 'physical_details', 'scope_and_content',
            'contents_note', 'general_note', 'bibliography_note', 'language',
        ]);
        $data['creators']     = $request->input('creators', []);
        $data['subjects']     = $request->input('subjects', []);
        $data['itemLocation'] = $this->collectItemLocation($request);

        // Add-new-as-child: form posts hidden parent=<slug> when launched
        // from a record's "Add new" action. Resolve to information_object.id
        // so the new record nests under it.
        $parentSlug = $request->input('parent');
        if ($parentSlug) {
            $parentId = \Illuminate\Support\Facades\DB::table('slug')
                ->where('slug', $parentSlug)
                ->value('object_id');
            if ($parentId) {
                $data['parent_id'] = (int) $parentId;
            }
        }

        $id = $this->service->create($data);
        $slug = $this->service->getSlug($id);

        return redirect()
            ->route('library.show', $slug)
            ->with('success', 'Library item created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $item = $this->service->getBySlug($slug);
        if (!$item) {
            abort(404);
        }

        $request->validate([
            'title' => 'required|string|max:1024',
            'identifier' => 'nullable|string|max:255',
            'level_of_description_id' => 'nullable|integer|exists:term,id',
            'repository_id' => 'nullable|integer|exists:repository,id',
            'material_type' => 'nullable|string|max:50',
            'subtitle' => 'nullable|string|max:1024',
            'responsibility_statement' => 'nullable|string|max:1024',
            'call_number' => 'nullable|string|max:255',
            'classification_scheme' => 'nullable|string|max:50',
            'classification_number' => 'nullable|string|max:255',
            'dewey_decimal' => 'nullable|string|max:50',
            'cutter_number' => 'nullable|string|max:50',
            'shelf_location' => 'nullable|string|max:255',
            'copy_number' => 'nullable|string|max:50',
            'volume_designation' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:20',
            'issn' => 'nullable|string|max:20',
            'lccn' => 'nullable|string|max:50',
            'oclc_number' => 'nullable|string|max:50',
            'openlibrary_id' => 'nullable|string|max:50',
            'goodreads_id' => 'nullable|string|max:50',
            'librarything_id' => 'nullable|string|max:50',
            'openlibrary_url' => 'nullable|url|max:500',
            'ebook_preview_url' => 'nullable|url|max:500',
            'cover_url' => 'nullable|url|max:500',
            'cover_url_original' => 'nullable|url|max:500',
            'doi' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'edition' => 'nullable|string|max:255',
            'edition_statement' => 'nullable|string|max:1024',
            'publisher' => 'nullable|string|max:500',
            'publication_place' => 'nullable|string|max:500',
            'publication_date' => 'nullable|string|max:255',
            'series_title' => 'nullable|string|max:500',
            'series_number' => 'nullable|string|max:50',
            'pagination' => 'nullable|string|max:100',
            'dimensions' => 'nullable|string|max:255',
            'physical_details' => 'nullable|string|max:1024',
            'scope_and_content' => 'nullable|string',
            'contents_note' => 'nullable|string',
            'general_note' => 'nullable|string',
            'bibliography_note' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'creators' => 'nullable|array',
            'creators.*.name' => 'nullable|string|max:500',
            'creators.*.role' => 'nullable|string|max:50',
            'creators.*.authority_uri' => 'nullable|string|max:500',
            'subjects' => 'nullable|array',
            'subjects.*.heading' => 'nullable|string|max:500',
            'item_physical_object_id' => 'nullable|integer',
            'item_barcode' => 'nullable|string|max:100',
            'item_box_number' => 'nullable|string|max:50',
            'item_folder_number' => 'nullable|string|max:50',
            'item_shelf' => 'nullable|string|max:50',
            'item_row' => 'nullable|string|max:50',
            'item_position' => 'nullable|string|max:50',
            'item_item_number' => 'nullable|string|max:50',
            'item_extent_value' => 'nullable|numeric',
            'item_extent_unit' => 'nullable|string|max:50',
            'item_condition_status' => 'nullable|string|max:43',
            'item_access_status' => 'nullable|string|max:53',
            'item_condition_notes' => 'nullable|string',
            'item_location_notes' => 'nullable|string',
        ]);

        $data = $request->only([
            'title', 'identifier', 'level_of_description_id', 'repository_id',
            'material_type', 'subtitle', 'responsibility_statement',
            'call_number', 'classification_scheme', 'classification_number',
            'dewey_decimal', 'cutter_number', 'shelf_location', 'copy_number',
            'volume_designation', 'isbn', 'issn', 'lccn', 'oclc_number',
            'openlibrary_id', 'goodreads_id', 'librarything_id', 'openlibrary_url',
            'ebook_preview_url', 'cover_url', 'cover_url_original', 'doi', 'barcode',
            'edition', 'edition_statement', 'publisher', 'publication_place',
            'publication_date', 'series_title', 'series_number', 'pagination',
            'dimensions', 'physical_details', 'scope_and_content',
            'contents_note', 'general_note', 'bibliography_note', 'language',
            // ICIP cultural-sensitivity URI (issue #36 Phase 2b) — persisted to information_object.icip_sensitivity.
            'icip_sensitivity',
        ]);
        $data['creators']     = $request->input('creators', []);
        $data['subjects']     = $request->input('subjects', []);
        $data['itemLocation'] = $this->collectItemLocation($request);

        $this->service->update($slug, $data);

        return redirect()
            ->route('library.show', $slug)
            ->with('success', 'Library item updated successfully.');
    }

    /**
     * Pull every item_* hidden under the form's "Item Physical Location"
     * section into a shape the service can upsert into
     * information_object_physical_location. Returns null if the user left every
     * field blank, so the service knows not to create an empty row.
     */
    private function collectItemLocation(Request $request): ?array
    {
        $map = [
            'physical_object_id' => 'item_physical_object_id',
            'barcode'            => 'item_barcode',
            'box_number'         => 'item_box_number',
            'folder_number'      => 'item_folder_number',
            'shelf'              => 'item_shelf',
            'row'                => 'item_row',
            'position'           => 'item_position',
            'item_number'        => 'item_item_number',
            'extent_value'       => 'item_extent_value',
            'extent_unit'        => 'item_extent_unit',
            'condition_status'   => 'item_condition_status',
            'access_status'      => 'item_access_status',
            'condition_notes'    => 'item_condition_notes',
            'notes'              => 'item_location_notes',
        ];
        $out = [];
        $anyFilled = false;
        foreach ($map as $col => $field) {
            $v = $request->input($field);
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '') $v = null;
            }
            $out[$col] = $v;
            if ($v !== null && $v !== '') $anyFilled = true;
        }
        return $anyFilled ? $out : null;
    }

    public function destroy(Request $request, string $slug)
    {
        $item = $this->service->getBySlug($slug);
        if (!$item) {
            abort(404);
        }

        $this->service->delete($slug);

        return redirect()
            ->route('library.browse')
            ->with('success', 'Library item deleted successfully.');
    }

    // ── Library Management ─────────────────────────────────────────

    public function index()
    {
        $totalItems = 0; $recentCount = 0; $circulatingCount = 0; $overdueCount = 0;
        return view('ahg-library::library.index', compact('totalItems', 'recentCount', 'circulatingCount', 'overdueCount'));
    }

    public function rename(string $slug)
    {
        $item = $this->service->getBySlug($slug);
        if (!$item) abort(404);

        $digitalObject = DB::table('digital_object')
            ->where('object_id', $item->id)
            ->select('id', 'name', 'path')
            ->first();

        return view('ahg-library::library.rename', compact('item', 'digitalObject'));
    }

    public function renameStore(Request $request, string $slug)
    {
        $item = $this->service->getBySlug($slug);
        if (!$item) abort(404);

        $culture = app()->getLocale();
        $updateTitle = $request->has('enable_title');
        $updateSlug = $request->has('enable_slug');
        $updateFilename = $request->has('enable_filename');

        if ($updateTitle) {
            $newTitle = $request->input('title', '');
            if (!empty($newTitle)) {
                DB::table('information_object_i18n')
                    ->where('id', $item->id)
                    ->where('culture', $culture)
                    ->update(['title' => $newTitle]);
            }
        }

        $newSlug = $slug;
        if ($updateSlug) {
            $newSlug = $request->input('slug', $slug);
            $newSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $newSlug)));
            if (!empty($newSlug) && $newSlug !== $slug) {
                // Check for duplicate
                $exists = DB::table('slug')->where('slug', $newSlug)->where('object_id', '!=', $item->id)->exists();
                if ($exists) {
                    $newSlug = $newSlug . '-' . $item->id;
                }
                DB::table('slug')->where('object_id', $item->id)->update(['slug' => $newSlug]);
                \AhgCore\Support\AuditLog::captureSecondaryMutation((int) $item->id, 'library_item', 'slug_rename', [
                    'data' => ['before' => $slug, 'after' => $newSlug],
                ]);
            }
        }

        if ($updateFilename) {
            $newFilename = $request->input('filename', '');
            if (!empty($newFilename)) {
                $do = DB::table('digital_object')->where('object_id', $item->id)->first();
                if ($do) {
                    $ext = pathinfo($do->name, PATHINFO_EXTENSION);
                    $cleanName = preg_replace('/[^a-z0-9\-\.]/', '', strtolower(str_replace(' ', '-', $newFilename)));
                    if (!str_ends_with($cleanName, '.' . $ext)) {
                        $cleanName .= '.' . $ext;
                    }
                    DB::table('digital_object')->where('id', $do->id)->update(['name' => $cleanName]);
                    \AhgCore\Support\AuditLog::captureSecondaryMutation((int) $item->id, 'library_item', 'digital_object_rename', [
                        'data' => ['before' => $do->name, 'after' => $cleanName, 'digital_object_id' => $do->id],
                    ]);
                }
            }
        }

        return redirect()->route('library.show', $newSlug)->with('success', 'Item renamed successfully.');
    }

    public function isbnProviders()
    {
        return view('ahg-library::library.isbn-providers', [
            'providers' => collect($this->isbnProvider->list()),
        ]);
    }

    public function isbnProviderEdit(int $id)
    {
        $provider = $this->isbnProvider->get($id)
            ?? (object) ['id' => $id, 'name' => '', 'api_url' => '', 'api_key' => '', 'priority' => 0, 'active' => true];
        return view('ahg-library::library.isbn-provider-edit', compact('provider'));
    }

    public function isbnProviderStore(Request $request, int $id)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'api_url'  => 'nullable|string|max:500',
            'api_key'  => 'nullable|string|max:255',
            'priority' => 'nullable|integer|min:0',
            'active'   => 'nullable',
        ]);
        $validated['active'] = $request->boolean('active');
        $this->isbnProvider->save($id, $validated);
        return redirect()->route('library.isbn-providers')->with('success', 'Provider saved.');
    }

    // ── Acquisition ────────────────────────────────────────────────

    public function acquisitions(Request $request)
    {
        return view('ahg-library::acquisition.index', [
            'orders' => collect($this->acq->listOrders([
                'status' => $request->query('status'),
                'search' => $request->query('q'),
            ])),
        ]);
    }

    public function batchCapture(Request $request)
    {
        $orders = collect($this->acq->listOrders(['status' => 'draft']));
        return view('ahg-library::acquisition.batch-capture', [
            'orders'          => $orders,
            'selectedOrderId' => (int) $request->query('order_id', 0),
            'rawIsbns'        => '',
        ]);
    }

    public function batchCaptureLookup(Request $request)
    {
        $validated = $request->validate([
            'isbns'    => 'nullable|string',
            'order_id' => 'nullable|integer|min:0',
        ]);

        $orderId = (int) ($validated['order_id'] ?? 0);
        $isbns = preg_split('/[\s,;]+/', (string) ($validated['isbns'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $added = 0;

        if ($orderId > 0 && $this->acq->getOrder($orderId)) {
            foreach ($isbns as $raw) {
                $isbn = preg_replace('/[\s\-]/', '', $raw);
                if ($isbn === '' || strlen($isbn) > 32) {
                    continue;
                }
                $this->acq->addLine($orderId, [
                    'isbn'       => $isbn,
                    'title'      => '',
                    'quantity'   => 1,
                    'unit_price' => 0,
                ]);
                $added++;
            }
        }

        $msg = $added > 0
            ? "Added $added line(s) to order."
            : 'Lookup complete.';

        return redirect()->route('library.batch-capture', ['order_id' => $orderId])->with('success', $msg);
    }

    public function budgets()
    {
        return view('ahg-library::acquisition.budgets', [
            'budgets' => collect($this->acq->listBudgets()),
        ]);
    }

    public function acquisitionOrder(int $id)
    {
        $order = $this->acq->getOrder($id)
            ?? (object) ['id' => $id, 'order_number' => '', 'vendor_name' => '', 'order_date' => '', 'status' => ''];
        return view('ahg-library::acquisition.order', [
            'order' => $order,
            'lines' => collect($this->acq->getOrderLines($id)),
        ]);
    }

    public function acquisitionOrderEdit(int $id)
    {
        $order = $this->acq->getOrder($id)
            ?? (object) ['id' => $id, 'order_number' => '', 'vendor_name' => '', 'order_date' => '', 'status' => 'draft'];
        return view('ahg-library::acquisition.order-edit', compact('order'));
    }

    public function acquisitionOrderStore(Request $request, int $id)
    {
        $validated = $request->validate([
            'order_number' => 'required|string|max:50',
            'vendor_name'  => 'nullable|string|max:255',
            'order_date'   => 'nullable|date',
            'status'       => 'nullable|in:draft,ordered,received,cancelled',
            'budget_id'    => 'nullable|integer|min:0',
            'notes'        => 'nullable|string',
        ]);

        if ($id > 0 && $this->acq->getOrder($id)) {
            $this->acq->updateOrder($id, $validated);
        } else {
            $id = $this->acq->createOrder($validated);
        }

        return $id > 0
            ? redirect()->route('library.acquisition-order', $id)->with('success', 'Order saved.')
            : redirect()->route('library.acquisitions')->with('success', 'Order saved.');
    }

    // ── Circulation ────────────────────────────────────────────────

    public function circulation()
    {
        return view('ahg-library::circulation.index', [
            'loans' => collect($this->circ->listCheckouts(['status' => 'active'])),
        ]);
    }

    public function loanRules()
    {
        return view('ahg-library::circulation.loan-rules', [
            'rules' => collect($this->circ->getLoanRules()),
        ]);
    }

    public function overdue()
    {
        return view('ahg-library::circulation.overdue', [
            'overdueItems' => collect($this->circ->listOverdue()),
        ]);
    }

    // ── ILL ────────────────────────────────────────────────────────

    public function ill(Request $request)
    {
        $status_counts = $this->ill->countByStatus();
        $overdue_count = count($this->ill->list(['overdue_only' => true]));
        $pending_count = $status_counts[LibraryIllService::STATUS_PENDING] ?? 0;
        $borrow_count  = $this->countBorrowActive($status_counts);
        $lend_count    = $this->countLendActive($status_counts);

        $filters = array_filter([
            'status'       => $request->query('status'),
            'type'         => $request->query('type'),
            'search'       => $request->query('q'),
            'overdue_only' => $request->query('overdue_only') ? true : null,
        ], fn($v) => !is_null($v));

        return view('ahg-library::ill.index', [
            'requests'      => collect($this->ill->list($filters)),
            'status_counts'=> $status_counts,
            'borrow_count' => $borrow_count,
            'lend_count'   => $lend_count,
            'pending_count'=> $pending_count,
            'overdue_count'=> $overdue_count,
            'status_filter'=> $request->query('status'),
            'type_filter'  => $request->query('type'),
            'overdue_filter'=> $request->query('overdue_only') ? true : null,
            'search_query' => $request->query('q'),
            'all_statuses' => LibraryIllService::STATUSES,
        ]);
    }

    public function illView(int $id)
    {
        $req = $this->ill->get($id);
        if (!$req) {
            abort(404);
        }

        $auditLog = [];
        try {
            $auditLog = DB::table('library_ill_audit')
                ->where('ill_number', $req->ill_number)
                ->orderByDesc('created_at')
                ->limit(50)->get()->all();
        } catch (\Throwable) {
            // fail silently — audit table may not exist yet
        }

        $type = $req->type ?? LibraryIllService::TYPE_BORROW;
        $available = auth()->check()
            ? $this->ill->availableTransitions($req->status ?? '', $type)
            : [];

        return view('ahg-library::ill.view', [
            'request'              => $req,
            'available_transitions'=> $available,
            'audit_log'            => $auditLog,
        ]);
    }

    // ── ILL create / store / update / transition / delete ────────────────

    public function illCreate(): \Illuminate\View\View
    {
        return view('ahg-library::ill.create');
    }

    public function illStore(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'type'              => 'sometimes|in:borrow,lend',
            'title'             => 'required|string|max:500',
            'author'            => 'sometimes|string|max:300',
            'isbn'              => 'sometimes|nullable|string|max:20',
            'issn'              => 'sometimes|nullable|string|max:20',
            'volume'            => 'sometimes|nullable|string|max:50',
            'issue'             => 'sometimes|nullable|string|max:20',
            'pages'             => 'sometimes|nullable|string|max:50',
            'edition'           => 'sometimes|nullable|string|max:100',
            'publication_year'  => 'sometimes|nullable|integer|min:1000|max:' . (date('Y') + 5),
            'library_name'      => 'required|string|max:300',
            'library_symbol'    => 'sometimes|nullable|string|max:50',
            'patron_id'         => 'sometimes|nullable|integer',
            'requester_note'    => 'sometimes|nullable|string|max:2000',
            'due_date'          => 'sometimes|nullable|date|after:today',
        ]);

        $id = $this->ill->create($data);

        return redirect()
            ->route('library.ill-view', $id)
            ->with('ill_success', 'ILL request created successfully.');
    }

    public function illUpdate(\Illuminate\Http\Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $req = $this->ill->get($id);
        if (!$req) {
            abort(404);
        }

        $data = $request->validate([
            'library_name'  => 'sometimes|string|max:300',
            'library_symbol'=> 'sometimes|nullable|string|max:50',
            'due_date'      => 'sometimes|nullable|date',
            'patron_id'     => 'sometimes|nullable|integer',
            'title'         => 'sometimes|string|max:500',
            'author'        => 'sometimes|string|max:300',
            'isbn'          => 'sometimes|nullable|string|max:20',
            'requester_note'=> 'sometimes|nullable|string|max:2000',
        ]);

        $this->ill->update($id, $data);

        return redirect()->route('library.ill-view', $id)
            ->with('ill_success', 'ILL request updated.');
    }

    public function illTransition(\Illuminate\Http\Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $status = $request->input('status');
        $note   = $request->input('note');

        $applied = $this->ill->transitionTo($id, $status, $note);

        if (!$applied) {
            return redirect()->route('library.ill-view', $id)
                ->with('ill_error', "Invalid status transition to '{$status}'.");
        }

        return redirect()->route('library.ill-view', $id)
            ->with('ill_success', "Status updated to '{$status}'.");
    }

    public function illDelete(int $id): \Illuminate\Http\RedirectResponse
    {
        $this->ill->delete($id);
        return redirect()->route('library.ill')->with('ill_success', 'ILL request deleted.');
    }

    public function illOpacSuppress(\Illuminate\Http\Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $suppress = $request->input('suppress') === '1';
        $this->ill->setOpacSuppress($id, $suppress);
        return redirect()->route('library.ill-view', $id)
            ->with('ill_success', $suppress ? 'ILL request suppressed from OPAC.' : 'ILL request visible in OPAC.');
    }

    public function illSettings(): \Illuminate\View\View
    {
        $keys = ['ill_default_due_days', 'ill_tipasa_partner',
                 'ill_oclc_api_key', 'ill_oclc_principal_id', 'ill_oclc_base_url',
                 'ill_auto_escalate_days'];
        $settings = [];
        foreach ($keys as $k) {
            $settings[$k] = SettingHelper::get($k);
        }
        return view('ahg-library::ill.settings', ['settings' => $settings]);
    }

    public function illSettingsStore(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'default_due_days'     => 'sometimes|nullable|integer|min:1|max:365',
            'tipasa_partner'       => 'sometimes|nullable|in:naz,sabinet,dals',
            'oclc_api_key'         => 'sometimes|nullable|string|max:200',
            'oclc_principal_id'    => 'sometimes|nullable|string|max:100',
            'auto_escalate_days'   => 'sometimes|nullable|integer|min:1|max:90',
        ]);

        foreach ($data as $key => $val) {
            SettingHelper::set('ill_' . $key, $val);
        }

        return redirect()->route('library.ill-settings')
            ->with('ill_success', 'ILL settings saved.');
    }

    // ── Patron (OPAC) ILL ──────────────────────────────────────────────────

    public function opacIllCreate(): \Illuminate\View\View
    {
        return view('ahg-library::ill.patron-create');
    }

    public function opacIllStore(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $patronId = auth()->user()->patron_id ?? null;
        if (!$patronId) {
            abort(403, 'No patron record linked to your account.');
        }

        $data = $request->validate([
            'title'             => 'required|string|max:500',
            'author'            => 'sometimes|string|max:300',
            'isbn'              => 'sometimes|nullable|string|max:20',
            'issn'              => 'sometimes|nullable|string|max:20',
            'volume'            => 'sometimes|nullable|string|max:50',
            'issue'             => 'sometimes|nullable|string|max:20',
            'pages'             => 'sometimes|nullable|string|max:50',
            'publication_year'  => 'sometimes|nullable|integer|min:1000|max:' . (date('Y') + 5),
            'requester_note'    => 'sometimes|nullable|string|max:1000',
        ]);

        $id = $this->ill->patronCreate($patronId, $data);

        return redirect()->route('library.opac')
            ->with('ill_success', "ILL request submitted. Reference: {$id}");
    }


    // ── ISBN ────────────────────────────────────────────────────────

    public function isbnLookup(Request $request)
    {
        $isbn = $request->input('isbn');
        $format = $request->input('format');

        if ($format === 'json' && $isbn) {
            return $this->isbnLookupJson($isbn);
        }

        return view('ahg-library::isbn.lookup', ['isbn' => $isbn, 'result' => null]);
    }

    public function isbnLookupSearch(Request $request)
    {
        $isbn = $request->input('isbn');

        if ($request->wantsJson() || $request->input('format') === 'json') {
            return $this->isbnLookupJson($isbn);
        }

        return view('ahg-library::isbn.lookup', ['isbn' => $isbn, 'result' => null]);
    }

    private function isbnLookupJson(string $isbn)
    {
        $cleanIsbn = preg_replace('/[\s\-]/', '', $isbn);

        try {
            $url = 'https://openlibrary.org/api/books?bibkeys=ISBN:' . urlencode($cleanIsbn) . '&format=json&jscmd=data';
            $response = @file_get_contents($url);

            if ($response === false) {
                return response()->json(['success' => false, 'error' => 'Could not reach Open Library API']);
            }

            $data = json_decode($response, true);
            $key = 'ISBN:' . $cleanIsbn;

            if (empty($data[$key])) {
                return response()->json(['success' => false, 'error' => 'ISBN not found in Open Library']);
            }

            $book = $data[$key];

            return response()->json([
                'success' => true,
                'data' => $book,
                'preview' => ['description' => $book['notes'] ?? ''],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Lookup failed: ' . $e->getMessage()]);
        }
    }

    // ── Patrons ────────────────────────────────────────────────────

    public function patrons(Request $request)
    {
        return view('ahg-library::patron.index', [
            'patrons' => collect($this->patrons->list([
                'search' => $request->query('q'),
                'status' => $request->query('status'),
                'type' => $request->query('type'),
            ])),
        ]);
    }

    public function patronView(int $id)
    {
        $patron = $this->patrons->get($id);
        if (!$patron) {
            abort(404);
        }
        return view('ahg-library::patron.view', [
            'patron' => $patron,
            'loans' => collect($this->patrons->getActiveLoans($id)),
            'holds' => collect($this->patrons->getActiveHolds($id)),
        ]);
    }

    // ── Serials ────────────────────────────────────────────────────

    public function serials(Request $request)
    {
        return view('ahg-library::serial.index', [
            'serials' => collect($this->serial->list([
                'status' => $request->query('status'),
                'search' => $request->query('q'),
            ])),
        ]);
    }

    public function serialView(int $id)
    {
        $serial = $this->serial->get($id)
            ?? (object) [
                'id' => $id, 'title' => '', 'issn' => '',
                'frequency' => '', 'publisher' => '', 'status' => '',
                'issues' => [],
            ];
        return view('ahg-library::serial.view', compact('serial'));
    }

    // ── OPAC ───────────────────────────────────────────────────────

    public function opac(Request $request)
    {
        $query = (string) $request->query('q', '');
        $results = $request->has('q')
            ? $this->opac->search($query, [
                'material_type' => $request->query('material_type'),
                'language' => $request->query('language'),
            ])
            : null;

        return view('ahg-library::opac.index', [
            'results' => $results,
            'newArrivals' => collect($this->opac->newArrivals()),
            'popular' => collect($this->opac->popular()),
            'settings' => [
                'show_availability' => \AhgLibrary\Support\LibrarySettings::opacShowAvailability(),
                'show_covers' => \AhgLibrary\Support\LibrarySettings::opacShowCovers(),
                'allow_holds' => \AhgLibrary\Support\LibrarySettings::opacAllowHolds(),
            ],
        ]);
    }

    public function opacView(string $slug)
    {
        $item = $this->service->getBySlug($slug);
        if (!$item) {
            abort(404);
        }
        $availability = \AhgLibrary\Support\LibrarySettings::opacShowAvailability()
            ? $this->opac->getAvailability((int) ($item->library_item_id ?? $item->id))
            : null;
        $item->available = $availability ? $availability['available'] > 0 : true;
        $item->availability = $availability;

        return view('ahg-library::opac.view', [
            'item' => $item,
            'allowHolds' => \AhgLibrary\Support\LibrarySettings::opacAllowHolds(),
        ]);
    }

    public function opacAccount(Request $request)
    {
        // Account view requires an authenticated patron. Look up by
        // user.email -> library_patron.email; falls back to empty
        // collections when no matching patron exists.
        $loans = collect();
        $holds = collect();
        $patron = null;

        if ($user = $request->user()) {
            $patron = DB::table('library_patron')
                ->where('email', $user->email)
                ->first();
            if ($patron) {
                $loans = collect($this->patrons->getActiveLoans((int) $patron->id));
                $holds = collect($this->patrons->getActiveHolds((int) $patron->id));
            }
        }

        return view('ahg-library::opac.account', compact('loans', 'holds', 'patron'));
    }

    public function opacHold(string $slug)
    {
        if (!\AhgLibrary\Support\LibrarySettings::opacAllowHolds()) {
            abort(404);
        }
        $item = $this->service->getBySlug($slug);
        if (!$item) {
            abort(404);
        }
        return view('ahg-library::opac.hold', compact('item'));
    }

    public function opacHoldStore(Request $request, string $slug)
    {
        if (!\AhgLibrary\Support\LibrarySettings::opacAllowHolds()) {
            abort(404);
        }

        $item = $this->service->getBySlug($slug);
        if (!$item) {
            abort(404);
        }

        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $patron = DB::table('library_patron')->where('email', $user->email)->first();
        if (!$patron) {
            return redirect()->route('library.opac-view', $slug)
                ->with('error', __('No library patron record matched your account. Contact a librarian.'));
        }

        $itemId = (int) ($item->library_item_id ?? $item->id);
        $holdId = $this->circ->placeHold($itemId, (int) $patron->id);

        if (!$holdId) {
            return redirect()->route('library.opac-view', $slug)
                ->with('error', __('Hold could not be placed (queue full, max holds reached, or patron suspended).'));
        }

        return redirect()->route('library.opac-view', $slug)
            ->with('success', __('Hold placed.'));
    }

    public function opacRenew(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $patron = DB::table('library_patron')->where('email', $user->email)->first();
        $checkout = DB::table('library_checkout')->where('id', $id)->first();

        // Ownership check: patrons may only renew their own checkouts.
        if (!$patron || !$checkout || (int) $checkout->patron_id !== (int) $patron->id) {
            abort(404);
        }

        if (!$this->circ->renew($id)) {
            return redirect()->route('library.opac-account')
                ->with('error', __('Renewal not allowed (max renewals reached or another patron is waiting).'));
        }

        return redirect()->route('library.opac-account')->with('success', __('Item renewed.'));
    }

    // ── Reports ────────────────────────────────────────────────────

    public function libraryReports()
    {
        $stats = [
            'items' => ['total' => 0, 'available' => 0, 'onLoan' => 0, 'reference' => 0],
            'byType' => collect(),
            'creators' => 0, 'subjects' => 0, 'recentlyAdded' => 0,
        ];
        try {
            if (\Schema::hasTable('library_item')) {
                $stats['items']['total'] = \DB::table('library_item')->count();
                $stats['items']['available'] = \DB::table('library_item')->where('status', 'available')->count();
                $stats['items']['onLoan'] = \DB::table('library_item')->where('status', 'on_loan')->count();
                $stats['items']['reference'] = \DB::table('library_item')->where('is_reference', 1)->count();
                $stats['byType'] = \DB::table('library_item')->select('material_type', \DB::raw('COUNT(*) as count'))
                    ->whereNotNull('material_type')->groupBy('material_type')->orderByDesc('count')->get();
                $stats['recentlyAdded'] = \DB::table('library_item')->where('created_at', '>=', now()->subDays(30))->count();
            }
            if (\Schema::hasTable('library_creator')) { $stats['creators'] = \DB::table('library_creator')->distinct('name')->count('name'); }
            if (\Schema::hasTable('library_subject')) { $stats['subjects'] = \DB::table('library_subject')->distinct('name')->count('name'); }
        } catch (\Throwable $e) {}
        return view('ahg-library::reports.index', compact('stats'));
    }

    public function reportCatalogue()
    {
        $items = collect();
        try {
            if (\Schema::hasTable('library_item')) {
                $items = \DB::table('library_item')->orderBy('title')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-library::reports.catalogue', compact('items'));
    }

    public function reportCreators()
    {
        $creators = collect();
        try {
            if (\Schema::hasTable('library_creator')) {
                $creators = \DB::table('library_creator')
                    ->select('name', \DB::raw('COUNT(*) as work_count'))
                    ->groupBy('name')->orderByDesc('work_count')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-library::reports.creators', compact('creators'));
    }

    public function reportPublishers()
    {
        $publishers = collect();
        try {
            if (\Schema::hasTable('library_item')) {
                $publishers = \DB::table('library_item')
                    ->select('publisher', 'publication_place', \DB::raw('COUNT(*) as title_count'))
                    ->whereNotNull('publisher')->groupBy('publisher', 'publication_place')
                    ->orderByDesc('title_count')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-library::reports.publishers', compact('publishers'));
    }

    public function reportSubjects()
    {
        $subjects = collect();
        try {
            if (\Schema::hasTable('library_subject')) {
                $subjects = \DB::table('library_subject')
                    ->select('name', \DB::raw('COUNT(*) as item_count'))
                    ->groupBy('name')->orderByDesc('item_count')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-library::reports.subjects', compact('subjects'));
    }

    public function reportCallNumbers()
    {
        $items = collect();
        try {
            if (\Schema::hasTable('library_item')) {
                $items = \DB::table('library_item')
                    ->whereNotNull('call_number')->where('call_number', '!=', '')
                    ->orderBy('call_number')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-library::reports.call-numbers', compact('items'));
    }

    // ── Issue #734: PSIS-parity actions ────────────────────────────

    /**
     * Toggle a provider's active flag. PSIS twin:
     * ahgLibraryPlugin/modules/library/actions/isbnProviderToggleAction.
     * Admin-only; honoured by the existing acl:update middleware on the route.
     */
    public function isbnProviderToggle(int $id)
    {
        $provider = $this->isbnProvider->get($id);
        if (!$provider) {
            return redirect()->route('library.isbn-providers')
                ->with('error', __('Provider not found.'));
        }
        $this->isbnProvider->save($id, [
            'name'     => $provider->name,
            'api_url'  => $provider->api_url,
            'api_key'  => $provider->api_key,
            'priority' => (int) $provider->priority,
            'active'   => empty($provider->active),
        ]);
        $label = empty($provider->active) ? __('enabled') : __('disabled');
        return redirect()->route('library.isbn-providers')
            ->with('success', __('Provider :name :state.', ['name' => $provider->name, 'state' => $label]));
    }

    /**
     * Delete a provider. PSIS twin: isbnProviderDeleteAction. Refuses to
     * delete the three seed providers (Open Library / Google Books /
     * WorldCat) - operators toggle them off instead. This preserves the
     * upstream "core providers" contract.
     */
    public function isbnProviderDelete(int $id)
    {
        $provider = $this->isbnProvider->get($id);
        if (!$provider) {
            return redirect()->route('library.isbn-providers')
                ->with('error', __('Provider not found.'));
        }
        $protected = ['Open Library', 'Google Books', 'WorldCat'];
        if (in_array($provider->name, $protected, true)) {
            return redirect()->route('library.isbn-providers')
                ->with('error', __('Core providers cannot be deleted - toggle them off instead.'));
        }
        $this->isbnProvider->delete($id);
        return redirect()->route('library.isbn-providers')
            ->with('success', __('Provider deleted.'));
    }

    /**
     * Server-side cover image proxy. Mirrors PSIS coverProxyAction.
     * - Strips ISBN to digits + X.
     * - Caches the JPEG under HERATIO_STORAGE_PATH/cache/covers/<isbn>-<size>.jpg.
     * - Falls through to upstream provider (currently Open Library) on miss.
     * - Returns image/jpeg with 24h cache; 404 on miss or undersized payload.
     */
    public function coverImage(Request $request, string $isbn)
    {
        $isbn = strtoupper(preg_replace('/[^0-9Xx]/', '', $isbn));
        $size = strtoupper((string) $request->query('size', 'M'));
        if ($isbn === '' || !in_array($size, ['S', 'M', 'L'], true)) {
            abort(404);
        }

        $storageRoot = (string) (config('heratio.storage_path') ?: storage_path('app'));
        $cacheDir = rtrim($storageRoot, '/') . '/cache/covers';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $cacheFile = $cacheDir . '/' . $isbn . '-' . $size . '.jpg';

        // Cache hit: stream from disk.
        if (is_file($cacheFile) && filesize($cacheFile) > 0) {
            return response()->file($cacheFile, [
                'Content-Type'  => 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        // Miss: fetch from the highest-priority active provider that returns a
        // resolvable cover URL. Today only Open Library exposes a no-key cover
        // CDN so we map other providers to the same /b/isbn/ pattern.
        $url = "https://covers.openlibrary.org/b/isbn/{$isbn}-{$size}.jpg";
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Heratio/LibraryPlugin'])
                ->get($url);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[library] cover proxy fetch failed: ' . $e->getMessage());
            abort(404);
        }
        $body = $resp->body();
        if (!$resp->successful() || strlen($body) < 1000) {
            abort(404);
        }

        // Best-effort cache write; failure here must not break the response.
        @file_put_contents($cacheFile, $body);

        return response($body, 200, [
            'Content-Type'   => 'image/jpeg',
            'Cache-Control'  => 'public, max-age=86400',
            'Content-Length' => (string) strlen($body),
        ]);
    }

    /**
     * Returns {slug: "kebab-case"} for the title typed into the rename / add
     * form. Pads with a numeric suffix when the slug is already taken so the
     * UI can show the user what will actually be saved.
     */
    public function slugPreview(Request $request)
    {
        $title = (string) $request->query('title', '');
        if ($title === '') {
            return response()->json(['slug' => '', 'padded' => false]);
        }
        $base = \Illuminate\Support\Str::slug($title) ?: 'untitled';
        $candidate = $base;
        $padded = false;
        $i = 1;
        // Cap the pad loop so a poisoned slug table can't spin us forever.
        while ($i < 50 && \Illuminate\Support\Facades\DB::table('slug')->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $i;
            $padded = true;
            $i++;
        }
        return response()->json(['slug' => $candidate, 'padded' => $padded]);
    }

    /**
     * POST /library/suggest-subjects {title, description} -> {subjects: [...]}
     *
     * Calls LlmService::complete via the cloud-mode override (AI is
     * remote-only per feedback_ai_remote_only). Returns up to 5 short
     * subject-heading strings. Failures degrade to an empty array + an
     * error string the caller can surface.
     */
    public function suggestSubjects(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:2048',
            'description' => 'nullable|string|max:8000',
        ]);

        $title = trim($validated['title']);
        $desc  = trim((string) ($validated['description'] ?? ''));

        $prompt = "You are a cataloguing assistant. Propose 5 concise subject headings "
            . "(2-4 words each, Library of Congress style where possible) for the resource below. "
            . "Return ONLY a JSON array of strings, no prose, no markdown fences.\n\n"
            . "Title: {$title}\n"
            . ($desc !== '' ? "Description: {$desc}\n" : '');

        $subjects = [];
        $error = null;
        try {
            $llm = app(\AhgAiServices\Services\LlmService::class);
            $raw = $llm->complete($prompt, ['temperature' => 0.2, 'purpose' => 'library.suggest_subjects']);
            if (is_string($raw) && $raw !== '') {
                // Trim common markdown fences then parse.
                $clean = trim($raw);
                $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $clean) ?? $clean;
                $decoded = json_decode($clean, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $s) {
                        if (is_string($s)) {
                            $s = trim($s);
                            if ($s !== '' && mb_strlen($s) <= 200) {
                                $subjects[] = $s;
                            }
                        }
                        if (count($subjects) >= 5) break;
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[library] suggestSubjects failed: ' . $e->getMessage());
            $error = 'LLM call failed.';
        }

        return response()->json([
            'success'  => $error === null,
            'subjects' => $subjects,
            'error'    => $error,
        ]);
    }

    /**
     * POST /library/patron/{id}/reactivate - re-enable a suspended or
     * expired patron and log the action to ahg_error_log (level=info) so
     * the audit feed picks it up. PSIS twin:
     * ahgLibraryPlugin patron/reactivateAction.
     */
    public function patronReactivate(Request $request, int $id)
    {
        $patron = $this->patrons->get($id);
        if (!$patron) {
            abort(404);
        }
        $previousStatus = (string) ($patron->borrowing_status ?? '');
        $ok = $this->patrons->reactivate($id);

        // Audit row in ahg_error_log (level=info is the convention used by
        // other admin write-paths - the table doubles as an activity feed
        // for the operator console).
        try {
            \Illuminate\Support\Facades\DB::table('ahg_error_log')->insert([
                'level'           => 'info',
                'message'         => 'Library patron reactivated (id=' . $id . ', was=' . $previousStatus . ')',
                'exception_class' => '',
                'file'            => __FILE__,
                'line'            => __LINE__,
                'url'             => $request->fullUrl(),
                'http_method'     => 'POST',
                'hostname'        => gethostname() ?: '',
                'is_read'         => 0,
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // ahg_error_log is best-effort; never block the user response.
            \Illuminate\Support\Facades\Log::warning('[library] patron reactivate audit log failed: ' . $e->getMessage());
        }

        $msg = $ok ? __('Patron reactivated.') : __('Patron could not be reactivated.');
        return redirect()->route('library.patron-view', $id)->with($ok ? 'success' : 'error', $msg);
    }

    // ── Serial CRUD + advanced ─────────────────────────────────────────

    public function serialCreate(): \Illuminate\View\View
    {
        $frequencies = \AhgLibrary\Services\LibrarySerialService::FREQUENCIES;
        return view('ahg-library::serial.create', compact('frequencies'));
    }

    public function serialStore(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'title'     => 'required|string|max:500',
            'issn'      => 'nullable|string|max:20',
            'frequency' => 'sometimes|string|max:20',
            'publisher' => 'nullable|string|max:500',
            'status'    => 'sometimes|in:active,ceased,suspended',
            'notes'     => 'nullable|string',
        ]);
        $id = $this->serial->create($data);
        return redirect()->route('library.serial-view', $id)->with('serial_success', 'Serial created.');
    }

    public function serialEdit(int $id): \Illuminate\View\View
    {
        $serial = $this->serial->get($id) ?? (object) ['id'=>$id,'title'=>'','issn'=>'','frequency'=>'','publisher'=>'','status'=>'active'];
        $frequencies = \AhgLibrary\Services\LibrarySerialService::FREQUENCIES;
        return view('ahg-library::serial.edit', compact('serial', 'frequencies'));
    }

    public function serialUpdate(\Illuminate\Http\Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'title'     => 'required|string|max:500',
            'issn'      => 'nullable|string|max:20',
            'frequency' => 'sometimes|string|max:20',
            'publisher' => 'nullable|string|max:500',
            'status'    => 'sometimes|in:active,ceased,suspended',
            'notes'     => 'nullable|string',
        ]);
        $this->serial->update($id, $data);
        return redirect()->route('library.serial-view', $id)->with('serial_success', 'Serial updated.');
    }

    public function serialDelete(int $id): \Illuminate\Http\RedirectResponse
    {
        $this->serial->delete($id);
        return redirect()->route('library.serials')->with('serial_success', 'Serial deleted.');
    }

    public function serialAddIssue(\Illuminate\Http\Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'volume'        => 'nullable|string|max:50',
            'issue_number' => 'nullable|string|max:20',
            'issue_date'   => 'nullable|date',
            'received_at'  => 'nullable|date',
            'status'       => 'sometimes|in:received,claimed,missing',
            'notes'        => 'nullable|string',
        ]);
        $this->serial->addIssue($id, $data);
        return redirect()->back()->with('serial_success', 'Issue added.');
    }

    public function serialSubscription(int $serialId): \Illuminate\View\View
    {
        $serial = $this->serial->get($serialId);
        if (!$serial) { abort(404); }
        $frequencies = \AhgLibrary\Services\LibrarySerialService::FREQUENCIES;
        return view('ahg-library::serial.subscription', compact('serial', 'frequencies'));
    }

    public function serialSubscriptionStore(\Illuminate\Http\Request $request, int $serialId): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'subscription_start'  => 'nullable|date',
            'subscription_end'     => 'nullable|date',
            'subscription_cost'   => 'nullable|numeric|min:0',
            'notification_email'  => 'nullable|email|max:255',
            'auto_claim_max'      => 'nullable|integer|min:0|max:12',
            'notes'               => 'nullable|string',
        ]);
        $this->serial->saveSubscription($serialId, $data);
        return redirect()->route('library.serial-subscription', $serialId)->with('serial_success', 'Subscription saved.');
    }

    public function serialPredict(int $serialId): \Illuminate\Http\JsonResponse
    {
        $frequencies = \AhgLibrary\Services\LibrarySerialService::FREQUENCIES;
        $serial = $this->serial->get($serialId);
        if (!$serial) { abort(404); }
        $nextDate = $this->serial->predictNextIssue($serialId);
        $expected = $this->serial->getExpectedIssues($serialId, 6);
        return response()->json([
            'serial_id'       => $serialId,
            'frequency'       => $serial->frequency,
            'predictions'     => $expected,
            'next_expected'   => $nextDate ? $nextDate->format('Y-m-d') : null,
            'days_until_next' => $nextDate ? now()->diffInDays($nextDate, false) : null,
            'frequency_label' => $frequencies[$serial->frequency] ?? $serial->frequency,
        ]);
    }

    public function serialCoverage(int $serialId): \Illuminate\View\View
    {
        $serial = $this->serial->get($serialId);
        if (!$serial) { abort(404); }
        $stats = $this->serial->getCoverageStats($serialId);
        $history = $this->serial->getIssueHistory($serialId);
        return view('ahg-library::serial.coverage', compact('serial', 'stats', 'history'));
    }

    public function serialClone(int $serialId): \Illuminate\Http\RedirectResponse
    {
        $newId = $this->serial->cloneSerial($serialId);
        return redirect()->route('library.serial-edit', $newId)->with('serial_success', 'Serial cloned. Review and save.');
    }

    public function serialOverdueClaims(): \Illuminate\View\View
    {
        $claims = collect($this->serial->listOverdueClaims());
        return view('ahg-library::serial.overdue-claims', compact('claims'));
    }

    public function serialClaimIssue(int $serialId, int $issueId): \Illuminate\Http\RedirectResponse
    {
        DB::table('library_serial_issue')
            ->where('id', $issueId)
            ->where('serial_id', $serialId)
            ->update(['status' => 'claimed', 'updated_at' => now()]);
        return redirect()->back()->with('serial_success', 'Issue claimed.');
    }
}
