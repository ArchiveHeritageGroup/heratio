<?php

/**
 * TimeMachineService - Service for Heratio
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

namespace AhgResearch\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TimeMachineService - Research OS moonshot 19 (heratio#1240). The honesty engine.
 *
 * Reconstructs how a project's research developed over time, READ-ONLY, purely
 * from timestamped / versioned data that other slices already capture. It adds no
 * new history of its own - no table, no writes to existing data, no ALTER. If a
 * slice's table is missing, that slice simply contributes no events; the rest of
 * the machine still works.
 *
 * Two products:
 *
 *  - timeline($projectId): a single merged, chronological feed of every dated
 *    event across the project. Each event is an associative array:
 *      [ 'when' => Carbon, 'kind' => string, 'label' => string,
 *        'detail' => string, 'link' => string|null, 'icon' => string,
 *        'badge' => string ]
 *
 *  - stateAsOf($projectId, $date): the project as it stood ON OR BEFORE a given
 *    date - the question brief version that was current then, the claims /
 *    decisions / argument steps / inbox items that already existed, and the method
 *    protocol state. This is a reconstruction from created_at / version_no, NOT a
 *    stored snapshot.
 *
 * Source tables it reads (all guarded with Schema::hasTable + try/catch):
 *   research_question_brief_version  (version_no + created_at + change_reason)  #1226
 *   research_decision_log            (decided_at)                               #1224
 *   research_assertion               (created_at + status)                      #1223
 *   research_argument / _step        (created_at)
 *   research_inbox_item              (captured_at)
 *   research_method_protocol         (created_at / updated_at)
 */
class TimeMachineService
{
    /** @var array<string,string> Bootstrap badge colour per event kind. */
    public const KIND_BADGES = [
        'brief'    => 'primary',
        'decision' => 'info',
        'claim'    => 'success',
        'argument' => 'warning',
        'inbox'    => 'secondary',
        'method'   => 'dark',
    ];

    /** @var array<string,string> Font Awesome icon per event kind. */
    public const KIND_ICONS = [
        'brief'    => 'fa-question',
        'decision' => 'fa-gavel',
        'claim'    => 'fa-flask',
        'argument' => 'fa-diagram-project',
        'inbox'    => 'fa-inbox',
        'method'   => 'fa-microscope',
    ];

    /** @var array<string,string> Human label per event kind. */
    public const KIND_LABELS = [
        'brief'    => 'Question brief',
        'decision' => 'Decision',
        'claim'    => 'Claim',
        'argument' => 'Argument',
        'inbox'    => 'Captured item',
        'method'   => 'Method protocol',
    ];

    /**
     * Build the full project timeline as a flat, sorted list of events.
     *
     * @param  int     $projectId
     * @param  string  $order  'desc' (newest first) or 'asc' (oldest first)
     * @return array<int,array<string,mixed>>
     */
    public function timeline(int $projectId, string $order = 'desc'): array
    {
        $events = array_merge(
            $this->briefEvents($projectId),
            $this->decisionEvents($projectId),
            $this->claimEvents($projectId),
            $this->argumentEvents($projectId),
            $this->inboxEvents($projectId),
            $this->methodEvents($projectId)
        );

        // Drop anything without a usable timestamp, then sort.
        $events = array_values(array_filter($events, static function ($e) {
            return isset($e['when']) && $e['when'] instanceof Carbon;
        }));

        usort($events, static function ($a, $b) use ($order) {
            $cmp = $a['when']->getTimestamp() <=> $b['when']->getTimestamp();
            return $order === 'asc' ? $cmp : -$cmp;
        });

        return $events;
    }

    /**
     * Group an already-ordered timeline by "YYYY-MM" month key.
     *
     * @param  array<int,array<string,mixed>>  $events
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function groupByMonth(array $events): array
    {
        $groups = [];
        foreach ($events as $e) {
            if (! ($e['when'] instanceof Carbon)) {
                continue;
            }
            $key = $e['when']->format('Y-m');
            $groups[$key][] = $e;
        }
        return $groups;
    }

    /**
     * Earliest and latest event timestamps across the whole timeline, used to
     * bound the date scrubber. Returns [Carbon|null $min, Carbon|null $max].
     *
     * @param  array<int,array<string,mixed>>  $events
     * @return array{0:?Carbon,1:?Carbon}
     */
    public function bounds(array $events): array
    {
        $min = null;
        $max = null;
        foreach ($events as $e) {
            if (! ($e['when'] instanceof Carbon)) {
                continue;
            }
            if ($min === null || $e['when']->lt($min)) {
                $min = $e['when']->copy();
            }
            if ($max === null || $e['when']->gt($max)) {
                $max = $e['when']->copy();
            }
        }
        return [$min, $max];
    }

