<?php

/**
 * AuthorityCompletenessService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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
 * Authority Completeness Service.
 *
 * Calculates completeness scores for authority records based on
 * 13 ISAAR(CPF) fields + external IDs + relations + resources.
 * Levels: stub (0-24), minimal (25-49), partial (50-74), full (75-100).
 */
class AuthorityCompletenessService
{
    /**
     * ISAAR(CPF) field weights for score calculation.
     */
    public const FIELD_WEIGHTS = [
        'authorized_name'   => 15,
        'entity_type'       => 5,
        'dates_existence'   => 10,
        'history'           => 10,
        'places'            => 5,
        'legal_status'      => 3,
        'functions'         => 5,
        'mandates'          => 3,
        'internal_struct'   => 3,
        'general_context'   => 3,
        'description_id'    => 3,
        'sources'           => 3,
        'maintenance_notes' => 2,
        'external_ids'      => 10,
        'relations'         => 10,
        'resources'         => 5,
        'contacts'          => 5,
    ];

    /**
     * Level thresholds.
     */
    public const LEVELS = [
        'stub'    => [0, 24],
        'minimal' => [25, 49],
        'partial' => [50, 74],
        'full'    => [75, 100],
    ];

    /**
     * Calculate and store completeness score for an actor.
     */
    public function calculateScore(int $actorId): array
    {
        if (!AhgSettingsService::getBool('authority_completeness_auto_recalc', true)) {
            return ['score' => 0, 'level' => 'stub', 'field_scores' => []];
        }

        $fieldScores = [];
        $totalWeight = array_sum(self::FIELD_WEIGHTS);
        $earnedWeight = 0;

        // Fetch actor_i18n data
        $actorI18n = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->where('culture', 'en')
            ->first();

        // Check basic fields
        $fieldScores['authorized_name'] = (!empty($actorI18n->authorized_form_of_name)) ? 1 : 0;
        $fieldScores['history'] = (!empty($actorI18n->history)) ? 1 : 0;
        $fieldScores['places'] = (!empty($actorI18n->places)) ? 1 : 0;
        $fieldScores['legal_status'] = (!empty($actorI18n->legal_status)) ? 1 : 0;
        $fieldScores['functions'] = (!empty($actorI18n->functions)) ? 1 : 0;
        $fieldScores['mandates'] = (!empty($actorI18n->mandates)) ? 1 : 0;
        $fieldScores['internal_struct'] = (!empty($actorI18n->internal_structures)) ? 1 : 0;
        $fieldScores['general_context'] = (!empty($actorI18n->general_context)) ? 1 : 0;
        $fieldScores['description_id'] = (!empty($actorI18n->description_identifier)) ? 1 : 0;
        $fieldScores['sources'] = (!empty($actorI18n->sources)) ? 1 : 0;
        $fieldScores['maintenance_notes'] = (!empty($actorI18n->revision_history)) ? 1 : 0;

        // Check actor entity type
        $actor = DB::table('actor')->where('id', $actorId)->first();
        $fieldScores['entity_type'] = ($actor && !empty($actor->entity_type_id)) ? 1 : 0;

        // Check dates of existence
        $fieldScores['dates_existence'] = (!empty($actorI18n->dates_of_existence)) ? 1 : 0;

        // Check external identifiers
        $hasIds = DB::table('ahg_actor_identifier')
            ->where('actor_id', $actorId)
            ->exists();
        $fieldScores['external_ids'] = $hasIds ? 1 : 0;

        // Check relations
        $hasRelations = DB::table('relation')
            ->where(function ($q) use ($actorId) {
                $q->where('subject_id', $actorId)
                    ->orWhere('object_id', $actorId);
            })
            ->exists();
        $fieldScores['relations'] = $hasRelations ? 1 : 0;

        // Check linked resources
        $hasResources = DB::table('event')
            ->where('actor_id', $actorId)
            ->exists();
        $fieldScores['resources'] = $hasResources ? 1 : 0;

        // Check contacts
        $hasContacts = false;
        try {
            $hasContacts = DB::table('contact_information')
                ->where('actor_id', $actorId)
                ->exists();
        } catch (\Exception $e) {
            // Table may not exist
        }
        $fieldScores['contacts'] = $hasContacts ? 1 : 0;

        // Calculate weighted score
        foreach ($fieldScores as $field => $score) {
            if ($score && isset(self::FIELD_WEIGHTS[$field])) {
                $earnedWeight += self::FIELD_WEIGHTS[$field];
            }
        }

        $percentage = $totalWeight > 0 ? round(($earnedWeight / $totalWeight) * 100) : 0;
        $level = $this->determineLevel($percentage);

        // Upsert completeness record
        $record = [
            'completeness_level' => $level,
            'completeness_score' => $percentage,
            'field_scores'       => json_encode($fieldScores),
            'has_external_ids'   => $fieldScores['external_ids'],
            'has_relations'      => $fieldScores['relations'],
            'has_resources'      => $fieldScores['resources'],
            'has_contacts'       => $fieldScores['contacts'],
            'scored_at'          => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        $existing = DB::table('ahg_actor_completeness')
            ->where('actor_id', $actorId)
            ->first();

        if ($existing) {
            if ($existing->manual_override) {
                $record['completeness_level'] = $existing->completeness_level;
            }
            DB::table('ahg_actor_completeness')
                ->where('id', $existing->id)
                ->update($record);
        } else {
            $record['actor_id'] = $actorId;
            $record['created_at'] = date('Y-m-d H:i:s');
            DB::table('ahg_actor_completeness')->insert($record);
        }

        return [
            'score'        => $percentage,
            'level'        => $level,
            'field_scores' => $fieldScores,
        ];
    }

    /**
     * Determine completeness level from percentage.
     */
    public function determineLevel(int $score): string
    {
        foreach (self::LEVELS as $level => $range) {
            if ($score >= $range[0] && $score <= $range[1]) {
                return $level;
            }
        }

        return 'stub';
    }

    /**
     * Get completeness record for an actor.
     */
    public function getCompleteness(int $actorId): ?object
    {
        return DB::table('ahg_actor_completeness')
            ->where('actor_id', $actorId)
            ->first();
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStats(): array
    {
        $byLevel = DB::table('ahg_actor_completeness')
            ->select('completeness_level', DB::raw('COUNT(*) as count'))
            ->groupBy('completeness_level')
            ->get()
            ->keyBy('completeness_level')
            ->all();

        $totalScored = DB::table('ahg_actor_completeness')->count();
        $totalActors = DB::table('actor')->count();
        $unscored = $totalActors - $totalScored;

        $avgScore = DB::table('ahg_actor_completeness')
            ->avg('completeness_score') ?? 0;

        $withExternalIds = DB::table('ahg_actor_completeness')
            ->where('has_external_ids', 1)
            ->count();

        $withRelations = DB::table('ahg_actor_completeness')
            ->where('has_relations', 1)
            ->count();

        return [
            'total_actors'    => $totalActors,
            'total_scored'    => $totalScored,
            'unscored'        => $unscored,
            'avg_score'       => round($avgScore, 1),
            'by_level'        => $byLevel,
            'with_external'   => $withExternalIds,
            'with_relations'  => $withRelations,
        ];
    }

    /**
     * Get workqueue items (assigned or unassigned incomplete records).
     */
    public function getWorkqueue(array $filters = []): array
    {
        $query = DB::table('ahg_actor_completeness as c')
            ->join('actor_i18n as ai', function ($j) {
                $j->on('c.actor_id', '=', 'ai.id')
                    ->where('ai.culture', '=', 'en');
            })
            ->leftJoin('slug', 'c.actor_id', '=', 'slug.object_id')
            ->select(
                'c.*',
                'ai.authorized_form_of_name as name',
                'slug.slug'
            );

        if (!empty($filters['level'])) {
            $query->where('c.completeness_level', $filters['level']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('c.assigned_to', $filters['assigned_to']);
        } elseif (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->whereNull('c.assigned_to');
        }

        if (!empty($filters['min_score'])) {
            $query->where('c.completeness_score', '>=', $filters['min_score']);
        }

        if (!empty($filters['max_score'])) {
            $query->where('c.completeness_score', '<=', $filters['max_score']);
        }

        $sort = $filters['sort'] ?? 'completeness_score';
        $dir = $filters['sortDir'] ?? 'asc';
        $query->orderBy($sort, $dir);

        $limit = $filters['limit'] ?? 50;
        $page = $filters['page'] ?? 1;

        return $query->paginate($limit, ['*'], 'page', $page)->toArray();
    }

    /**
     * Batch assign records to an archivist.
     */
    public function batchAssign(array $actorIds, int $assigneeId): int
    {
        return DB::table('ahg_actor_completeness')
            ->whereIn('actor_id', $actorIds)
            ->update([
                'assigned_to' => $assigneeId,
                'assigned_at' => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Batch calculate completeness for all actors.
     */
    public function batchCalculate(int $limit = 0): int
    {
        $query = DB::table('actor')
            ->select('actor.id')
            ->orderBy('actor.id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $actorIds = $query->pluck('id')->all();
        $count = 0;

        foreach ($actorIds as $actorId) {
            $this->calculateScore((int) $actorId);
            $count++;
        }

        return $count;
    }
}
