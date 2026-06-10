<?php

/**
 * PreservationTriageService - Heratio ahg-preservation
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgPreservation\Services;

use Illuminate\Support\Facades\DB;

/**
 * heratio#1200 - collections-wide preservation triage. Scores every assessed record's
 * preservation risk from its latest condition report (rating, priority, overdue check, how
 * stale the assessment is) and ranks the worst first - so a conservator gets a holding-wide
 * priority list rather than one room at a time. First slice: condition-report driven; digital
 * format-obsolescence and budget optimisation are later axes.
 */
class PreservationTriageService
{
    /** overall_rating (lower-cased) -> base risk contribution. */
    private const RATING = [
        'excellent' => 0, 'good' => 15, 'fair' => 45, 'poor' => 80,
        'bad' => 95, 'critical' => 95, 'damaged' => 90, 'unstable' => 85,
    ];

    /** priority (lower-cased) -> risk bump. */
    private const PRIORITY = [
        'low' => 0, 'normal' => 5, 'medium' => 5, 'high' => 15, 'urgent' => 25, 'critical' => 25,
    ];

    /** @return array{items:array, summary:array} */
    public function triage(int $limit = 200): array
    {
        // Latest condition report per information object (dedupe newest-first in PHP - the
        // assessed set is small relative to the catalogue).
        $reports = DB::table('condition_report as cr')
            ->join('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'cr.information_object_id')->where('i.culture', '=', 'en'); })
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'cr.information_object_id')
            ->whereNotNull('cr.information_object_id')
            ->orderByDesc('cr.assessment_date')->orderByDesc('cr.id')
            ->get(['cr.information_object_id as io_id', 'i.title', 'sl.slug', 'cr.overall_rating',
                'cr.priority', 'cr.assessment_date', 'cr.next_check_date', 'cr.recommendations']);

        $today = now();
        $seen = [];
        $items = [];
        $bands = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        $overdueCount = 0;

        foreach ($reports as $r) {
            if (isset($seen[$r->io_id])) {
                continue;   // keep only the latest report per object
            }
            $seen[$r->io_id] = true;

            $overdue = $r->next_check_date && $r->next_check_date < $today->toDateString();
            if ($overdue) {
                $overdueCount++;
            }
            $score = $this->score($r->overall_rating, $r->priority, $overdue, $r->assessment_date, $today);
            $band = $this->band($score);
            $bands[$band]++;

            $items[] = [
                'io_id' => (int) $r->io_id,
                'title' => (string) ($r->title ?: ('#'.$r->io_id)),
                'slug' => $r->slug,
                'rating' => $r->overall_rating,
                'priority' => $r->priority,
                'assessed' => $r->assessment_date,
                'next_check' => $r->next_check_date,
                'overdue' => $overdue,
                'score' => $score,
                'band' => $band,
                'recommendation' => trim(mb_substr(strip_tags((string) ($r->recommendations ?? '')), 0, 160)),
            ];
        }

        usort($items, fn ($a, $b) => $b['score'] <=> $a['score']);

        $assessed = count($seen);
        $totalObjects = (int) DB::table('information_object')->where('parent_id', '!=', 1)->count();

        return [
            'items' => array_slice($items, 0, $limit),
            'summary' => [
                'bands' => $bands,
                'assessed' => $assessed,
                'total_objects' => $totalObjects,
                'unassessed' => max(0, $totalObjects - $assessed),
                'overdue' => $overdueCount,
            ],
        ];
    }

    /** Combine rating + priority + overdue + staleness into a 0-100 risk score. */
    private function score(?string $rating, ?string $priority, bool $overdue, ?string $assessedDate, $today): int
    {
        $base = self::RATING[strtolower(trim((string) $rating))] ?? 40;   // unknown rating = mid risk
        $base += self::PRIORITY[strtolower(trim((string) $priority))] ?? 5;
        if ($overdue) {
            $base += 20;
        }
        if ($assessedDate) {
            try {
                $years = (int) floor($today->diffInDays(\Illuminate\Support\Carbon::parse($assessedDate)) / 365);
                $base += min(20, $years * 5);   // stale assessments drift up
            } catch (\Throwable $e) {
                // ignore unparseable dates
            }
        }

        return max(0, min(100, $base));
    }

    private function band(int $score): string
    {
        return match (true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };
    }
}
