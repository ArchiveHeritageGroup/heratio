<?php

/**
 * JobsService - Background job management for Heratio
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

namespace Ahg\Jobs\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JobsService
{
    /**
     * Job types
     */
    public const TYPE_EXPORT = 'export';
    public const TYPE_IMPORT = 'import';
    public const TYPE_AI_PROCESSING = 'ai_processing';
    public const TYPE_BATCH_UPDATE = 'batch_update';
    public const TYPE_VALIDATION = 'validation';
    public const TYPE_SYNC = 'sync';
    public const TYPE_CONVERSION = 'conversion';
    public const TYPE_INGEST = 'ingest';

    /**
     * Job statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Priorities
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * Create a new job
     */
    public function create(array $data): array
    {
        $jobId = DB::table('job')->insertGetId([
            'name' => $data['name'],
            'status_id' => 183, // pending status
            'object_id' => $data['object_id'] ?? null,
            'type' => $data['job_type'] ?? $data['type'] ?? self::TYPE_EXPORT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->find($jobId);
    }

    /**
     * Find job by ID
     */
    public function find(int $id): ?array
    {
        $job = DB::table('job')->where('id', $id)->first();
        return $job ? (array) $job : null;
    }

    /**
     * Browse jobs with filters
     */
    public function browse(array $filters = []): array
    {
        $query = DB::table('job')
            ->join('object', 'job.id', '=', 'object.id')
            ->select('job.*', 'object.created_at');

        if (!empty($filters['status'])) {
            $statusId = $this->getStatusId($filters['status']);
            $query->where('job.status_id', $statusId);
        }

        if (!empty($filters['job_type'])) {
            $query->where('job.type', $filters['job_type']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('job.user_id', $filters['created_by']);
        }

        $total = $query->count();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, (int) ($filters['limit'] ?? 25));

        $sort = $filters['sort'] ?? 'date';
        if ($sort === 'name') {
            $query->orderBy('job.name', 'asc');
        } else {
            $query->orderBy('object.created_at', 'desc');
        }

        $jobs = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'data' => array_map(fn($j) => (array) $j, $jobs),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit),
        ];
    }

    /**
     * Start a job
     */
    public function start(int $id): ?array
    {
        $job = $this->find($id);
        if (!$job) {
            return null;
        }

        DB::table('job')
            ->where('id', $id)
            ->update([
                'status_id' => 182, // running status
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->find($id);
    }

    /**
     * Complete a job
     */
    public function complete(int $id, array $output = []): ?array
    {
        $job = $this->find($id);
        if (!$job) {
            return null;
        }

        $duration = null;
        if (!empty($job['started_at'])) {
            $duration = Carbon::parse($job['started_at'])->diffInSeconds(now());
        }

        DB::table('job')
            ->where('id', $id)
            ->update([
                'status_id' => 184, // completed status
                'completed_at' => now(),
                'duration' => $duration,
                'output' => json_encode($output),
                'updated_at' => now(),
            ]);

        return $this->find($id);
    }

    /**
     * Fail a job
     */
    public function fail(int $id, string $errorMessage, ?string $stackTrace = null): ?array
    {
        $job = $this->find($id);
        if (!$job) {
            return null;
        }

        $duration = null;
        if (!empty($job['started_at'])) {
            $duration = Carbon::parse($job['started_at'])->diffInSeconds(now());
        }

        DB::table('job')
            ->where('id', $id)
            ->update([
                'status_id' => 185, // failed status
                'completed_at' => now(),
                'duration' => $duration,
                'error_message' => $errorMessage,
                'stack_trace' => $stackTrace,
                'updated_at' => now(),
            ]);

        return $this->find($id);
    }

    /**
     * Cancel a job
     */
    public function cancel(int $id): ?array
    {
        $job = $this->find($id);
        if (!$job) {
            return null;
        }

        DB::table('job')
            ->where('id', $id)
            ->update([
                'status_id' => 186, // cancelled status
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->find($id);
    }

    /**
     * Delete a job (only completed/failed)
     */
    public function delete(int $id): bool
    {
        $job = $this->find($id);
        if (!$job || !in_array($job['status_id'], [184, 185, 186])) {
            return false;
        }

        DB::table('job')->where('id', $id)->delete();
        DB::table('object')->where('id', $id)->delete();

        return true;
    }

    /**
     * Get job statistics
     */
    public function getStats(): array
    {
        return [
            'total' => DB::table('job')->count(),
            'pending' => DB::table('job')->where('status_id', 183)->count(),
            'running' => DB::table('job')->where('status_id', 182)->count(),
            'completed' => DB::table('job')->where('status_id', 184)->count(),
            'failed' => DB::table('job')->where('status_id', 185)->count(),
            'cancelled' => DB::table('job')->where('status_id', 186)->count(),
        ];
    }

    /**
     * Clear inactive (completed/failed) jobs
     */
    public function clearInactive(int $daysOld = 30): int
    {
        $cutoff = Carbon::now()->subDays($daysOld);

        $ids = DB::table('job')
            ->whereIn('status_id', [184, 185, 186])
            ->where('completed_at', '<', $cutoff)
            ->pluck('id')
            ->toArray();

        if (empty($ids)) {
            return 0;
        }

        DB::table('job')->whereIn('id', $ids)->delete();
        DB::table('object')->whereIn('id', $ids)->delete();

        return count($ids);
    }

    /**
     * Get status ID from name
     */
    protected function getStatusId(string $status): int
    {
        return match ($status) {
            'pending' => 183,
            'running' => 182,
            'completed' => 184,
            'failed' => 185,
            'cancelled' => 186,
            default => 183,
        };
    }
}
