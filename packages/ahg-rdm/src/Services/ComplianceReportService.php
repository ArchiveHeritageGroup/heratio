<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Services;

use Illuminate\Support\Facades\DB;

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
        $q = DB::table('rdm_dataset as d')
            ->leftJoin('research_project as p', 'p.id', '=', 'd.project_id')
            ->leftJoin('rdm_scan_finding as f', 'f.dataset_id', '=', 'd.id')
            ->select(
                'd.id', 'd.title', 'd.verdict', 'd.disposition', 'd.status', 'd.doi', 'd.created_at',
                'p.title as project_title', 'p.institution',
                DB::raw("COUNT(f.id) AS findings"),
                DB::raw("SUM(CASE WHEN f.review_status = 'pending' THEN 1 ELSE 0 END) AS pending"),
                DB::raw("SUM(CASE WHEN f.review_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed")
            )
            ->groupBy('d.id', 'd.title', 'd.verdict', 'd.disposition', 'd.status', 'd.doi', 'd.created_at', 'p.title', 'p.institution');

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
        ];
    }
}
