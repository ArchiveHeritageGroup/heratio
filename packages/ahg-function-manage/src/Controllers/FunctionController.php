<?php

namespace AhgFunctionManage\Controllers;

use AhgFunctionManage\Services\FunctionBrowseService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FunctionController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new FunctionBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-function-manage::browse', [
            'pager' => $pager,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
                'identifier' => 'Identifier',
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $function = DB::table('function_object')
            ->join('slug', 'function_object.id', '=', 'slug.object_id')
            ->join('function_object_i18n', 'function_object.id', '=', 'function_object_i18n.id')
            ->join('object', 'function_object.id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('function_object_i18n.culture', $culture)
            ->select([
                'function_object.id',
                'function_object.type_id',
                'function_object.description_identifier',
                'function_object.description_status_id',
                'function_object.description_detail_id',
                'function_object.source_standard',
                'function_object_i18n.authorized_form_of_name',
                'function_object_i18n.classification',
                'function_object_i18n.dates',
                'function_object_i18n.description',
                'function_object_i18n.history',
                'function_object_i18n.legislation',
                'function_object_i18n.institution_identifier',
                'function_object_i18n.revision_history',
                'function_object_i18n.rules',
                'function_object_i18n.sources',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$function) {
            abort(404);
        }

        // Get type name
        $typeName = null;
        if ($function->type_id) {
            $typeName = DB::table('term_i18n')
                ->where('id', $function->type_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Get description status name
        $descriptionStatus = null;
        if ($function->description_status_id) {
            $descriptionStatus = DB::table('term_i18n')
                ->where('id', $function->description_status_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Get description detail level name
        $descriptionDetail = null;
        if ($function->description_detail_id) {
            $descriptionDetail = DB::table('term_i18n')
                ->where('id', $function->description_detail_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Get related functions (via relation table)
        $relatedFunctions = DB::table('relation')
            ->join('function_object', function ($join) use ($function) {
                $join->on('relation.object_id', '=', 'function_object.id')
                    ->where('relation.subject_id', '=', $function->id);
            })
            ->join('function_object_i18n', 'function_object.id', '=', 'function_object_i18n.id')
            ->join('slug', 'function_object.id', '=', 'slug.object_id')
            ->where('function_object_i18n.culture', $culture)
            ->select([
                'function_object.id',
                'function_object_i18n.authorized_form_of_name',
                'slug.slug',
            ])
            ->get();

        // Also get reverse relations (where this function is the object)
        $reverseRelatedFunctions = DB::table('relation')
            ->join('function_object', function ($join) use ($function) {
                $join->on('relation.subject_id', '=', 'function_object.id')
                    ->where('relation.object_id', '=', $function->id);
            })
            ->join('function_object_i18n', 'function_object.id', '=', 'function_object_i18n.id')
            ->join('slug', 'function_object.id', '=', 'slug.object_id')
            ->where('function_object_i18n.culture', $culture)
            ->select([
                'function_object.id',
                'function_object_i18n.authorized_form_of_name',
                'slug.slug',
            ])
            ->get();

        $allRelatedFunctions = $relatedFunctions->merge($reverseRelatedFunctions)->unique('id');

        // Get related resources (information objects linked via relation)
        $relatedResources = DB::table('relation')
            ->join('information_object', 'relation.object_id', '=', 'information_object.id')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $function->id)
            ->where('information_object_i18n.culture', $culture)
            ->where('information_object.id', '!=', 1)
            ->select([
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug',
            ])
            ->distinct()
            ->limit(50)
            ->get();

        return view('ahg-function-manage::show', [
            'function' => $function,
            'typeName' => $typeName,
            'descriptionStatus' => $descriptionStatus,
            'descriptionDetail' => $descriptionDetail,
            'relatedFunctions' => $allRelatedFunctions,
            'relatedResources' => $relatedResources,
        ]);
    }
}
