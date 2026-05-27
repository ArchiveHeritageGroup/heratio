<?php

declare(strict_types=1);

/**
 * Heratio - Spectrum Statistics Service.
 *
 * Aggregates Spectrum 5.1 procedure data from spectrum_event and the per-procedure
 * tables (acquisition / loan-in / loan-out / valuation / conservation / etc.) into
 * read-only summaries suitable for machine consumers (member museums, auditors,
 * external dashboards) via the v2 public API.
 *
 * Mirrors the AtoM-AHG ahgSpectrumEventService statistics surface (procedure
 * registry, status registry, recent-activity counters) but uses Laravel's DB
 * facade instead of Capsule and treats every read as pre-checked by the
 * caller's bearer scope.
 *
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems (Pty) Ltd
 * @license   AGPL-3.0-or-later
 * @package   AhgSpectrum\Services
 */

namespace AhgSpectrum\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpectrumStatisticsService
{
    /**
     * Spectrum 5.1 procedure registry. Procedure_id values match the ones
     * written by ahgSpectrumEventService on the PSIS side so a federated
     * consumer can join Heratio + PSIS event streams by procedure_id.
     */
    public const PROCEDURES = [
        'object_entry' => ['name' => 'Object Entry', 'spectrum_ref' => '1', 'category' => 'pre_entry'],
        'acquisition' => ['name' => 'Acquisition', 'spectrum_ref' => '2', 'category' => 'acquisition'],
        'location_movement' => ['name' => 'Location and Movement Control', 'spectrum_ref' => '3', 'category' => 'location'],
        'inventory' => ['name' => 'Inventory Control', 'spectrum_ref' => '4', 'category' => 'inventory'],
        'cataloguing' => ['name' => 'Cataloguing', 'spectrum_ref' => '5', 'category' => 'documentation'],
        'object_condition' => ['name' => 'Object Condition Checking', 'spectrum_ref' => '6', 'category' => 'care'],
        'conservation' => ['name' => 'Conservation and Collections Care', 'spectrum_ref' => '7', 'category' => 'care'],
        'risk_management' => ['name' => 'Risk Management', 'spectrum_ref' => '8', 'category' => 'care'],
        'insurance' => ['name' => 'Insurance and Indemnity', 'spectrum_ref' => '9', 'category' => 'legal'],
        'valuation' => ['name' => 'Valuation Control', 'spectrum_ref' => '10', 'category' => 'legal'],
        'audit' => ['name' => 'Audit', 'spectrum_ref' => '11', 'category' => 'accountability'],
        'rights_management' => ['name' => 'Rights Management', 'spectrum_ref' => '12', 'category' => 'legal'],
        'reproduction' => ['name' => 'Reproduction', 'spectrum_ref' => '13', 'category' => 'use'],
        'loans_in' => ['name' => 'Loans In', 'spectrum_ref' => '14', 'category' => 'loans'],
        'loans_out' => ['name' => 'Loans Out', 'spectrum_ref' => '15', 'category' => 'loans'],
        'loss_damage' => ['name' => 'Loss and Damage', 'spectrum_ref' => '16', 'category' => 'incidents'],
        'deaccession' => ['name' => 'Deaccession and Disposal', 'spectrum_ref' => '17', 'category' => 'exit'],
        'documentation_planning' => ['name' => 'Documentation Planning', 'spectrum_ref' => '18', 'category' => 'planning'],
        'object_exit' => ['name' => 'Object Exit', 'spectrum_ref' => '19', 'category' => 'exit'],
        'emergency_planning' => ['name' => 'Emergency Planning', 'spectrum_ref' => '20', 'category' => 'planning'],
        'collections_review' => ['name' => 'Collections Review', 'spectrum_ref' => '21', 'category' => 'planning'],
    ];

