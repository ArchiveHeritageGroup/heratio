<?php

namespace AhgApi\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActorApiController extends Controller
{
    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale();
    }

    /**
     * GET /api/v1/actors
     *
     * Paginated list of authority records.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $sort = $request->get('sort', 'alphabetic');
        $sortDir = $request->get('sort_direction', 'asc');
        $entityType = $request->get('entity_type');
        $offset = ($page - 1) * $limit;

        $query = DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->where('actor_i18n.culture', $this->culture)
            ->where('object.class_name', 'QubitActor')
            ->where('actor.parent_id', '!=', 0); // Exclude root

        if ($entityType) {
            $query->where('actor.entity_type_id', $entityType);
        }

        $total = $query->count();

        // Sort
        switch ($sort) {
            case 'updated':
                $query->orderBy('object.updated_at', $sortDir === 'asc' ? 'asc' : 'desc');
                break;
            default: // alphabetic
                $query->orderBy('actor_i18n.authorized_form_of_name', $sortDir === 'desc' ? 'desc' : 'asc');
                break;
        }

        $rows = $query
            ->select([
                'actor.id',
                'actor.entity_type_id',
                'actor_i18n.authorized_form_of_name',
                'actor_i18n.dates_of_existence',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Resolve entity type names
        $typeIds = $rows->pluck('entity_type_id')->filter()->unique()->values()->toArray();
        $typeNames = [];
        if (!empty($typeIds)) {
            $typeNames = DB::table('term_i18n')
                ->whereIn('id', $typeIds)
                ->where('culture', $this->culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        $data = $rows->map(function ($row) use ($typeNames) {
            return [
                'id' => $row->id,
                'slug' => $row->slug,
                'authorized_form_of_name' => $row->authorized_form_of_name,
                'dates_of_existence' => $row->dates_of_existence,
                'entity_type' => $typeNames[$row->entity_type_id] ?? null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        });

        return $this->paginatedResponse($data, $total, $page, $limit, 'actors');
    }

    /**
     * GET /api/v1/actors/{slug}
     *
     * Full actor with all ISAAR(CPF) fields.
     */
    public function show(string $slug): JsonResponse
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Authority record '{$slug}' not found.",
            ], 404);
        }

        $actor = DB::table('actor')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->where('actor.id', $objectId)
            ->where('object.class_name', 'QubitActor')
            ->select([
                'actor.id',
                'actor.entity_type_id',
                'actor.description_status_id',
                'actor.description_detail_id',
                'actor.description_identifier',
                'actor.source_standard',
                'actor.corporate_body_identifiers',
                'actor.source_culture',
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
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$actor) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Authority record '{$slug}' not found.",
            ], 404);
        }

        // Entity type name
        $entityTypeName = null;
        if ($actor->entity_type_id) {
            $entityTypeName = DB::table('term_i18n')
                ->where('id', $actor->entity_type_id)
                ->where('culture', $this->culture)
                ->value('name');
        }

        // Other names
        $otherNames = DB::table('other_name')
            ->leftJoin('other_name_i18n', function ($j) {
                $j->on('other_name.id', '=', 'other_name_i18n.id')
                    ->where('other_name_i18n.culture', '=', $this->culture);
            })
            ->where('other_name.object_id', $actor->id)
            ->select('other_name.type_id', 'other_name_i18n.name', 'other_name_i18n.dates')
            ->get()
            ->map(function ($name) {
                return [
                    'name' => $name->name,
                    'type_id' => $name->type_id,
                    'dates' => $name->dates,
                ];
            });

        // Contact information
        $contacts = DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($j) {
                $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', '=', $this->culture);
            })
            ->where('contact_information.actor_id', $actor->id)
            ->select([
                'contact_information.contact_person',
                'contact_information.street_address',
                'contact_information.website',
                'contact_information.email',
                'contact_information.telephone',
                'contact_information.fax',
                'contact_information.postal_code',
                'contact_information.country_code',
                'contact_information_i18n.contact_type',
                'contact_information_i18n.city',
                'contact_information_i18n.region',
            ])
            ->get();

        // Related resources (via events)
        $relatedResources = DB::table('event')
            ->join('information_object', 'event.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug as io_slug', 'information_object.id', '=', 'io_slug.object_id')
            ->where('event.actor_id', $actor->id)
            ->where('information_object.id', '!=', 1)
            ->select(
                'information_object.id',
                'information_object_i18n.title',
                'information_object.identifier',
                'io_slug.slug'
            )
            ->distinct()
            ->get()
            ->map(function ($res) {
                return [
                    'id' => $res->id,
                    'title' => $res->title,
                    'identifier' => $res->identifier,
                    'slug' => $res->slug,
                ];
            });

        // Description term names
        $descStatusName = $this->termName($actor->description_status_id);
        $descDetailName = $this->termName($actor->description_detail_id);

        $data = [
            'id' => $actor->id,
            'slug' => $actor->slug,
            'authorized_form_of_name' => $actor->authorized_form_of_name,
            'entity_type' => $entityTypeName,
            'entity_type_id' => $actor->entity_type_id,
            'corporate_body_identifiers' => $actor->corporate_body_identifiers,
            'description_status' => $descStatusName,
            'description_detail' => $descDetailName,
            'description_identifier' => $actor->description_identifier,
            'source_standard' => $actor->source_standard,
            'source_culture' => $actor->source_culture,
            // ISAAR(CPF) fields
            'dates_of_existence' => $actor->dates_of_existence,
            'history' => $actor->history,
            'places' => $actor->places,
            'legal_status' => $actor->legal_status,
            'functions' => $actor->functions,
            'mandates' => $actor->mandates,
            'internal_structures' => $actor->internal_structures,
            'general_context' => $actor->general_context,
            'institution_responsible_identifier' => $actor->institution_responsible_identifier,
            'rules' => $actor->rules,
            'sources' => $actor->sources,
            'revision_history' => $actor->revision_history,
            // Related data
            'other_names' => $otherNames->values(),
            'contacts' => $contacts->values(),
            'related_resources' => $relatedResources->values(),
            'created_at' => $actor->created_at,
            'updated_at' => $actor->updated_at,
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * Resolve a term name by ID.
     */
    protected function termName(?int $termId): ?string
    {
        if (!$termId) {
            return null;
        }

        return DB::table('term_i18n')
            ->where('id', $termId)
            ->where('culture', $this->culture)
            ->value('name');
    }

    /**
     * Build a paginated JSON response.
     */
    protected function paginatedResponse($data, int $total, int $page, int $limit, string $path): JsonResponse
    {
        $lastPage = max(1, (int) ceil($total / $limit));
        $baseUrl = url("/api/v1/{$path}");

        $links = ['self' => "{$baseUrl}?page={$page}&limit={$limit}"];
        if ($page < $lastPage) {
            $links['next'] = "{$baseUrl}?page=" . ($page + 1) . "&limit={$limit}";
        }
        if ($page > 1) {
            $links['prev'] = "{$baseUrl}?page=" . ($page - 1) . "&limit={$limit}";
        }
        $links['first'] = "{$baseUrl}?page=1&limit={$limit}";
        $links['last'] = "{$baseUrl}?page={$lastPage}&limit={$limit}";

        return response()->json([
            'data' => $data->values(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'last_page' => $lastPage,
            ],
            'links' => $links,
        ]);
    }
}
