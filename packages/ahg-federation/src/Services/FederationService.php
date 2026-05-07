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
        $activePeerCount = DB::table('federation_peer')->where('is_active', 1)->count();
        $harvestCount = DB::table('federation_harvest_log')->count();
        $lastHarvest = DB::table('federation_harvest_log')
            ->orderByDesc('harvest_date')
            ->value('harvest_date');

        $searchCacheRows = DB::table('federation_search_cache')->count();
        $searchCacheLive = DB::table('federation_search_cache')
            ->where('expires_at', '>', now())
            ->count();
        $searchLogCount = DB::table('federation_search_log')->count();
        $vocabSyncCount = DB::table('federation_vocab_sync')
            ->where('sync_enabled', 1)
            ->count();

        return [
            'peerCount' => $peerCount,
            'activePeerCount' => $activePeerCount,
            'harvestCount' => $harvestCount,
            'lastHarvest' => $lastHarvest,
            'searchCacheRows' => $searchCacheRows,
            'searchCacheLive' => $searchCacheLive,
            'searchLogCount' => $searchLogCount,
            'vocabSyncCount' => $vocabSyncCount,
        ];
    }

    /**
     * Recent harvest sessions, newest first.
     */
    public function getRecentHarvestSessions(int $limit = 5): \Illuminate\Support\Collection
    {
        return DB::table('federation_harvest_session as s')
            ->leftJoin('federation_peer as p', 's.peer_id', '=', 'p.id')
            ->orderByDesc('s.started_at')
            ->limit($limit)
            ->select('s.*', 'p.name as peer_name')
            ->get();
    }

    /**
     * Recent federated searches, newest first.
     */
    public function getRecentSearches(int $limit = 5): \Illuminate\Support\Collection
    {
        return DB::table('federation_search_log')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Vocabulary sync configurations (ungrouped).
     */
    public function getVocabSyncConfigs(?string $culture = null): \Illuminate\Support\Collection
    {
        $culture = $culture ?: app()->getLocale();

        return DB::table('federation_vocab_sync as vs')
            ->leftJoin('federation_peer as p', 'vs.peer_id', '=', 'p.id')
            ->leftJoin('taxonomy_i18n as ti', function ($j) use ($culture) {
                $j->on('vs.taxonomy_id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->orderBy('p.name')
            ->orderBy('ti.name')
            ->select('vs.*', 'p.name as peer_name', 'ti.name as taxonomy_name')
            ->get();
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
        // The AtoM `setting` table has no `value` column — the value lives in
        // setting_i18n keyed by (id, culture). The previous direct
        // ->value('value') call threw SQL 1054 on every invocation, which
        // bubbled up as a 500 from the middleware. Plus the federation_enabled
        // row has scope='federation' (not null) so SettingHelper::get (which
        // filters whereNull(scope)) misses it — must use ::getScoped.
        $val = \AhgCore\Services\SettingHelper::getScoped('federation', 'federation_enabled', '1');
        if ($val === '') return false;
        return (bool) intval($val);
    }
}
