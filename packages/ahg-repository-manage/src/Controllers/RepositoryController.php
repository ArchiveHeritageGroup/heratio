<?php

namespace AhgRepositoryManage\Controllers;

use AhgRepositoryManage\Services\RepositoryBrowseService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepositoryController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new RepositoryBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-repository-manage::browse', [
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

        $repository = DB::table('repository')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->join('repository_i18n', 'repository.id', '=', 'repository_i18n.id')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->leftJoin('actor', 'repository.id', '=', 'actor.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('repository_i18n.culture', $culture)
            ->where('actor_i18n.culture', $culture)
            ->select([
                'repository.id',
                // From actor_i18n (repository extends actor)
                'actor_i18n.authorized_form_of_name',
                'actor_i18n.dates_of_existence',
                'actor_i18n.history',
                'actor_i18n.places',
                'actor_i18n.legal_status',
                'actor_i18n.functions',
                'actor_i18n.mandates',
                'actor_i18n.internal_structures',
                'actor_i18n.general_context',
                'actor_i18n.institution_responsible_identifier',
                'actor_i18n.rules',
                'actor_i18n.sources',
                'actor_i18n.revision_history',
                // From actor
                'actor.description_identifier as identifier',
                'actor.corporate_body_identifiers',
                // From repository_i18n
                'repository_i18n.geocultural_context',
                'repository_i18n.collecting_policies',
                'repository_i18n.buildings',
                'repository_i18n.holdings',
                'repository_i18n.finding_aids',
                'repository_i18n.opening_times',
                'repository_i18n.access_conditions',
                'repository_i18n.disabled_access',
                'repository_i18n.research_services',
                'repository_i18n.reproduction_services',
                'repository_i18n.public_facilities',
                'repository_i18n.desc_institution_identifier',
                'repository_i18n.desc_rules',
                'repository_i18n.desc_sources',
                'repository_i18n.desc_revision_history',
                // From object
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$repository) {
            abort(404);
        }

        // Get contact information
        $contacts = DB::table('contact_information')
            ->join('contact_information_i18n', 'contact_information.id', '=', 'contact_information_i18n.id')
            ->where('contact_information.actor_id', $repository->id)
            ->where('contact_information_i18n.culture', $culture)
            ->select('contact_information.*', 'contact_information_i18n.*')
            ->get();

        // Get digital object (thumbnail)
        $digitalObject = DB::table('digital_object')
            ->where('object_id', $repository->id)
            ->first();

        // Get holdings count (top-level descriptions in this repository)
        $holdingsCount = DB::table('information_object')
            ->where('repository_id', $repository->id)
            ->where('id', '!=', 1)
            ->count();

        return view('ahg-repository-manage::show', [
            'repository' => $repository,
            'contacts' => $contacts,
            'digitalObject' => $digitalObject,
            'holdingsCount' => $holdingsCount,
        ]);
    }
}
