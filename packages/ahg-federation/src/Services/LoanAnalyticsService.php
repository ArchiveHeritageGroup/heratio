<?php

/**
 * LoanAnalyticsService - read-only aggregate analytics over the
 * inter-institution loan workflow for the federated GLAM network
 * (#1203 loan-analytics slice).
 *
 * Computes cheap aggregate reports over federation_loan_request, decorated
 * with member names from federation_member (read-only reuse of the
 * union-catalogue member registry). All of:
 *
 *   - total request count
 *   - counts by status (requested|approved|declined|in_transit|returned|cancelled)
 *   - incoming vs outgoing split relative to the self-member
 *   - top partner members (top borrowers / top lenders)
 *   - approval rate (approved vs declined, of the decided requests)
 *   - average turnaround in days (decided_at - created_at, where present)
 *
 * Every query is Schema::hasTable-guarded and wrapped in try/catch, so a fresh
 * install (table not yet created) or a DB-less CI boot degrades to a dignified
 * empty-state rather than a 500. Only aggregate COUNT / AVG / GROUP BY queries
 * are issued - no per-record loops, no writes, no ALTER. The member registry
 * and the loan table are read-only here.
 *
 * Fresh code under #1203 - never touches the locked F3 SharePoint
 * FederatedSearchService / FederationController / Connectors / edit-peer view.
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
use Illuminate\Support\Facades\Schema;

class LoanAnalyticsService
{
    /** The loan-request store table (read-only here). */
    public const TABLE = 'federation_loan_request';

    /** The union-catalogue member registry, reused read-only. */
    public const MEMBER_TABLE = 'federation_member';

    /** The loan status workflow (kept in lockstep with LoanRequestService). */
    public const STATUSES = [
        'requested',
        'approved',
        'declined',
        'in_transit',
        'returned',
        'cancelled',
    ];

    /**
     * Build the full analytics payload. Always returns a well-formed array,
     * even when the table is missing or empty (empty-state); never throws.
     *
     * Shape:
     *   ready          bool   - the loan table exists
     *   total          int    - total loan requests
     *   self_member_id int    - the self-member id (0 if none registered)
     *   self_member    ?obj   - the self-member row (name etc.) or null
     *   status_counts  array  - status => int, all six statuses present
     *   direction      array  - incoming|outgoing|external => int
     *   approval       array  - approved|declined|decided|rate_pct
     *   turnaround     array  - avg_days (?float), decided_count (int)
     *   top_borrowers  array  - [ {member_id,name,count}, ... ]
     *   top_lenders    array  - [ {member_id,name,count}, ... ]
     *
     * @return array<string, mixed>
     */
    public function report(int $topN = 8): array
    {
        $empty = [
            'ready' => false,
            'total' => 0,
            'self_member_id' => 0,
            'self_member' => null,
            'status_counts' => array_fill_keys(self::STATUSES, 0),
            'direction' => ['incoming' => 0, 'outgoing' => 0, 'external' => 0],
            'approval' => ['approved' => 0, 'declined' => 0, 'decided' => 0, 'rate_pct' => null],
            'turnaround' => ['avg_days' => null, 'decided_count' => 0],
            'top_borrowers' => [],
            'top_lenders' => [],
        ];

        if (! $this->tableReady(self::TABLE)) {
            return $empty;
        }

        try {
            $self = $this->selfMember();
            $selfId = $self ? (int) $self->id : 0;
            $names = $this->memberNameMap();

            $report = $empty;
            $report['ready'] = true;
            $report['self_member_id'] = $selfId;
            $report['self_member'] = $self;

            $report['status_counts'] = $this->statusCounts();
            $report['total'] = array_sum($report['status_counts']);

            $report['direction'] = $this->directionCounts($selfId);
            $report['approval'] = $this->approval($report['status_counts']);
            $report['turnaround'] = $this->turnaround();
            $report['top_borrowers'] = $this->topByColumn('requesting_member_id', $names, $topN);
            $report['top_lenders'] = $this->topByColumn('holding_member_id', $names, $topN);

            return $report;
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    // -----------------------------------------------------------------
    // individual aggregates
    // -----------------------------------------------------------------

    /** status => int over every request. All six statuses always present. */
    protected function statusCounts(): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        try {
            $rows = DB::table(self::TABLE)
                ->select('status', DB::raw('COUNT(*) as c'))
                ->groupBy('status')
                ->get();
            foreach ($rows as $r) {
                $key = (string) $r->status;
                if (array_key_exists($key, $counts)) {
                    $counts[$key] = (int) $r->c;
                }
            }
        } catch (\Throwable $e) {
            // leave the zeroed map
        }

        return $counts;
    }

    /**
     * incoming|outgoing|external => int relative to the self-member. With no
     * self-member registered every row is 'external' (there is no notion of
     * incoming/outgoing), surfaced by the view as a hint.
     */
    protected function directionCounts(int $selfId): array
    {
        $out = ['incoming' => 0, 'outgoing' => 0, 'external' => 0];
        if ($selfId < 1) {
            try {
                $out['external'] = (int) DB::table(self::TABLE)->count();
            } catch (\Throwable $e) {
                // leave zero
            }

            return $out;
        }

        try {
            $out['incoming'] = (int) DB::table(self::TABLE)
                ->where('holding_member_id', $selfId)
                ->count();
            $out['outgoing'] = (int) DB::table(self::TABLE)
                ->where('requesting_member_id', $selfId)
                ->count();
            $out['external'] = (int) DB::table(self::TABLE)
                ->where('holding_member_id', '!=', $selfId)
                ->where('requesting_member_id', '!=', $selfId)
                ->count();
        } catch (\Throwable $e) {
            // leave zeros
        }

        return $out;
    }

    /**
     * Approval rate over the decided requests. "Decided" = approved + declined;
     * rate_pct is approved / decided as a 0-100 float (null when nothing has
     * been decided, so the view shows a dash rather than a misleading 0%).
     */
    protected function approval(array $statusCounts): array
    {
        $approved = (int) ($statusCounts['approved'] ?? 0);
        $declined = (int) ($statusCounts['declined'] ?? 0);
        $decided = $approved + $declined;
        $rate = $decided > 0 ? round(($approved / $decided) * 100, 1) : null;

        return [
            'approved' => $approved,
            'declined' => $declined,
            'decided' => $decided,
            'rate_pct' => $rate,
        ];
    }

    /**
     * Average turnaround in days between created_at and decided_at, over the
     * rows that have both timestamps. One aggregate AVG query; avg_days is null
     * when no request has been decided yet.
     */
    protected function turnaround(): array
    {
        $out = ['avg_days' => null, 'decided_count' => 0];
        try {
            $row = DB::table(self::TABLE)
                ->whereNotNull('decided_at')
                ->whereNotNull('created_at')
                ->selectRaw('COUNT(*) as c, AVG(TIMESTAMPDIFF(SECOND, created_at, decided_at)) as avg_secs')
                ->first();

            if ($row) {
                $out['decided_count'] = (int) $row->c;
                if ($row->c > 0 && $row->avg_secs !== null) {
                    $out['avg_days'] = round(((float) $row->avg_secs) / 86400, 1);
                }
            }
        } catch (\Throwable $e) {
            // leave nulls
        }

        return $out;
    }

    /**
     * Top members by a counted column (requesting_member_id => top borrowers,
     * holding_member_id => top lenders). Single GROUP BY ... ORDER BY COUNT
     * query; decorated with the member name from the supplied map.
     *
     * @return array<int, array{member_id:int,name:string,count:int}>
     */
    protected function topByColumn(string $column, array $names, int $topN): array
    {
        $topN = max(1, min(50, $topN));
        $list = [];
        try {
            $rows = DB::table(self::TABLE)
                ->select($column.' as member_id', DB::raw('COUNT(*) as c'))
                ->groupBy($column)
                ->orderByDesc('c')
                ->limit($topN)
                ->get();

            foreach ($rows as $r) {
                $mid = (int) $r->member_id;
                $list[] = [
                    'member_id' => $mid,
                    'name' => $names[$mid] ?? ('#'.$mid),
                    'count' => (int) $r->c,
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $list;
    }

    // -----------------------------------------------------------------
    // member registry (read-only reuse of the union-catalogue slice)
    // -----------------------------------------------------------------

    /** The local self-member row, if registered. */
    public function selfMember(): ?object
    {
        if (! $this->tableReady(self::MEMBER_TABLE)) {
            return null;
        }
        try {
            return DB::table(self::MEMBER_TABLE)
                ->where('is_self', 1)
                ->orderBy('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Map of member id => name, for decorating partner rows without N+1. */
    protected function memberNameMap(): array
    {
        if (! $this->tableReady(self::MEMBER_TABLE)) {
            return [];
        }
        try {
            return DB::table(self::MEMBER_TABLE)
                ->pluck('name', 'id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    protected function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
