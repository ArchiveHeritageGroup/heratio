<?php

/**
 * CaptureQueueService - Heratio ahg-core
 *
 * heratio#1205 "race against loss": the actionable workflow that sits on top of
 * the read-only capture-priority register (CapturePriorityService). The register
 * tells an operator WHICH records are most at risk; this service lets them act on
 * it - pull a record into a working capture queue, track its status as it moves
 * from queued -> in progress -> captured (or deferred), assign it, annotate it,
 * and pull it back out. That turns the at-risk register into real digitisation
 * work instead of a static report.
 *
 * Scope of writes: ONLY the ahg_capture_queue side table. No AtoM/Qubit base
 * tables are ever written. Status values come exclusively from the Dropdown
 * Manager group `capture_queue_status` (ahg_dropdown) - never an ENUM, never a
 * hardcoded option list. A status that is not a known, active dropdown code is
 * rejected so the table can never drift from the configured taxonomy.
 *
 * Resilient by design: every public method is schema-guarded. If the
 * ahg_capture_queue table or the ahg_dropdown table is missing (fresh install,
 * mid-migration), reads return empty and writes become safe no-ops rather than
 * throwing - the surrounding queue UI simply hides itself and the at-risk
 * register still works.
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

class CaptureQueueService
{
    /** Dropdown Manager taxonomy that defines the workflow statuses. */
    public const STATUS_TAXONOMY = 'capture_queue_status';

    /** Fallback status used only when the dropdown taxonomy is unreadable. */
    private const FALLBACK_STATUS = 'queued';

    /** A "captured" status stamps captured_at; any other status clears it. */
    private const CAPTURED_STATUS = 'captured';

    /**
     * Is the queue feature available on this install? True only when its own
     * table exists. Callers use this to decide whether to render queue UI at all.
     */
    public function isAvailable(): bool
    {
        try {
            return Schema::hasTable('ahg_capture_queue');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The configured workflow statuses, as ordered {code,label,color,icon} rows,
     * read live from the Dropdown Manager group `capture_queue_status`. Returns an
     * empty array when the dropdown table or group is unavailable - callers treat
     * "no configured statuses" as "queue status controls hidden".
     *
     * @return array<int, array{code:string,label:string,color:?string,icon:?string}>
     */
    public function statuses(): array
    {
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return [];
            }

            return DB::table('ahg_dropdown')
                ->where('taxonomy', self::STATUS_TAXONOMY)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(['code', 'label', 'color', 'icon'])
                ->map(fn ($r) => [
                    'code' => (string) $r->code,
                    'label' => (string) $r->label,
                    'color' => $r->color !== null ? (string) $r->color : null,
                    'icon' => $r->icon !== null ? (string) $r->icon : null,
                ])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The set of valid status codes from the dropdown group.
     *
     * @return array<int, string>
     */
    public function statusCodes(): array
    {
        return array_map(fn ($s) => $s['code'], $this->statuses());
    }

    /**
     * True when $status is a known, active dropdown code. When no statuses are
     * configured at all we accept the fallback so a fresh install can still queue.
     */
    private function isValidStatus(string $status): bool
    {
        $codes = $this->statusCodes();
        if (empty($codes)) {
            return $status === self::FALLBACK_STATUS;
        }

        return in_array($status, $codes, true);
    }

    /**
     * Add an information object to the capture queue (idempotent on the unique
     * information_object_id). If it is already queued, the existing row is left in
     * place and returned - re-adding never resets status, note, or assignee. The
     * priority_score is a snapshot of the register score at queue time so the queue
     * stays meaningful even as the live score shifts.
     *
     * @return int|null  the queue row id, or null when the feature is unavailable.
     */
    public function add(int $ioId, int $score = 0, ?string $note = null, ?string $assignedTo = null): ?int
    {
        if ($ioId <= 0 || ! $this->isAvailable()) {
            return null;
        }

        try {
            $existing = DB::table('ahg_capture_queue')
                ->where('information_object_id', $ioId)
                ->first();
            if ($existing) {
                return (int) $existing->id;
            }

            $status = $this->isValidStatus(self::FALLBACK_STATUS) ? self::FALLBACK_STATUS : ($this->statusCodes()[0] ?? self::FALLBACK_STATUS);
            $now = now();

            return (int) DB::table('ahg_capture_queue')->insertGetId([
                'information_object_id' => $ioId,
                'status' => $status,
                'priority_score' => max(0, $score),
                'note' => ($note !== null && trim($note) !== '') ? $note : null,
                'assigned_to' => ($assignedTo !== null && trim($assignedTo) !== '') ? trim($assignedTo) : null,
                'queued_at' => $now,
                'captured_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] capture-queue add failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Change a queue row's status. The row is resolved by queue id OR by
     * information_object_id (whichever the caller has). $status must be a known,
     * active dropdown code - an unknown status is rejected (no write). Moving to
     * the "captured" status stamps captured_at once; moving away clears it.
     */
    public function setStatus(int $idOrIoId, string $status, bool $byIoId = false): bool
    {
        $status = trim($status);
        if ($idOrIoId <= 0 || $status === '' || ! $this->isAvailable() || ! $this->isValidStatus($status)) {
            return false;
        }

        try {
            $column = $byIoId ? 'information_object_id' : 'id';
            $update = [
                'status' => $status,
                'updated_at' => now(),
            ];
            if ($status === self::CAPTURED_STATUS) {
                // Stamp the capture time only the first time it lands on captured.
                $row = DB::table('ahg_capture_queue')->where($column, $idOrIoId)->first(['captured_at']);
                if ($row && $row->captured_at === null) {
                    $update['captured_at'] = now();
                }
            } else {
                $update['captured_at'] = null;
            }

            return DB::table('ahg_capture_queue')->where($column, $idOrIoId)->update($update) > 0;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] capture-queue setStatus failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Assign (or clear, with null/empty) the operator responsible for a queue row.
     */
    public function assign(int $idOrIoId, ?string $assignedTo, bool $byIoId = false): bool
    {
        if ($idOrIoId <= 0 || ! $this->isAvailable()) {
            return false;
        }

        try {
            $column = $byIoId ? 'information_object_id' : 'id';
            $value = ($assignedTo !== null && trim($assignedTo) !== '') ? trim($assignedTo) : null;

            return DB::table('ahg_capture_queue')
                ->where($column, $idOrIoId)
                ->update(['assigned_to' => $value, 'updated_at' => now()]) > 0;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] capture-queue assign failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Remove a record from the capture queue (resolved by queue id or IO id).
     */
    public function remove(int $idOrIoId, bool $byIoId = false): bool
    {
        if ($idOrIoId <= 0 || ! $this->isAvailable()) {
            return false;
        }

        try {
            $column = $byIoId ? 'information_object_id' : 'id';

            return DB::table('ahg_capture_queue')->where($column, $idOrIoId)->delete() > 0;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] capture-queue remove failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * The capture queue, newest-queued first, joined to each record's title + slug
     * for display. Optional filter: status (a single dropdown code). Returns an
     * empty array when the feature is unavailable so the caller renders an empty
     * state rather than 500ing.
     *
     * @param  array{status?:string}  $filters
     * @return array<int, array{id:int,information_object_id:int,status:string,priority_score:int,note:?string,assigned_to:?string,queued_at:?string,captured_at:?string,title:string,slug:?string}>
     */
    public function list(array $filters = []): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $q = DB::table('ahg_capture_queue as q')
                ->leftJoin('information_object as io', 'io.id', '=', 'q.information_object_id')
                ->leftJoin('information_object_i18n as i18n', function ($j) {
                    $j->on('i18n.id', '=', 'io.id')
                        ->on('i18n.culture', '=', 'io.source_culture');
                })
                ->leftJoin('slug as s', 's.object_id', '=', 'q.information_object_id')
                ->select([
                    'q.id',
                    'q.information_object_id',
                    'q.status',
                    'q.priority_score',
                    'q.note',
                    'q.assigned_to',
                    'q.queued_at',
                    'q.captured_at',
                    'i18n.title',
                    's.slug',
                ]);

            $status = trim((string) ($filters['status'] ?? ''));
            if ($status !== '') {
                $q->where('q.status', $status);
            }

            return $q->orderByDesc('q.queued_at')
                ->orderByDesc('q.id')
                ->get()
                ->map(function ($r) {
                    $title = trim((string) ($r->title ?? ''));

                    return [
                        'id' => (int) $r->id,
                        'information_object_id' => (int) $r->information_object_id,
                        'status' => (string) $r->status,
                        'priority_score' => (int) $r->priority_score,
                        'note' => $r->note !== null ? (string) $r->note : null,
                        'assigned_to' => $r->assigned_to !== null ? (string) $r->assigned_to : null,
                        'queued_at' => $r->queued_at !== null ? (string) $r->queued_at : null,
                        'captured_at' => $r->captured_at !== null ? (string) $r->captured_at : null,
                        'title' => $title !== '' ? $title : '(untitled record #'.(int) $r->information_object_id.')',
                        'slug' => $r->slug !== null ? (string) $r->slug : null,
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] capture-queue list failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Per-status counts plus a total, for the queue dashboard cards / filter pills.
     * Every configured status is present (zero when empty). Returns an empty shape
     * when the feature is unavailable.
     *
     * @return array{total:int,by_status:array<string,int>}
     */
    public function counts(): array
    {
        $out = ['total' => 0, 'by_status' => []];
        foreach ($this->statusCodes() as $code) {
            $out['by_status'][$code] = 0;
        }

        if (! $this->isAvailable()) {
            return $out;
        }

        try {
            $rows = DB::table('ahg_capture_queue')
                ->select('status', DB::raw('COUNT(*) as n'))
                ->groupBy('status')
                ->get();
            foreach ($rows as $r) {
                $out['by_status'][(string) $r->status] = (int) $r->n;
                $out['total'] += (int) $r->n;
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] capture-queue counts failed: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * The set of information_object ids already in the queue, for O(1) "already
     * queued?" checks when rendering the at-risk register's per-row action.
     *
     * @param  array<int, int>  $ioIds  optional restriction to a candidate set.
     * @return array<int, bool>  [ioId => true]
     */
    public function queuedIds(array $ioIds = []): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $q = DB::table('ahg_capture_queue');
            if (! empty($ioIds)) {
                $q->whereIn('information_object_id', array_map('intval', $ioIds));
            }

            return $q->pluck('information_object_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
