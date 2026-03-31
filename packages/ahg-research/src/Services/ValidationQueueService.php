<?php

/**
 * ValidationQueueService - Service for Heratio
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



namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;

/**
 * ValidationQueueService - AI Extraction Validation Queue Management
 *
 * Migrated from AtoM: ahgResearchPlugin/lib/Services/ValidationQueueService.php
 */
class ValidationQueueService
{
    private string $culture = 'en';

    public function getQueue(?int $researcherId = null, array $filters = [], int $page = 1, int $limit = 25): array
    {
        $query = DB::table('research_validation_queue as vq')
            ->join('research_extraction_result as er', 'vq.result_id', '=', 'er.id')
            ->join('research_extraction_job as ej', 'er.job_id', '=', 'ej.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('er.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('research_researcher as reviewer', 'vq.reviewer_id', '=', 'reviewer.id')
            ->select(
                'vq.id',
                'vq.result_id',
                'vq.researcher_id',
                'vq.status',
                'vq.reviewer_id',
                'vq.reviewed_at',
                'vq.notes',
                'vq.modified_data_json',
                'vq.created_at',
                'er.object_id',
                'er.result_type',
                'er.data_json',
                'er.confidence',
                'er.model_version',
                'er.job_id',
                'ej.extraction_type',
                'ej.project_id',
                'ioi.title as object_title',
                'reviewer.first_name as reviewer_first_name',
                'reviewer.last_name as reviewer_last_name'
            );

        if ($researcherId !== null) {
            $query->where('vq.researcher_id', $researcherId);
        }
        if (!empty($filters['status'])) {
            $query->where('vq.status', $filters['status']);
        }
        if (!empty($filters['result_type'])) {
            $query->where('er.result_type', $filters['result_type']);
        }
        if (!empty($filters['extraction_type'])) {
            $query->where('ej.extraction_type', $filters['extraction_type']);
        }
        if (isset($filters['min_confidence']) && $filters['min_confidence'] !== '' && $filters['min_confidence'] !== null) {
            $query->where('er.confidence', '>=', (float) $filters['min_confidence']);
        }

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $items = (clone $query)
            ->orderBy('vq.created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function getQueueStats(?int $researcherId = null): array
    {
        $baseQuery = DB::table('research_validation_queue as vq')
            ->join('research_extraction_result as er', 'vq.result_id', '=', 'er.id');

        if ($researcherId !== null) {
            $baseQuery->where('vq.researcher_id', $researcherId);
        }

        $counts = (clone $baseQuery)
            ->selectRaw("
                SUM(CASE WHEN vq.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN vq.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                SUM(CASE WHEN vq.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN vq.status = 'modified' THEN 1 ELSE 0 END) as modified_count
            ")
            ->first();

        $avgConfidence = (clone $baseQuery)
            ->where('vq.status', 'pending')
            ->whereNotNull('er.confidence')
            ->avg('er.confidence');

        return [
            'pending' => (int) ($counts->pending_count ?? 0),
            'accepted' => (int) ($counts->accepted_count ?? 0),
            'rejected' => (int) ($counts->rejected_count ?? 0),
            'modified' => (int) ($counts->modified_count ?? 0),
            'avg_confidence' => $avgConfidence !== null ? round((float) $avgConfidence, 4) : null,
        ];
    }

    public function getPendingCount(?int $researcherId = null): int
    {
        $query = DB::table('research_validation_queue')
            ->where('status', 'pending');

        if ($researcherId !== null) {
            $query->where('researcher_id', $researcherId);
        }

        return $query->count();
    }

    public function acceptResult(int $resultId, int $reviewerId): bool
    {
        $now = date('Y-m-d H:i:s');

        $updated = DB::table('research_validation_queue')
            ->where('result_id', $resultId)
            ->where('status', 'pending')
            ->update([
                'status' => 'accepted',
                'reviewer_id' => $reviewerId,
                'reviewed_at' => $now,
            ]);

        if ($updated === 0) {
            return false;
        }

        $result = DB::table('research_extraction_result as er')
            ->join('research_extraction_job as ej', 'er.job_id', '=', 'ej.id')
            ->where('er.id', $resultId)
            ->select('er.object_id', 'er.result_type', 'er.data_json', 'er.confidence', 'ej.project_id', 'ej.researcher_id')
            ->first();

        if ($result && $result->result_type === 'entity') {
            $this->createAssertionFromEntity($result, $reviewerId);
        }

        return true;
    }

    public function rejectResult(int $resultId, int $reviewerId, string $reason = ''): bool
    {
        return DB::table('research_validation_queue')
            ->where('result_id', $resultId)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'reviewer_id' => $reviewerId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'notes' => $reason,
            ]) > 0;
    }

    public function modifyResult(int $resultId, int $reviewerId, array $modifiedData): bool
    {
        $now = date('Y-m-d H:i:s');

        $updated = DB::table('research_validation_queue')
            ->where('result_id', $resultId)
            ->where('status', 'pending')
            ->update([
                'status' => 'modified',
                'reviewer_id' => $reviewerId,
                'reviewed_at' => $now,
                'modified_data_json' => json_encode($modifiedData),
            ]);

        if ($updated === 0) {
            return false;
        }

        $result = DB::table('research_extraction_result as er')
            ->join('research_extraction_job as ej', 'er.job_id', '=', 'ej.id')
            ->where('er.id', $resultId)
            ->select('er.object_id', 'er.result_type', 'er.data_json', 'er.confidence', 'ej.project_id', 'ej.researcher_id')
            ->first();

        if ($result && $result->result_type === 'entity') {
            $this->createAssertionFromEntity($result, $reviewerId);
        }

        return true;
    }

    public function bulkAccept(array $resultIds, int $reviewerId): int
    {
        $count = 0;
        foreach ($resultIds as $resultId) {
            if ($this->acceptResult((int) $resultId, $reviewerId)) {
                $count++;
            }
        }
        return $count;
    }

    public function bulkReject(array $resultIds, int $reviewerId, string $reason = ''): int
    {
        $count = 0;
        foreach ($resultIds as $resultId) {
            if ($this->rejectResult((int) $resultId, $reviewerId, $reason)) {
                $count++;
            }
        }
        return $count;
    }

    private function createAssertionFromEntity(object $result, int $reviewerId): void
    {
        try {
            $data = json_decode($result->data_json, true);
            if (!$data || empty($data['entity_type']) || empty($data['entity_name'])) {
                return;
            }

            DB::table('research_assertion')->insert([
                'project_id' => $result->project_id,
                'researcher_id' => $reviewerId,
                'subject_type' => 'information_object',
                'subject_id' => $result->object_id,
                'predicate' => 'mentions',
                'object_type' => $data['entity_type'],
                'object_value' => $data['entity_name'],
                'assertion_type' => 'extraction',
                'confidence' => $result->confidence,
                'status' => 'accepted',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Table may not exist
        }
    }
}
