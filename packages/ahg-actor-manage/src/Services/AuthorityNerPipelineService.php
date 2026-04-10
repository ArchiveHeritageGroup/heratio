<?php

/**
 * AuthorityNerPipelineService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgActorManage\Services;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\DB;

/**
 * NER-to-Authority Pipeline Service.
 *
 * Extends the NER review workflow: findMatchingActors -> create stub -> link entity.
 */
class AuthorityNerPipelineService
{
    /**
     * Get NER entities that can become authority stubs.
     */
    public function getPendingEntities(array $filters = []): array
    {
        $query = DB::table('ahg_ner_entity as ne')
            ->leftJoin('ahg_ner_authority_stub as stub', 'ne.id', '=', 'stub.ner_entity_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ne.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->whereNull('stub.id')
            ->whereIn('ne.entity_type', ['PERSON', 'ORG', 'GPE'])
            ->select(
                'ne.*',
                'ioi.title as source_title'
            );

        if (!empty($filters['entity_type'])) {
            $query->where('ne.entity_type', $filters['entity_type']);
        }

        if (!empty($filters['min_confidence'])) {
            $query->where('ne.confidence', '>=', $filters['min_confidence']);
        }

        if (!empty($filters['search'])) {
            $query->where('ne.entity_value', 'like', '%' . $filters['search'] . '%');
        }

        $sort = $filters['sort'] ?? 'ne.confidence';
        $dir = $filters['sortDir'] ?? 'desc';
        $limit = $filters['limit'] ?? 50;
        $page = $filters['page'] ?? 1;

        return $query->orderBy($sort, $dir)
            ->paginate($limit, ['*'], 'page', $page)
            ->toArray();
    }

    /**
     * Get existing authority stubs.
     */
    public function getStubs(array $filters = []): array
    {
        $query = DB::table('ahg_ner_authority_stub as s')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('s.actor_id', '=', 'ai.id')
                    ->where('ai.culture', '=', 'en');
            })
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('s.source_object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 's.actor_id', '=', 'slug.object_id')
            ->select(
                's.*',
                'ai.authorized_form_of_name as actor_name',
                'ioi.title as source_title',
                'slug.slug'
            );

        if (!empty($filters['status'])) {
            $query->where('s.status', $filters['status']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('s.entity_type', $filters['entity_type']);
        }

        $sort = $filters['sort'] ?? 's.created_at';
        $dir = $filters['sortDir'] ?? 'desc';
        $limit = $filters['limit'] ?? 50;
        $page = $filters['page'] ?? 1;

        return $query->orderBy($sort, $dir)
            ->paginate($limit, ['*'], 'page', $page)
            ->toArray();
    }

    /**
     * Find matching actors for a NER entity value.
     */
    public function findMatchingActors(string $entityValue, string $entityType = 'PERSON', int $limit = 5): array
    {
        return DB::table('actor_i18n as ai')
            ->leftJoin('slug', 'ai.id', '=', 'slug.object_id')
            ->where('ai.culture', 'en')
            ->where('ai.authorized_form_of_name', 'like', '%' . $entityValue . '%')
            ->select('ai.id', 'ai.authorized_form_of_name as name', 'slug.slug')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Create a stub authority record from a NER entity.
     */
    public function createStub(int $nerEntityId, int $userId): ?int
    {
        // Gate: auto-stub creation must be enabled
        if (!AhgSettingsService::getBool('authority_ner_auto_stub_enabled', false)) {
            return null;
        }

        $entity = DB::table('ahg_ner_entity')
            ->where('id', $nerEntityId)
            ->first();

        if (!$entity) {
            return null;
        }

        // Check confidence against configurable threshold
        $threshold = (float) AhgSettingsService::get('authority_ner_auto_stub_threshold', '0.85');
        if (($entity->confidence ?? 0) < $threshold) {
            return null;
        }

        // Check if stub already exists
        $existing = DB::table('ahg_ner_authority_stub')
            ->where('ner_entity_id', $nerEntityId)
            ->first();

        if ($existing) {
            return (int) $existing->actor_id;
        }

        // Create the actor via object/actor tables
        $objectId = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitActor',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        DB::table('actor')->insert([
            'id'             => $objectId,
            'entity_type_id' => $this->getEntityTypeId($entity->entity_type),
        ]);

        DB::table('actor_i18n')->insert([
            'id'                          => $objectId,
            'culture'                     => 'en',
            'authorized_form_of_name'     => $entity->entity_value,
            'description_identifier'      => 'NER-STUB-' . $nerEntityId,
            'sources'                     => 'Created from NER extraction',
        ]);

        $slug = $this->generateSlug($entity->entity_value);
        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug'      => $slug,
        ]);

        DB::table('ahg_ner_authority_stub')->insert([
            'ner_entity_id'   => $nerEntityId,
            'actor_id'        => $objectId,
            'source_object_id' => $entity->object_id,
            'entity_type'     => $entity->entity_type,
            'entity_value'    => $entity->entity_value,
            'confidence'      => $entity->confidence ?? 1.0,
            'status'          => 'stub',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return $objectId;
    }

    /**
     * Promote a stub to a full authority record.
     */
    public function promoteStub(int $stubId, int $userId): bool
    {
        return DB::table('ahg_ner_authority_stub')
            ->where('id', $stubId)
            ->update([
                'status'      => 'promoted',
                'promoted_by' => $userId,
                'promoted_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Reject a stub (mark as not a valid authority).
     */
    public function rejectStub(int $stubId, int $userId): bool
    {
        return DB::table('ahg_ner_authority_stub')
            ->where('id', $stubId)
            ->update([
                'status'      => 'rejected',
                'promoted_by' => $userId,
                'promoted_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Get pipeline statistics.
     */
    public function getStats(): array
    {
        $byStatus = [];
        try {
            $byStatus = DB::table('ahg_ner_authority_stub')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->keyBy('status')
                ->all();
        } catch (\Exception $e) {
            // Table may not exist
        }

        $pendingEntities = 0;
        try {
            $pendingEntities = DB::table('ahg_ner_entity as ne')
                ->leftJoin('ahg_ner_authority_stub as stub', 'ne.id', '=', 'stub.ner_entity_id')
                ->whereNull('stub.id')
                ->whereIn('ne.entity_type', ['PERSON', 'ORG', 'GPE'])
                ->count();
        } catch (\Exception $e) {
            // ner_entity table may not exist
        }

        $totalStubs = 0;
        try {
            $totalStubs = DB::table('ahg_ner_authority_stub')->count();
        } catch (\Exception $e) {
            // Table may not exist
        }

        return [
            'pending_entities' => $pendingEntities,
            'by_status'        => $byStatus,
            'total_stubs'      => $totalStubs,
        ];
    }

    protected function getEntityTypeId(string $entityType): ?int
    {
        $termName = 'Person';
        if ($entityType === 'ORG' || $entityType === 'GPE') {
            $termName = 'Corporate body';
        }

        $term = DB::table('term_i18n')
            ->join('term', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 32) // ACTOR_ENTITY_TYPE taxonomy
            ->where('term_i18n.culture', 'en')
            ->where('term_i18n.name', $termName)
            ->select('term.id')
            ->first();

        return $term ? (int) $term->id : null;
    }

    protected function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'ner-stub';
        }

        $base = $slug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
