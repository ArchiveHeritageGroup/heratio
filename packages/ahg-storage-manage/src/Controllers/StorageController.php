<?php

namespace AhgStorageManage\Controllers;

use AhgStorageManage\Services\StorageBrowseService;
use AhgStorageManage\Services\StorageService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageController extends Controller
{
    protected StorageService $service;

    public function __construct()
    {
        $this->service = new StorageService(app()->getLocale());
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new StorageBrowseService($culture);

        $result = $browseService->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        // Batch resolve type_id to term names
        $typeIds = collect($pager->getResults())
            ->pluck('type_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $typeNames = [];
        if ($typeIds) {
            $typeNames = DB::table('term_i18n')
                ->whereIn('id', $typeIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->all();
        }

        return view('ahg-storage-manage::browse', [
            'pager' => $pager,
            'typeNames' => $typeNames,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $storage = $this->service->getBySlug($slug);
        if (!$storage) {
            abort(404);
        }

        $typeName = $this->service->getTermName($storage->type_id);
        $descriptions = $this->service->getLinkedDescriptions($storage->id);

        return view('ahg-storage-manage::show', [
            'storage' => $storage,
            'typeName' => $typeName,
            'descriptions' => $descriptions,
        ]);
    }

    public function create()
    {
        return view('ahg-storage-manage::edit', [
            'storage' => null,
            'typeChoices' => $this->service->getFormChoices(),
        ]);
    }

    public function edit(string $slug)
    {
        $storage = $this->service->getBySlug($slug);
        if (!$storage) {
            abort(404);
        }

        return view('ahg-storage-manage::edit', [
            'storage' => $storage,
            'typeChoices' => $this->service->getFormChoices(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:1024']);
        $id = $this->service->create($request->only($this->fields()));
        return redirect()
            ->route('physicalobject.show', $this->service->getSlug($id))
            ->with('success', 'Physical storage created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $storage = $this->service->getBySlug($slug);
        if (!$storage) {
            abort(404);
        }

        $request->validate(['name' => 'required|string|max:1024']);
        $this->service->update($storage->id, $request->only($this->fields()));
        return redirect()
            ->route('physicalobject.show', $slug)
            ->with('success', 'Physical storage updated successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $storage = $this->service->getBySlug($slug);
        if (!$storage) {
            abort(404);
        }

        return view('ahg-storage-manage::delete', ['storage' => $storage]);
    }

    public function destroy(string $slug)
    {
        $storage = $this->service->getBySlug($slug);
        if (!$storage) {
            abort(404);
        }

        $this->service->delete($storage->id);
        return redirect()
            ->route('physicalobject.browse')
            ->with('success', 'Physical storage deleted successfully.');
    }

    private function fields(): array
    {
        return ['name', 'type_id', 'location', 'description'];
    }
}
