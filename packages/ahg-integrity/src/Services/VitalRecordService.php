<?php

namespace AhgIntegrity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class VitalRecordService
{
    /**
     * Flag an information object as a vital record.
     *
     * @return int The vital_record ID
     */
    public function flagAsVital(int $ioId, string $reason, int $reviewCycleDays, int $userId): int
    {
        $now = Carbon::now();
        $nextReview = $now->copy()->addDays($reviewCycleDays)->toDateString();

        // Check if already flagged and active
        if (Schema::hasTable('vital_record')) {
            $existing = DB::table('vital_record')
                ->where('information_object_id', $ioId)
                ->where('is_active', 1)
                ->first();

            if ($existing) {
                // Update existing record
                DB::table('vital_record')
                    ->where('id', $existing->id)
                    ->update([
                        'reason' => $reason,
                        'review_cycle_days' => $reviewCycleDays,
                        'next_review_date' => $nextReview,
                        'updated_at' => $now,
                    ]);
                return (int) $existing->id;
            }
        }

        return (int) DB::table('vital_record')->insertGetId([
            'information_object_id' => $ioId,
            'reason' => $reason,
            'review_cycle_days' => $reviewCycleDays,
            'next_review_date' => $nextReview,
            'is_active' => 1,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Unflag a vital record (set is_active=0).
     */
    public function unflagVital(int $ioId, int $userId): bool
    {
        if (!Schema::hasTable('vital_record')) {
            return false;
        }

        $affected = DB::table('vital_record')
            ->where('information_object_id', $ioId)
            ->where('is_active', 1)
            ->update([
                'is_active' => 0,
                'updated_at' => Carbon::now(),
            ]);

        return $affected > 0;
    }

    /**
     * Check if an IO is flagged as a vital record.
     */
    public function isVital(int $ioId): bool
    {
        if (!Schema::hasTable('vital_record')) {
            return false;
        }

        return DB::table('vital_record')
            ->where('information_object_id', $ioId)
            ->where('is_active', 1)
            ->exists();
    }

    /**
     * Get paginated list of vital records, optionally filtered by repository.
     */
    public function getVitalRecords(?int $repositoryId, int $page = 1, int $perPage = 25): array
    {
        if (!Schema::hasTable('vital_record')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $culture = app()->getLocale();

        $query = DB::table('vital_record as vr')
            ->join('information_object as io', 'vr.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('vr.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->where('vr.is_active', 1);

        if ($repositoryId) {
            $query->where('io.repository_id', $repositoryId);
        }

        $total = $query->count();

        $data = $query->select([
                'vr.*',
                'io_i18n.title as io_title',
                'io.repository_id',
            ])
            ->orderBy('vr.next_review_date', 'asc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Get vital records that are overdue for review.
     */
    public function getOverdueReviews(): array
    {
        if (!Schema::hasTable('vital_record')) {
            return [];
        }

        $culture = app()->getLocale();

        return DB::table('vital_record as vr')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('vr.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->where('vr.is_active', 1)
            ->where('vr.next_review_date', '<=', Carbon::now()->toDateString())
            ->select([
                'vr.*',
                'io_i18n.title as io_title',
            ])
            ->orderBy('vr.next_review_date', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Mark a vital record as reviewed, resetting the review cycle.
     */
    public function reviewVitalRecord(int $vitalRecordId, int $userId): bool
    {
        if (!Schema::hasTable('vital_record')) {
            return false;
        }

        $record = DB::table('vital_record')->where('id', $vitalRecordId)->first();
        if (!$record || !$record->is_active) {
            return false;
        }

        $now = Carbon::now();
        $nextReview = $now->copy()->addDays($record->review_cycle_days)->toDateString();

        $affected = DB::table('vital_record')
            ->where('id', $vitalRecordId)
            ->update([
                'last_reviewed_at' => $now,
                'last_reviewed_by' => (string) $userId,
                'next_review_date' => $nextReview,
                'updated_at' => $now,
            ]);

        return $affected > 0;
    }
}
