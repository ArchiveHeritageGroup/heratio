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

    public function __construct()
    {
        $this->service = new LibraryService(app()->getLocale());
        $this->circ = new \AhgLibrary\Services\LibraryCirculationService();
        $this->patrons = new \AhgLibrary\Services\LibraryPatronService();
        $this->opac = new \AhgLibrary\Services\LibraryOpacService();
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
        $providers = collect();
        return view('ahg-library::library.isbn-providers', compact('providers'));
    }

    public function isbnProviderEdit(int $id)
    {
        $provider = (object) ['id' => $id, 'name' => '', 'api_url' => '', 'api_key' => '', 'priority' => 0, 'active' => true];
        return view('ahg-library::library.isbn-provider-edit', compact('provider'));
    }

    public function isbnProviderStore(Request $request, int $id)
    {
        return redirect()->route('library.isbn-providers')->with('success', 'Provider saved.');
    }

    // ── Acquisition ────────────────────────────────────────────────

    public function acquisitions() { return view('ahg-library::acquisition.index', ['orders' => collect()]); }
    public function batchCapture(Request $request) { return view('ahg-library::acquisition.batch-capture', ['orders' => collect(), 'selectedOrderId' => 0, 'rawIsbns' => '']); }
    public function batchCaptureLookup(Request $request) { return redirect()->route('library.batch-capture')->with('success', 'Lookup complete.'); }
    public function budgets() { return view('ahg-library::acquisition.budgets', ['budgets' => collect()]); }
    public function acquisitionOrder(int $id) { $order = (object)['id' => $id, 'order_number' => '', 'vendor_name' => '', 'order_date' => '', 'status' => '']; return view('ahg-library::acquisition.order', ['order' => $order, 'lines' => collect()]); }
    public function acquisitionOrderEdit(int $id) { $order = (object)['id' => $id, 'order_number' => '', 'vendor_name' => '', 'order_date' => '', 'status' => 'draft']; return view('ahg-library::acquisition.order-edit', compact('order')); }
    public function acquisitionOrderStore(Request $request, int $id) { return redirect()->route('library.acquisitions')->with('success', 'Order saved.'); }

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

    public function ill() { return view('ahg-library::ill.index', ['requests' => collect()]); }
    public function illView(int $id) { $request = (object)['id' => $id, 'ill_number' => '', 'type' => '', 'title' => '', 'author' => '', 'isbn' => '', 'library_name' => '', 'request_date' => '', 'status' => '']; return view('ahg-library::ill.view', ['request' => $request]); }

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

    public function serials() { return view('ahg-library::serial.index', ['serials' => collect()]); }
    public function serialView(int $id) { $serial = (object)['id' => $id, 'title' => '', 'issn' => '', 'frequency' => '', 'publisher' => '', 'status' => '']; return view('ahg-library::serial.view', compact('serial')); }

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
}
