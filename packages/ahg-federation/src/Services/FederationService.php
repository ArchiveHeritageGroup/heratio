<?php

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
        $lastHarvest = DB::table('federation_harvest_log')
            ->orderByDesc('started_at')
            ->value('started_at');

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

        return $query->orderByDesc('federation_harvest_log.started_at')->paginate($perPage);
    }
}
