<?php

/**
 * NetworkDirectoryService - read-only aggregate over the federation member
 * registry that powers the PUBLIC GLAM-network directory (#1203 slice).
 *
 * The directory is the network-effects story made visible: a public roll of
 * the participating institutions, what each one shares, how many discovery
 * records it contributes to the union index, and where to reach it. The more
 * institutions opt in, the richer the shared memory becomes.
 *
 * This service NEVER writes. It reads aggregate over:
 *
 *   - federation_member        the participating institutions (is_enabled=1)
 *   - federation_union_record  per-member contributed-record counts (left join)
 *
 * No new table is introduced. Every query is Schema::hasTable-guarded and
 * try/catch wrapped so a fresh install (tables not yet created) degrades to a
 * dignified empty-state rather than a 500.
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NetworkDirectoryService
{
    /**
     * The public directory payload: a list of the enabled participating
     * institutions plus headline totals for the network-effects framing.
     *
     * Shape:
     *   [
     *     'members' => [ {object} ... ],   // enabled members, self first
     *     'memberCount'  => int,           // count of enabled members
     *     'recordCount'  => int,           // total shared records across them
     *     'selfMemberId' => ?int,          // the local institution, if any
     *   ]
     *
     * Each member object carries:
     *   id, name, base_url, contact, share_scope, is_self, is_enabled,
     *   record_count (records this member contributes to the union index),
     *   catalogue_url (the union catalogue filtered to this member's name).
     *
     * Always returns a safe structure - never throws.
     */
    public function directory(): array
    {
        $empty = [
            'members' => [],
            'memberCount' => 0,
            'recordCount' => 0,
            'selfMemberId' => null,
        ];

        if (! $this->tableReady('federation_member')) {
            return $empty;
        }

        try {
            $members = DB::table('federation_member')
                ->where('is_enabled', 1)
                ->orderByDesc('is_self')
                ->orderBy('name')
                ->limit(500)
                ->get();

            if ($members->isEmpty()) {
                return $empty;
            }

            // Per-member contributed-record counts, in one aggregate read,
            // only when the union index table exists.
            $counts = [];
            if ($this->tableReady('federation_union_record')) {
                $rows = DB::table('federation_union_record')
                    ->select('member_id', DB::raw('COUNT(*) as c'))
                    ->whereIn('member_id', $members->pluck('id')->all())
                    ->groupBy('member_id')
                    ->get();
                foreach ($rows as $row) {
                    $counts[(int) $row->member_id] = (int) $row->c;
                }
            }

            $selfMemberId = null;
            $recordTotal = 0;
            $out = [];

            $base = rtrim((string) (function_exists('url') ? url('/') : ''), '/');

            foreach ($members as $m) {
                $id = (int) $m->id;
                $count = $counts[$id] ?? 0;
                $recordTotal += $count;
                if ((int) ($m->is_self ?? 0) === 1 && $selfMemberId === null) {
                    $selfMemberId = $id;
                }

                $out[] = (object) [
                    'id' => $id,
                    'name' => (string) $m->name,
                    'base_url' => $this->nullable($m->base_url ?? null),
                    'contact' => $this->nullable($m->contact ?? null),
                    'share_scope' => $this->nullable($m->share_scope ?? null),
                    'is_self' => (int) ($m->is_self ?? 0),
                    'is_enabled' => (int) ($m->is_enabled ?? 0),
                    'record_count' => $count,
                    // Link to the union catalogue. The catalogue search matches
                    // titles / repositories / dates; we seed it with the member
                    // name so the visitor lands on that institution's records.
                    'catalogue_url' => $base !== ''
                        ? $base.'/union-catalogue?q='.rawurlencode((string) $m->name)
                        : null,
                ];
            }

            return [
                'members' => $out,
                'memberCount' => count($out),
                'recordCount' => $recordTotal,
                'selfMemberId' => $selfMemberId,
            ];
        } catch (\Throwable $e) {
            return $empty;
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

    protected function nullable($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return ($v === '' || $v === null) ? null : (string) $v;
    }
}
