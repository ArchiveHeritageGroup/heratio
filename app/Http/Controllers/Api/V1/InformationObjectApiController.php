<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformationObjectApiController extends Controller
{
    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale();
    }

    /**
     * GET /api/v1/informationobjects
     *
     * Paginated list of archival descriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $sort = $request->get('sort', 'updated');
        $sortDir = $request->get('sort_direction', 'desc');
        $repositoryFilter = $request->get('repository');
        $levelFilter = $request->get('level');
        $parentFilter = $request->get('parent');
        $offset = ($page - 1) * $limit;

        $query = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->leftJoin('status', function ($j) {
                $j->on('information_object.id', '=', 'status.object_id')
                    ->where('status.type_id', '=', 158);
            })
            ->where('information_object_i18n.culture', $this->culture)
            ->where('information_object.id', '!=', 1) // Exclude root
            ->where('status.status_id', '=', 160); // Published only

        if ($repositoryFilter) {
            $query->where('information_object.repository_id', $repositoryFilter);
        }

        if ($levelFilter) {
            $query->where('information_object.level_of_description_id', $levelFilter);
        }

        if ($parentFilter) {
            $query->where('information_object.parent_id', $parentFilter);
        }

        $total = $query->count();

        // Sort
        switch ($sort) {
            case 'alphabetic':
            case 'title':
                $query->orderBy('information_object_i18n.title', $sortDir);
                break;
            case 'identifier':
                $query->orderBy('information_object.identifier', $sortDir);
                break;
            default: // 'updated'
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }

        $rows = $query
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object_i18n.title',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Resolve level names
        $levelIds = $rows->pluck('level_of_description_id')->filter()->unique()->values()->toArray();
        $levelNames = [];
        if (!empty($levelIds)) {
            $levelNames = DB::table('term_i18n')
                ->whereIn('id', $levelIds)
                ->where('culture', $this->culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Resolve repository names
        $repoIds = $rows->pluck('repository_id')->filter()->unique()->values()->toArray();
        $repoNames = [];
        if (!empty($repoIds)) {
            $repoNames = DB::table('actor_i18n')
                ->whereIn('id', $repoIds)
                ->where('culture', $this->culture)
                ->pluck('authorized_form_of_name', 'id')
                ->toArray();
        }

        $data = $rows->map(function ($row) use ($levelNames, $repoNames) {
            return [
                'id' => $row->id,
                'slug' => $row->slug,
                'identifier' => $row->identifier,
                'title' => $row->title,
                'level_of_description' => $levelNames[$row->level_of_description_id] ?? null,
                'repository' => $repoNames[$row->repository_id] ?? null,
                'parent_id' => $row->parent_id != 1 ? $row->parent_id : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        });

        return $this->paginatedResponse($data, $total, $page, $limit, 'informationobjects');
    }

    /**
     * GET /api/v1/informationobjects/search?query=X
     *
     * Search information objects by title, identifier, scope and content.
     */
    public function search(Request $request): JsonResponse
    {
        $queryStr = $request->get('query', '');
        if (empty($queryStr)) {
            return response()->json([
                'error' => 'Bad Request',
                'message' => 'The "query" parameter is required.',
            ], 400);
        }

        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $searchTerm = '%' . $queryStr . '%';

        $query = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->leftJoin('status', function ($j) {
                $j->on('information_object.id', '=', 'status.object_id')
                    ->where('status.type_id', '=', 158);
            })
            ->where('information_object_i18n.culture', $this->culture)
            ->where('information_object.id', '!=', 1)
            ->where('status.status_id', '=', 160)
            ->where(function ($q) use ($searchTerm) {
                $q->where('information_object_i18n.title', 'LIKE', $searchTerm)
                    ->orWhere('information_object.identifier', 'LIKE', $searchTerm)
                    ->orWhere('information_object_i18n.scope_and_content', 'LIKE', $searchTerm)
                    ->orWhere('information_object_i18n.archival_history', 'LIKE', $searchTerm);
            });

        $total = $query->count();

        $rows = $query
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.repository_id',
                'information_object_i18n.title',
                'object.updated_at',
                'slug.slug',
            ])
            ->orderByRaw("CASE WHEN information_object_i18n.title LIKE ? THEN 0 ELSE 1 END", [$searchTerm])
            ->orderBy('object.updated_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Resolve level names
        $levelIds = $rows->pluck('level_of_description_id')->filter()->unique()->values()->toArray();
        $levelNames = [];
        if (!empty($levelIds)) {
            $levelNames = DB::table('term_i18n')
                ->whereIn('id', $levelIds)
                ->where('culture', $this->culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        $data = $rows->map(function ($row) use ($levelNames) {
            return [
                'id' => $row->id,
                'slug' => $row->slug,
                'identifier' => $row->identifier,
                'title' => $row->title,
                'level_of_description' => $levelNames[$row->level_of_description_id] ?? null,
            ];
        });

        return $this->paginatedResponse($data, $total, $page, $limit, 'informationobjects/search');
    }

    /**
     * GET /api/v1/informationobjects/{slug}
     *
     * Full information object with all ISAD fields, events, creators, access points.
     */
    public function show(string $slug): JsonResponse
    {
        $io = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $this->culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.oai_local_identifier',
                'information_object.level_of_description_id',
                'information_object.collection_type_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.description_status_id',
                'information_object.description_detail_id',
                'information_object.description_identifier',
                'information_object.source_standard',
                'information_object.display_standard_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.alternate_title',
                'information_object_i18n.edition',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.arrangement',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.institution_responsible_identifier',
                'information_object_i18n.rules',
                'information_object_i18n.sources',
                'information_object_i18n.revision_history',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$io) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Description '{$slug}' not found.",
            ], 404);
        }

        // Level of description
        $levelName = null;
        if ($io->level_of_description_id) {
            $levelName = DB::table('term_i18n')
                ->where('id', $io->level_of_description_id)
                ->where('culture', $this->culture)
                ->value('name');
        }

        // Repository
        $repository = null;
        if ($io->repository_id) {
            $repository = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug as repo_slug', 'repository.id', '=', 'repo_slug.object_id')
                ->where('repository.id', $io->repository_id)
                ->where('actor_i18n.culture', $this->culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'repo_slug.slug')
                ->first();
        }

        // Events
        $events = DB::table('event')
            ->leftJoin('event_i18n', function ($j) {
                $j->on('event.id', '=', 'event_i18n.id')
                    ->where('event_i18n.culture', '=', $this->culture);
            })
            ->where('event.object_id', $io->id)
            ->select(
                'event.id',
                'event.type_id',
                'event.actor_id',
                'event.start_date',
                'event.end_date',
                'event_i18n.date as date_display',
                'event_i18n.name as event_name'
            )
            ->get();

        // Resolve event type names
        $eventTypeIds = $events->pluck('type_id')->filter()->unique()->values()->toArray();
        $eventTypeNames = [];
        if (!empty($eventTypeIds)) {
            $eventTypeNames = DB::table('term_i18n')
                ->whereIn('id', $eventTypeIds)
                ->where('culture', $this->culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Resolve actor names for events
        $actorIds = $events->pluck('actor_id')->filter()->unique()->values()->toArray();
        $actorNames = [];
        if (!empty($actorIds)) {
            $actorNames = DB::table('actor_i18n')
                ->whereIn('id', $actorIds)
                ->where('culture', $this->culture)
                ->pluck('authorized_form_of_name', 'id')
                ->toArray();
        }

        $eventsData = $events->map(function ($event) use ($eventTypeNames, $actorNames) {
            return [
                'id' => $event->id,
                'type' => $eventTypeNames[$event->type_id] ?? null,
                'type_id' => $event->type_id,
                'actor' => $actorNames[$event->actor_id] ?? null,
                'actor_id' => $event->actor_id,
                'date_display' => $event->date_display,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
            ];
        });

        // Creators (creation events)
        $creators = DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('slug as creator_slug', 'event.actor_id', '=', 'creator_slug.object_id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $this->culture)
            ->whereNotNull('event.actor_id')
            ->select(
                'event.actor_id as id',
                'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.dates_of_existence',
                'actor_i18n.history',
                'actor.entity_type_id',
                'creator_slug.slug'
            )
            ->distinct()
            ->get()
            ->map(function ($creator) {
                return [
                    'id' => $creator->id,
                    'name' => $creator->name,
                    'slug' => $creator->slug,
                    'dates_of_existence' => $creator->dates_of_existence,
                    'history' => $creator->history,
                ];
            });

        // Subject access points (taxonomy 35)
        $subjects = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 35)
            ->where('term_i18n.culture', $this->culture)
            ->pluck('term_i18n.name');

        // Place access points (taxonomy 42)
        $places = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $this->culture)
            ->pluck('term_i18n.name');

        // Genre access points (taxonomy 78)
        $genres = DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 78)
            ->where('term_i18n.culture', $this->culture)
            ->pluck('term_i18n.name');

        // Name access points (via relation table)
        $nameAccessPoints = DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $io->id)
            ->where('relation.type_id', 161)
            ->where('actor_i18n.culture', $this->culture)
            ->pluck('actor_i18n.authorized_form_of_name');

        // Publication status
        $publicationStatus = null;
        $statusRow = DB::table('status')
            ->where('object_id', $io->id)
            ->where('type_id', 158)
            ->first();
        if ($statusRow && $statusRow->status_id) {
            $publicationStatus = DB::table('term_i18n')
                ->where('id', $statusRow->status_id)
                ->where('culture', $this->culture)
                ->value('name');
        }

        // Notes
        $notes = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $this->culture)
            ->select('note.type_id', 'note_i18n.content')
            ->get();

        $noteTypeIds = $notes->pluck('type_id')->filter()->unique()->values()->toArray();
        $noteTypeNames = [];
        if (!empty($noteTypeIds)) {
            $noteTypeNames = DB::table('term_i18n')
                ->whereIn('id', $noteTypeIds)
                ->where('culture', $this->culture)
                ->pluck('name', 'id')
                ->toArray();
        }

        $notesData = $notes->map(function ($note) use ($noteTypeNames) {
            return [
                'type' => $noteTypeNames[$note->type_id] ?? null,
                'content' => $note->content,
            ];
        });

        // Description term names
        $descStatusName = $this->termName($io->description_status_id);
        $descDetailName = $this->termName($io->description_detail_id);

        // Build response
        $data = [
            'id' => $io->id,
            'slug' => $io->slug,
            'identifier' => $io->identifier,
            'oai_local_identifier' => $io->oai_local_identifier,
            'title' => $io->title,
            'alternate_title' => $io->alternate_title,
            'level_of_description' => $levelName,
            'level_of_description_id' => $io->level_of_description_id,
            'repository' => $repository ? [
                'id' => $repository->id,
                'name' => $repository->name,
                'slug' => $repository->slug,
            ] : null,
            'publication_status' => $publicationStatus,
            'description_status' => $descStatusName,
            'description_detail' => $descDetailName,
            'description_identifier' => $io->description_identifier,
            'source_standard' => $io->source_standard,
            'source_culture' => $io->source_culture,
            'parent_id' => $io->parent_id != 1 ? $io->parent_id : null,
            // ISAD(G) fields
            'edition' => $io->edition,
            'extent_and_medium' => $io->extent_and_medium,
            'archival_history' => $io->archival_history,
            'acquisition' => $io->acquisition,
            'scope_and_content' => $io->scope_and_content,
            'appraisal' => $io->appraisal,
            'accruals' => $io->accruals,
            'arrangement' => $io->arrangement,
            'access_conditions' => $io->access_conditions,
            'reproduction_conditions' => $io->reproduction_conditions,
            'physical_characteristics' => $io->physical_characteristics,
            'finding_aids' => $io->finding_aids,
            'location_of_originals' => $io->location_of_originals,
            'location_of_copies' => $io->location_of_copies,
            'related_units_of_description' => $io->related_units_of_description,
            'institution_responsible_identifier' => $io->institution_responsible_identifier,
            'rules' => $io->rules,
            'sources' => $io->sources,
            'revision_history' => $io->revision_history,
            // Related data
            'events' => $eventsData->values(),
            'creators' => $creators->values(),
            'access_points' => [
                'subjects' => $subjects->values(),
                'places' => $places->values(),
                'genres' => $genres->values(),
                'names' => $nameAccessPoints->values(),
            ],
            'notes' => $notesData->values(),
            'created_at' => $io->created_at,
            'updated_at' => $io->updated_at,
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
