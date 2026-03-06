<?php

namespace AhgActorManage\Controllers;

use AhgActorManage\Services\ActorBrowseService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActorController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new ActorBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-actor-manage::browse', [
            'pager' => $pager,
            'entityTypeNames' => $result['entityTypeNames'] ?? [],
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

        $actor = DB::table('actor')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('actor_i18n.culture', $culture)
            ->select([
                'actor.id',
                'actor.entity_type_id',
                'actor.description_identifier',
                'actor_i18n.authorized_form_of_name',
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
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$actor) {
            abort(404);
        }

        // Get entity type name
        $entityTypeName = null;
        if ($actor->entity_type_id) {
            $entityTypeName = DB::table('term_i18n')
                ->where('id', $actor->entity_type_id)
                ->where('culture', $culture)
                ->value('name');
        }

        // Get other names
        $otherNames = DB::table('other_name')
            ->join('other_name_i18n', 'other_name.id', '=', 'other_name_i18n.id')
            ->where('other_name.object_id', $actor->id)
            ->where('other_name_i18n.culture', $culture)
            ->select('other_name_i18n.name', 'other_name.type_id')
            ->get();

        // Get events (dates)
        $events = DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.actor_id', $actor->id)
            ->where('event_i18n.culture', $culture)
            ->select('event.*', 'event_i18n.date as date_display', 'event_i18n.name as event_name')
            ->get();

        // Get related resources (information objects linked via event)
        $relatedResources = DB::table('event')
            ->join('information_object', 'event.object_id', '=', 'information_object.id')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('event.actor_id', $actor->id)
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

        // Get contact information
        $contacts = DB::table('contact_information')
            ->join('contact_information_i18n', 'contact_information.id', '=', 'contact_information_i18n.id')
            ->where('contact_information.actor_id', $actor->id)
            ->where('contact_information_i18n.culture', $culture)
            ->select('contact_information.*', 'contact_information_i18n.*')
            ->get();

        // Get digital object (thumbnail)
        $digitalObject = DB::table('digital_object')
            ->where('object_id', $actor->id)
            ->first();

        return view('ahg-actor-manage::show', [
            'actor' => $actor,
            'entityTypeName' => $entityTypeName,
            'otherNames' => $otherNames,
            'events' => $events,
            'relatedResources' => $relatedResources,
            'contacts' => $contacts,
            'digitalObject' => $digitalObject,
        ]);
    }
}
