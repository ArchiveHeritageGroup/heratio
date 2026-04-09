<?php

namespace AhgApi\Controllers\V2;

use AhgApi\Services\WebhookService;
use AhgCore\Constants\TermId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DescriptionController extends BaseApiController
{
    public function __construct(protected WebhookService $webhooks)
    {
        parent::__construct();
    }

    /**
     * GET /api/v2/descriptions
     */
    public function index(Request $request): JsonResponse
    {
        ['page' => $page, 'limit' => $limit, 'sort' => $sort, 'sortDir' => $sortDir] = $this->paginationParams($request);
        $offset = ($page - 1) * $limit;

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->join('object', 'io.id', '=', 'object.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', TermId::STATUS_TYPE_PUBLICATION);
            })
            ->where('ioi.culture', $this->culture)
            ->where('io.id', '!=', 1)
            ->where('status.status_id', TermId::PUBLICATION_STATUS_PUBLISHED);

        if ($repo = $request->get('repository')) {
            $repoId = is_numeric($repo) ? $repo : $this->slugToId($repo);
            if ($repoId) {
                $query->where('io.repository_id', $repoId);
            }
        }
        if ($level = $request->get('level')) {
            $query->where('io.level_of_description_id', $level);
        }
        if ($parent = $request->get('parent')) {
            $query->where('io.parent_id', $parent);
        }

        $total = $query->count();

        match ($sort) {
            'alphabetic', 'title' => $query->orderBy('ioi.title', $sortDir),
            'identifier' => $query->orderBy('io.identifier', $sortDir),
            default => $query->orderBy('object.updated_at', $sortDir),
        };

        $rows = $query->select(
            'io.id', 'io.identifier', 'io.level_of_description_id', 'io.repository_id', 'io.parent_id',
            'ioi.title', 'object.created_at', 'object.updated_at', 'slug.slug'
        )->offset($offset)->limit($limit)->get();

        $levelNames = $this->resolveTermNames($rows->pluck('level_of_description_id'));
        $repoNames = $this->resolveRepoNames($rows->pluck('repository_id'));

        $data = $rows->map(fn ($r) => [
            'id' => $r->id,
            'slug' => $r->slug,
            'identifier' => $r->identifier,
            'title' => $r->title,
            'level_of_description' => $levelNames[$r->level_of_description_id] ?? null,
            'repository' => $repoNames[$r->repository_id] ?? null,
            'parent_id' => $r->parent_id != 1 ? $r->parent_id : null,
            'created_at' => $r->created_at,
            'updated_at' => $r->updated_at,
        ]);

        return $this->paginated($data, $total, $page, $limit, '/api/v2/descriptions');
    }

    /**
     * GET /api/v2/descriptions/{slug}
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $id = $this->slugToId($slug);
        if (!$id) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        $io = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->join('object', 'io.id', '=', 'object.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('ioi.culture', $this->culture)
            ->select('io.*', 'ioi.*', 'object.created_at', 'object.updated_at', 'slug.slug')
            ->first();

        if (!$io) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        $data = $this->buildShowData($io);

        // If ?full=true, include relations (matches AtoM getFullDescription)
        if ($request->boolean('full', true)) {
            $data['events'] = $this->getEvents($io->id);
            $data['creators'] = $this->getCreators($io->id);
            $data['access_points'] = $this->getAccessPoints($io->id);
            $data['notes'] = $this->getNotes($io->id);
            $data['digital_objects'] = $this->getDigitalObjects($io->id);
            $data['properties'] = $this->getProperties($io->id);
            $data['hierarchy'] = $this->getHierarchy($io->id);
            $data['children_count'] = $this->getChildrenCount($io->id);
        }

        return $this->success($data);
    }

    /**
     * POST /api/v2/descriptions
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'title' => 'required|string|max:1024',
            'parent_slug' => 'nullable|string',
            'identifier' => 'nullable|string|max:255',
            'level_of_description_id' => 'nullable|integer',
            'repository_id' => 'nullable|integer',
            'scope_and_content' => 'nullable|string',
            'extent_and_medium' => 'nullable|string',
            'archival_history' => 'nullable|string',
            'acquisition' => 'nullable|string',
            'appraisal' => 'nullable|string',
            'accruals' => 'nullable|string',
            'arrangement' => 'nullable|string',
            'access_conditions' => 'nullable|string',
            'reproduction_conditions' => 'nullable|string',
            'physical_characteristics' => 'nullable|string',
            'finding_aids' => 'nullable|string',
            'location_of_originals' => 'nullable|string',
            'location_of_copies' => 'nullable|string',
            'related_units_of_description' => 'nullable|string',
            'rules' => 'nullable|string',
            'sources' => 'nullable|string',
            'revision_history' => 'nullable|string',
            'publication_status' => 'nullable|in:draft,published',
        ]);

        // Resolve parent
        $parentId = 1; // root
        if (!empty($input['parent_slug'])) {
            $parentId = $this->slugToId($input['parent_slug']);
            if (!$parentId) {
                return $this->error('Bad Request', "Parent '{$input['parent_slug']}' not found.", 400);
            }
        }

        $parent = DB::table('information_object')->where('id', $parentId)->first();
        if (!$parent) {
            return $this->error('Bad Request', 'Parent not found.', 400);
        }

        return DB::transaction(function () use ($input, $parent, $parentId) {
            // Shift nested set to make room
            $rgt = $parent->rgt;
            DB::table('information_object')->where('lft', '>', $rgt)->update(['lft' => DB::raw('lft + 2')]);
            DB::table('information_object')->where('rgt', '>=', $rgt)->update(['rgt' => DB::raw('rgt + 2')]);

            // Create object row
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitInformationObject',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create information_object row
            DB::table('information_object')->insert([
                'id' => $objectId,
                'identifier' => $input['identifier'] ?? null,
                'level_of_description_id' => $input['level_of_description_id'] ?? null,
                'repository_id' => $input['repository_id'] ?? null,
                'parent_id' => $parentId,
                'lft' => $rgt,
                'rgt' => $rgt + 1,
                'source_culture' => $this->culture,
            ]);

            // Create i18n row
            $i18nFields = ['title', 'scope_and_content', 'extent_and_medium', 'archival_history',
                'acquisition', 'appraisal', 'accruals', 'arrangement', 'access_conditions',
                'reproduction_conditions', 'physical_characteristics', 'finding_aids',
                'location_of_originals', 'location_of_copies', 'related_units_of_description',
                'rules', 'sources', 'revision_history'];
            $i18nData = ['id' => $objectId, 'culture' => $this->culture];
            foreach ($i18nFields as $field) {
                if (isset($input[$field])) {
                    $i18nData[$field] = $input[$field];
                }
            }
            DB::table('information_object_i18n')->insert($i18nData);

            // Create slug
            $slug = $this->generateSlug($input['title']);
            DB::table('slug')->insert(['object_id' => $objectId, 'slug' => $slug]);

            // Set publication status (default draft)
            $statusId = ($input['publication_status'] ?? 'draft') === 'published'
                ? TermId::PUBLICATION_STATUS_PUBLISHED
                : TermId::PUBLICATION_STATUS_DRAFT;
            DB::table('status')->insert([
                'object_id' => $objectId,
                'type_id' => TermId::STATUS_TYPE_PUBLICATION,
                'status_id' => $statusId,
            ]);

            // Trigger webhook
            $this->webhooks->trigger('item.created', 'informationobject', $objectId, [
                'slug' => $slug,
                'title' => $input['title'],
                'identifier' => $input['identifier'] ?? null,
            ]);

            return $this->success([
                'id' => $objectId,
                'slug' => $slug,
                'title' => $input['title'],
            ], 201);
        });
    }

    /**
     * PUT /api/v2/descriptions/{slug}
     */
    public function update(string $slug, Request $request): JsonResponse
    {
        $id = $this->slugToId($slug);
        if (!$id) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        $input = $request->validate([
            'title' => 'nullable|string|max:1024',
            'identifier' => 'nullable|string|max:255',
            'level_of_description_id' => 'nullable|integer',
            'repository_id' => 'nullable|integer',
            'scope_and_content' => 'nullable|string',
            'extent_and_medium' => 'nullable|string',
            'archival_history' => 'nullable|string',
            'acquisition' => 'nullable|string',
            'appraisal' => 'nullable|string',
            'accruals' => 'nullable|string',
            'arrangement' => 'nullable|string',
            'access_conditions' => 'nullable|string',
            'reproduction_conditions' => 'nullable|string',
            'physical_characteristics' => 'nullable|string',
            'finding_aids' => 'nullable|string',
            'location_of_originals' => 'nullable|string',
            'location_of_copies' => 'nullable|string',
            'related_units_of_description' => 'nullable|string',
            'rules' => 'nullable|string',
            'sources' => 'nullable|string',
            'revision_history' => 'nullable|string',
            'publication_status' => 'nullable|in:draft,published',
        ]);

        DB::transaction(function () use ($id, $input) {
            // Update base table fields
            $baseFields = ['identifier', 'level_of_description_id', 'repository_id'];
            $baseUpdate = array_intersect_key($input, array_flip($baseFields));
            if (!empty($baseUpdate)) {
                DB::table('information_object')->where('id', $id)->update($baseUpdate);
            }

            // Update i18n fields
            $i18nFields = ['title', 'scope_and_content', 'extent_and_medium', 'archival_history',
                'acquisition', 'appraisal', 'accruals', 'arrangement', 'access_conditions',
                'reproduction_conditions', 'physical_characteristics', 'finding_aids',
                'location_of_originals', 'location_of_copies', 'related_units_of_description',
                'rules', 'sources', 'revision_history'];
            $i18nUpdate = array_intersect_key($input, array_flip($i18nFields));
            if (!empty($i18nUpdate)) {
                DB::table('information_object_i18n')
                    ->where('id', $id)
                    ->where('culture', $this->culture)
                    ->update($i18nUpdate);
            }

            // Update publication status
            if (isset($input['publication_status'])) {
                $statusId = $input['publication_status'] === 'published'
                    ? TermId::PUBLICATION_STATUS_PUBLISHED
                    : TermId::PUBLICATION_STATUS_DRAFT;
                DB::table('status')
                    ->where('object_id', $id)
                    ->where('type_id', TermId::STATUS_TYPE_PUBLICATION)
                    ->update(['status_id' => $statusId]);
            }

            // Update timestamp
            DB::table('object')->where('id', $id)->update(['updated_at' => now()]);
        });

        $this->webhooks->trigger('item.updated', 'informationobject', $id, [
            'slug' => $slug,
            'title' => $input['title'] ?? null,
        ]);

        return $this->success(['id' => $id, 'slug' => $slug, 'message' => 'Description updated.']);
    }

    /**
     * DELETE /api/v2/descriptions/{slug}
     */
    public function destroy(string $slug): JsonResponse
    {
        $id = $this->slugToId($slug);
        if (!$id) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        // Check for children
        $io = DB::table('information_object')->where('id', $id)->first();
        if (!$io) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        $hasChildren = DB::table('information_object')
            ->where('parent_id', $id)
            ->exists();

        if ($hasChildren) {
            return $this->error('Conflict', 'Cannot delete description with children. Delete children first.', 409);
        }

        DB::transaction(function () use ($id, $io) {
            // Delete related records
            DB::table('status')->where('object_id', $id)->delete();
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('information_object_i18n')->where('id', $id)->delete();
            DB::table('note')->where('object_id', $id)->delete();
            DB::table('note_i18n')->whereIn('id', function ($q) use ($id) {
                $q->select('id')->from('note')->where('object_id', $id);
            })->delete();
            DB::table('event')->where('object_id', $id)->delete();
            DB::table('relation')->where('subject_id', $id)->orWhere('object_id', $id)->delete();
            DB::table('property')->where('object_id', $id)->delete();
            DB::table('object_term_relation')->where('object_id', $id)->delete();
            DB::table('information_object')->where('id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();

            // Close nested set gap
            $width = $io->rgt - $io->lft + 1;
            DB::table('information_object')->where('lft', '>', $io->rgt)->update(['lft' => DB::raw("lft - {$width}")]);
            DB::table('information_object')->where('rgt', '>', $io->rgt)->update(['rgt' => DB::raw("rgt - {$width}")]);
        });

        $this->webhooks->trigger('item.deleted', 'informationobject', $id, ['slug' => $slug]);

        return $this->success(['id' => $id, 'slug' => $slug, 'message' => 'Description deleted.']);
    }

    // -- Helper methods --

    protected function buildShowData(object $io): array
    {
        $repo = null;
        if ($io->repository_id) {
            $repo = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug as rs', 'repository.id', '=', 'rs.object_id')
                ->where('repository.id', $io->repository_id)
                ->where('actor_i18n.culture', $this->culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'rs.slug')
                ->first();
        }

        $pubStatus = null;
        $statusRow = DB::table('status')->where('object_id', $io->id)->where('type_id', TermId::STATUS_TYPE_PUBLICATION)->first();
        if ($statusRow) {
            $pubStatus = $this->termName($statusRow->status_id);
        }

        return [
            'id' => $io->id,
            'slug' => $io->slug,
            'identifier' => $io->identifier,
            'title' => $io->title,
            'level_of_description' => $this->termName($io->level_of_description_id),
            'level_of_description_id' => $io->level_of_description_id,
            'repository' => $repo ? ['id' => $repo->id, 'name' => $repo->name, 'slug' => $repo->slug] : null,
            'publication_status' => $pubStatus,
            'description_status' => $this->termName($io->description_status_id ?? null),
            'description_detail' => $this->termName($io->description_detail_id ?? null),
            'parent_id' => ($io->parent_id ?? 1) != 1 ? $io->parent_id : null,
            'extent_and_medium' => $io->extent_and_medium ?? null,
            'archival_history' => $io->archival_history ?? null,
            'acquisition' => $io->acquisition ?? null,
            'scope_and_content' => $io->scope_and_content ?? null,
            'appraisal' => $io->appraisal ?? null,
            'accruals' => $io->accruals ?? null,
            'arrangement' => $io->arrangement ?? null,
            'access_conditions' => $io->access_conditions ?? null,
            'reproduction_conditions' => $io->reproduction_conditions ?? null,
            'physical_characteristics' => $io->physical_characteristics ?? null,
            'finding_aids' => $io->finding_aids ?? null,
            'location_of_originals' => $io->location_of_originals ?? null,
            'location_of_copies' => $io->location_of_copies ?? null,
            'related_units_of_description' => $io->related_units_of_description ?? null,
            'rules' => $io->rules ?? null,
            'sources' => $io->sources ?? null,
            'revision_history' => $io->revision_history ?? null,
            'created_at' => $io->created_at,
            'updated_at' => $io->updated_at,
        ];
    }

    protected function getEvents(int $objectId): array
    {
        $events = DB::table('event')
            ->leftJoin('event_i18n', function ($j) {
                $j->on('event.id', '=', 'event_i18n.id')->where('event_i18n.culture', $this->culture);
            })
            ->where('event.object_id', $objectId)
            ->select('event.id', 'event.type_id', 'event.actor_id', 'event.start_date', 'event.end_date',
                'event_i18n.date as date_display', 'event_i18n.name as event_name')
            ->get();

        $typeNames = $this->resolveTermNames($events->pluck('type_id'));
        $actorNames = $this->resolveActorNames($events->pluck('actor_id'));

        return $events->map(fn ($e) => [
            'id' => $e->id,
            'type' => $typeNames[$e->type_id] ?? null,
            'type_id' => $e->type_id,
            'actor' => $actorNames[$e->actor_id] ?? null,
            'actor_id' => $e->actor_id,
            'date_display' => $e->date_display,
            'start_date' => $e->start_date,
            'end_date' => $e->end_date,
        ])->values()->toArray();
    }

    protected function getCreators(int $objectId): array
    {
        return DB::table('event')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('slug as cs', 'event.actor_id', '=', 'cs.object_id')
            ->where('event.object_id', $objectId)
            ->where('event.type_id', TermId::EVENT_TYPE_CREATION)
            ->where('actor_i18n.culture', $this->culture)
            ->whereNotNull('event.actor_id')
            ->select('event.actor_id as id', 'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.dates_of_existence', 'actor_i18n.history', 'cs.slug')
            ->distinct()
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug,
                'dates_of_existence' => $c->dates_of_existence, 'history' => $c->history])
            ->values()->toArray();
    }

    protected function getAccessPoints(int $objectId): array
    {
        $getTerms = fn (int $taxonomyId) => DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $objectId)
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $this->culture)
            ->pluck('term_i18n.name')->values()->toArray();

        $names = DB::table('relation')
            ->join('actor_i18n', 'relation.object_id', '=', 'actor_i18n.id')
            ->where('relation.subject_id', $objectId)
            ->where('relation.type_id', TermId::RELATION_NAME_ACCESS_POINT)
            ->where('actor_i18n.culture', $this->culture)
            ->pluck('actor_i18n.authorized_form_of_name')->values()->toArray();

        return [
            'subjects' => $getTerms(35),
            'places' => $getTerms(42),
            'genres' => $getTerms(78),
            'names' => $names,
        ];
    }

    protected function getNotes(int $objectId): array
    {
        $notes = DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $objectId)
            ->where('note_i18n.culture', $this->culture)
            ->select('note.type_id', 'note_i18n.content')
            ->get();

        $typeNames = $this->resolveTermNames($notes->pluck('type_id'));

        return $notes->map(fn ($n) => [
            'type' => $typeNames[$n->type_id] ?? null,
            'content' => $n->content,
        ])->values()->toArray();
    }

    /**
     * Get digital objects attached to an information object.
     * Ported from AtoM ApiRepository::getDigitalObjects().
     */
    protected function getDigitalObjects(int $objectId): array
    {
        $objects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->select('id', 'name', 'path', 'mime_type', 'byte_size', 'checksum', 'usage_id')
            ->get();

        return $objects->map(function ($row) {
            $thumbnailPath = null;
            if ($row->path) {
                $pathInfo = pathinfo($row->path);
                $thumbnailPath = '/uploads/' . $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_142.' . ($pathInfo['extension'] ?? 'jpg');
            }
            return [
                'id' => $row->id,
                'name' => $row->name,
                'mime_type' => $row->mime_type,
                'byte_size' => $row->byte_size,
                'checksum' => $row->checksum,
                'thumbnail_url' => $thumbnailPath,
                'master_url' => $row->path ? '/uploads/' . $row->path : null,
            ];
        })->values()->toArray();
    }

    /**
     * Get custom properties for an information object.
     * Ported from AtoM ApiRepository::getProperties().
     */
    protected function getProperties(int $objectId): array
    {
        $props = DB::table('property as p')
            ->leftJoin('property_i18n as pi', function ($j) {
                $j->on('p.id', '=', 'pi.id')->where('pi.culture', $this->culture);
            })
            ->where('p.object_id', $objectId)
            ->select('p.name', 'pi.value')
            ->get();

        $result = [];
        foreach ($props as $prop) {
            if ($prop->name && $prop->value) {
                $result[$prop->name] = $prop->value;
            }
        }
        return $result;
    }

    /**
     * Get ancestor hierarchy (breadcrumb) for an information object.
     * Ported from AtoM ApiRepository::getHierarchy().
     */
    protected function getHierarchy(int $objectId): array
    {
        $current = DB::table('information_object')
            ->where('id', $objectId)
            ->select('parent_id', 'lft', 'rgt')
            ->first();

        if (!$current || $current->parent_id == 1) {
            return [];
        }

        $ancestors = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', $this->culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.lft', '<', $current->lft)
            ->where('io.rgt', '>', $current->rgt)
            ->where('io.id', '!=', 1)
            ->select('io.id', 'slug.slug', 'ioi.title', 'io.lft')
            ->orderBy('io.lft', 'asc')
            ->get();

        return $ancestors->map(fn ($row) => [
            'id' => $row->id,
            'slug' => $row->slug,
            'title' => $row->title,
        ])->values()->toArray();
    }

    /**
     * Get direct children count for an information object.
     * Ported from AtoM ApiRepository::getChildrenCount().
     */
    protected function getChildrenCount(int $objectId): int
    {
        return DB::table('information_object')
            ->where('parent_id', $objectId)
            ->count();
    }

    protected function resolveTermNames($ids): array
    {
        $ids = $ids->filter()->unique()->values()->toArray();
        if (empty($ids)) return [];
        return DB::table('term_i18n')->whereIn('id', $ids)->where('culture', $this->culture)->pluck('name', 'id')->toArray();
    }

    protected function resolveRepoNames($ids): array
    {
        $ids = $ids->filter()->unique()->values()->toArray();
        if (empty($ids)) return [];
        return DB::table('actor_i18n')->whereIn('id', $ids)->where('culture', $this->culture)->pluck('authorized_form_of_name', 'id')->toArray();
    }

    protected function resolveActorNames($ids): array
    {
        $ids = $ids->filter()->unique()->values()->toArray();
        if (empty($ids)) return [];
        return DB::table('actor_i18n')->whereIn('id', $ids)->where('culture', $this->culture)->pluck('authorized_form_of_name', 'id')->toArray();
    }
}
