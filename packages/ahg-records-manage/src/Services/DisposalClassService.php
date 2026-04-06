<?php

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DisposalClassService
{
    /**
     * Create a new disposal class within a schedule.
     */
    public function create(int $scheduleId, array $data): int
    {
        $now = Carbon::now();

        return (int) DB::table('rm_disposal_class')->insertGetId([
            'retention_schedule_id'        => $scheduleId,
            'class_ref'                    => $data['class_ref'],
            'title'                        => $data['title'],
            'description'                  => $data['description'] ?? null,
            'retention_period_years'       => $data['retention_period_years'] ?? null,
            'retention_period_months'      => $data['retention_period_months'] ?? null,
            'retention_trigger'            => $data['retention_trigger'] ?? 'creation_date',
            'disposal_action'              => $data['disposal_action'],
            'disposal_confirmation_required' => $data['disposal_confirmation_required'] ?? 1,
            'review_required'              => $data['review_required'] ?? 1,
            'citation'                     => $data['citation'] ?? null,
            'is_active'                    => $data['is_active'] ?? 1,
            'sort_order'                   => $data['sort_order'] ?? 0,
            'created_by'                   => $data['created_by'],
            'created_at'                   => $now,
            'updated_at'                   => $now,
        ]);
    }

    /**
     * Update an existing disposal class.
     */
    public function update(int $id, array $data): bool
    {
        $update = [
            'updated_at' => Carbon::now(),
        ];

        $fields = ['class_ref', 'title', 'description', 'retention_period_years',
                    'retention_period_months', 'retention_trigger', 'disposal_action',
                    'disposal_confirmation_required', 'review_required', 'citation',
                    'is_active', 'sort_order'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        $affected = DB::table('rm_disposal_class')
            ->where('id', $id)
            ->update($update);

        return $affected > 0;
    }

    /**
     * Get a disposal class by ID with schedule info and record count.
     */
    public function getById(int $id): ?object
    {
        $class = DB::table('rm_disposal_class as dc')
            ->leftJoin('rm_retention_schedule as rs', 'dc.retention_schedule_id', '=', 'rs.id')
            ->where('dc.id', $id)
            ->select([
                'dc.*',
                'rs.schedule_ref as schedule_ref',
                'rs.title as schedule_title',
                'rs.status as schedule_status',
            ])
            ->first();

        if (!$class) {
            return null;
        }

        $class->record_count = DB::table('rm_record_disposal_class')
            ->where('disposal_class_id', $id)
            ->count();

        return $class;
    }

    /**
     * Get all disposal classes for a schedule.
     */
    public function getBySchedule(int $scheduleId): array
    {
        return DB::table('rm_disposal_class as dc')
            ->leftJoin(DB::raw('(SELECT disposal_class_id, COUNT(*) as record_count FROM rm_record_disposal_class GROUP BY disposal_class_id) as rdc_counts'), 'dc.id', '=', 'rdc_counts.disposal_class_id')
            ->where('dc.retention_schedule_id', $scheduleId)
            ->select([
                'dc.*',
                DB::raw('COALESCE(rdc_counts.record_count, 0) as record_count'),
            ])
            ->orderBy('dc.sort_order')
            ->orderBy('dc.class_ref')
            ->get()
            ->toArray();
    }

    /**
     * Assign a disposal class to an information object.
     */
    public function assignToRecord(int $ioId, int $classId, int $userId, ?string $startDate = null): int
    {
        // Remove existing assignment for this IO
        DB::table('rm_record_disposal_class')
            ->where('information_object_id', $ioId)
            ->delete();

        $disposalDate = $this->calculateDisposalDate($classId, $startDate);

        $now = Carbon::now();

        return (int) DB::table('rm_record_disposal_class')->insertGetId([
            'information_object_id'   => $ioId,
            'disposal_class_id'       => $classId,
            'assigned_by'             => $userId,
            'assigned_at'             => $now,
            'retention_start_date'    => $startDate,
            'calculated_disposal_date' => $disposalDate,
            'override_disposal_date'  => null,
            'override_reason'         => null,
        ]);
    }

    /**
     * Unassign disposal class from an information object.
     */
    public function unassignFromRecord(int $ioId): bool
    {
        $affected = DB::table('rm_record_disposal_class')
            ->where('information_object_id', $ioId)
            ->delete();

        return $affected > 0;
    }

    /**
     * Get paginated records assigned to a disposal class.
     */
    public function getRecordsByClass(int $classId, int $page = 1, int $perPage = 25): array
    {
        $culture = app()->getLocale();

        $query = DB::table('rm_record_disposal_class as rdc')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('rdc.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->where('rdc.disposal_class_id', $classId);

        $total = $query->count();

        $data = (clone $query)
            ->select([
                'rdc.*',
                'io_i18n.title as io_title',
            ])
            ->orderBy('rdc.assigned_at', 'desc')
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
     * Calculate the disposal date based on retention period and trigger type.
     */
    public function calculateDisposalDate(int $classId, ?string $startDate = null): ?string
    {
        $class = DB::table('rm_disposal_class')->where('id', $classId)->first();
        if (!$class) {
            return null;
        }

        if (!$class->retention_period_years && !$class->retention_period_months) {
            return null;
        }

        $base = $startDate ? Carbon::parse($startDate) : Carbon::now();

        if ($class->retention_period_years) {
            $base->addYears($class->retention_period_years);
        }
        if ($class->retention_period_months) {
            $base->addMonths($class->retention_period_months);
        }

        return $base->toDateString();
    }

    /**
     * Delete a disposal class (only if no records assigned).
     */
    public function delete(int $id): bool
    {
        $recordCount = DB::table('rm_record_disposal_class')
            ->where('disposal_class_id', $id)
            ->count();

        if ($recordCount > 0) {
            return false;
        }

        DB::table('rm_disposal_class')->where('id', $id)->delete();

        return true;
    }

    /**
     * Get the assigned disposal class for an information object.
     */
    public function getAssignmentForRecord(int $ioId): ?object
    {
        return DB::table('rm_record_disposal_class as rdc')
            ->leftJoin('rm_disposal_class as dc', 'rdc.disposal_class_id', '=', 'dc.id')
            ->leftJoin('rm_retention_schedule as rs', 'dc.retention_schedule_id', '=', 'rs.id')
            ->where('rdc.information_object_id', $ioId)
            ->select([
                'rdc.*',
                'dc.class_ref',
                'dc.title as class_title',
                'dc.disposal_action',
                'dc.retention_period_years',
                'dc.retention_period_months',
                'rs.title as schedule_title',
                'rs.schedule_ref',
            ])
            ->first();
    }

    /**
     * Get all active disposal classes for a dropdown.
     */
    public function getActiveClasses(): array
    {
        return DB::table('rm_disposal_class as dc')
            ->join('rm_retention_schedule as rs', 'dc.retention_schedule_id', '=', 'rs.id')
            ->where('dc.is_active', 1)
            ->where('rs.status', 'active')
            ->select([
                'dc.id',
                'dc.class_ref',
                'dc.title',
                'dc.disposal_action',
                'rs.title as schedule_title',
            ])
            ->orderBy('rs.title')
            ->orderBy('dc.sort_order')
            ->orderBy('dc.class_ref')
            ->get()
            ->toArray();
    }
}
