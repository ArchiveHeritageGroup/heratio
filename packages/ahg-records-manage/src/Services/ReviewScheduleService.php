<?php

/**
 * ReviewScheduleService — periodic review queue for the RM module (Phase 2.4).
 *
 * A review is a check-in scheduled on a record before its disposal date — "look
 * again before destroying / transferring". A disposal class with review_required=1
 * spawns a review_schedule row when the record is assigned to that class. When the
 * reviewer completes the check, they pick a decision: extend retention, schedule
 * another review, trigger disposal, or transfer to archives.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewScheduleService
{
    /**
     * List reviews matching filters. Default: pending + overdue first.
     *
     * @param array $filters status, due_before, due_after, assigned_to, ioId, q
     * @return array{rows: array, total: int}
     */
    public function listQueue(array $filters = []): array
    {
        $q = DB::table('rm_review_schedule as r')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'r.information_object_id')->where('ioi.culture', '=', app()->getLocale());
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'r.information_object_id')
            ->leftJoin('rm_disposal_class as dc', 'dc.id', '=', 'r.disposal_class_id')
            ->select(
                'r.id', 'r.information_object_id', 'r.disposal_class_id', 'r.review_type',
                'r.review_due_date', 'r.review_completed_date', 'r.status', 'r.assigned_to',
                'r.decision', 'r.decision_notes', 'r.next_review_due_date',
                'r.created_at', 'r.updated_at',
                'ioi.title as record_title',
                'slug.slug as record_slug',
                'dc.title as disposal_class_title',
                'dc.class_ref as disposal_class_ref'
            );

        if (! empty($filters['status'])) {
            $q->where('r.status', $filters['status']);
        }
        if (! empty($filters['due_before'])) {
            $q->where('r.review_due_date', '<=', $filters['due_before']);
        }
        if (! empty($filters['due_after'])) {
            $q->where('r.review_due_date', '>=', $filters['due_after']);
        }
        if (! empty($filters['assigned_to'])) {
            $q->where('r.assigned_to', $filters['assigned_to']);
        }
        if (! empty($filters['ioId'])) {
            $q->where('r.information_object_id', $filters['ioId']);
        }
        if (! empty($filters['q'])) {
            $q->where('ioi.title', 'like', '%' . $filters['q'] . '%');
        }

        $total = (clone $q)->count();
        $rows  = $q->orderByRaw("FIELD(r.status, 'pending') DESC, r.review_due_date ASC")
            ->limit($filters['limit'] ?? 100)
            ->offset($filters['offset'] ?? 0)
            ->get()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Fetch a single review with its joined record and disposal class detail.
     */
    public function get(int $id): ?object
    {
        return DB::table('rm_review_schedule as r')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'r.information_object_id')->where('ioi.culture', '=', app()->getLocale());
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'r.information_object_id')
            ->leftJoin('rm_disposal_class as dc', 'dc.id', '=', 'r.disposal_class_id')
            ->select(
                'r.*',
                'ioi.title as record_title',
                'slug.slug as record_slug',
                'dc.title as disposal_class_title',
                'dc.class_ref as disposal_class_ref',
                'dc.disposal_action as disposal_class_action',
                'dc.retention_period_years as disposal_class_years'
            )
            ->where('r.id', $id)
            ->first();
    }

    /**
     * Schedule a new review.
     *
     * @return int new review ID
     */
    public function schedule(array $data, int $userId): int
    {
        return DB::table('rm_review_schedule')->insertGetId([
            'information_object_id' => $data['information_object_id'],
            'disposal_class_id'     => $data['disposal_class_id'] ?? null,
            'review_type'           => $data['review_type'] ?? 'periodic',
            'review_due_date'       => $data['review_due_date'],
            'status'                => 'pending',
            'assigned_to'           => $data['assigned_to'] ?? null,
            'created_by'            => $userId,
        ]);
    }

    /**
     * Complete a review with a decision.
     *
     * Decisions:
     *   - retain_extend / retain_review / no_change → leaves the record retained, may set next_review_due_date
     *   - dispose                                   → also creates an rm_disposal_action row (recorded as triggered_disposal_action_id)
     *   - transfer                                  → creates a transfer-action rm_disposal_action row
     */
    public function complete(int $id, array $data, int $userId): bool
    {
        $review = $this->get($id);
        if (! $review || $review->status === 'completed') {
            return false;
        }

        $update = [
            'status'                => 'completed',
            'decision'              => $data['decision'],
            'decision_notes'        => $data['decision_notes'] ?? null,
            'review_completed_date' => now()->toDateString(),
            'next_review_due_date'  => $data['next_review_due_date'] ?? null,
        ];

        $disposalActionId = null;
        if (in_array($data['decision'], ['dispose', 'transfer'], true)) {
            $disposalActionId = DB::table('rm_disposal_action')->insertGetId([
                'information_object_id' => $review->information_object_id,
                'disposal_class_id'     => $review->disposal_class_id,
                'action_type'           => $data['decision'] === 'transfer' ? 'transfer_archives' : 'destroy',
                'status'                => 'pending',
                'reason'                => 'Triggered by review #' . $id . ' decision: ' . $data['decision'],
                'initiated_by'          => $userId,
            ]);
            $update['triggered_disposal_action_id'] = $disposalActionId;
        }

        DB::table('rm_review_schedule')->where('id', $id)->update($update);

        Log::info('rm: review completed', [
            'review_id'   => $id,
            'decision'    => $data['decision'],
            'disposal_id' => $disposalActionId,
            'user_id'     => $userId,
        ]);

        return true;
    }

    /**
     * Assign a reviewer.
     */
    public function assign(int $id, int $userId): bool
    {
        return DB::table('rm_review_schedule')->where('id', $id)->update([
            'assigned_to' => $userId,
        ]) > 0;
    }

    /**
     * Quick stats for the dashboard card.
     */
    public function counts(): array
    {
        $today = now()->toDateString();
        $base  = DB::table('rm_review_schedule');
        return [
            'pending'    => (clone $base)->where('status', 'pending')->count(),
            'overdue'    => (clone $base)->where('status', 'pending')->where('review_due_date', '<=', $today)->count(),
            'due_30d'    => (clone $base)->where('status', 'pending')
                              ->whereBetween('review_due_date', [$today, now()->addDays(30)->toDateString()])
                              ->count(),
            'completed'  => (clone $base)->where('status', 'completed')->count(),
        ];
    }
}
