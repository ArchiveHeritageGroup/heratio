<?php

/**
 * LibraryCalendarService - opening hours and closures for circulation (#1475)
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

namespace AhgLibrary\Services;

use AhgLibrary\Support\LibraryBranch;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A day is closed when its weekday is marked closed (a branch's own row beats
 * the all-branches row) or a dated closure covers it (all-branches closures
 * apply everywhere, and a branch's own closures add to them).
 *
 * With nothing configured every day is open, so an installation that never
 * opens this screen keeps its exact previous due dates and fines.
 */
class LibraryCalendarService
{
    /** Longest search for an open day, so a fully closed calendar cannot hang a checkout. */
    private const MAX_SEARCH_DAYS = 366;

    /** Long-lived processes must still see calendar edits, so the cache expires. */
    private const CACHE_SECONDS = 60;

    private array $cache = [];

    public function forget(): void
    {
        $this->cache = [];
    }

    public function isClosed(string $date, ?int $branchId = null): bool
    {
        $calendar = $this->load($branchId);

        return $this->closedOn($this->day($date), $calendar);
    }

    /** The date itself when open, otherwise the next day the branch is open. */
    public function nextOpenDay(string $date, ?int $branchId = null): string
    {
        $calendar = $this->load($branchId);
        $day = $this->day($date);

        if (! $calendar['weekly'] && ! $calendar['closures']) {
            return $day->format('Y-m-d');
        }

        for ($i = 0; $i <= self::MAX_SEARCH_DAYS; $i++) {
            $candidate = $day->modify("+{$i} days");
            if (! $this->closedOn($candidate, $calendar)) {
                return $candidate->format('Y-m-d');
            }
        }

        Log::warning('[library] no open day within a year; due date left unadjusted', ['branch_id' => $branchId]);

        return $day->format('Y-m-d');
    }

    /** Closed days after $after, up to and including $through. */
    public function closedDaysBetween(string $after, string $through, ?int $branchId = null): int
    {
        $calendar = $this->load($branchId);
        if (! $calendar['weekly'] && ! $calendar['closures']) {
            return 0;
        }

        $end = $this->day($through);
        $count = 0;
        for ($d = $this->day($after)->modify('+1 day'); $d <= $end; $d = $d->modify('+1 day')) {
            if ($this->closedOn($d, $calendar)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The seven days as they apply to a branch, each flagged with whether the
     * row is the branch's own or inherited from the all-branches pattern.
     */
    public function week(int $branchId): array
    {
        $rows = $this->tablesReady()
            ? DB::table('library_opening_hours')->whereIn('branch_id', [$branchId, LibraryBranch::ALL])->get()
            : collect();

        $week = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $own = $rows->first(fn ($r) => (int) $r->branch_id === $branchId && (int) $r->weekday === $weekday);
            $all = $rows->first(fn ($r) => (int) $r->branch_id === LibraryBranch::ALL && (int) $r->weekday === $weekday);
            $row = $own ?? $all;
            $week[$weekday] = (object) [
                'weekday' => $weekday,
                'is_open' => $row ? (bool) $row->is_open : true,
                'opens_at' => $row && $row->opens_at ? substr((string) $row->opens_at, 0, 5) : '',
                'closes_at' => $row && $row->closes_at ? substr((string) $row->closes_at, 0, 5) : '',
                'inherited' => $own === null,
            ];
        }

        return $week;
    }

    /** @param array<int, array{is_open?: mixed, opens_at?: ?string, closes_at?: ?string}> $days */
    public function saveWeek(int $branchId, array $days): void
    {
        DB::transaction(function () use ($branchId, $days) {
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $day = $days[$weekday] ?? [];
                $open = ! empty($day['is_open']);
                DB::table('library_opening_hours')->updateOrInsert(
                    ['branch_id' => $branchId, 'weekday' => $weekday],
                    [
                        'is_open' => $open ? 1 : 0,
                        'opens_at' => $open ? (($day['opens_at'] ?? null) ?: null) : null,
                        'closes_at' => $open ? (($day['closes_at'] ?? null) ?: null) : null,
                        'updated_at' => now(),
                    ]
                );
            }
        });
        $this->forget();
    }

    /** Drop a branch's own pattern so it follows the all-branches hours again. */
    public function resetWeek(int $branchId): void
    {
        DB::table('library_opening_hours')->where('branch_id', $branchId)->delete();
        $this->forget();
    }

    /** Closures that affect a branch, newest first. */
    public function closures(int $branchId): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        return DB::table('library_closure')
            ->whereIn('branch_id', [$branchId, LibraryBranch::ALL])
            ->orderByDesc('start_date')
            ->get()
            ->all();
    }

