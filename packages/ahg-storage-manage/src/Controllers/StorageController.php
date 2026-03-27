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
        $accessions = $this->service->getLinkedAccessions($storage->id);

        return view('ahg-storage-manage::show', [
            'storage' => $storage,
            'typeName' => $typeName,
            'descriptions' => $descriptions,
            'accessions' => $accessions,
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

        $culture = app()->getLocale();
        $informationObjects = DB::table('relation')
            ->join('information_object', 'relation.subject_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($j) use ($culture) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.object_id', $storage->id)
            ->where('relation.type_id', 179)
            ->select('information_object_i18n.title', 'slug.slug')
            ->get();

        return view('ahg-storage-manage::delete', [
            'storage' => $storage,
            'informationObjects' => $informationObjects,
        ]);
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

    public function autocomplete(Request $request) { $q = $request->input('query', ''); $results = DB::table('physical_object')->join('physical_object_i18n','physical_object.id','=','physical_object_i18n.id')->where('physical_object_i18n.name','LIKE','%'.$q.'%')->where('physical_object_i18n.culture','en')->limit(10)->select('physical_object.id','physical_object_i18n.name')->get(); return response()->json($results); }

    public function boxList(Request $request)
    {
        $slug = $request->get('slug');
        $storage = null;
        $rows = collect();

        if ($slug) {
            $storage = $this->service->getBySlug($slug);
            if ($storage) {
                $culture = app()->getLocale();

                // Get linked information objects with AtoM box-list columns
                $items = DB::table('relation')
                    ->join('information_object', 'relation.subject_id', '=', 'information_object.id')
                    ->leftJoin('information_object_i18n', function ($j) use ($culture) {
                        $j->on('information_object.id', '=', 'information_object_i18n.id')
                            ->where('information_object_i18n.culture', '=', $culture);
                    })
                    ->join('slug', 'information_object.id', '=', 'slug.object_id')
                    ->where('relation.object_id', $storage->id)
                    ->where('relation.type_id', 151)
                    ->select([
                        'information_object.id',
                        'information_object.identifier',
                        'information_object.parent_id',
                        'information_object_i18n.title',
                        'information_object_i18n.access_conditions',
                        'slug.slug',
                    ])
                    ->get();

                // Build reference codes and fetch dates/parent titles
                foreach ($items as $item) {
                    // Build reference code (repository identifier + IO identifiers up the hierarchy)
                    $item->reference_code = $this->buildReferenceCode($item->id, $culture);

                    // Get dates
                    $item->dates = DB::table('event')
                        ->leftJoin('event_i18n', function ($j) use ($culture) {
                            $j->on('event.id', '=', 'event_i18n.id')
                                ->where('event_i18n.culture', '=', $culture);
                        })
                        ->leftJoin('term_i18n', function ($j) use ($culture) {
                            $j->on('event.type_id', '=', 'term_i18n.id')
                                ->where('term_i18n.culture', '=', $culture);
                        })
                        ->where('event.object_id', $item->id)
                        ->select([
                            'event_i18n.date as date_display',
                            'event.start_date',
                            'event.end_date',
                            'term_i18n.name as type_name',
                        ])
                        ->get();

                    // Get collection root (part of)
                    $item->part_of = $this->getCollectionRootTitle($item->id, $culture);
                }

                $rows = $items;
            }
        }

        return view('ahg-storage-manage::box-list', [
            'storage' => $storage,
            'rows' => $rows,
        ]);
    }

    private function buildReferenceCode(int $ioId, string $culture): string
    {
        $parts = [];
        $currentId = $ioId;
        $rootId = DB::table('information_object')->whereNull('parent_id')->value('id')
            ?? DB::table('information_object')->where('parent_id', 0)->value('id');

        while ($currentId && $currentId != $rootId) {
            $row = DB::table('information_object')
                ->where('id', $currentId)
                ->select('identifier', 'parent_id', 'repository_id')
                ->first();

            if (!$row) {
                break;
            }

            if ($row->identifier) {
                array_unshift($parts, $row->identifier);
            }

            // If this is the top-level IO with a repository, prepend repository identifier
            if ($row->repository_id) {
                $repoIdentifier = DB::table('repository')
                    ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                    ->where('repository.id', $row->repository_id)
                    ->where('actor_i18n.culture', $culture)
                    ->value('actor_i18n.authorized_form_of_name');
                if ($repoIdentifier) {
                    $repoId = DB::table('repository')->where('id', $row->repository_id)->value('identifier');
                    if ($repoId) {
                        array_unshift($parts, $repoId);
                    }
                }
            }

            $currentId = $row->parent_id;
        }

        return implode(' - ', $parts);
    }

    private function getCollectionRootTitle(int $ioId, string $culture): string
    {
        $currentId = $ioId;
        $rootId = DB::table('information_object')->whereNull('parent_id')->value('id')
            ?? DB::table('information_object')->where('parent_id', 0)->value('id');
        $lastValidId = $ioId;

        while ($currentId && $currentId != $rootId) {
            $lastValidId = $currentId;
            $parentId = DB::table('information_object')->where('id', $currentId)->value('parent_id');
            if (!$parentId || $parentId == $rootId) {
                break;
            }
            $currentId = $parentId;
        }

        // If the collection root is the same as the item, return empty
        if ($lastValidId == $ioId) {
            return '';
        }

        return DB::table('information_object_i18n')
            ->where('id', $lastValidId)
            ->where('culture', $culture)
            ->value('title') ?? '';
    }
}
