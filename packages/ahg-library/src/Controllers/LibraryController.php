<?php

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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

        return view('ahg-library::library.show', [
            'item' => $item,
            'levelName' => $levelName,
            'creators' => $creators,
            'subjects' => $subjects,
        ]);
    }

    public function create()
    {
        $formChoices = $this->service->getFormChoices();

        return view('ahg-library::library.edit', [
            'item' => null,
            'formChoices' => $formChoices,
        ]);
    }

    public function edit(string $slug)
    {
        $item = $this->service->getBySlug($slug);
        if (!$item) {
            abort(404);
        }

        $formChoices = $this->service->getFormChoices();

        return view('ahg-library::library.edit', [
            'item' => $item,
            'formChoices' => $formChoices,
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
}
