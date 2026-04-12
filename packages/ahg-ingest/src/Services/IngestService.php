<?php

/**
 * IngestService - Service for Heratio
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



namespace AhgIngest\Services;

use Illuminate\Support\Facades\DB;

class IngestService
{
    public function getSessions(?int $userId = null): array
    {
        $query = DB::table('ingest_session')
            ->leftJoin('user', 'ingest_session.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', 'user.id', '=', 'actor_i18n.id')
            ->select('ingest_session.*', 'actor_i18n.authorized_form_of_name as user_name');

        if ($userId) {
            $query->where('ingest_session.user_id', $userId);
        }

        return $query->orderByDesc('ingest_session.updated_at')->get()->toArray();
    }

    public function getSession(int $id): ?object
    {
        return DB::table('ingest_session')->where('id', $id)->first();
    }

    public function createSession(int $userId, array $config): int
    {
        return DB::table('ingest_session')->insertGetId([
            'user_id' => $userId,
            'title' => $config['title'] ?? '',
            'entity_type' => $config['entity_type'] ?? 'description',
            'sector' => $config['sector'] ?? 'archive',
            'standard' => $config['standard'] ?? 'isadg',
            'repository_id' => $config['repository_id'] ?? null,
            'parent_id' => $config['parent_id'] ?? null,
            'config' => json_encode($config),
            'status' => 'configure',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateSession(int $id, array $config): void
    {
        DB::table('ingest_session')->where('id', $id)->update([
            'title' => $config['title'] ?? '',
            'entity_type' => $config['entity_type'] ?? 'description',
            'sector' => $config['sector'] ?? 'archive',
            'standard' => $config['standard'] ?? 'isadg',
            'repository_id' => $config['repository_id'] ?? null,
            'parent_id' => $config['parent_id'] ?? null,
            'config' => json_encode($config),
            'updated_at' => now(),
        ]);
    }

    public function updateSessionStatus(int $id, string $status): void
    {
        DB::table('ingest_session')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    /**
     * Delete an ingest session and all its dependent rows (files, mappings,
     * rows, validation errors, jobs). Heratio-specific — no PSIS equivalent.
     */
    public function deleteSession(int $id): bool
    {
        DB::table('ingest_validation')->where('session_id', $id)->delete();
        DB::table('ingest_row')->where('session_id', $id)->delete();
        DB::table('ingest_mapping')->where('session_id', $id)->delete();
        DB::table('ingest_file')->where('session_id', $id)->delete();
        DB::table('ingest_job')->where('session_id', $id)->delete();
        return DB::table('ingest_session')->where('id', $id)->delete() > 0;
    }

    public function getFiles(int $sessionId): \Illuminate\Support\Collection
    {
        return DB::table('ingest_file')
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get();
    }

    public function getMappings(int $sessionId): \Illuminate\Support\Collection
    {
        return DB::table('ingest_column_mapping')
            ->where('session_id', $sessionId)
            ->orderBy('source_column')
            ->get();
    }

    public function getValidationErrors(int $sessionId): \Illuminate\Support\Collection
    {
        return DB::table('ingest_validation_error')
            ->where('session_id', $sessionId)
            ->orderBy('row_number')
            ->get();
    }

    public function getRowCount(int $sessionId): int
    {
        return DB::table('ingest_row')
            ->where('session_id', $sessionId)
            ->where('is_excluded', 0)
            ->count();
    }

    public function validateSession(int $sessionId): array
    {
        $total = DB::table('ingest_row')->where('session_id', $sessionId)->count();
        $excluded = DB::table('ingest_row')->where('session_id', $sessionId)->where('is_excluded', 1)->count();
        $errors = DB::table('ingest_validation_error')->where('session_id', $sessionId)->count();

        return [
            'total' => $total,
            'excluded' => $excluded,
            'valid' => $total - $excluded - $errors,
            'errors' => $errors,
        ];
    }

    public function getJobBySession(int $sessionId): ?object
    {
        return DB::table('ingest_job')
            ->where('session_id', $sessionId)
            ->orderByDesc('id')
            ->first();
    }

    public function getRepositories(): array
    {
        return DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get()
            ->toArray();
    }
}
