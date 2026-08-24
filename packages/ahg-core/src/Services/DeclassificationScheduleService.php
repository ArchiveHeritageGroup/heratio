<?php

/**
 * DeclassificationScheduleService - the one reader of the declassification
 * schedule.
 *
 * `object_declassification_schedule` records that an object's classification
 * is to be downgraded on a given date. Two packages need to read it and
 * neither can reach the other: ahg-acl and ahg-security-clearance both depend
 * on ahg-core and not on each other. Without a shared home this query becomes
 * two copies that drift - the loan-rule lookup in ahg-library reached FOUR
 * copies that way, one of which quoted patrons the wrong fine rate.
 *
 * NOTE there is also an EMPTY `security_declassification_schedule` table with
 * a near-identical shape and no reader or writer anywhere in the codebase.
 * `object_declassification_schedule` is the one the application uses; do not
 * "fix" a query onto the other one.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeclassificationScheduleService
{
    private const TABLE = 'object_declassification_schedule';

    /**
     * Declassifications that have come due: unprocessed and scheduled on or
     * before the horizon (default 30 days out, which is what the security
     * dashboard's "reviews due" counter has always meant).
     */
    public function due(int $limit = 10, int $horizonDays = 30): \Illuminate\Support\Collection
    {
        return $this->query(
            fn ($q) => $q->where('ods.scheduled_date', '<=', now()->addDays($horizonDays)),
            $limit
        );
    }

    /**
     * Declassifications still in the future - beyond the due horizon, so the
     * two lists never show the same row twice.
     */
    public function scheduled(int $limit = 100, int $horizonDays = 30): \Illuminate\Support\Collection
    {
        return $this->query(
            fn ($q) => $q->where('ods.scheduled_date', '>', now()->addDays($horizonDays)),
            $limit
        );
    }

    /**
     * Count of due declassifications, without building the rows.
     */
    public function dueCount(int $horizonDays = 30): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        try {
            return DB::table(self::TABLE)
                ->where('scheduled_date', '<=', now()->addDays($horizonDays))
                ->where('processed', 0)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Both classification names are aliased twice: `from_classification` /
     * `to_classification` for the due table and `from_name` / `to_name` for
     * the scheduled table, because the two views were written against
     * different names and renaming either one would break the other.
     */
    private function query(callable $dateFilter, int $limit): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable(self::TABLE)) {
            return collect();
        }

        try {
            $q = DB::table(self::TABLE.' as ods')
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('ods.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->leftJoin('information_object as io', 'ods.object_id', '=', 'io.id')
                ->leftJoin('security_classification as sc_from', 'ods.from_classification_id', '=', 'sc_from.id')
                ->leftJoin('security_classification as sc_to', 'ods.to_classification_id', '=', 'sc_to.id')
                ->where('ods.processed', 0);

            $dateFilter($q);

            return $q->select(
                'ods.*',
                'ioi.title',
                'io.identifier',
                'sc_from.name as from_classification',
                'sc_to.name as to_classification',
                'sc_from.name as from_name',
                'sc_to.name as to_name'
            )
                ->orderBy('ods.scheduled_date')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }
}
