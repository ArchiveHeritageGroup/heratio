<?php

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class RetentionScheduleService
{
    /**
     * Create a new retention schedule.
     */
    public function create(array $data): int
    {
        $now = Carbon::now();

        return (int) DB::table('rm_retention_schedule')->insertGetId([
            'schedule_ref'        => $data['schedule_ref'],
            'title'               => $data['title'],
            'description'         => $data['description'] ?? null,
            'authority'           => $data['authority'] ?? null,
            'jurisdiction'        => $data['jurisdiction'] ?? null,
            'effective_date'      => $data['effective_date'] ?? null,
            'review_date'         => $data['review_date'] ?? null,
            'expiry_date'         => $data['expiry_date'] ?? null,
            'status'              => 'draft',
            'version'             => 1,
            'previous_version_id' => null,
            'naz_schedule_id'     => $data['naz_schedule_id'] ?? null,
            'approved_by'         => null,
            'approved_at'         => null,
            'created_by'          => $data['created_by'],
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }

    /**
     * Update an existing retention schedule.
     */
    public function update(int $id, array $data): bool
    {
        $update = [
            'updated_at' => Carbon::now(),
        ];

        $fields = ['schedule_ref', 'title', 'description', 'authority', 'jurisdiction',
                    'effective_date', 'review_date', 'expiry_date', 'naz_schedule_id'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        $affected = DB::table('rm_retention_schedule')
            ->where('id', $id)
            ->update($update);

        return $affected > 0;
    }

    /**
     * Get a retention schedule by ID with disposal class count.
     */
    public function getById(int $id): ?object
    {
        $schedule = DB::table('rm_retention_schedule')
            ->where('id', $id)
            ->first();

        if (!$schedule) {
            return null;
        }

        $schedule->class_count = DB::table('rm_disposal_class')
            ->where('retention_schedule_id', $id)
            ->count();

        return $schedule;
    }

    /**
     * Browse retention schedules with pagination and filters.
     */
    public function browse(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $query = DB::table('rm_retention_schedule as rs')
            ->leftJoin(DB::raw('(SELECT retention_schedule_id, COUNT(*) as class_count FROM rm_disposal_class GROUP BY retention_schedule_id) as dc_counts'), 'rs.id', '=', 'dc_counts.retention_schedule_id')
            ->select([
                'rs.*',
                DB::raw('COALESCE(dc_counts.class_count, 0) as class_count'),
            ]);

        if (!empty($filters['status'])) {
            $query->where('rs.status', $filters['status']);
        }
        if (!empty($filters['jurisdiction'])) {
            $query->where('rs.jurisdiction', $filters['jurisdiction']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('rs.title', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('rs.schedule_ref', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        $total = $query->count();

        $data = (clone $query)
            ->orderBy('rs.created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Approve a draft schedule (set status=active).
     */
    public function approve(int $id, string $approvedBy): bool
    {
        $now = Carbon::now();

        $affected = DB::table('rm_retention_schedule')
            ->where('id', $id)
            ->where('status', 'draft')
            ->update([
                'status'      => 'active',
                'approved_by' => $approvedBy,
                'approved_at' => $now,
                'updated_at'  => $now,
            ]);

        return $affected > 0;
    }

    /**
     * Supersede an old schedule with a new one.
     */
    public function supersede(int $id, int $newId): bool
    {
        $now = Carbon::now();

        DB::table('rm_retention_schedule')
            ->where('id', $newId)
            ->update([
                'previous_version_id' => $id,
                'updated_at'          => $now,
            ]);

        $affected = DB::table('rm_retention_schedule')
            ->where('id', $id)
            ->update([
                'status'     => 'superseded',
                'updated_at' => $now,
            ]);

        return $affected > 0;
    }

    /**
     * Delete a schedule (only if status=draft).
     */
    public function delete(int $id): bool
    {
        $schedule = DB::table('rm_retention_schedule')->where('id', $id)->first();
        if (!$schedule || $schedule->status !== 'draft') {
            return false;
        }

        // Delete associated disposal classes first
        DB::table('rm_disposal_class')->where('retention_schedule_id', $id)->delete();
        DB::table('rm_retention_schedule')->where('id', $id)->delete();

        return true;
    }

    /**
     * Link a retention schedule to a NAZ schedule.
     */
    public function linkToNazSchedule(int $rmScheduleId, int $nazScheduleId): void
    {
        DB::table('rm_retention_schedule')
            ->where('id', $rmScheduleId)
            ->update([
                'naz_schedule_id' => $nazScheduleId,
                'updated_at'      => Carbon::now(),
            ]);
    }

    /**
     * Get schedule statistics: counts by status, total classes.
     */
    public function getScheduleStats(): array
    {
        $statusCounts = DB::table('rm_retention_schedule')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $totalClasses = DB::table('rm_disposal_class')->count();

        return [
            'draft'      => $statusCounts['draft'] ?? 0,
            'active'     => $statusCounts['active'] ?? 0,
            'superseded' => $statusCounts['superseded'] ?? 0,
            'expired'    => $statusCounts['expired'] ?? 0,
            'total_schedules' => array_sum($statusCounts),
            'total_classes'   => $totalClasses,
        ];
    }

    /**
     * Get distinct jurisdictions for filter dropdown.
     */
    public function getJurisdictions(): array
    {
        return DB::table('rm_retention_schedule')
            ->whereNotNull('jurisdiction')
            ->where('jurisdiction', '!=', '')
            ->select('jurisdiction')
            ->distinct()
            ->orderBy('jurisdiction')
            ->pluck('jurisdiction')
            ->toArray();
    }
}
