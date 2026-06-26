<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RDM compliance scoreboard (#1342) - the RDM librarian's defensibility view:
 * which datasets are deposited, their POPIA-flag verdict, finding counts,
 * access disposition, DOI, and project/faculty linkage. A status row only, not
 * a BI suite. Read-only aggregation over the ahg-rdm tables.
 */
class ComplianceReportService
{
    /**
     * @param  array{institution?:string, verdict?:string, disposition?:string}  $filters
     */
    public function rows(array $filters = [])
    {
        // DMP linkage (#1337 Feature 1) is shown only when both the rdm_dataset
        // column and the ahg-research DMP table are present, so the scoreboard
        // degrades gracefully on installs without the DMP slice.
        $hasDmp = Schema::hasColumn('rdm_dataset', 'dmp_id') && Schema::hasTable('research_dmp');

        $q = DB::table('rdm_dataset as d')
            ->leftJoin('research_project as p', 'p.id', '=', 'd.project_id')
            ->leftJoin('rdm_scan_finding as f', 'f.dataset_id', '=', 'd.id');

        $select = [
            'd.id', 'd.title', 'd.verdict', 'd.disposition', 'd.status', 'd.doi', 'd.created_at',
            'p.title as project_title', 'p.institution',
            DB::raw("COUNT(f.id) AS findings"),
            DB::raw("SUM(CASE WHEN f.review_status = 'pending' THEN 1 ELSE 0 END) AS pending"),
            DB::raw("SUM(CASE WHEN f.review_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed"),
        ];
        $group = ['d.id', 'd.title', 'd.verdict', 'd.disposition', 'd.status', 'd.doi', 'd.created_at', 'p.title', 'p.institution'];

        if ($hasDmp) {
            $q->leftJoin('research_dmp as dmp', 'dmp.id', '=', 'd.dmp_id');
            $select[] = 'd.dmp_id';
            $select[] = 'dmp.title as dmp_title';
            $select[] = 'dmp.status as dmp_status';
            $group[] = 'd.dmp_id';
            $group[] = 'dmp.title';
            $group[] = 'dmp.status';
        }

        $q->select($select)->groupBy($group);

        if (! empty($filters['institution'])) {
            $q->where('p.institution', $filters['institution']);
        }
        if (! empty($filters['verdict'])) {
            $q->where('d.verdict', $filters['verdict']);
        }
        if (! empty($filters['disposition'])) {
            $q->where('d.disposition', $filters['disposition']);
        }

        return $q->orderByDesc('d.id')->get();
    }

    /** Distinct faculties/institutions for the filter dropdown. */
    public function institutions()
    {
        return DB::table('research_project')
            ->whereNotNull('institution')->where('institution', '<>', '')
            ->distinct()->orderBy('institution')->pluck('institution');
    }

    /** Headline counts for the scoreboard summary strip. */
    public function summary(array $filters = []): array
    {
        $rows = $this->rows($filters);

        return [
            'total'      => $rows->count(),
            'flagged'    => $rows->whereIn('verdict', ['PERSONAL', 'SPECIAL_CATEGORY'])->count(),
            'restricted' => $rows->whereIn('disposition', ['restrict', 'embargo', 'de-identify'])->count(),
            'open'       => $rows->where('status', 'published')->count(),
            'unreviewed' => $rows->where('pending', '>', 0)->count(),
            'dmp_linked' => $rows->whereNotNull('dmp_id')->count(),
        ];
    }
}
