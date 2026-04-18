<?php

/**
 * AiNerService - Service for Heratio
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



namespace AhgInformationObjectManage\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for AI/NER entity extraction and review operations.
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgAIPlugin/lib/repository/NerRepository.php
 *
 * Tables: ahg_ner_entity, ahg_ner_entity_link, ahg_ner_extraction, ahg_ner_usage
 */
class AiNerService
{
    /**
     * Get all NER entities for an information object, joined with entity links.
     */
    public function getEntitiesForObject(int $objectId): Collection
    {
        try {
            return DB::table('ahg_ner_entity')
                ->where('ahg_ner_entity.object_id', $objectId)
                ->select(
                    'ahg_ner_entity.id',
                    'ahg_ner_entity.extraction_id',
                    'ahg_ner_entity.object_id',
                    'ahg_ner_entity.entity_type',
                    'ahg_ner_entity.entity_value',
                    'ahg_ner_entity.original_value',
                    'ahg_ner_entity.original_type',
                    'ahg_ner_entity.correction_type',
                    'ahg_ner_entity.training_exported',
                    'ahg_ner_entity.confidence',
                    'ahg_ner_entity.status',
                    'ahg_ner_entity.linked_actor_id',
                    'ahg_ner_entity.reviewed_by',
                    'ahg_ner_entity.reviewed_at',
                    'ahg_ner_entity.created_at'
                )
                ->orderBy('ahg_ner_entity.entity_type')
                ->orderBy('ahg_ner_entity.entity_value')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get pending extractions grouped by object for the review dashboard.
     * Returns objects with pending entity counts, approved counts, titles, slugs.
     */
    public function getPendingExtractions(?int $objectId = null): Collection
    {
        $culture = app()->getLocale();

        try {
            $query = DB::table('ahg_ner_entity')
                ->join('information_object', 'ahg_ner_entity.object_id', '=', 'information_object.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                    $join->on('information_object.id', '=', 'information_object_i18n.id')
                         ->where('information_object_i18n.culture', '=', $culture);
                })
                ->where('ahg_ner_entity.status', 'pending');

            if ($objectId) {
                $query->where('ahg_ner_entity.object_id', $objectId);
            }

            return $query
                ->select(
                    'ahg_ner_entity.object_id as id',
                    'information_object_i18n.title',
                    'slug.slug',
                    DB::raw('COUNT(CASE WHEN ahg_ner_entity.status = \'pending\' THEN 1 END) as pending_count'),
                    DB::raw('(SELECT COUNT(*) FROM ahg_ner_entity AS ae2 WHERE ae2.object_id = ahg_ner_entity.object_id AND ae2.status = \'approved\') as approved_count'),
                    DB::raw('EXISTS(SELECT 1 FROM digital_object WHERE digital_object.object_id = ahg_ner_entity.object_id AND digital_object.mime_type = \'application/pdf\') as has_pdf')
                )
                ->groupBy(
                    'ahg_ner_entity.object_id',
                    'information_object_i18n.title',
                    'slug.slug'
                )
                ->orderBy('pending_count', 'desc')
                ->limit(50)
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get a single extraction record by ID.
     */
    public function getExtraction(int $id): ?object
    {
        try {
            return DB::table('ahg_ner_extraction')
                ->where('id', $id)
                ->first();
        } catch (\Illuminate\Database\QueryException $e) {
            return null;
        }
    }

    /**
     * Get entity links (ahg_ner_entity_link) for an object's entities, with confidence scores.
     * Links entities to actors via ahg_ner_entity_link.
     */
    public function getEntityLinks(int $objectId): Collection
    {
        try {
            return DB::table('ahg_ner_entity_link')
                ->join('ahg_ner_entity', 'ahg_ner_entity.id', '=', 'ahg_ner_entity_link.entity_id')
                ->leftJoin('actor_i18n', function ($join) {
                    $join->on('actor_i18n.id', '=', 'ahg_ner_entity_link.actor_id')
                         ->where('actor_i18n.culture', '=', app()->getLocale());
                })
                ->where('ahg_ner_entity.object_id', $objectId)
                ->select(
                    'ahg_ner_entity_link.id as link_id',
                    'ahg_ner_entity_link.entity_id',
                    'ahg_ner_entity_link.actor_id',
                    'ahg_ner_entity_link.link_type',
                    'ahg_ner_entity_link.confidence',
                    'ahg_ner_entity_link.created_by',
                    'ahg_ner_entity_link.created_at',
                    'ahg_ner_entity.entity_type',
                    'ahg_ner_entity.entity_value',
                    'ahg_ner_entity.status as entity_status',
                    'actor_i18n.authorized_form_of_name as actor_name'
                )
                ->orderBy('ahg_ner_entity.entity_type')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get NER API usage statistics from ahg_ner_usage.
     */
    public function getUsageStats(): object
    {
        try {
            $stats = new \stdClass();

            $stats->total_requests = DB::table('ahg_ner_usage')->count();
            $stats->today_requests = DB::table('ahg_ner_usage')
                ->whereDate('created_at', today())
                ->count();
            $stats->avg_response_time = DB::table('ahg_ner_usage')
                ->whereNotNull('response_time_ms')
                ->avg('response_time_ms');
            $stats->error_count = DB::table('ahg_ner_usage')
                ->where('status_code', '>=', 400)
                ->count();
            $stats->by_endpoint = DB::table('ahg_ner_usage')
                ->select('endpoint', DB::raw('COUNT(*) as count'))
                ->groupBy('endpoint')
                ->orderByDesc('count')
                ->get();

            return $stats;
        } catch (\Illuminate\Database\QueryException $e) {
            $stats = new \stdClass();
            $stats->total_requests = 0;
            $stats->today_requests = 0;
            $stats->avg_response_time = 0;
            $stats->error_count = 0;
            $stats->by_endpoint = collect();
            return $stats;
        }
    }

    /**
     * Approve an entity (update status to 'approved').
     */
    public function approveEntity(int $entityId, ?int $reviewedBy = null): bool
    {
        try {
            return DB::table('ahg_ner_entity')
                ->where('id', $entityId)
                ->update([
                    'status'      => 'approved',
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => now(),
                ]) > 0;
        } catch (\Illuminate\Database\QueryException $e) {
            return false;
        }
    }

    /**
     * Reject an entity (update status to 'rejected').
     */
    public function rejectEntity(int $entityId, ?int $reviewedBy = null): bool
    {
        try {
            return DB::table('ahg_ner_entity')
                ->where('id', $entityId)
                ->update([
                    'status'      => 'rejected',
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => now(),
                ]) > 0;
        } catch (\Illuminate\Database\QueryException $e) {
            return false;
        }
    }

    /**
     * Get extraction history for an object.
     */
    public function getExtractionHistory(int $objectId): Collection
    {
        try {
            return DB::table('ahg_ner_extraction')
                ->where('object_id', $objectId)
                ->orderBy('extracted_at', 'desc')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get count of pending entities across all objects.
     */
    public function getPendingCount(): int
    {
        try {
            return DB::table('ahg_ner_entity')
                ->where('status', 'pending')
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            return 0;
        }
    }

    /**
     * Find matching actors for a given entity value (for linking).
     * Returns exact and partial matches.
     */
    public function findMatchingActors(string $entityValue): array
    {
        try {
            $culture = app()->getLocale();

            $exact = DB::table('actor_i18n')
                ->join('actor', 'actor.id', '=', 'actor_i18n.id')
                ->where('actor_i18n.culture', $culture)
                ->where('actor_i18n.authorized_form_of_name', $entityValue)
                ->select('actor.id', 'actor_i18n.authorized_form_of_name as name')
                ->get()
                ->toArray();

            $exactIds = array_column($exact, 'id');

            $partial = DB::table('actor_i18n')
                ->join('actor', 'actor.id', '=', 'actor_i18n.id')
                ->where('actor_i18n.culture', $culture)
                ->where('actor_i18n.authorized_form_of_name', 'LIKE', '%' . $entityValue . '%')
                ->when(!empty($exactIds), function ($q) use ($exactIds) {
                    $q->whereNotIn('actor.id', $exactIds);
                })
                ->select('actor.id', 'actor_i18n.authorized_form_of_name as name')
                ->limit(5)
                ->get()
                ->toArray();

            return [
                'exact'   => $exact,
                'partial' => $partial,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            return ['exact' => [], 'partial' => []];
        }
    }
}
