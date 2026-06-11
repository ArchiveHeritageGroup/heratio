<?php

/**
 * LoanRequestService - inter-institution loan-request workflow for the
 * federated GLAM network (#1203 loan slice).
 *
 * Sits alongside the union-catalogue slice and REUSES its member registry
 * (federation_member) read-only: a loan request links a requesting member to a
 * holding member and a soft item reference. This service owns:
 *
 *   - the loan-request store (federation_loan_request)
 *   - create / list / filter (by status and by direction incoming|outgoing)
 *   - status transitions with a who/when audit (decided_by / decided_at)
 *
 * Every query is Schema::hasTable-guarded and try/catch wrapped, so a fresh
 * install (table not yet created) degrades to a dignified empty-state rather
 * than a 500. The only writes are INSERT / UPDATE on federation_loan_request -
 * the member registry and all catalogue tables are read-only here. No ALTER.
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

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanRequestService
{
    /** The loan-request store table. */
    public const TABLE = 'federation_loan_request';

    /** The union-catalogue member registry, reused read-only. */
    public const MEMBER_TABLE = 'federation_member';

    /**
     * The loan status workflow. VARCHAR values (no ENUM), and the allowed
     * forward transitions from each status. cancelled is reachable from any
     * pre-fulfilment status as an early exit.
     */
    public const STATUSES = [
        'requested',
        'approved',
        'declined',
        'in_transit',
        'returned',
        'cancelled',
    ];

    /** Allowed forward transitions, keyed by current status. */
    public const TRANSITIONS = [
        'requested' => ['approved', 'declined', 'cancelled'],
        'approved' => ['in_transit', 'cancelled'],
        'in_transit' => ['returned'],
        'declined' => [],
        'returned' => [],
        'cancelled' => [],
    ];

    // -----------------------------------------------------------------
    // Member registry (read-only reuse of the union-catalogue slice)
    // -----------------------------------------------------------------

    /**
     * All registered members, for the request-form member pickers. Newest
     * self-member first, then by name. Read-only over federation_member.
     */
    public function members(): array
    {
        if (! $this->tableReady(self::MEMBER_TABLE)) {
            return [];
        }
        try {
            return DB::table(self::MEMBER_TABLE)
                ->orderByDesc('is_self')
                ->orderBy('name')
                ->limit(500)
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** The local self-member row, if registered (default requesting party). */
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

    /** Map of member id => name, for decorating loan rows without N+1 joins. */
    public function memberNameMap(): array
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

    public function findMember(int $id): ?object
    {
        if (! $this->tableReady(self::MEMBER_TABLE)) {
            return null;
        }
        try {
            return DB::table(self::MEMBER_TABLE)->where('id', $id)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Loan requests - create
    // -----------------------------------------------------------------

    /**
     * Create a loan request. Returns the new row id on success, null on
     * failure (table missing, invalid members, etc.). New requests always
     * start at 'requested'.
     */
    public function create(array $data): ?int
    {
        if (! $this->tableReady(self::TABLE)) {
            return null;
        }

        $requesting = (int) ($data['requesting_member_id'] ?? 0);
        $holding = (int) ($data['holding_member_id'] ?? 0);
        if ($requesting < 1 || $holding < 1) {
            return null;
        }

        try {
            $now = now();
            $row = [
                'requesting_member_id' => $requesting,
                'holding_member_id' => $holding,
                'item_ref' => $this->nullable($data['item_ref'] ?? null),
                'item_title' => $this->clip($data['item_title'] ?? null, 1024),
                'purpose' => $this->clip($data['purpose'] ?? null, 2048),
                'status' => 'requested',
                'needed_from' => $this->date($data['needed_from'] ?? null),
                'needed_to' => $this->date($data['needed_to'] ?? null),
                'notes' => $this->nullable($data['notes'] ?? null),
                'decided_by' => null,
                'decided_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            return (int) DB::table(self::TABLE)->insertGetId($row);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Loan requests - read / list / filter
    // -----------------------------------------------------------------

    public function find(int $id): ?object
    {
        if (! $this->tableReady(self::TABLE)) {
            return null;
        }
        try {
            $row = DB::table(self::TABLE)->where('id', $id)->first();
            if ($row) {
                $this->decorate($row, $this->memberNameMap());
            }

            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Filtered loan-request list. Filters:
     *   status:    one of self::STATUSES or '' for all
     *   direction: 'incoming' (this institution is the holder),
     *              'outgoing' (this institution is the requester),
     *              or '' for all
     *
     * Returns rows decorated with requesting_name / holding_name / direction.
     * Bounded to a sane limit. Always returns [] on any failure.
     */
    public function list(string $status = '', string $direction = '', int $limit = 500): array
    {
        if (! $this->tableReady(self::TABLE)) {
            return [];
        }

        $status = in_array($status, self::STATUSES, true) ? $status : '';
        $direction = in_array($direction, ['incoming', 'outgoing'], true) ? $direction : '';
        $self = $this->selfMember();
        $selfId = $self ? (int) $self->id : 0;

        try {
            $q = DB::table(self::TABLE);

            if ($status !== '') {
                $q->where('status', $status);
            }

            // Direction is relative to the self-member. Without a self-member
            // registered there is no notion of incoming/outgoing, so the
            // direction filter is a no-op (the view surfaces a hint).
            if ($direction === 'incoming' && $selfId > 0) {
                $q->where('holding_member_id', $selfId);
            } elseif ($direction === 'outgoing' && $selfId > 0) {
                $q->where('requesting_member_id', $selfId);
            }

            $rows = $q->orderByDesc('id')->limit(max(1, min(2000, $limit)))->get();

            $names = $this->memberNameMap();
            foreach ($rows as $row) {
                $this->decorate($row, $names, $selfId);
            }

            return $rows->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Counts per status (for the worklist filter badges), respecting the
     * direction filter if one is given. Always returns a full status=>int map.
     */
    public function statusCounts(string $direction = ''): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        $counts['all'] = 0;

        if (! $this->tableReady(self::TABLE)) {
            return $counts;
        }

        $direction = in_array($direction, ['incoming', 'outgoing'], true) ? $direction : '';
        $self = $this->selfMember();
        $selfId = $self ? (int) $self->id : 0;

        try {
            $q = DB::table(self::TABLE);
            if ($direction === 'incoming' && $selfId > 0) {
                $q->where('holding_member_id', $selfId);
            } elseif ($direction === 'outgoing' && $selfId > 0) {
                $q->where('requesting_member_id', $selfId);
            }

            $rows = (clone $q)
                ->select('status', DB::raw('COUNT(*) as c'))
                ->groupBy('status')
                ->get();

            foreach ($rows as $r) {
                $key = (string) $r->status;
                if (array_key_exists($key, $counts)) {
                    $counts[$key] = (int) $r->c;
                }
                $counts['all'] += (int) $r->c;
            }
        } catch (\Throwable $e) {
            // leave the zeroed map
        }

        return $counts;
    }

    // -----------------------------------------------------------------
    // Loan requests - status transition (with who/when audit)
    // -----------------------------------------------------------------

    /**
     * Transition a loan request to a new status. Validates the move against
     * self::TRANSITIONS. Records who decided (decided_by) and when
     * (decided_at). Returns true on success, false on an illegal move,
     * missing row, or DB failure.
     */
    public function transition(int $id, string $to, ?string $extraNote = null): bool
    {
        if (! $this->tableReady(self::TABLE)) {
            return false;
        }
        if (! in_array($to, self::STATUSES, true)) {
            return false;
        }

        try {
            $row = DB::table(self::TABLE)->where('id', $id)->first();
            if (! $row) {
                return false;
            }

            $from = (string) $row->status;
            $allowed = self::TRANSITIONS[$from] ?? [];
            if (! in_array($to, $allowed, true)) {
                return false;
            }

            $note = trim((string) ($row->notes ?? ''));
            if ($extraNote !== null && trim($extraNote) !== '') {
                $stamp = '['.now()->toDateTimeString().' '.$from.' -> '.$to.'] '.trim($extraNote);
                $note = $note === '' ? $stamp : $note."\n".$stamp;
            }

            DB::table(self::TABLE)->where('id', $id)->update([
                'status' => $to,
                'notes' => $note === '' ? null : $note,
                'decided_by' => $this->actorLabel(),
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The forward transitions available from a given status (for the view). */
    public function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /** Add display fields to a loan row: names + (optional) direction label. */
    protected function decorate(object $row, array $names, int $selfId = 0): void
    {
        $row->requesting_name = $names[$row->requesting_member_id]
            ?? ('#'.$row->requesting_member_id);
        $row->holding_name = $names[$row->holding_member_id]
            ?? ('#'.$row->holding_member_id);

        if ($selfId > 0) {
            if ((int) $row->holding_member_id === $selfId) {
                $row->direction = 'incoming';
            } elseif ((int) $row->requesting_member_id === $selfId) {
                $row->direction = 'outgoing';
            } else {
                $row->direction = 'external';
            }
        } else {
            $row->direction = 'unknown';
        }
    }

    /** A stable label for the acting user, for the decided_by audit field. */
    protected function actorLabel(): string
    {
        try {
            $user = Auth::user();
            if ($user) {
                $name = $user->name ?? $user->username ?? $user->email ?? null;

                return $name ? (string) $name : ('user#'.($user->id ?? '?'));
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return 'system';
    }

    protected function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function nullable($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return ($v === '' || $v === null) ? null : (string) $v;
    }

    protected function clip($v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }

        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }

    /** Normalise a Y-m-d date input to a storable string or null. */
    protected function date($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        if ($v === '' || $v === null) {
            return null;
        }
        $ts = strtotime((string) $v);

        return $ts === false ? null : date('Y-m-d', $ts);
    }
}
