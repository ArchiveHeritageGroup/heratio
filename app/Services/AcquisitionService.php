<?php

/**
 * AcquisitionService - where heratio.org visitors come from, and how far they
 * get (H09). Records visits (a page seen for five seconds in a real browser),
 * clicks to the live demo, the guided tour, documentation and GitHub, and
 * contact enquiries; and builds the weekly source-to-enquiry report.
 *
 * Privacy: no IP address, cookie, user agent, name or email is stored. A visit
 * is grouped with its later clicks only by a random id that the browser keeps
 * for the tab session.
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

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcquisitionService
{
    public const EVENTS = ['visit', 'demo', 'tour', 'docs', 'github', 'enquiry'];

    public function record(string $event, string $source, ?string $detail = null, ?string $path = null, ?string $visitId = null): void
    {
        if (! in_array($event, self::EVENTS, true) || ! Schema::hasTable('ahg_acquisition_event')) {
            return;
        }
        DB::table('ahg_acquisition_event')->insert([
            'occurred_at' => now(),
            'event' => $event,
            'source' => substr($source !== '' ? $source : 'direct', 0, 60),
            'detail' => $detail !== null ? substr($detail, 0, 120) : null,
            'path' => $path !== null ? substr($path, 0, 255) : null,
            'visit_id' => $visitId !== null && preg_match('/^[a-z0-9]{16}$/', $visitId) ? $visitId : null,
        ]);
    }

    /**
     * Weekly totals, newest week first: week (Monday) => [event => count].
     *
     * @return array<string, array<string, int>>
     */
    public function weekly(int $weeks = 12): array
    {
        $out = [];
        for ($w = 0; $w < $weeks; $w++) {
            $out[now()->startOfWeek()->subWeeks($w)->toDateString()] = array_fill_keys(self::EVENTS, 0);
        }
        if (! Schema::hasTable('ahg_acquisition_event')) {
            return $out;
        }
        $rows = DB::table('ahg_acquisition_event')
            ->where('occurred_at', '>=', now()->startOfWeek()->subWeeks($weeks - 1))
            ->get(['occurred_at', 'event']);
        foreach ($rows as $r) {
            $week = \Illuminate\Support\Carbon::parse($r->occurred_at)->startOfWeek()->toDateString();
            if (isset($out[$week][$r->event])) {
                $out[$week][$r->event]++;
            }
        }

        return $out;
    }

    /**
     * Per source over a period: source => [event => count], sorted by visits.
     *
     * @return array<string, array<string, int>>
     */
    public function bySource(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        if (! Schema::hasTable('ahg_acquisition_event')) {
            return [];
        }
        $out = [];
        foreach (DB::table('ahg_acquisition_event')->whereBetween('occurred_at', [$from, $to])
            ->select('source', 'event', DB::raw('COUNT(*) as n'))->groupBy('source', 'event')->get() as $r) {
            $out[$r->source] ??= array_fill_keys(self::EVENTS, 0);
            $out[$r->source][$r->event] = (int) $r->n;
        }
        uasort($out, fn ($a, $b) => $b['visit'] <=> $a['visit'] ?: $b['enquiry'] <=> $a['enquiry']);

        return $out;
    }

    /** Enquiries over a period by type: detail => count. */
    public function enquiriesByType(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        if (! Schema::hasTable('ahg_acquisition_event')) {
            return [];
        }

        return DB::table('ahg_acquisition_event')->where('event', 'enquiry')->whereBetween('occurred_at', [$from, $to])
            ->select('detail', DB::raw('COUNT(*) as n'))->groupBy('detail')->orderByDesc('n')
            ->pluck('n', 'detail')->map(fn ($n) => (int) $n)->all();
    }
}
