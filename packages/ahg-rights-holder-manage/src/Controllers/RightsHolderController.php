<?php

namespace AhgRightsHolderManage\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgRightsHolderManage\Services\RightsHolderBrowseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RightsHolderController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new RightsHolderBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-rights-holder-manage::browse', [
            'pager' => $pager,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $rightsHolder = DB::table('rights_holder')
            ->join('actor_i18n', 'rights_holder.id', '=', 'actor_i18n.id')
            ->join('slug', 'rights_holder.id', '=', 'slug.object_id')
            ->join('object', 'rights_holder.id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('actor_i18n.culture', $culture)
            ->select([
                'rights_holder.id',
                'actor_i18n.authorized_form_of_name',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$rightsHolder) {
            abort(404);
        }

        // Get related rights
        $rights = DB::table('rights')
            ->leftJoin('rights_i18n', function ($join) use ($culture) {
                $join->on('rights.id', '=', 'rights_i18n.id')
                    ->where('rights_i18n.culture', $culture);
            })
            ->where('rights.rights_holder_id', $rightsHolder->id)
            ->select([
                'rights.id',
                'rights.basis_id',
                'rights.start_date',
                'rights.end_date',
                'rights.copyright_status_id',
                'rights.copyright_jurisdiction',
                'rights_i18n.rights_note',
                'rights_i18n.copyright_note',
                'rights_i18n.license_terms',
                'rights_i18n.license_note',
                'rights_i18n.statute_jurisdiction',
                'rights_i18n.statute_note',
            ])
            ->get();

        // Batch resolve basis term names
        $basisNames = [];
        $basisIds = array_filter(array_unique($rights->pluck('basis_id')->toArray()));
        if (!empty($basisIds)) {
            $basisNames = DB::table('term_i18n')
                ->whereIn('id', $basisIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        return view('ahg-rights-holder-manage::show', [
            'rightsHolder' => $rightsHolder,
            'rights' => $rights,
            'basisNames' => $basisNames,
        ]);
    }
}