    public function findClosure(int $id): ?object
    {
        return $this->tablesReady() ? DB::table('library_closure')->where('id', $id)->first() : null;
    }

    public function saveClosure(array $data, ?int $id = null, ?int $userId = null): int
    {
        $values = [
            'branch_id' => (int) ($data['branch_id'] ?? LibraryBranch::ALL),
            'closure_type' => (string) $data['closure_type'],
            'name' => (string) $data['name'],
            'start_date' => (string) $data['start_date'],
            'end_date' => (string) (($data['end_date'] ?? null) ?: $data['start_date']),
            'recurs_annually' => ! empty($data['recurs_annually']) ? 1 : 0,
            'notes' => ($data['notes'] ?? null) ?: null,
            'updated_at' => now(),
        ];

        if ($id !== null) {
            DB::table('library_closure')->where('id', $id)->update($values);
        } else {
            $id = (int) DB::table('library_closure')->insertGetId($values + [
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }
        $this->forget();

        return $id;
    }

    public function deleteClosure(int $id): bool
    {
        $deleted = DB::table('library_closure')->where('id', $id)->delete() > 0;
        $this->forget();

        return $deleted;
    }

    public function tablesReady(): bool
    {
        return Schema::hasTable('library_opening_hours') && Schema::hasTable('library_closure');
    }

    /**
     * Whether the signed-in operator may change settings for a branch. Staff an
     * administrator has locked to one branch may not change another branch's
     * terms, or the terms that apply to all branches.
     */
    public static function operatorMayManage(int $branchId): bool
    {
        if (! LibraryBranch::operatorLocked()) {
            return true;
        }

        $own = LibraryBranch::operatorBranchId();

        return $own === null || $own === $branchId;
    }

    private function closedOn(DateTimeImmutable $day, array $calendar): bool
    {
        $weekday = (int) $day->format('N');
        if (array_key_exists($weekday, $calendar['weekly']) && ! $calendar['weekly'][$weekday]) {
            return true;
        }

        $date = $day->format('Y-m-d');
        foreach ($calendar['closures'] as $closure) {
            if ($closure['recurs']) {
                if ($date < $closure['start']) {
                    continue;
                }
                $md = substr($date, 5);
                $from = substr($closure['start'], 5);
                $to = substr($closure['end'], 5);
                // A span such as 24 Dec - 2 Jan wraps the year end.
                if ($from <= $to ? ($md >= $from && $md <= $to) : ($md >= $from || $md <= $to)) {
                    return true;
                }
            } elseif ($date >= $closure['start'] && $date <= $closure['end']) {
                return true;
            }
        }

        return false;
    }

    /** @return array{weekly: array<int, bool>, closures: array<int, array{start: string, end: string, recurs: bool}>} */
    private function load(?int $branchId): array
    {
        $branch = $branchId !== null && $branchId > 0 ? $branchId : LibraryBranch::ALL;
        $key = DB::getDefaultConnection() . ':' . $branch;

        if (isset($this->cache[$key]) && $this->cache[$key]['at'] > time() - self::CACHE_SECONDS) {
            return $this->cache[$key]['data'];
        }

        $data = ['weekly' => [], 'closures' => []];

        if ($this->tablesReady()) {
            $branches = array_unique([LibraryBranch::ALL, $branch]);

            // All-branches rows first, so a branch's own row overwrites them.
            $hours = DB::table('library_opening_hours')->whereIn('branch_id', $branches)->orderBy('branch_id')->get();
            foreach ($hours as $row) {
                $data['weekly'][(int) $row->weekday] = (bool) $row->is_open;
            }
            // An all-open pattern cannot close anything; dropping it keeps the
            // no-configuration fast path for the nightly fine sweep.
            if (! in_array(false, $data['weekly'], true)) {
                $data['weekly'] = [];
            }

            foreach (DB::table('library_closure')->whereIn('branch_id', $branches)->get() as $row) {
                $data['closures'][] = [
                    'start' => substr((string) $row->start_date, 0, 10),
                    'end' => substr((string) $row->end_date, 0, 10),
                    'recurs' => (bool) $row->recurs_annually,
                ];
            }
        }

        $this->cache[$key] = ['at' => time(), 'data' => $data];

        return $data;
    }

    private function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable(substr($date, 0, 10), new DateTimeZone('UTC'));
    }
}
