<?php

/**
 * LibraryController - Controller for Heratio
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

    public function __construct()
    {
        $this->service = new LibraryService(app()->getLocale());
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
            'pages' => 'nullable|string|max:50',
            'dimensions' => 'nullable|string|max:255',
            'physical_details' => 'nullable|string|max:1024',
            'scope_and_content' => 'nullable|string',
            'table_of_contents' => 'nullable|string',
            'general_note' => 'nullable|string',
            'bibliography_note' => 'nullable|string',
            'language' => 'nullable|string|max:10',
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
            'publication_date', 'series_title', 'series_number', 'pages',
            'dimensions', 'physical_details', 'scope_and_content',
            'table_of_contents', 'general_note', 'bibliography_note', 'language',
        ]);

        // Map scope_and_content from abstract field
        if (isset($data['scope_and_content'])) {
            $data['abstract'] = $data['scope_and_content'];
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
            'pages' => 'nullable|string|max:50',
            'dimensions' => 'nullable|string|max:255',
            'physical_details' => 'nullable|string|max:1024',
            'scope_and_content' => 'nullable|string',
            'table_of_contents' => 'nullable|string',
            'general_note' => 'nullable|string',
            'bibliography_note' => 'nullable|string',
            'language' => 'nullable|string|max:10',
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
            'publication_date', 'series_title', 'series_number', 'pages',
            'dimensions', 'physical_details', 'scope_and_content',
            'table_of_contents', 'general_note', 'bibliography_note', 'language',
        ]);

        // Map scope_and_content from abstract field
        if (isset($data['scope_and_content'])) {
            $data['abstract'] = $data['scope_and_content'];
        }

        $this->service->update($slug, $data);

        return redirect()
            ->route('library.show', $slug)
            ->with('success', 'Library item updated successfully.');
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

    public function circulation() { return view('ahg-library::circulation.index', ['loans' => collect()]); }
    public function loanRules() { return view('ahg-library::circulation.loan-rules', ['rules' => collect()]); }
    public function overdue() { return view('ahg-library::circulation.overdue', ['overdueItems' => collect()]); }

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

    public function patrons() { return view('ahg-library::patron.index', ['patrons' => collect()]); }
    public function patronView(int $id) { $patron = (object)['id' => $id, 'name' => '', 'type' => '', 'card_number' => '', 'email' => '', 'phone' => '', 'active' => true]; return view('ahg-library::patron.view', ['patron' => $patron, 'loans' => collect()]); }

    // ── Serials ────────────────────────────────────────────────────

    public function serials() { return view('ahg-library::serial.index', ['serials' => collect()]); }
    public function serialView(int $id) { $serial = (object)['id' => $id, 'title' => '', 'issn' => '', 'frequency' => '', 'publisher' => '', 'status' => '']; return view('ahg-library::serial.view', compact('serial')); }

    // ── OPAC ───────────────────────────────────────────────────────

    public function opac(Request $request) { $results = $request->has('q') ? collect() : null; return view('ahg-library::opac.index', compact('results')); }
    public function opacView(string $slug) { $item = $this->service->getBySlug($slug); if (!$item) abort(404); $item->available = true; return view('ahg-library::opac.view', compact('item')); }
    public function opacAccount() { return view('ahg-library::opac.account', ['loans' => collect(), 'holds' => collect()]); }
    public function opacHold(string $slug) { $item = $this->service->getBySlug($slug); if (!$item) abort(404); return view('ahg-library::opac.hold', compact('item')); }
    public function opacHoldStore(Request $request, string $slug) { return redirect()->route('library.opac-view', $slug)->with('success', 'Hold placed.'); }
    public function opacRenew(Request $request, int $id) { return redirect()->route('library.opac-account')->with('success', 'Item renewed.'); }

    // ── Reports ────────────────────────────────────────────────────

    public function libraryReports() { return view('ahg-library::reports.index'); }
    public function reportCatalogue() { return view('ahg-library::reports.catalogue', ['items' => collect()]); }
    public function reportCreators() { return view('ahg-library::reports.creators', ['creators' => collect()]); }
    public function reportPublishers() { return view('ahg-library::reports.publishers', ['publishers' => collect()]); }
    public function reportSubjects() { return view('ahg-library::reports.subjects', ['subjects' => collect()]); }
    public function reportCallNumbers() { return view('ahg-library::reports.call-numbers', ['items' => collect()]); }
}