    /**
     * Status registry. Same key set as ahgSpectrumEventService::$statusLabels
     * on PSIS so a federated consumer can render status badges identically.
     */
    public const STATUS_LABELS = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'pending_review' => 'Pending Review',
        'pending_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
        'overdue' => 'Overdue',
    ];

    /**
     * Map of procedure_id -> the per-procedure detail table that signals
     * "this procedure exists for this object". Used by the statistics
     * aggregator when spectrum_event is empty / sparse (which is common
     * for objects migrated from AtoM before the event log was wired up).
     */
    public const PROCEDURE_TABLES = [
        'object_entry' => 'spectrum_object_entry',
        'acquisition' => 'spectrum_acquisition',
        'location_movement' => 'spectrum_movement',
        'cataloguing' => null,
        'object_condition' => 'spectrum_condition_check',
        'conservation' => 'spectrum_conservation',
        'valuation' => 'spectrum_valuation',
        'loans_in' => 'spectrum_loan_in',
        'loans_out' => 'spectrum_loan_out',
        'deaccession' => 'spectrum_deaccession',
        'object_exit' => 'spectrum_object_exit',
    ];

    /**
     * Aggregate statistics for the public /statistics endpoint.
     *
     * Returns:
     *   - object_count : total information_object rows
     *   - by_procedure : per-procedure event counts and most recent activity
     *   - by_status    : event counts grouped by status_to
     *   - last_month   : event count for the trailing 30 days, plus per-day
     *                    breakdown for the same window
     *   - generated_at : ISO8601 timestamp
     */
    public function aggregate(): array
    {
        $objectCount = $this->safeCount('information_object');

        return [
            'object_count' => $objectCount,
            'by_procedure' => $this->byProcedure(),
            'by_status' => $this->byStatus(),
            'last_month' => $this->lastMonthActivity(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Per-procedure breakdown: event counts plus per-procedure detail-table
     * row counts (so an empty event log still reports object_entry / loan_in
     * volume correctly for migrated archives).
     */
    public function byProcedure(): array
    {
        $eventCounts = [];
        if (Schema::hasTable('spectrum_event')) {
            $rows = DB::table('spectrum_event')
                ->select('procedure_id', DB::raw('COUNT(*) as event_count'), DB::raw('MAX(created_at) as last_event_at'))
                ->groupBy('procedure_id')
                ->get();

            foreach ($rows as $row) {
                $eventCounts[$row->procedure_id] = [
                    'event_count' => (int) $row->event_count,
                    'last_event_at' => $row->last_event_at,
                ];
            }
        }

        $out = [];
        foreach (self::PROCEDURES as $procId => $proc) {
            $tableCount = null;
            $detailTable = self::PROCEDURE_TABLES[$procId] ?? null;
            if ($detailTable !== null && Schema::hasTable($detailTable)) {
                $tableCount = (int) DB::table($detailTable)->count();
            }

            $out[] = [
                'procedure_id' => $procId,
                'name' => $proc['name'],
                'spectrum_ref' => $proc['spectrum_ref'],
                'category' => $proc['category'],
                'event_count' => $eventCounts[$procId]['event_count'] ?? 0,
                'record_count' => $tableCount,
                'last_event_at' => $eventCounts[$procId]['last_event_at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Status breakdown from spectrum_event.status_to.
     */
    public function byStatus(): array
    {
        if (! Schema::hasTable('spectrum_event')) {
            return [];
        }

        $rows = DB::table('spectrum_event')
            ->select('status_to', DB::raw('COUNT(*) as event_count'))
            ->whereNotNull('status_to')
            ->groupBy('status_to')
            ->get();

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = (string) $row->status_to;
            $out[] = [
                'status' => $key,
                'label' => self::STATUS_LABELS[$key] ?? ucwords(str_replace('_', ' ', $key)),
                'count' => (int) $row->event_count,
            ];
            $seen[$key] = true;
        }

        // Always include every well-known status so downstream dashboards can
        // render a stable axis even when the count is zero.
        foreach (self::STATUS_LABELS as $key => $label) {
            if (! isset($seen[$key])) {
                $out[] = ['status' => $key, 'label' => $label, 'count' => 0];
            }
        }

        return $out;
    }

    /**
     * Activity for the trailing 30 days: total + per-day breakdown.
     */
    public function lastMonthActivity(): array
    {
        if (! Schema::hasTable('spectrum_event')) {
            return ['total' => 0, 'window_days' => 30, 'by_day' => []];
        }

        $since = Carbon::now()->subDays(30)->startOfDay();

        $rows = DB::table('spectrum_event')
            ->select(DB::raw('DATE(created_at) as event_date'), DB::raw('COUNT(*) as event_count'))
            ->where('created_at', '>=', $since)
            ->groupBy('event_date')
            ->orderBy('event_date')
            ->get();

        $byDay = [];
        $total = 0;
        foreach ($rows as $row) {
            $count = (int) $row->event_count;
            $total += $count;
            $byDay[] = ['date' => (string) $row->event_date, 'count' => $count];
        }

        return [
            'total' => $total,
            'window_days' => 30,
            'since' => $since->toIso8601String(),
            'by_day' => $byDay,
        ];
    }

    /**
     * Chronological event feed for the public /events endpoint.
     *
     * @param  \DateTimeInterface|null  $since  Only events at or after this instant.
     * @param  int  $limit  Maximum events to return (server-enforced cap of 500).
     */
    public function events(?\DateTimeInterface $since = null, int $limit = 50): array
    {
        $limit = max(1, min($limit, 500));

        if (! Schema::hasTable('spectrum_event')) {
            return [];
        }

        $q = DB::table('spectrum_event as e')
            ->leftJoin('information_object as io', 'e.object_id', '=', 'io.id')
            ->select(
                'e.id',
                'e.event_type',
                'e.procedure_id',
                'e.object_id',
                'e.status_from',
                'e.status_to',
                'e.created_at',
                'io.identifier as object_identifier',
            );

        if ($since !== null) {
            $q->where('e.created_at', '>=', $since->format('Y-m-d H:i:s'));
        }

        $rows = $q->orderBy('e.created_at', 'desc')
            ->orderBy('e.id', 'desc')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->formatEvent($row);
        }

        return $out;
    }

    /**
     * Per-object procedure timeline for the public /activity/{object_id}
     * endpoint. Includes every spectrum_event row for the object, ordered
     * newest first, plus a tiny header with object identifier + counts.
     */
    public function activity(int $objectId): ?array
    {
        $object = DB::table('information_object')->where('id', $objectId)->first();
        if (! $object) {
            return null;
        }

        $events = [];
        if (Schema::hasTable('spectrum_event')) {
            $rows = DB::table('spectrum_event as e')
                ->leftJoin('information_object as io', 'e.object_id', '=', 'io.id')
                ->select(
                    'e.id', 'e.event_type', 'e.procedure_id', 'e.object_id',
                    'e.status_from', 'e.status_to', 'e.created_at',
                    'io.identifier as object_identifier',
                )
                ->where('e.object_id', $objectId)
                ->orderBy('e.created_at', 'desc')
                ->orderBy('e.id', 'desc')
                ->limit(500)
                ->get();

            foreach ($rows as $row) {
                $events[] = $this->formatEvent($row);
            }
        }

        // Per-procedure summary for this object - current status_to of the
        // most recent event in each procedure.
        $procedureSummary = [];
        $latestByProc = [];
        foreach ($events as $ev) {
            $pid = $ev['procedure_id'];
            if (! isset($latestByProc[$pid])) {
                $latestByProc[$pid] = $ev;
            }
        }
        foreach ($latestByProc as $pid => $ev) {
            $proc = self::PROCEDURES[$pid] ?? null;
            $procedureSummary[] = [
                'procedure_id' => $pid,
                'name' => $proc['name'] ?? $pid,
                'spectrum_ref' => $proc['spectrum_ref'] ?? null,
                'current_status' => $ev['status_to'],
                'current_status_label' => $ev['status_to_label'],
                'last_event_at' => $ev['occurred_at'],
            ];
        }

        return [
            'object_id' => (int) $object->id,
            'object_identifier' => $object->identifier ?? null,
            'event_count' => count($events),
            'procedures' => $procedureSummary,
            'events' => $events,
        ];
    }

    /**
     * Normalise a raw spectrum_event row into the public event shape.
     *
     * @param  object  $row
     */
    protected function formatEvent($row): array
    {
        $procedure = self::PROCEDURES[$row->procedure_id] ?? null;
        $procedureName = $procedure['name'] ?? $row->procedure_id;
        $eventType = (string) $row->event_type;
        $statusTo = $row->status_to ? (string) $row->status_to : null;
        $statusFrom = $row->status_from ? (string) $row->status_from : null;

        $summary = $this->summarise($procedureName, $eventType, $statusFrom, $statusTo);

        return [
            'id' => (int) $row->id,
            'type' => $eventType,
            'procedure_id' => $row->procedure_id,
            'procedure_name' => $procedureName,
            'object_id' => (int) $row->object_id,
            'object_identifier' => $row->object_identifier ?? null,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'status_to_label' => $statusTo ? (self::STATUS_LABELS[$statusTo] ?? null) : null,
            'occurred_at' => $row->created_at,
            'summary' => $summary,
        ];
    }

    /**
     * Build a one-line human summary for an event row.
     */
    protected function summarise(string $procedureName, string $eventType, ?string $from, ?string $to): string
    {
        $eventLabel = ucwords(str_replace('_', ' ', $eventType));
        if ($eventType === 'status_change' && $to !== null) {
            $toLabel = self::STATUS_LABELS[$to] ?? ucwords(str_replace('_', ' ', $to));
            if ($from !== null) {
                $fromLabel = self::STATUS_LABELS[$from] ?? ucwords(str_replace('_', ' ', $from));

                return sprintf('%s: %s -> %s', $procedureName, $fromLabel, $toLabel);
            }

            return sprintf('%s: %s', $procedureName, $toLabel);
        }

        return sprintf('%s: %s', $procedureName, $eventLabel);
    }

    /**
     * Defensive count: returns 0 when the table is absent (fresh install,
     * test environment, partial migration).
     */
    protected function safeCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }
}
