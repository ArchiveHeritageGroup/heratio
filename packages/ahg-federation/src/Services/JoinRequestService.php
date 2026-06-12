<?php

/**
 * JoinRequestService - read/write helpers for the public "Join the network"
 * request queue (#1203 join-request slice).
 *
 * This service owns the federation_join_request table only. It backs:
 *
 *   - the public submission from GET/POST /federation/join (lands pending)
 *   - the admin moderation dashboard GET /federation/join-requests
 *   - the admin status transition POST /federation/join-requests/{id}
 *
 * The only writes anywhere in the federation join slice are:
 *
 *   - a public INSERT of a new request (status='pending') into THIS table
 *   - an admin UPDATE of the status / review fields of a row in THIS table
 *
 * No existing table is ever written, no ALTER is issued, no foreign key is
 * created. Every query is Schema::hasTable-guarded and try/catch wrapped so a
 * fresh install (table not yet created) degrades to a dignified empty-state /
 * graceful failure rather than a 500.
 *
 * Approving a request is a LABEL only - it does NOT create a federation_member.
 * Turning an approved institution into a member stays the admin's deliberate
 * action via the existing UnionMemberController member registry.
 *
 * Carved out as fresh code alongside - never touching - the locked F3
 * SharePoint FederatedSearchService / FederationController / Connectors.
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

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JoinRequestService
{
    /** The moderation states. VARCHAR-backed; never a MySQL ENUM. */
    public const STATUSES = ['pending', 'reviewing', 'approved', 'declined'];

    /**
     * Insert a new public join request as status='pending'.
     *
     * $data is the already-validated payload from the public form. Returns the
     * new row id on success, or null if the table is missing / the insert
     * fails (so the controller can show a graceful message instead of a 500).
     */
    public function submit(array $data): ?int
    {
        if (! $this->tableReady()) {
            return null;
        }

        try {
            $now = Carbon::now();

            return (int) DB::table('federation_join_request')->insertGetId([
                'institution_name' => $this->clip($data['institution_name'] ?? '', 255),
                'contact_name' => $this->clipNullable($data['contact_name'] ?? null, 255),
                'contact_email' => $this->clipNullable($data['contact_email'] ?? null, 255),
                'base_url' => $this->clipNullable($data['base_url'] ?? null, 1024),
                'what_they_share' => $this->nullable($data['what_they_share'] ?? null),
                'notes' => $this->nullable($data['notes'] ?? null),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * All requests for the admin moderation dashboard, newest first, grouped
     * by status for headline counts. Always returns a safe structure.
     *
     * Shape:
     *   [
     *     'requests' => [ {object} ... ],
     *     'counts'   => ['pending'=>int,'reviewing'=>int,'approved'=>int,'declined'=>int],
     *     'total'    => int,
     *   ]
     */
    public function queue(?string $filterStatus = null): array
    {
        $empty = [
            'requests' => [],
            'counts' => array_fill_keys(self::STATUSES, 0),
            'total' => 0,
        ];

        if (! $this->tableReady()) {
            return $empty;
        }

        try {
            $query = DB::table('federation_join_request')
                ->orderByRaw("FIELD(status, 'pending', 'reviewing', 'approved', 'declined')")
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            if ($filterStatus !== null && in_array($filterStatus, self::STATUSES, true)) {
                $query->where('status', $filterStatus);
            }

            $requests = $query->limit(1000)->get();

            $counts = array_fill_keys(self::STATUSES, 0);
            $countRows = DB::table('federation_join_request')
                ->select('status', DB::raw('COUNT(*) as c'))
                ->groupBy('status')
                ->get();
            $total = 0;
            foreach ($countRows as $row) {
                $status = (string) $row->status;
                $c = (int) $row->c;
                $total += $c;
                if (array_key_exists($status, $counts)) {
                    $counts[$status] = $c;
                }
            }

            return [
                'requests' => $requests->all(),
                'counts' => $counts,
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /** A single request by id, or null. */
    public function find(int $id): ?object
    {
        if (! $this->tableReady()) {
            return null;
        }

        try {
            return DB::table('federation_join_request')->where('id', $id)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Move a request to a new moderation status. $status must be one of
     * self::STATUSES; $reviewer is a free-text label (admin name/email).
     * $reviewNote, when present, is appended to the notes column so the
     * decision trail is preserved.
     *
     * Returns true on success, false on a bad status / missing table / error.
     */
    public function setStatus(int $id, string $status, ?string $reviewer = null, ?string $reviewNote = null): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            return false;
        }
        if (! $this->tableReady()) {
            return false;
        }

        try {
            $row = DB::table('federation_join_request')->where('id', $id)->first();
            if (! $row) {
                return false;
            }

            $update = [
                'status' => $status,
                'reviewed_by' => $this->clipNullable($reviewer, 255),
                'reviewed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            $note = $this->nullable($reviewNote);
            if ($note !== null) {
                $existing = $this->nullable($row->notes ?? null);
                $stamp = '['.Carbon::now()->toDateString().' '.$status.'] '.$note;
                $update['notes'] = $existing !== null
                    ? $existing."\n".$stamp
                    : $stamp;
            }

            DB::table('federation_join_request')->where('id', $id)->update($update);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Count of requests still awaiting first review (pending). Used to badge
     * the admin entry point. Never throws.
     */
    public function pendingCount(): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        try {
            return (int) DB::table('federation_join_request')
                ->where('status', 'pending')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    protected function tableReady(): bool
    {
        try {
            return Schema::hasTable('federation_join_request');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function nullable($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return ($v === '' || $v === null) ? null : (string) $v;
    }

    protected function clip($v, int $max): string
    {
        $v = is_string($v) ? trim($v) : (string) $v;

        return mb_substr($v, 0, $max);
    }

    protected function clipNullable($v, int $max): ?string
    {
        $v = $this->nullable($v);

        return $v === null ? null : mb_substr($v, 0, $max);
    }
}
