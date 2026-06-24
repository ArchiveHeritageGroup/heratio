<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Researcher download / storage quota engine (#1325).
 *
 * Limits live in research_quota_policy and are resolved most-specific-first
 * (user > project > role > global) per metric, so a partial override (e.g. a
 * per-user storage bump) inherits the rest from the broader policy.
 *
 * Usage is computed from authoritative sources, never a drift-prone counter:
 *   - downloads: research_activity_log rows with activity_type = 'download'
 *     in the policy period (monthly = current calendar month, total = all-time);
 *   - storage:   SUM(file_size) of the researcher's research_workspace_file rows.
 *
 * All enumerated values (scope, period) come from the Dropdown Manager.
 */
class ResearchQuotaService
{
    /** Precedence high -> low. Per-metric: first policy with a non-null value wins. */
    private const PRECEDENCE = ['user', 'project', 'role', 'global'];

    /**
     * Effective limits for a researcher (optionally within a project context).
     *
     * @return array{max_downloads:?int,max_storage_bytes:?int,soft_warn_pct:int,period:string,sources:array<string,string>}
     */
    public function getEffectiveLimits(int $researcherId, ?int $projectId = null): array
    {
        $roleCode = $this->researcherTypeCode($researcherId);

        $candidates = [];
        foreach (self::PRECEDENCE as $scope) {
            $key = match ($scope) {
                'user'    => (string) $researcherId,
                'project' => $projectId !== null ? (string) $projectId : null,
                'role'    => $roleCode,
                'global'  => '*',
            };
            if ($key === null) {
                continue;
            }
            $candidates[$scope] = $this->policyFor($scope, $key);
        }

        $result = [
            'max_downloads'     => null,
            'max_storage_bytes' => null,
            'soft_warn_pct'     => 80,
            'period'            => 'monthly',
            'sources'           => [],
        ];

        // Per-metric: first (highest-precedence) policy that defines it wins.
        foreach (self::PRECEDENCE as $scope) {
            $p = $candidates[$scope] ?? null;
            if ($p === null) {
                continue;
            }
            if ($result['max_downloads'] === null && $p->max_downloads !== null) {
                $result['max_downloads'] = (int) $p->max_downloads;
                $result['sources']['downloads'] = $scope;
                $result['soft_warn_pct'] = (int) $p->soft_warn_pct;
                $result['period'] = (string) $p->period;
            }
            if ($result['max_storage_bytes'] === null && $p->max_storage_bytes !== null) {
                $result['max_storage_bytes'] = (int) $p->max_storage_bytes;
                $result['sources']['storage'] = $scope;
            }
        }

        return $result;
    }

    /** Downloads counted for the researcher in the given period. */
    public function currentDownloadUsage(int $researcherId, string $period = 'monthly'): int
    {
        if (! Schema::hasTable('research_activity_log')) {
            return 0;
        }
        $q = DB::table('research_activity_log')
            ->where('researcher_id', $researcherId)
            ->where('activity_type', 'download');
        if ($period === 'monthly') {
            $q->where('created_at', '>=', date('Y-m-01 00:00:00'));
        }

        return (int) $q->count();
    }

    /** Total bytes of workspace files owned by the researcher. */
    public function currentStorageUsage(int $researcherId): int
    {
        if (! Schema::hasTable('research_workspace_file')) {
            return 0;
        }

        return (int) DB::table('research_workspace_file')
            ->where('researcher_id', $researcherId)
            ->sum('file_size');
    }

    /**
     * Evaluate the download quota.
     *
     * @return array{allowed:bool,warn:bool,usage:int,limit:?int,pct:float,message:?string}
     */
    public function checkDownload(int $researcherId, ?int $projectId = null): array
    {
        $limits = $this->getEffectiveLimits($researcherId, $projectId);
        $usage = $this->currentDownloadUsage($researcherId, $limits['period']);

        return $this->evaluate($usage, $limits['max_downloads'], $limits['soft_warn_pct'], 'download', 0);
    }

    /**
     * Evaluate the storage quota for an upload of $addBytes.
     *
     * @return array{allowed:bool,warn:bool,usage:int,limit:?int,pct:float,message:?string}
     */
    public function checkStorage(int $researcherId, int $addBytes = 0, ?int $projectId = null): array
    {
        $limits = $this->getEffectiveLimits($researcherId, $projectId);
        $usage = $this->currentStorageUsage($researcherId);

        return $this->evaluate($usage, $limits['max_storage_bytes'], $limits['soft_warn_pct'], 'storage', $addBytes);
    }

    /**
     * Record a download event so it counts toward the quota. Resolves nothing
     * itself; callers pass the acting researcher. Best-effort.
     */
    public function logDownload(int $researcherId, ?int $projectId = null, ?int $entityId = null, ?string $entityTitle = null): void
    {
        try {
            DB::table('research_activity_log')->insert([
                'researcher_id' => $researcherId,
                'project_id'    => $projectId,
                'activity_type' => 'download',
                'entity_type'   => 'download',
                'entity_id'     => $entityId,
                'entity_title'  => $entityTitle !== null ? mb_substr($entityTitle, 0, 500) : null,
                'session_id'    => session()->getId() ?: null,
                'ip_address'    => request()->ip(),
                'user_agent'    => substr(request()->userAgent() ?? '', 0, 500),
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /**
     * Resolve the reproduction-request researcher for a reproduction file id
     * (research_reproduction_file -> item -> request -> researcher).
     */
    public function researcherIdForReproductionFile(int $fileId): ?int
    {
        try {
            $rid = DB::table('research_reproduction_file as f')
                ->join('research_reproduction_item as i', 'i.id', '=', 'f.item_id')
                ->join('research_reproduction_request as r', 'r.id', '=', 'i.request_id')
                ->where('f.id', $fileId)
                ->value('r.researcher_id');

            return $rid !== null ? (int) $rid : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Per-researcher usage-vs-limit rows for the admin dashboard.
     *
     * @return array<int,array<string,mixed>>
     */
    public function usageReport(int $limit = 200): array
    {
        if (! Schema::hasTable('research_researcher')) {
            return [];
        }
        $researchers = DB::table('research_researcher')
            ->select('id', 'first_name', 'last_name', 'email', 'researcher_type_id', 'status')
            ->orderBy('last_name')
            ->limit($limit)
            ->get();

        $rows = [];
        foreach ($researchers as $r) {
            $limits = $this->getEffectiveLimits((int) $r->id);
            $dlUsage = $this->currentDownloadUsage((int) $r->id, $limits['period']);
            $stUsage = $this->currentStorageUsage((int) $r->id);
            $rows[] = [
                'researcher_id'   => (int) $r->id,
                'name'            => trim(($r->first_name ?? '').' '.($r->last_name ?? '')),
                'email'           => $r->email,
                'status'          => $r->status,
                'download_usage'  => $dlUsage,
                'download_limit'  => $limits['max_downloads'],
                'download_pct'    => $this->pct($dlUsage, $limits['max_downloads']),
                'storage_usage'   => $stUsage,
                'storage_limit'   => $limits['max_storage_bytes'],
                'storage_pct'     => $this->pct($stUsage, $limits['max_storage_bytes']),
                'period'          => $limits['period'],
            ];
        }

        return $rows;
    }

    // --- internals --------------------------------------------------------

    private function evaluate(int $usage, ?int $limit, int $softPct, string $kind, int $add): array
    {
        $projected = $usage + $add;
        if ($limit === null) {
            return ['allowed' => true, 'warn' => false, 'usage' => $usage, 'limit' => null, 'pct' => 0.0, 'message' => null];
        }
        $allowed = $projected <= $limit;
        $pct = $this->pct($projected, $limit);
        $warn = $allowed && $pct >= $softPct;
        $message = null;
        if (! $allowed) {
            $message = $kind === 'storage'
                ? 'Storage quota exceeded ('.$this->humanBytes($usage).' of '.$this->humanBytes($limit).').'
                : 'Download quota exceeded ('.$usage.' of '.$limit.' this period).';
        } elseif ($warn) {
            $message = $kind === 'storage'
                ? 'Approaching storage quota ('.$this->humanBytes($projected).' of '.$this->humanBytes($limit).').'
                : 'Approaching download quota ('.$projected.' of '.$limit.' this period).';
        }

        return ['allowed' => $allowed, 'warn' => $warn, 'usage' => $usage, 'limit' => $limit, 'pct' => $pct, 'message' => $message];
    }

    private function policyFor(string $scope, string $key): ?object
    {
        if (! Schema::hasTable('research_quota_policy')) {
            return null;
        }

        return DB::table('research_quota_policy')
            ->where('scope', $scope)
            ->where('scope_key', $key)
            ->where('is_active', 1)
            ->first();
    }

    private function researcherTypeCode(int $researcherId): ?string
    {
        try {
            if (! Schema::hasTable('research_researcher') || ! Schema::hasTable('research_researcher_type')) {
                return null;
            }

            return DB::table('research_researcher as rr')
                ->join('research_researcher_type as t', 't.id', '=', 'rr.researcher_type_id')
                ->where('rr.id', $researcherId)
                ->value('t.code');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function pct(int $usage, ?int $limit): float
    {
        if ($limit === null || $limit <= 0) {
            return 0.0;
        }

        return round(($usage / $limit) * 100, 1);
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return round($n, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
