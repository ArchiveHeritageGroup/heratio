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
 */
class DashboardService
{
    /** @return array<string,mixed> */
    public function overview(): array
    {
        $hasDmp = Schema::hasColumn('rdm_dataset', 'dmp_id');

        $datasets   = (int) DB::table('rdm_dataset')->count();
        $files      = (int) DB::table('rdm_dataset_file')->count();
        $flagged    = (int) DB::table('rdm_dataset')->whereIn('verdict', ['PERSONAL', 'SPECIAL_CATEGORY'])->count();
        $restricted = (int) DB::table('rdm_dataset')->whereIn('disposition', ['restrict', 'embargo', 'de-identify'])->count();
        $open       = (int) DB::table('rdm_dataset')->where(function ($q) {
            $q->where('status', 'published')->orWhere('disposition', 'release');
        })->count();
        $dois       = (int) DB::table('rdm_dataset')->whereNotNull('doi')->where('doi', '<>', '')->count();
        $dmpLinked  = $hasDmp ? (int) DB::table('rdm_dataset')->whereNotNull('dmp_id')->count() : 0;
        $backlog    = (int) DB::table('rdm_scan_finding')->where('review_status', 'pending')->distinct()->count('dataset_id');

        // POPIA verdict mix (unscanned = the remainder).
        $v = DB::table('rdm_dataset')->select('verdict', DB::raw('COUNT(*) c'))->groupBy('verdict')->pluck('c', 'verdict');
        $verdict = [
            'CLEAR'            => (int) ($v['CLEAR'] ?? 0),
            'PERSONAL'         => (int) ($v['PERSONAL'] ?? 0),
            'SPECIAL_CATEGORY' => (int) ($v['SPECIAL_CATEGORY'] ?? 0),
        ];
        $verdict['unscanned'] = max(0, $datasets - array_sum($verdict));

        // Access disposition mix (undecided = the remainder).
        $dz = DB::table('rdm_dataset')->select('disposition', DB::raw('COUNT(*) c'))->groupBy('disposition')->pluck('c', 'disposition');
        $disposition = [
            'restrict'    => (int) ($dz['restrict'] ?? 0),
            'embargo'     => (int) ($dz['embargo'] ?? 0),
            'de-identify' => (int) ($dz['de-identify'] ?? 0),
            'release'     => (int) ($dz['release'] ?? 0),
        ];
        $disposition['undecided'] = max(0, $datasets - array_sum($disposition));

        // Finding composition - by PII type and by detection method (the AI-vs-rule split).
        $byType = DB::table('rdm_scan_finding')->select('type', DB::raw('COUNT(*) c'))
            ->groupBy('type')->orderByDesc('c')->pluck('c', 'type')->all();
        $byMethod = DB::table('rdm_scan_finding')->select('method', DB::raw('COUNT(*) c'))
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
            'deposits_by_month'  => $this->depositsByMonth(),
            'by_institution'     => $this->byInstitution($hasDmp),
            'backlog_list'       => $this->backlogList(),
            'recent'             => $this->recent(),
        ];
    }

    /** Deposits per month for the last 12 months, zero-filled. */
    private function depositsByMonth(): array
    {
        $raw = DB::table('rdm_dataset')
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') ym"), DB::raw('COUNT(*) c'))
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')->pluck('c', 'ym');

        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->subMonths($i)->format('Y-m');
            $out[] = ['label' => $m, 'count' => (int) ($raw[$m] ?? 0)];
        }

        return $out;
    }

    /** Per-faculty posture: total / POPIA-flagged / DMP-linked. */
    private function byInstitution(bool $hasDmp): array
    {
        $dmpExpr = $hasDmp ? 'SUM(CASE WHEN d.dmp_id IS NOT NULL THEN 1 ELSE 0 END)' : '0';

        return DB::table('rdm_dataset as d')
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
    private function backlogList(): array
    {
        return DB::table('rdm_dataset as d')
            ->join('rdm_scan_finding as f', 'f.dataset_id', '=', 'd.id')
            ->where('f.review_status', 'pending')
            ->select('d.id', 'd.title', 'd.verdict', DB::raw('COUNT(f.id) AS pending'))
            ->groupBy('d.id', 'd.title', 'd.verdict')
            ->orderByDesc('pending')->limit(10)->get()->all();
    }

    /** Most recent deposits. */
    private function recent(): array
    {
        return DB::table('rdm_dataset')
            ->select('id', 'title', 'status', 'verdict', 'disposition', 'doi', 'created_at')
            ->orderByDesc('id')->limit(8)->get()->all();
    }
}
