<?php

/**
 * FederationService - Service for Heratio
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



namespace AhgFederation\Services;

use Illuminate\Support\Facades\DB;

class FederationService
{
    /**
     * Get federation dashboard stats.
     */
    public function getStats(): array
    {
        $peerCount = DB::table('federation_peer')->count();
        $harvestCount = DB::table('federation_harvest_log')->count();
        // Real column is `harvest_date`; code previously used `started_at`.
        $lastHarvest = DB::table('federation_harvest_log')
            ->orderByDesc('harvest_date')
            ->value('harvest_date');

        return [
            'peerCount' => $peerCount,
            'harvestCount' => $harvestCount,
            'lastHarvest' => $lastHarvest,
        ];
    }

    /**
     * Get all peers.
     */
    public function getPeers(): \Illuminate\Support\Collection
    {
        return DB::table('federation_peer')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a single peer by ID.
     */
    public function getPeer(int $id): ?object
    {
        return DB::table('federation_peer')->where('id', $id)->first();
    }

    /**
     * Get harvest logs, optionally filtered by peer.
     */
    public function getHarvestLogs(?int $peerId = null, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::table('federation_harvest_log')
            ->leftJoin('federation_peer', 'federation_harvest_log.peer_id', '=', 'federation_peer.id')
            ->select('federation_harvest_log.*', 'federation_peer.name as peer_name');

        if ($peerId) {
            $query->where('federation_harvest_log.peer_id', $peerId);
        }

        return $query->orderByDesc('federation_harvest_log.harvest_date')->paginate($perPage);
    }

    /**
     * Is federation feature enabled in settings?
     *
     * Reads the existing `federation_enabled` setting (seeded by the plugin).
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        $val = DB::table('setting')->where('name', 'federation_enabled')->value('value');
        if ($val === null) {
            return false;
        }
        // Stored as '1' / '0' in seed SQL — cast to bool
        return (bool) intval($val);
    }
}
