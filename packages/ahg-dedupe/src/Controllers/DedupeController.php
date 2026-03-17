<?php

namespace AhgDedupe\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DedupeController extends Controller
{
    /**
     * Check if all required dedupe tables exist.
     */
    private function tablesExist(): bool
    {
        return Schema::hasTable('ahg_duplicate_detection')
            && Schema::hasTable('ahg_duplicate_rule')
            && Schema::hasTable('ahg_dedupe_scan');
    }

    /**
     * Dashboard — stats, top pending, recent scans.
     */
    public function index()
    {
        if (!$this->tablesExist()) {
            return view('ahg-dedupe::not-configured');
        }

        $totalDetected = DB::table('ahg_duplicate_detection')->count();
        $pending       = DB::table('ahg_duplicate_detection')->where('status', 'pending')->count();
        $confirmed     = DB::table('ahg_duplicate_detection')->where('status', 'confirmed')->count();
        $merged        = DB::table('ahg_duplicate_detection')->where('status', 'merged')->count();
        $dismissed     = DB::table('ahg_duplicate_detection')->where('status', 'dismissed')->count();
        $activeRules   = DB::table('ahg_duplicate_rule')->where('is_enabled', 1)->count();

        $culture = app()->getLocale();

        $topPending = DB::table('ahg_duplicate_detection as d')
            ->leftJoin('information_object_i18n as a_i18n', function ($join) use ($culture) {
                $join->on('d.record_a_id', '=', 'a_i18n.id')
                    ->where('a_i18n.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as b_i18n', function ($join) use ($culture) {
                $join->on('d.record_b_id', '=', 'b_i18n.id')
                    ->where('b_i18n.culture', '=', $culture);
            })
            ->where('d.status', 'pending')
            ->orderByDesc('d.similarity_score')
            ->limit(10)
            ->select([
                'd.id',
                'd.record_a_id',
                'd.record_b_id',
                'd.similarity_score',
                'd.detection_method',
                'd.detected_at',
                'a_i18n.title as record_a_title',
                'b_i18n.title as record_b_title',
            ])
            ->get();

        $recentScans = DB::table('ahg_dedupe_scan')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $methodCounts = DB::table('ahg_duplicate_detection')
            ->select('detection_method', DB::raw('COUNT(*) as total'))
            ->groupBy('detection_method')
            ->orderByDesc('total')
            ->get();

        return view('ahg-dedupe::index', [
            'stats' => [
                'total'       => $totalDetected,
                'pending'     => $pending,
                'confirmed'   => $confirmed,
                'merged'      => $merged,
                'dismissed'   => $dismissed,
                'activeRules' => $activeRules,
            ],
            'topPending'   => $topPending,
            'recentScans'  => $recentScans,
            'methodCounts' => $methodCounts,
        ]);
    }

    /**
     * Browse — paginated duplicate list with filters.
     */
    public function browse(Request $request)
    {
        if (!$this->tablesExist()) {
            return view('ahg-dedupe::not-configured');
        }

        $culture  = app()->getLocale();
        $page     = max(1, (int) $request->get('page', 1));
        $limit    = max(1, (int) $request->get('limit', SettingHelper::hitsPerPage()));
        $status   = $request->get('status', '');
        $method   = $request->get('method', '');
        $minScore = $request->get('min_score', '');

        $query = DB::table('ahg_duplicate_detection as d')
            ->leftJoin('information_object_i18n as a_i18n', function ($join) use ($culture) {
                $join->on('d.record_a_id', '=', 'a_i18n.id')
                    ->where('a_i18n.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as b_i18n', function ($join) use ($culture) {
                $join->on('d.record_b_id', '=', 'b_i18n.id')
                    ->where('b_i18n.culture', '=', $culture);
            });

        if ($status !== '') {
            $query->where('d.status', $status);
        }
        if ($method !== '') {
            $query->where('d.detection_method', $method);
        }
        if ($minScore !== '') {
            $query->where('d.similarity_score', '>=', (float) $minScore);
        }

        $total = $query->count();

        $rows = $query
            ->orderByDesc('d.similarity_score')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->select([
                'd.id',
                'd.record_a_id',
                'd.record_b_id',
                'd.similarity_score',
                'd.detection_method',
                'd.status',
                'd.detected_at',
                'a_i18n.title as record_a_title',
                'b_i18n.title as record_b_title',
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        $pager = new SimplePager([
            'hits'  => $rows,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);

        $methods = DB::table('ahg_duplicate_detection')
            ->select('detection_method')
            ->distinct()
            ->orderBy('detection_method')
            ->pluck('detection_method')
            ->toArray();

        return view('ahg-dedupe::browse', [
            'pager'         => $pager,
            'currentStatus' => $status,
            'currentMethod' => $method,
            'currentScore'  => $minScore,
            'methods'       => $methods,
        ]);
    }

    /**
     * Compare — side-by-side view of two potential duplicates.
     */
    public function compare(int $id)
    {
        if (!$this->tablesExist()) {
            return view('ahg-dedupe::not-configured');
        }

        $culture = app()->getLocale();

        $duplicate = DB::table('ahg_duplicate_detection')->where('id', $id)->first();

        if (!$duplicate) {
            abort(404);
        }

        $recordA = DB::table('information_object')
            ->join('information_object_i18n', function ($join) use ($culture) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->leftJoin('repository_i18n', function ($join) use ($culture) {
                $join->on('information_object.repository_id', '=', 'repository_i18n.id')
                    ->where('repository_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level_term', function ($join) use ($culture) {
                $join->on('information_object.level_of_description_id', '=', 'level_term.id')
                    ->where('level_term.culture', '=', $culture);
            })
            ->where('information_object.id', $duplicate->record_a_id)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object_i18n.title',
                'information_object_i18n.extent_and_medium as extent',
                'information_object_i18n.scope_and_content',
                'level_term.name as level_of_description',
                'repository_i18n.authorized_form_of_name as repository',
            ])
            ->first();

        $recordB = DB::table('information_object')
            ->join('information_object_i18n', function ($join) use ($culture) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->leftJoin('repository_i18n', function ($join) use ($culture) {
                $join->on('information_object.repository_id', '=', 'repository_i18n.id')
                    ->where('repository_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level_term', function ($join) use ($culture) {
                $join->on('information_object.level_of_description_id', '=', 'level_term.id')
                    ->where('level_term.culture', '=', $culture);
            })
            ->where('information_object.id', $duplicate->record_b_id)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object_i18n.title',
                'information_object_i18n.extent_and_medium as extent',
                'information_object_i18n.scope_and_content',
                'level_term.name as level_of_description',
                'repository_i18n.authorized_form_of_name as repository',
            ])
            ->first();

        $fields = ['title', 'identifier', 'level_of_description', 'repository', 'extent', 'scope_and_content'];

        $comparison = [];
        foreach ($fields as $field) {
            $valA = $recordA ? ($recordA->$field ?? '') : '';
            $valB = $recordB ? ($recordB->$field ?? '') : '';
            $comparison[] = [
                'label' => ucwords(str_replace('_', ' ', $field)),
                'a'     => $valA,
                'b'     => $valB,
                'match' => trim((string) $valA) !== '' && trim((string) $valA) === trim((string) $valB),
            ];
        }

        return view('ahg-dedupe::compare', [
            'duplicate'  => $duplicate,
            'recordA'    => $recordA,
            'recordB'    => $recordB,
            'comparison' => $comparison,
        ]);
    }

    /**
     * Dismiss a duplicate pair (AJAX).
     */
    public function dismiss(Request $request, int $id)
    {
        if (!$this->tablesExist()) {
            return response()->json(['error' => 'Duplicate detection tables not configured.'], 500);
        }

        $duplicate = DB::table('ahg_duplicate_detection')->where('id', $id)->first();

        if (!$duplicate) {
            return response()->json(['error' => 'Duplicate record not found.'], 404);
        }

        DB::table('ahg_duplicate_detection')
            ->where('id', $id)
            ->update([
                'status'      => 'dismissed',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

        return response()->json(['success' => true, 'message' => 'Duplicate dismissed.']);
    }

    /**
     * Rules — list all detection rules.
     */
    public function rules()
    {
        if (!$this->tablesExist()) {
            return view('ahg-dedupe::not-configured');
        }

        $rules = DB::table('ahg_duplicate_rule')
            ->orderBy('priority')
            ->get();

        return view('ahg-dedupe::rules', [
            'rules' => $rules,
        ]);
    }

    /**
     * Report — monthly stats and method breakdown.
     */
    public function report()
    {
        if (!$this->tablesExist()) {
            return view('ahg-dedupe::not-configured');
        }

        $monthlyStats = DB::table('ahg_duplicate_detection')
            ->select(
                DB::raw("DATE_FORMAT(detected_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed"),
                DB::raw("SUM(CASE WHEN status = 'merged' THEN 1 ELSE 0 END) as merged"),
                DB::raw("SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed")
            )
            ->groupBy(DB::raw("DATE_FORMAT(detected_at, '%Y-%m')"))
            ->orderByDesc('month')
            ->limit(12)
            ->get();

        $methodBreakdown = DB::table('ahg_duplicate_detection')
            ->select(
                'detection_method',
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(similarity_score) as avg_score'),
                DB::raw("SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed"),
                DB::raw("SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed")
            )
            ->groupBy('detection_method')
            ->orderByDesc('total')
            ->get();

        return view('ahg-dedupe::report', [
            'monthlyStats'    => $monthlyStats,
            'methodBreakdown' => $methodBreakdown,
        ]);
    }
}
