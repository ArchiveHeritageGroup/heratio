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
            'extendedData' => $this->service->getExtendedData($storage->id),
        ]);
    }

    public function create()
    {
        return view('ahg-storage-manage::edit', [
            'storage' => null,
            'typeChoices' => $this->service->getFormChoices(),
            'extendedData' => [],
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
            'extendedData' => $this->service->getExtendedData($storage->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:1024']);
        $id = $this->service->create($request->only($this->baseFields()));
        $this->service->saveExtendedData($id, $request->only($this->extendedFields()));
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
        $this->service->update($storage->id, $request->only($this->baseFields()));
        $this->service->saveExtendedData($storage->id, $request->only($this->extendedFields()));
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

        $this->service->deleteExtendedData($storage->id);
        $this->service->delete($storage->id);
        return redirect()
            ->route('physicalobject.browse')
            ->with('success', 'Physical storage deleted successfully.');
    }

    public function holdingsReportExport()
    {
        $culture = app()->getLocale();
        $rows = DB::table('physical_object')
            ->join('physical_object_i18n', 'physical_object.id', '=', 'physical_object_i18n.id')
            ->leftJoin('term_i18n', function ($j) use ($culture) {
                $j->on('physical_object.type_id', '=', 'term_i18n.id')
                  ->where('term_i18n.culture', '=', $culture);
            })
            ->where('physical_object_i18n.culture', $culture)
            ->select('physical_object_i18n.name', 'term_i18n.name as type', 'physical_object_i18n.location')
            ->orderBy('physical_object_i18n.name')
            ->get();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Type', 'Location']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->name, $r->type ?? '', $r->location ?? '']);
            }
            fclose($out);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="storage-report-' . date('Ymd') . '.csv"']);
    }

    private function baseFields(): array
    {
        return ['name', 'type_id', 'location', 'description'];
    }

    private function extendedFields(): array
    {
        return [
            'building', 'floor', 'room', 'aisle', 'bay', 'rack', 'shelf', 'position',
            'barcode', 'reference_code',
            'width', 'height', 'depth',
            'total_capacity', 'used_capacity', 'capacity_unit',
            'total_linear_metres', 'used_linear_metres',
            'climate_controlled', 'temperature_min', 'temperature_max',
            'humidity_min', 'humidity_max',
            'security_level', 'access_restrictions', 'status', 'notes',
        ];
    }
}