    /**
     * Reconstruct the project state as it stood ON OR BEFORE $asOf.
     *
     * @param  int            $projectId
     * @param  Carbon|string  $asOf  any parseable date; invalid input defaults to now
     * @return array<string,mixed>
     */
    public function stateAsOf(int $projectId, $asOf): array
    {
        $cutoff = $this->parseDate($asOf);
        $cutoffStr = $cutoff->format('Y-m-d H:i:s');

        return [
            'asOf'      => $cutoff,
            'brief'     => $this->briefAsOf($projectId, $cutoffStr),
            'claims'    => $this->claimsAsOf($projectId, $cutoffStr),
            'decisions' => $this->decisionsAsOf($projectId, $cutoffStr),
            'arguments' => $this->argumentsAsOf($projectId, $cutoffStr),
            'inbox'     => $this->inboxAsOf($projectId, $cutoffStr),
            'methods'   => $this->methodsAsOf($projectId, $cutoffStr),
        ];
    }

    // -----------------------------------------------------------------------
    // Timeline event builders (one per source). Each is fully guarded.
    // -----------------------------------------------------------------------

    /** Question brief version events (one per saved version). */
    protected function briefEvents(int $projectId): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('research_question_brief') || ! Schema::hasTable('research_question_brief_version')) {
                return $out;
            }
            $rows = DB::table('research_question_brief_version as v')
                ->join('research_question_brief as b', 'b.id', '=', 'v.brief_id')
                ->where('b.project_id', $projectId)
                ->whereNotNull('v.created_at')
                ->orderBy('v.created_at')
                ->select('v.version_no', 'v.change_reason', 'v.created_at', 'v.primary_question')
                ->get();

            foreach ($rows as $r) {
                $reason = trim((string) ($r->change_reason ?? ''));
                $q      = trim((string) ($r->primary_question ?? ''));
                $detail = $reason !== '' ? $reason : ($q !== '' ? $q : '');
                $out[] = [
                    'when'   => $this->toCarbon($r->created_at),
                    'kind'   => 'brief',
                    'label'  => 'Question brief v' . (int) $r->version_no . ' saved',
                    'detail' => $detail,
                    'link'   => $this->safeRoute('research.questionbuilder.index', $projectId),
                    'icon'   => self::KIND_ICONS['brief'],
                    'badge'  => self::KIND_BADGES['brief'],
                ];
            }
        } catch (\Throwable $e) {
            // missing slice -> no events
        }
        return $out;
    }

    /** Decision-log events. */
    protected function decisionEvents(int $projectId): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('research_decision_log')) {
                return $out;
            }
            $rows = DB::table('research_decision_log')
                ->where('project_id', $projectId)
                ->get();

            foreach ($rows as $r) {
                $when = $r->decided_at ?? $r->created_at ?? null;
                $out[] = [
                    'when'   => $this->toCarbon($when),
                    'kind'   => 'decision',
                    'label'  => trim((string) ($r->summary ?? 'Decision')),
                    'detail' => trim((string) ($r->reason ?? '')),
                    'link'   => $this->safeRoute('research.decisionlog.index', $projectId),
                    'icon'   => self::KIND_ICONS['decision'],
                    'badge'  => self::KIND_BADGES['decision'],
                ];
            }
        } catch (\Throwable $e) {
            // missing slice -> no events
        }
        return $out;
    }

    /** Claim (assertion) events. */
    protected function claimEvents(int $projectId): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('research_assertion')) {
                return $out;
            }
            $rows = DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->whereNotNull('created_at')
                ->get();

            foreach ($rows as $r) {
                $status = trim((string) ($r->status ?? ''));
                $out[] = [
                    'when'   => $this->toCarbon($r->created_at),
                    'kind'   => 'claim',
                    'label'  => 'Claim recorded' . ($status !== '' ? ' (' . $status . ')' : ''),
                    'detail' => $this->assertionLabel($r),
                    'link'   => $this->safeRoute('research.claimledger.index', $projectId),
                    'icon'   => self::KIND_ICONS['claim'],
                    'badge'  => self::KIND_BADGES['claim'],
                ];
            }
        } catch (\Throwable $e) {
            // missing slice -> no events
        }
        return $out;
    }

    /** Argument-builder events: argument created + each step added. */
    protected function argumentEvents(int $projectId): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('research_argument')) {
                return $out;
            }
            $args = DB::table('research_argument')
                ->where('project_id', $projectId)
                ->get();

            $argIds = [];
            foreach ($args as $a) {
                $argIds[$a->id] = trim((string) ($a->title ?? 'Argument'));
                $out[] = [
                    'when'   => $this->toCarbon($a->created_at),
                    'kind'   => 'argument',
                    'label'  => 'Argument started: ' . ($a->title ? trim((string) $a->title) : 'Untitled'),
                    'detail' => trim((string) ($a->central_thesis ?? '')),
                    'link'   => $this->safeRoute('research.argumentbuilder.index', $projectId),
                    'icon'   => self::KIND_ICONS['argument'],
                    'badge'  => self::KIND_BADGES['argument'],
                ];
            }

            // Steps belong to the project via their parent argument.
            if (! empty($argIds) && Schema::hasTable('research_argument_step')) {
                $steps = DB::table('research_argument_step')
                    ->whereIn('argument_id', array_keys($argIds))
                    ->whereNotNull('created_at')
                    ->get();

                foreach ($steps as $s) {
                    $slot = trim((string) ($s->slot ?? 'step'));
                    $parent = $argIds[$s->argument_id] ?? 'Argument';
                    $out[] = [
                        'when'   => $this->toCarbon($s->created_at),
                        'kind'   => 'argument',
                        'label'  => 'Argument step (' . $slot . ') added to "' . $parent . '"',
                        'detail' => trim((string) ($s->note ?? '')),
                        'link'   => $this->safeRoute('research.argumentbuilder.index', $projectId),
                        'icon'   => self::KIND_ICONS['argument'],
                        'badge'  => self::KIND_BADGES['argument'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            // missing slice -> no events
        }
        return $out;
    }

    /** Inbox-capture events. */
    protected function inboxEvents(int $projectId): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('research_inbox_item')) {
                return $out;
            }
            $rows = DB::table('research_inbox_item')
                ->where('project_id', $projectId)
                ->get();

            foreach ($rows as $r) {
                $when = $r->captured_at ?? $r->created_at ?? null;
                $kind = trim((string) ($r->kind ?? 'note'));
                $out[] = [
                    'when'   => $this->toCarbon($when),
                    'kind'   => 'inbox',
                    'label'  => 'Captured ' . $kind . ': ' . ($r->title ? trim((string) $r->title) : 'untitled'),
                    'detail' => trim((string) ($r->body ?? '')),
                    'link'   => $this->safeRoute('research.inbox.index', $projectId),
                    'icon'   => self::KIND_ICONS['inbox'],
                    'badge'  => self::KIND_BADGES['inbox'],
                ];
            }
        } catch (\Throwable $e) {
            // missing slice -> no events
        }
        return $out;
    }

    /** Method-protocol events: creation, and a separate "revised" event if updated later. */
    protected function methodEvents(int $projectId): array
    {
        $out = [];
        try {
            if (! Schema::hasTable('research_method_protocol')) {
                return $out;
            }
            $rows = DB::table('research_method_protocol')
                ->where('project_id', $projectId)
                ->get();

            foreach ($rows as $r) {
                $title  = trim((string) ($r->title ?? 'Protocol'));
                $status = trim((string) ($r->status ?? ''));
                $out[] = [
                    'when'   => $this->toCarbon($r->created_at),
                    'kind'   => 'method',
                    'label'  => 'Method protocol created: ' . $title,
                    'detail' => $status !== '' ? ('Status: ' . $status) : '',
                    'link'   => $this->safeRoute('research.methodstudio.index', $projectId),
                    'icon'   => self::KIND_ICONS['method'],
                    'badge'  => self::KIND_BADGES['method'],
                ];

                // Treat a meaningfully later updated_at as a distinct revision event.
                $created = $this->toCarbon($r->created_at);
                $updated = $this->toCarbon($r->updated_at);
                if ($created && $updated && $updated->gt($created->copy()->addMinute())) {
                    $out[] = [
                        'when'   => $updated,
                        'kind'   => 'method',
                        'label'  => 'Method protocol revised: ' . $title,
                        'detail' => $status !== '' ? ('Status: ' . $status) : '',
                        'link'   => $this->safeRoute('research.methodstudio.index', $projectId),
                        'icon'   => self::KIND_ICONS['method'],
                        'badge'  => self::KIND_BADGES['method'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            // missing slice -> no events
        }
        return $out;
    }

    // -----------------------------------------------------------------------
    // "State as of" reconstruction (one per source). All guarded.
    // -----------------------------------------------------------------------

    /** The brief version that was current on or before the cutoff (latest created_at <= cutoff). */
    protected function briefAsOf(int $projectId, string $cutoffStr): ?object
    {
        try {
            if (! Schema::hasTable('research_question_brief') || ! Schema::hasTable('research_question_brief_version')) {
                return null;
            }
            return DB::table('research_question_brief_version as v')
                ->join('research_question_brief as b', 'b.id', '=', 'v.brief_id')
                ->where('b.project_id', $projectId)
                ->whereNotNull('v.created_at')
                ->where('v.created_at', '<=', $cutoffStr)
                ->orderByDesc('v.created_at')
                ->orderByDesc('v.version_no')
                ->select('v.*')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Claims that existed on or before the cutoff. */
    protected function claimsAsOf(int $projectId, string $cutoffStr): array
    {
        try {
            if (! Schema::hasTable('research_assertion')) {
                return [];
            }
            return DB::table('research_assertion')
                ->where('project_id', $projectId)
                ->whereNotNull('created_at')
                ->where('created_at', '<=', $cutoffStr)
                ->orderBy('created_at')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Decisions made on or before the cutoff. */
    protected function decisionsAsOf(int $projectId, string $cutoffStr): array
    {
        try {
            if (! Schema::hasTable('research_decision_log')) {
                return [];
            }
            return DB::table('research_decision_log')
                ->where('project_id', $projectId)
                ->where(function ($q) use ($cutoffStr) {
                    $q->where('decided_at', '<=', $cutoffStr)
                      ->orWhere(function ($q2) use ($cutoffStr) {
                          $q2->whereNull('decided_at')->where('created_at', '<=', $cutoffStr);
                      });
                })
                ->orderByRaw('COALESCE(decided_at, created_at)')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Arguments (and their step counts) that existed on or before the cutoff. */
    protected function argumentsAsOf(int $projectId, string $cutoffStr): array
    {
        try {
            if (! Schema::hasTable('research_argument')) {
                return [];
            }
            $args = DB::table('research_argument')
                ->where('project_id', $projectId)
                ->whereNotNull('created_at')
                ->where('created_at', '<=', $cutoffStr)
                ->orderBy('created_at')
                ->get();

            $hasSteps = Schema::hasTable('research_argument_step');
            $out = [];
            foreach ($args as $a) {
                $stepCount = 0;
                if ($hasSteps) {
                    $stepCount = (int) DB::table('research_argument_step')
                        ->where('argument_id', $a->id)
                        ->whereNotNull('created_at')
                        ->where('created_at', '<=', $cutoffStr)
                        ->count();
                }
                $a->step_count = $stepCount;
                $out[] = $a;
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Inbox items captured on or before the cutoff. */
    protected function inboxAsOf(int $projectId, string $cutoffStr): array
    {
        try {
            if (! Schema::hasTable('research_inbox_item')) {
                return [];
            }
            return DB::table('research_inbox_item')
                ->where('project_id', $projectId)
                ->where(function ($q) use ($cutoffStr) {
                    $q->where('captured_at', '<=', $cutoffStr)
                      ->orWhere(function ($q2) use ($cutoffStr) {
                          $q2->whereNull('captured_at')->where('created_at', '<=', $cutoffStr);
                      });
                })
                ->orderByRaw('COALESCE(captured_at, created_at)')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Method protocols created on or before the cutoff. */
    protected function methodsAsOf(int $projectId, string $cutoffStr): array
    {
        try {
            if (! Schema::hasTable('research_method_protocol')) {
                return [];
            }
            return DB::table('research_method_protocol')
                ->where('project_id', $projectId)
                ->whereNotNull('created_at')
                ->where('created_at', '<=', $cutoffStr)
                ->orderBy('created_at')
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Compose a readable label for an assertion row (subject / predicate / object). */
    protected function assertionLabel(object $r): string
    {
        $subject = trim((string) ($r->subject_label ?? ''));
        $pred    = trim((string) ($r->predicate ?? ''));
        $object  = trim((string) ($r->object_label ?? ''));
        if ($object === '') {
            $object = trim((string) ($r->object_value ?? ''));
        }
        $parts = array_filter([$subject, $pred, $object], static fn ($p) => $p !== '');
        return implode(' ', $parts);
    }

    /**
     * Defensive date parse. Invalid / empty input falls back to "now".
     *
     * @param  Carbon|string|null  $value
     */
    protected function parseDate($value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return Carbon::now();
        }
        try {
            // A bare YYYY-MM-DD means "the whole of that day", so use end of day.
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return Carbon::parse($value)->endOfDay();
            }
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return Carbon::now();
        }
    }

    /** Convert a DB timestamp value to Carbon, or null if it cannot be parsed. */
    protected function toCarbon($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Resolve a route name to a URL, or null if the route is not registered. */
    protected function safeRoute(string $name, int $projectId): ?string
    {
        try {
            return route($name, $projectId);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
