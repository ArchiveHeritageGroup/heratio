<?php

namespace AhgStorageManage\Controllers;

use AhgStorageManage\Services\StorageBrowseService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new StorageBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
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

    public function show(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $storage = DB::table('physical_object')
            ->join('physical_object_i18n', 'physical_object.id', '=', 'physical_object_i18n.id')
            ->join('object', 'physical_object.id', '=', 'object.id')
            ->join('slug', 'physical_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('physical_object_i18n.culture', $culture)
            ->select([
                'physical_object.id',
                'physical_object.type_id',
                'physical_object_i18n.name',
                'physical_object_i18n.location',
                'physical_object_i18n.description',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$storage) {
            abort(404);
        }

        // Get type name
        $typeName = null;
        if ($storage->type_id) {
            $typeName = DB::table('term_i18n')
                ->where('id', $storage->type_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Get linked information objects via relation table
        // relation.subject_id = physical_object.id, relation.object_id = information_object.id
        // type_id = 147 (HAS_PHYSICAL_OBJECT)
        $descriptions = DB::table('relation')
            ->join('information_object_i18n', 'relation.object_id', '=', 'information_object_i18n.id')
            ->join('slug', 'relation.object_id', '=', 'slug.object_id')
            ->where('relation.subject_id', $storage->id)
            ->where('relation.type_id', 147)
            ->where('information_object_i18n.culture', $culture)
            ->select([
                'relation.object_id as id',
                'information_object_i18n.title',
                'slug.slug',
            ])
            ->get();

        return view('ahg-storage-manage::show', [
            'storage' => $storage,
            'typeName' => $typeName,
            'descriptions' => $descriptions,
        ]);
    }
}
