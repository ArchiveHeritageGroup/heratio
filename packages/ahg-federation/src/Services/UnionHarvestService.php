<?php

/**
 * UnionHarvestService - bounded, paginated read over the union index for the
 * public harvest API (#1203 slice).
 *
 * Partner / aggregator systems pull the shared discovery records out of the
 * union catalogue (federation_union_record) through this service. Every query
 * is page-bounded (hard cap on per_page) and forward-keyed by id so the harvest
 * never loads the whole set into memory. Two optional filters are honoured:
 *
 *   - member  : restrict to one contributing institution (federation_member.id)
 *   - from    : incremental harvest - only records indexed at/after a timestamp
 *
 * Each record is returned as Dublin-Core-ish fields:
 *   identifier (record_ref), title, type (level), date (dates),
 *   source (member name + repository), and the source record url. The member
 *   name + base_url are joined from federation_member for the source/about
 *   fields.
 *
 * Schema::hasTable-guarded and try/catch wrapped throughout: a fresh install
 * with no tables, or an empty index, degrades to a dignified empty harvest
 * (total 0, empty record list) rather than a 500.
 *
 * Fresh code under #1203 - reads federation_union_record + federation_member
 * only. No writes, no ALTER, no new table. Never touches the locked F3
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

class UnionHarvestService
{
    /** Default page size for the harvest when ?per_page is not supplied. */
    public const PER_PAGE_DEFAULT = 100;

    /** Hard cap on per_page - a partner cannot ask for more than this. */
    public const PER_PAGE_MAX = 500;

    /**
     * One page of the union index as a portable harvest result.
     *
     * Only records contributed by ENABLED members (federation_member
     * is_enabled = 1) are harvestable, matching the public search surface.
     *
     * @param  int          $page      1-based page number
     * @param  int|null     $perPage   requested page size (clamped 1..MAX)
     * @param  int|null     $memberId  optional member filter (federation_member.id)
     * @param  string|null  $from      optional ISO-ish indexed_at lower bound
     * @return array{
     *     records: array<int, array<string, mixed>>,
     *     page: int, per_page: int, total: int, last_page: int,
     *     count: int, member: ?int, from: ?string
     * }
     */
    public function harvest(
        int $page = 1,
        ?int $perPage = null,
        ?int $memberId = null,
        ?string $from = null
    ): array {
        $page = max(1, $page);
        $perPage = $this->clampPerPage($perPage);
        $memberId = ($memberId !== null && $memberId > 0) ? $memberId : null;
        $from = $this->normaliseFrom($from);

        $empty = [
            'records' => [],
            'page' => $page,
            'per_page' => $perPage,
            'total' => 0,
            'last_page' => 1,
            'count' => 0,
            'member' => $memberId,
            'from' => $from,
        ];

        if (! $this->tableReady('federation_union_record')
            || ! $this->tableReady('federation_member')) {
            return $empty;
        }

        try {
            // Only enabled members are harvestable. If a ?member filter is
            // supplied it must also be enabled to return anything.
            $enabledIds = DB::table('federation_member')
                ->where('is_enabled', 1)
                ->pluck('id')
                ->all();

            if (empty($enabledIds)) {
                return $empty;
            }
            if ($memberId !== null && ! in_array($memberId, array_map('intval', $enabledIds), true)) {
                return $empty;
            }

            $base = DB::table('federation_union_record as ur')
                ->whereIn('ur.member_id', $enabledIds);

            if ($memberId !== null) {
                $base->where('ur.member_id', $memberId);
            }
            if ($from !== null) {
                $base->where('ur.indexed_at', '>=', $from);
            }

            $total = (clone $base)->count();
            $lastPage = max(1, (int) ceil($total / $perPage));

            $rows = $base
                ->join('federation_member as m', 'm.id', '=', 'ur.member_id')
                ->orderBy('ur.id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->select([
                    'ur.id',
                    'ur.member_id',
                    'ur.record_ref',
                    'ur.title',
                    'ur.level',
                    'ur.dates',
                    'ur.repository',
                    'ur.url',
                    'ur.indexed_at',
                    'm.name as member_name',
                    'm.base_url as member_base_url',
                ])
                ->get();

            $records = [];
            foreach ($rows as $row) {
                $records[] = $this->mapRecord($row);
            }

            return [
                'records' => $records,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'count' => count($records),
                'member' => $memberId,
                'from' => $from,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * Shape one union row as a Dublin-Core-ish harvest record.
     *
     * identifier -> record_ref (source-stable reference at the member)
     * title      -> title
     * type       -> level (level of description label)
     * date       -> dates (display date string)
     * source     -> contributing member name + holding repository
     * url        -> permalink to the source record at the member
     */
    protected function mapRecord(object $row): array
    {
        $memberName = (string) ($row->member_name ?? '');
        $repository = $row->repository !== null ? (string) $row->repository : null;

        // "source" is the human-readable provenance: the member institution
        // and, when present, the holding repository within it.
        $sourceParts = [];
        if ($memberName !== '') {
            $sourceParts[] = $memberName;
        }
        if ($repository !== null && $repository !== '' && $repository !== $memberName) {
            $sourceParts[] = $repository;
        }
        $source = implode(' / ', $sourceParts);

        return [
            'identifier' => (string) $row->record_ref,
            'title' => $row->title !== null ? (string) $row->title : null,
            'type' => $row->level !== null ? (string) $row->level : null,
            'date' => $row->dates !== null ? (string) $row->dates : null,
            'source' => $source !== '' ? $source : null,
            'member' => $memberName !== '' ? $memberName : null,
            'member_id' => (int) $row->member_id,
            'repository' => $repository,
            'url' => $row->url !== null ? (string) $row->url : null,
            'datestamp' => $this->isoStamp($row->indexed_at ?? null),
        ];
    }

    /** Clamp a requested page size into the bounded 1..MAX window. */
    protected function clampPerPage(?int $perPage): int
    {
        if ($perPage === null || $perPage <= 0) {
            return self::PER_PAGE_DEFAULT;
        }

        return min(self::PER_PAGE_MAX, $perPage);
    }

    /**
     * Normalise an incremental ?from filter into a comparable datetime string.
     * Accepts an ISO date or datetime; returns null when unparseable so the
     * harvest simply ignores a malformed filter rather than erroring.
     */
    protected function normaliseFrom(?string $from): ?string
    {
        if ($from === null) {
            return null;
        }
        $from = trim($from);
        if ($from === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($from))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Format a stored datetime as an OAI-style UTC ISO 8601 stamp. */
    protected function isoStamp($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
