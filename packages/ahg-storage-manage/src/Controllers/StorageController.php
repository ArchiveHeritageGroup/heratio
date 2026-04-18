<?php

/**
 * StorageController - Controller for Heratio
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
        // Redirect to link-to page if object_id is provided
        $objectId = $request->get('object_id');
        if ($objectId) {
            $ioSlug = DB::table('slug')->where('object_id', $objectId)->value('slug');
            if ($ioSlug) {
                return redirect()->route('physicalobject.link-to', $ioSlug);
            }
        }

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

    /**
     * Link Physical Storage — single page to manage container links for an IO.
     */
    public function linkTo(Request $request, string $slug)
    {
        $culture = app()->getLocale();
        $io = DB::table('information_object')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->join('information_object_i18n', function ($j) use ($culture) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                   ->where('information_object_i18n.culture', $culture);
            })
            ->where('slug.slug', $slug)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();

        if (!$io) abort(404);

        // Current linked containers
        $linked = DB::table('relation')
            ->join('physical_object', 'relation.subject_id', '=', 'physical_object.id')
            ->leftJoin('physical_object_i18n', function ($j) use ($culture) {
                $j->on('physical_object.id', '=', 'physical_object_i18n.id')
                   ->where('physical_object_i18n.culture', $culture);
            })
            ->leftJoin('physical_object_extended as poe', 'physical_object.id', '=', 'poe.physical_object_id')
            ->leftJoin('slug as po_slug', 'physical_object.id', '=', 'po_slug.object_id')
            ->where('relation.object_id', $io->id)
            ->where('relation.type_id', 161)
            ->select(
                'relation.id as relation_id',
                'physical_object.id as po_id',
                'physical_object_i18n.name as po_name',
                'physical_object_i18n.location as po_location',
                'po_slug.slug as po_slug',
                'poe.barcode', 'poe.building', 'poe.floor', 'poe.room',
                'poe.aisle', 'poe.bay', 'poe.rack', 'poe.shelf', 'poe.position',
                'poe.total_capacity', 'poe.used_capacity', 'poe.capacity_unit',
                'poe.status as po_status', 'poe.climate_controlled', 'poe.security_level'
            )
            ->get();

        // Container types for the create form
        $containerTypes = DB::table('term')
            ->join('term_i18n', function ($j) use ($culture) {
                $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', $culture);
            })
            ->where('term.taxonomy_id', 56)
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();

        return view('ahg-storage-manage::link-to', compact('io', 'linked', 'containerTypes'));
    }

    /**
     * Store container link — link existing or create new.
     */
    public function linkToStore(Request $request, string $slug)
    {
        $culture = app()->getLocale();
        $ioId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$ioId) abort(404);

        $action = $request->input('action');

        if ($action === 'link_existing') {
            $poId = $request->input('physical_object_id');
            if ($poId) {
                // Check not already linked
                $exists = DB::table('relation')
                    ->where('object_id', $ioId)
                    ->where('subject_id', $poId)
                    ->where('type_id', 161)
                    ->exists();
                if (!$exists) {
                    DB::table('relation')->insert([
                        'object_id' => $ioId,
                        'subject_id' => $poId,
                        'type_id' => 161,
                    ]);
                }
            }
        } elseif ($action === 'create_new') {
            // Create physical_object + i18n + extended + relation
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitPhysicalObject',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('physical_object')->insert([
                'id' => $objectId,
                'type_id' => $request->input('type_id') ?: null,
                'source_culture' => $culture,
            ]);

            DB::table('physical_object_i18n')->insert([
                'id' => $objectId,
                'culture' => $culture,
                'name' => $request->input('name', ''),
                'location' => $request->input('location', ''),
            ]);

            // Generate slug
            $nameSlug = \Illuminate\Support\Str::slug($request->input('name', 'container-' . $objectId));
            $slugExists = DB::table('slug')->where('slug', $nameSlug)->exists();
            if ($slugExists) $nameSlug .= '-' . $objectId;
            DB::table('slug')->insert(['object_id' => $objectId, 'slug' => $nameSlug]);

            // Extended data
            DB::table('physical_object_extended')->insert(array_filter([
                'physical_object_id' => $objectId,
                'building' => $request->input('building') ?: null,
                'floor' => $request->input('floor') ?: null,
                'room' => $request->input('room') ?: null,
                'aisle' => $request->input('aisle') ?: null,
                'bay' => $request->input('bay') ?: null,
                'rack' => $request->input('rack') ?: null,
                'shelf' => $request->input('shelf') ?: null,
                'position' => $request->input('position') ?: null,
                'barcode' => $request->input('barcode') ?: null,
                'total_capacity' => $request->input('total_capacity') ?: null,
                'capacity_unit' => $request->input('capacity_unit') ?: null,
                'climate_controlled' => $request->has('climate_controlled') ? 1 : 0,
                'security_level' => $request->input('security_level') ?: null,
                'status' => 'active',
            ], fn($v) => $v !== null));

            // Link to IO
            DB::table('relation')->insert([
                'object_id' => $ioId,
                'subject_id' => $objectId,
                'type_id' => 161,
            ]);
        }

        return redirect()->route('physicalobject.link-to', $slug)->with('success', 'Physical storage updated.');
    }

    /**
     * Unlink a container from an IO.
     */
    public function unlink(Request $request, int $relationId)
    {
        $relation = DB::table('relation')->where('id', $relationId)->first();
        if (!$relation) abort(404);

        $slug = DB::table('slug')->where('object_id', $relation->object_id)->value('slug');

        DB::table('relation')->where('id', $relationId)->delete();

        return redirect()->route('physicalobject.link-to', $slug ?? '')->with('success', 'Container unlinked.');
    }
}
