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
 * RDM dashboard (#1337 Feature 3) - the RDM unit's at-a-glance operational +
 * strategic view: headline KPIs, POPIA/disposition/DMP breakdowns, finding
 * composition, deposit trend, per-faculty posture, and the human-gate backlog.
 *
 * Read-only live aggregation over the ahg-rdm tables (+ research_project for
 * faculty). The compliance scoreboard (#1342) stays as the per-dataset drill
 * down; this is the roll-up above it. Everything is guarded so the dashboard
 * renders on installs without the DMP slice.
 *
 * Filters (#1345): from / to (deposit date) + institution. They resolve to a
 * single set of matching dataset ids that scopes every aggregate, so the filter
 * logic lives in one place. No filter => null id-set => unscoped (full view).
 */
class DashboardService
{
    /**
     * @param  array{from?:string, to?:string, institution?:string}  $filters
     * @return array<string,mixed>
     */
    public function overview(array $filters = []): array
    {
        $hasDmp = Schema::hasColumn('rdm_dataset', 'dmp_id');
        $ids    = $this->filteredDatasetIds($filters); // null = no filter active

        // Scopers: constrain a query to the filtered dataset set (no-op when null).
        $ds   = fn ($q) => $ids === null ? $q : $q->whereIn('id', $ids);          // rdm_dataset (alias-less)
        $dsA  = fn ($q) => $ids === null ? $q : $q->whereIn('d.id', $ids);        // rdm_dataset as d
        $find = fn ($q) => $ids === null ? $q : $q->whereIn('dataset_id', $ids);  // rdm_scan_finding

        $datasets   = (int) $ds(DB::table('rdm_dataset'))->count();
        $files      = (int) DB::table('rdm_dataset_file')->when($ids !== null, fn ($q) => $q->whereIn('dataset_id', $ids))->count();
        $flagged    = (int) $ds(DB::table('rdm_dataset'))->whereIn('verdict', ['PERSONAL', 'SPECIAL_CATEGORY'])->count();
        $restricted = (int) $ds(DB::table('rdm_dataset'))->whereIn('disposition', ['restrict', 'embargo', 'de-identify'])->count();
        $open       = (int) $ds(DB::table('rdm_dataset'))->where(function ($q) {
            $q->where('status', 'published')->orWhere('disposition', 'release');
        })->count();
        $dois       = (int) $ds(DB::table('rdm_dataset'))->whereNotNull('doi')->where('doi', '<>', '')->count();
        $dmpLinked  = $hasDmp ? (int) $ds(DB::table('rdm_dataset'))->whereNotNull('dmp_id')->count() : 0;
        $backlog    = (int) $find(DB::table('rdm_scan_finding'))->where('review_status', 'pending')->distinct()->count('dataset_id');

        // POPIA verdict mix (unscanned = the remainder).
        $v = $ds(DB::table('rdm_dataset'))->select('verdict', DB::raw('COUNT(*) c'))->groupBy('verdict')->pluck('c', 'verdict');
        $verdict = [
            'CLEAR'            => (int) ($v['CLEAR'] ?? 0),
            'PERSONAL'         => (int) ($v['PERSONAL'] ?? 0),
            'SPECIAL_CATEGORY' => (int) ($v['SPECIAL_CATEGORY'] ?? 0),
        ];
        $verdict['unscanned'] = max(0, $datasets - array_sum($verdict));

        // Access disposition mix (undecided = the remainder).
        $dz = $ds(DB::table('rdm_dataset'))->select('disposition', DB::raw('COUNT(*) c'))->groupBy('disposition')->pluck('c', 'disposition');
        $disposition = [
            'restrict'    => (int) ($dz['restrict'] ?? 0),
            'embargo'     => (int) ($dz['embargo'] ?? 0),
            'de-identify' => (int) ($dz['de-identify'] ?? 0),
            'release'     => (int) ($dz['release'] ?? 0),
        ];
        $disposition['undecided'] = max(0, $datasets - array_sum($disposition));

        // Finding composition - by PII type and by detection method (the AI-vs-rule split).
        $byType = $find(DB::table('rdm_scan_finding'))->select('type', DB::raw('COUNT(*) c'))
            ->groupBy('type')->orderByDesc('c')->pluck('c', 'type')->all();
        $byMethod = $find(DB::table('rdm_scan_finding'))->select('method', DB::raw('COUNT(*) c'))
            ->groupBy('method')->pluck('c', 'method')->all();

        return [
            'kpi' => [
                'datasets'   => $datasets,
                'files'      => $files,
                'flagged'    => $flagged,
                'backlog'    => $backlog,
                'restricted' => $restricted,
                'open'       => $open,
                'dois'       => $dois,
                'dmp_linked' => $dmpLinked,
                'dmp_pct'    => $datasets > 0 ? (int) round($dmpLinked / $datasets * 100) : 0,
            ],
            'has_dmp'         => $hasDmp,
            'verdict'         => $verdict,
            'disposition'     => $disposition,
            'findings_by_type'   => $byType,
            'findings_by_method' => $byMethod,
            'deposits_by_month'  => $this->depositsByMonth(trim((string) ($filters['institution'] ?? '')) ?: null),
            'by_institution'     => $this->byInstitution($hasDmp, $dsA),
            'backlog_list'       => $this->backlogList($dsA),
            'recent'             => $this->recent($ds),
        ];
    }

    /**
     * Resolve the active filters to a list of matching dataset ids, or null when
     * no filter is active (so callers leave their queries unscoped).
     *
     * @param  array{from?:string, to?:string, institution?:string}  $filters
     */
    private function filteredDatasetIds(array $filters): ?array
    {
        $institution = trim((string) ($filters['institution'] ?? ''));
        $from        = trim((string) ($filters['from'] ?? ''));
        $to          = trim((string) ($filters['to'] ?? ''));

        if ($institution === '' && $from === '' && $to === '') {
            return null;
        }

        $q = DB::table('rdm_dataset as d');
        if ($institution !== '') {
            $q->join('research_project as p', 'p.id', '=', 'd.project_id')->where('p.institution', $institution);
        }
        if ($from !== '') {
            $q->whereDate('d.created_at', '>=', $from);
        }
        if ($to !== '') {
            $q->whereDate('d.created_at', '<=', $to);
        }

        // Empty array (no matches) is intentional: scopes everything to zero rows.
        return $q->pluck('d.id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Deposits per month for the last 12 months, zero-filled. Honours the
     * institution filter only - the rolling year is shown regardless of any
     * date-range filter (it is trend context, not a filtered count).
     */
    private function depositsByMonth(?string $institution): array
    {
        $q = DB::table('rdm_dataset as d');
        if ($institution !== null && $institution !== '') {
            $q->join('research_project as p', 'p.id', '=', 'd.project_id')->where('p.institution', $institution);
        }
        $raw = $q
            ->select(DB::raw("DATE_FORMAT(d.created_at, '%Y-%m') ym"), DB::raw('COUNT(*) c'))
            ->where('d.created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')->pluck('c', 'ym');

        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->subMonths($i)->format('Y-m');
            $out[] = ['label' => $m, 'count' => (int) ($raw[$m] ?? 0)];
        }

        return $out;
    }

    /** Per-faculty posture: total / POPIA-flagged / DMP-linked. */
    private function byInstitution(bool $hasDmp, callable $scope): array
    {
        $dmpExpr = $hasDmp ? 'SUM(CASE WHEN d.dmp_id IS NOT NULL THEN 1 ELSE 0 END)' : '0';

        return $scope(DB::table('rdm_dataset as d'))
            ->leftJoin('research_project as p', 'p.id', '=', 'd.project_id')
            ->select(
                DB::raw("COALESCE(NULLIF(p.institution, ''), '(unlinked)') AS institution"),
                DB::raw('COUNT(*) AS total'),
                DB::raw("SUM(CASE WHEN d.verdict IN ('PERSONAL','SPECIAL_CATEGORY') THEN 1 ELSE 0 END) AS flagged"),
                DB::raw("{$dmpExpr} AS dmp")
            )
            ->groupBy('institution')
            ->orderByDesc('total')
            ->get()->all();
    }

    /** Datasets with unresolved findings - the human-gate backlog (top 10). */
    private function backlogList(callable $scope): array
    {
        return $scope(DB::table('rdm_dataset as d'))
            ->join('rdm_scan_finding as f', 'f.dataset_id', '=', 'd.id')
            ->where('f.review_status', 'pending')
            ->select('d.id', 'd.title', 'd.verdict', DB::raw('COUNT(f.id) AS pending'))
            ->groupBy('d.id', 'd.title', 'd.verdict')
            ->orderByDesc('pending')->limit(10)->get()->all();
    }

    /** Most recent deposits. */
    private function recent(callable $scope): array
    {
        return $scope(DB::table('rdm_dataset'))
            ->select('id', 'title', 'status', 'verdict', 'disposition', 'doi', 'created_at')
            ->orderByDesc('id')->limit(8)->get()->all();
    }
}
