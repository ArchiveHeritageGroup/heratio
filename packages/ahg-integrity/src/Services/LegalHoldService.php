<?php

/**
 * LegalHoldService - Legal hold management for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgIntegrity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegalHoldService
{
    /**
     * Place a legal hold on an information object.
     */
    public function placeHold(int $ioId, string $reason, int $userId): int
    {
        if (!Schema::hasTable('integrity_legal_hold')) {
            throw new \RuntimeException('Table integrity_legal_hold does not exist.');
        }

        $username = DB::table('user')
            ->join('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('user.id', $userId)
            ->value('actor_i18n.authorized_form_of_name') ?? 'User #' . $userId;

        $holdId = DB::table('integrity_legal_hold')->insertGetId([
            'information_object_id' => $ioId,
            'reason'               => $reason,
            'placed_by'            => $username,
            'placed_at'            => now(),
            'status'               => 'active',
            'created_at'           => now(),
        ]);

        return $holdId;
    }

    /**
     * Release a legal hold.
     */
    public function releaseHold(int $holdId, int $userId, string $reason): bool
    {
        if (!Schema::hasTable('integrity_legal_hold')) {
            return false;
        }

        $username = DB::table('user')
            ->join('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('user.id', $userId)
            ->value('actor_i18n.authorized_form_of_name') ?? 'User #' . $userId;

        $updated = DB::table('integrity_legal_hold')
            ->where('id', $holdId)
            ->where('status', 'active')
            ->update([
                'released_by' => $username,
                'released_at' => now(),
                'status'      => 'released',
            ]);

        return $updated > 0;
    }

    /**
     * Check if an information object is under active hold.
     */
    public function isUnderHold(int $ioId): bool
    {
        if (!Schema::hasTable('integrity_legal_hold')) {
            return false;
        }

        return DB::table('integrity_legal_hold')
            ->where('information_object_id', $ioId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get paginated active holds, optionally filtered by repository.
     */
    public function getActiveHolds(?int $repositoryId = null, int $page = 1, int $perPage = 25): array
    {
        if (!Schema::hasTable('integrity_legal_hold')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        }

        $culture = app()->getLocale();

        $query = DB::table('integrity_legal_hold')
            ->leftJoin('information_object', 'integrity_legal_hold.information_object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->where('integrity_legal_hold.status', 'active');

        if ($repositoryId) {
            $query->where('information_object.repository_id', $repositoryId);
        }

        $total = $query->count();

        $data = (clone $query)
            ->select([
                'integrity_legal_hold.*',
                'information_object_i18n.title as io_title',
                'information_object.repository_id',
            ])
            ->orderBy('integrity_legal_hold.placed_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data'    => $data,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Get all holds (active and released) for a given information object.
     */
    public function getHoldHistory(int $ioId): array
    {
        if (!Schema::hasTable('integrity_legal_hold')) {
            return [];
        }

        return DB::table('integrity_legal_hold')
            ->where('information_object_id', $ioId)
            ->orderBy('placed_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get counts of holds by status.
     */
    public function getHoldCounts(): array
    {
        if (!Schema::hasTable('integrity_legal_hold')) {
            return ['active' => 0, 'released' => 0, 'total' => 0];
        }

        $counts = DB::table('integrity_legal_hold')
            ->select(
                DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count"),
                DB::raw("SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released_count"),
                DB::raw('COUNT(*) as total_count')
            )
            ->first();

        return [
            'active'   => (int) ($counts->active_count ?? 0),
            'released' => (int) ($counts->released_count ?? 0),
            'total'    => (int) ($counts->total_count ?? 0),
        ];
    }
}
