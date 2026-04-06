<?php

namespace AhgIntegrity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class RetentionService
{
    /**
     * Scan for eligible records based on retention policies.
     * Inserts newly eligible IOs into integrity_disposition_queue.
     */
    public function scanEligible(?int $policyId = null): int
    {
        if (!Schema::hasTable('integrity_retention_policy') || !Schema::hasTable('integrity_disposition_queue')) {
            return 0;
        }

        $query = DB::table('integrity_retention_policy')->where('is_enabled', 1);
        if ($policyId) {
            $query->where('id', $policyId);
        }
        $policies = $query->get();

        $newCount = 0;

        foreach ($policies as $policy) {
            $eligibleIoIds = [];

            if ($policy->trigger_type === 'ingest_date') {
                // Objects where created_at + retention_period_days <= NOW()
                $cutoff = Carbon::now()->subDays($policy->retention_period_days);

                $ioQuery = DB::table('information_object')
                    ->where('created_at', '<=', $cutoff)
                    ->select('information_object.id');

                // Scope filtering
                if ($policy->repository_id) {
                    $ioQuery->where('information_object.repository_id', $policy->repository_id);
                }
                if ($policy->information_object_id) {
                    $ioQuery->where('information_object.id', $policy->information_object_id);
                }

                $eligibleIoIds = $ioQuery->pluck('id')->toArray();

            } elseif (str_starts_with($policy->trigger_type, 'event')) {
                // Check retention_trigger_event for matching event_type
                if (!Schema::hasTable('retention_trigger_event')) {
                    continue;
                }

                $eventType = str_replace('event:', '', $policy->trigger_type);
                $cutoff = Carbon::now()->subDays($policy->retention_period_days);

                $ioQuery = DB::table('retention_trigger_event')
                    ->where('event_type', $eventType)
                    ->where('event_date', '<=', $cutoff)
                    ->select('information_object_id');

                // Scope filtering
                if ($policy->repository_id) {
                    $ioQuery->join('information_object', 'retention_trigger_event.information_object_id', '=', 'information_object.id')
                        ->where('information_object.repository_id', $policy->repository_id);
                }
                if ($policy->information_object_id) {
                    $ioQuery->where('retention_trigger_event.information_object_id', $policy->information_object_id);
                }

                $eligibleIoIds = $ioQuery->pluck('information_object_id')->toArray();
            }

            if (empty($eligibleIoIds)) {
                continue;
            }

            // Exclude objects with active legal holds
            if (Schema::hasTable('integrity_legal_hold')) {
                $heldIds = DB::table('integrity_legal_hold')
                    ->whereIn('information_object_id', $eligibleIoIds)
                    ->where('status', 'active')
                    ->pluck('information_object_id')
                    ->toArray();

                $eligibleIoIds = array_diff($eligibleIoIds, $heldIds);
            }

            if (empty($eligibleIoIds)) {
                continue;
            }

            // Exclude objects already in the disposition queue
            $existingIds = DB::table('integrity_disposition_queue')
                ->where('policy_id', $policy->id)
                ->whereIn('information_object_id', $eligibleIoIds)
                ->pluck('information_object_id')
                ->toArray();

            $newIds = array_diff($eligibleIoIds, $existingIds);

            foreach ($newIds as $ioId) {
                DB::table('integrity_disposition_queue')->insert([
                    'policy_id' => $policy->id,
                    'information_object_id' => $ioId,
                    'status' => 'eligible',
                    'eligible_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                $newCount++;
            }
        }

        return $newCount;
    }

    /**
     * Fire a retention trigger event for an information object.
     */
    public function fireRetentionEvent(int $ioId, string $eventType, int $userId, ?string $notes = null): void
    {
        if (!Schema::hasTable('retention_trigger_event')) {
            return;
        }

        DB::table('retention_trigger_event')->insert([
            'information_object_id' => $ioId,
            'event_type' => $eventType,
            'event_date' => Carbon::now(),
            'triggered_by' => (string) $userId,
            'notes' => $notes,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Get paginated eligible records from the disposition queue.
     */
    public function getEligibleRecords(?int $policyId, int $page = 1, int $perPage = 25): array
    {
        if (!Schema::hasTable('integrity_disposition_queue')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $culture = app()->getLocale();

        $query = DB::table('integrity_disposition_queue as dq')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('dq.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('integrity_retention_policy as rp', 'dq.policy_id', '=', 'rp.id');

        if ($policyId) {
            $query->where('dq.policy_id', $policyId);
        }

        $total = $query->count();

        $data = $query->select([
                'dq.*',
                'io_i18n.title as io_title',
                'rp.name as policy_name',
            ])
            ->orderBy('dq.eligible_at', 'desc')
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
     * Get all retention policies.
     */
    public function getRetentionPolicies(): array
    {
        if (!Schema::hasTable('integrity_retention_policy')) {
            return [];
        }

        return DB::table('integrity_retention_policy')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get distinct event types from retention_trigger_event.
     */
    public function getEventTypes(): array
    {
        if (!Schema::hasTable('retention_trigger_event')) {
            return [];
        }

        return DB::table('retention_trigger_event')
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type')
            ->toArray();
    }

    /**
     * Get all retention trigger events, paginated.
     */
    public function getRetentionEvents(int $page = 1, int $perPage = 50): array
    {
        if (!Schema::hasTable('retention_trigger_event')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $culture = app()->getLocale();

        $query = DB::table('retention_trigger_event as rte')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('rte.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            });

        $total = $query->count();

        $data = $query->select([
                'rte.*',
                'io_i18n.title as io_title',
            ])
            ->orderBy('rte.event_date', 'desc')
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
}
