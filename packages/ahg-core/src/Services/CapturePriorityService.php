<?php

/**
 * CapturePriorityService - Heratio ahg-core
 *
 * heratio#1205 north-star, first slice: "race against loss - endangered-heritage
 * capture network". This is the detection-and-triage foundation: a transparent,
 * explainable capture-priority register that surfaces the records most in need of
 * digitisation or most at risk of loss, ranked by simple catalogue signals.
 *
 * The score is deliberately a hand-tuned weighted sum of plain signals (no opaque
 * ML): the strongest is "has no master/preservation digital surrogate" (the record
 * exists only on the original carrier and is one accident away from being lost),
 * then poor/fragile recorded condition, then any endangerment/decay flag captured
 * in the catalogue, with a small nudge for high-value records that are cheap to
 * detect. Every contributing signal is returned as a human-readable reason so an
 * operator can see exactly why a record ranks where it does and disagree with it.
 *
 * Optional source tables (condition_report, museum_metadata) are schema-guarded so
 * the register still works on an install that does not have them.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CapturePriorityService
{
    /**
     * usage_id of a MASTER (preservation) digital object. A record with NO master
     * digital object is "not yet captured" - the strongest at-risk signal.
     */
    private const USAGE_MASTER = 140;

    /**
     * Transparent scoring weights. A maintainer tunes the register entirely by
     * editing these numbers - higher weight = that signal pushes a record further
     * up the capture queue. Keep them as round, comparable values so the resulting
     * score stays easy to read and defend. They can be overridden per call via
     * $opts['weights'] (e.g. from a future settings page) without touching code.
     */
    public const DEFAULT_WEIGHTS = [
        'no_master'       => 50,  // exists only on the original carrier - not backed up
        'condition_poor'  => 30,  // recorded condition is poor / unstable
        'condition_fair'  => 12,  // recorded condition is fair (early warning)
        'endangerment'    => 25,  // a catalogue field flags decay / risk / fragility
        'priority_high'   => 15,  // a condition report marks remedial priority "high"
        'check_overdue'   => 10,  // a scheduled condition re-check is past due
        'high_value'      => 8,   // cheap value proxy: top-level / described / has detail
    ];

    /**
     * Keywords that, when found in a free-text condition/risk field, indicate the
     * item is fragile / decaying / endangered. Lower-cased substring match - kept
     * deliberately small and explainable. International, jurisdiction-neutral terms.
     */
    private const ENDANGERMENT_KEYWORDS = [
        'fragile', 'brittle', 'decay', 'deteriorat', 'unstable', 'crumbl',
        'mould', 'mold', 'mildew', 'pest', 'infestation', 'water damage',
        'flood', 'fire', 'acidic', 'foxing', 'tear', 'torn', 'flaking',
        'vinegar syndrome', 'nitrate', 'magnetic', 'obsolete', 'at risk',
        'endangered', 'rot', 'corros', 'rust', 'insect',
    ];

    /**
     * Build the prioritised capture register.
     *
     * @param  array  $opts  limit (int, 0 = no bound), weights (array override)
     * @return array{
     *     rows: array<int, array{id:int,title:string,slug:?string,score:int,reasons:array<int,string>}>,
     *     summary: array{total:int,no_master:int,poor_condition:int,endangered:int,scored:int},
     *     reason_counts: array<string,int>,
     *     weights: array<string,int>,
     *     generated_at: string,
     *     notes: array{condition_reports:bool,museum_metadata:bool}
     * }
     */
    public function register(array $opts = []): array
    {
        $limit = max(0, (int) ($opts['limit'] ?? 0));
        $weights = array_merge(self::DEFAULT_WEIGHTS, is_array($opts['weights'] ?? null) ? $opts['weights'] : []);

        $hasCondition = Schema::hasTable('condition_report');
        $hasMuseum = Schema::hasTable('museum_metadata');

        // Pre-load the at-risk side tables once (keyed by information_object id) so the
        // main scan is a single pass with no N+1 queries. Both are optional.
        $conditionByIo = $hasCondition ? $this->loadConditionSignals() : [];
        $museumByIo = $hasMuseum ? $this->loadMuseumSignals() : [];

        // Master-digital-object presence per record (one grouped query).
        $hasMaster = DB::table('digital_object')
            ->where('usage_id', self::USAGE_MASTER)
            ->whereNotNull('object_id')
            ->distinct()
            ->pluck('object_id')
            ->flip(); // [object_id => index] for O(1) isset() lookups

        // Core record set: every information object with its title + slug. Skip the
        // synthetic root (id 1) which is not a real description.
        $records = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')
                    ->on('i18n.culture', '=', 'io.source_culture');
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', '>', 1)
            ->select([
                'io.id',
                'io.parent_id',
                'io.description_detail_id',
                'i18n.title',
                'i18n.physical_characteristics',
                's.slug',
            ])
            ->orderBy('io.id')
            ->get();

        $rows = [];
        $reasonCounts = [];
        $noMaster = 0;
        $poorCondition = 0;
        $endangered = 0;

        foreach ($records as $rec) {
            $id = (int) $rec->id;
            $score = 0;
            $reasons = [];

            // 1) No master / preservation surrogate - the headline risk.
            if (! isset($hasMaster[$id])) {
                $score += $weights['no_master'];
                $reasons[] = 'No master digital surrogate (not yet captured)';
                $noMaster++;
            }

            // 2) Recorded condition (condition_report.overall_rating + priority + next check).
            $cond = $conditionByIo[$id] ?? null;
            if ($cond) {
                $rating = strtolower((string) ($cond['rating'] ?? ''));
                if ($rating === 'poor' || $rating === 'bad' || $rating === 'unstable') {
                    $score += $weights['condition_poor'];
                    $reasons[] = 'Condition assessed: poor';
                    $poorCondition++;
                } elseif ($rating === 'fair') {
                    $score += $weights['condition_fair'];
                    $reasons[] = 'Condition assessed: fair';
                }
                if (strtolower((string) ($cond['priority'] ?? '')) === 'high') {
                    $score += $weights['priority_high'];
                    $reasons[] = 'Condition report flags high remedial priority';
                }
                if (! empty($cond['check_overdue'])) {
                    $score += $weights['check_overdue'];
                    $reasons[] = 'Scheduled condition re-check is overdue';
                }
            }

            // 3) Endangerment / decay flags from free-text catalogue fields. Pull text
            //    from the museum condition fields and the ISAD physical-characteristics
            //    note, then keyword-match. One matched keyword = one endangerment hit.
            $riskText = (string) ($rec->physical_characteristics ?? '');
            if (isset($museumByIo[$id])) {
                $riskText .= ' '.$museumByIo[$id];
            }
            if ($cond && ! empty($cond['summary'])) {
                $riskText .= ' '.$cond['summary'];
            }
            $hit = $this->firstEndangermentKeyword($riskText);
            if ($hit !== null) {
                $score += $weights['endangerment'];
                $reasons[] = 'Catalogue flags fragility/decay ("'.$hit.'")';
                $endangered++;
            }

            // 4) Cheap value proxy: a top-level record (no parent) with full detail is
            //    worth capturing sooner because losing it loses a whole unit. Tiny weight.
            $topLevel = ((int) ($rec->parent_id ?? 0)) <= 1;
            $fullDetail = (int) ($rec->description_detail_id ?? 0) > 0;
            if ($topLevel && $fullDetail) {
                $score += $weights['high_value'];
                $reasons[] = 'High-value record (top-level, fully described)';
            }

            // Only records with at least one risk signal belong in the register.
            if ($score <= 0 || empty($reasons)) {
                continue;
            }

            foreach ($reasons as $r) {
                // Tally by the leading phrase before any parenthetical/colon detail so
                // the summary groups "Condition assessed: poor" + "...fair" sensibly.
                $key = $this->reasonKey($r);
                $reasonCounts[$key] = ($reasonCounts[$key] ?? 0) + 1;
            }

            $rows[] = [
                'id' => $id,
                'title' => trim((string) ($rec->title ?? '')) !== '' ? (string) $rec->title : '(untitled record #'.$id.')',
                'slug' => $rec->slug !== null ? (string) $rec->slug : null,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        // Rank: highest score first, ties broken by id for a stable order.
        usort($rows, function ($a, $b) {
            return $b['score'] <=> $a['score'] ?: $a['id'] <=> $b['id'];
        });

        arsort($reasonCounts);

        $scored = count($rows);
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total' => DB::table('information_object')->where('id', '>', 1)->count(),
                'no_master' => $noMaster,
                'poor_condition' => $poorCondition,
                'endangered' => $endangered,
                'scored' => $scored,
            ],
            'reason_counts' => $reasonCounts,
            'weights' => $weights,
            'generated_at' => now()->toDateTimeString(),
            'notes' => [
                'condition_reports' => $hasCondition,
                'museum_metadata' => $hasMuseum,
            ],
        ];
    }

    /**
     * Most-recent condition_report per information object, reduced to the signals we
     * score on: overall rating, remedial priority, whether the re-check is overdue,
     * and the free-text summary for keyword matching.
     *
     * @return array<int, array{rating:?string,priority:?string,check_overdue:bool,summary:?string}>
     */
    private function loadConditionSignals(): array
    {
        $out = [];
        $rows = DB::table('condition_report')
            ->whereNotNull('information_object_id')
            ->orderBy('information_object_id')
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get(['information_object_id', 'overall_rating', 'priority', 'next_check_date', 'summary']);

        $today = now()->toDateString();
        foreach ($rows as $r) {
            $io = (int) $r->information_object_id;
            if (isset($out[$io])) {
                continue; // first row per IO is the most recent (ordered above)
            }
            $overdue = $r->next_check_date !== null
                && (string) $r->next_check_date !== ''
                && (string) $r->next_check_date < $today;
            $out[$io] = [
                'rating' => $r->overall_rating,
                'priority' => $r->priority,
                'check_overdue' => $overdue,
                'summary' => $r->summary,
            ];
        }

        return $out;
    }

    /**
     * Per-information-object blob of the museum condition fields, concatenated for
     * keyword matching. museum_metadata.object_id is the information_object id.
     *
     * @return array<int, string>
     */
    private function loadMuseumSignals(): array
    {
        $out = [];
        $rows = DB::table('museum_metadata')
            ->whereNotNull('object_id')
            ->get(['object_id', 'condition_term', 'condition_description', 'condition_notes']);

        foreach ($rows as $r) {
            $io = (int) $r->object_id;
            $blob = trim(implode(' ', array_filter([
                (string) ($r->condition_term ?? ''),
                (string) ($r->condition_description ?? ''),
                (string) ($r->condition_notes ?? ''),
            ])));
            if ($blob !== '') {
                $out[$io] = isset($out[$io]) ? $out[$io].' '.$blob : $blob;
            }
        }

        return $out;
    }

    /**
     * Return the first endangerment keyword found in $text (lower-cased substring),
     * or null. Returning the matched word lets the UI show WHY it flagged.
     */
    private function firstEndangermentKeyword(string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }
        $hay = strtolower($text);
        foreach (self::ENDANGERMENT_KEYWORDS as $kw) {
            if (str_contains($hay, $kw)) {
                return $kw;
            }
        }

        return null;
    }

    /**
     * Group key for a reason string: the part before the first ":" or "(".
     */
    private function reasonKey(string $reason): string
    {
        $cut = strcspn($reason, ':(');

        return rtrim(substr($reason, 0, $cut));
    }
}
