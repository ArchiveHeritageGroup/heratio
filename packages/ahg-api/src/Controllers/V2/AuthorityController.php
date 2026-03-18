<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorityController extends BaseApiController
{
    /**
     * GET /api/v2/authorities
     */
    public function index(Request $request): JsonResponse
    {
        ['page' => $page, 'limit' => $limit, 'sort' => $sort, 'sortDir' => $sortDir] = $this->paginationParams($request);
        $offset = ($page - 1) * $limit;

        $query = DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->where('actor_i18n.culture', $this->culture)
            ->where('object.class_name', 'QubitActor')
            ->where('actor.parent_id', '!=', 0);

        if ($type = $request->get('entity_type')) {
            $query->where('actor.entity_type_id', $type);
        }

        $total = $query->count();

        match ($sort) {
            'alphabetic', 'name' => $query->orderBy('actor_i18n.authorized_form_of_name', $sortDir),
            default => $query->orderBy('object.updated_at', $sortDir),
        };

        $rows = $query->select(
            'actor.id', 'actor.entity_type_id',
            'actor_i18n.authorized_form_of_name', 'actor_i18n.dates_of_existence',
            'object.created_at', 'object.updated_at', 'slug.slug'
        )->offset($offset)->limit($limit)->get();

        $typeNames = $this->resolveTermNames($rows->pluck('entity_type_id'));

        $data = $rows->map(fn ($r) => [
            'id' => $r->id,
            'slug' => $r->slug,
            'authorized_form_of_name' => $r->authorized_form_of_name,
            'dates_of_existence' => $r->dates_of_existence,
            'entity_type' => $typeNames[$r->entity_type_id] ?? null,
            'created_at' => $r->created_at,
            'updated_at' => $r->updated_at,
        ]);

        return $this->paginated($data, $total, $page, $limit, '/api/v2/authorities');
    }

    /**
     * GET /api/v2/authorities/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $actor = DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('actor_i18n.culture', $this->culture)
            ->select('actor.*', 'actor_i18n.*', 'object.created_at', 'object.updated_at', 'slug.slug')
            ->first();

        if (!$actor) {
            return $this->error('Not Found', "Authority '{$slug}' not found.", 404);
        }

        // Other names
        $otherNames = DB::table('other_name')
            ->leftJoin('other_name_i18n', function ($j) {
                $j->on('other_name.id', '=', 'other_name_i18n.id')->where('other_name_i18n.culture', $this->culture);
            })
            ->where('other_name.object_id', $actor->id)
            ->select('other_name.type_id', 'other_name_i18n.name')
            ->get()
            ->map(fn ($n) => ['name' => $n->name, 'type' => $this->termName($n->type_id)])
            ->values();

        // Contact information
        $contacts = DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($j) {
                $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', $this->culture);
            })
            ->where('contact_information.actor_id', $actor->id)
            ->select('contact_information.*', 'contact_information_i18n.*')
            ->get();

        // Related resources
        $resources = DB::table('event')
            ->join('information_object_i18n', function ($j) {
                $j->on('event.object_id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', $this->culture);
            })
            ->join('slug as rs', 'event.object_id', '=', 'rs.object_id')
            ->where('event.actor_id', $actor->id)
            ->where('event.object_id', '!=', 1)
            ->select('event.object_id as id', 'information_object_i18n.title', 'rs.slug')
            ->distinct()
            ->limit(50)
            ->get();

        return $this->success([
            'id' => $actor->id,
            'slug' => $actor->slug,
            'authorized_form_of_name' => $actor->authorized_form_of_name,
            'entity_type' => $this->termName($actor->entity_type_id),
            'dates_of_existence' => $actor->dates_of_existence,
            'history' => $actor->history,
            'places' => $actor->places,
            'legal_status' => $actor->legal_status,
            'functions' => $actor->functions,
            'mandates' => $actor->mandates,
            'internal_structures' => $actor->internal_structures,
            'general_context' => $actor->general_context,
            'description_status' => $this->termName($actor->description_status_id ?? null),
            'description_detail' => $this->termName($actor->description_detail_id ?? null),
            'other_names' => $otherNames,
            'contact_information' => $contacts,
            'related_resources' => $resources,
            'created_at' => $actor->created_at,
            'updated_at' => $actor->updated_at,
        ]);
    }

    protected function resolveTermNames($ids): array
    {
        $ids = $ids->filter()->unique()->values()->toArray();
        if (empty($ids)) return [];
        return DB::table('term_i18n')->whereIn('id', $ids)->where('culture', $this->culture)->pluck('name', 'id')->toArray();
    }
}
