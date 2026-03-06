<?php

namespace AhgDonorManage\Controllers;

use AhgDonorManage\Services\DonorBrowseService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonorController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new DonorBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-donor-manage::browse', [
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

        $donor = DB::table('donor')
            ->join('slug', 'donor.id', '=', 'slug.object_id')
            ->join('actor_i18n', 'donor.id', '=', 'actor_i18n.id')
            ->join('object', 'donor.id', '=', 'object.id')
            ->leftJoin('actor', 'donor.id', '=', 'actor.id')
            ->where('slug.slug', $slug)
            ->where('actor_i18n.culture', $culture)
            ->select([
                'donor.id',
                'actor.description_identifier',
                'actor_i18n.authorized_form_of_name',
                'actor_i18n.history',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$donor) {
            abort(404);
        }

        // Get contact information
        $contacts = DB::table('contact_information')
            ->join('contact_information_i18n', 'contact_information.id', '=', 'contact_information_i18n.id')
            ->where('contact_information.actor_id', $donor->id)
            ->where('contact_information_i18n.culture', $culture)
            ->select('contact_information.*', 'contact_information_i18n.*')
            ->get();

        // Get related accessions via relation table (donor→accession link)
        $accessions = DB::table('relation')
            ->join('accession', 'relation.object_id', '=', 'accession.id')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->join('slug', 'accession.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $donor->id)
            ->where('accession_i18n.culture', $culture)
            ->select([
                'accession.id',
                'accession.identifier',
                'accession_i18n.title',
                'slug.slug',
            ])
            ->get();

        return view('ahg-donor-manage::show', [
            'donor' => $donor,
            'contacts' => $contacts,
            'accessions' => $accessions,
        ]);
    }
}
