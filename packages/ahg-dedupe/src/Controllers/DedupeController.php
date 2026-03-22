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

        $culture = app()->getLocale();

        $rules = DB::table('ahg_duplicate_rule as r')
            ->leftJoin('repository_i18n as ri', function ($join) use ($culture) {
                $join->on('r.repository_id', '=', 'ri.id')
                    ->where('ri.culture', '=', $culture);
            })
            ->select('r.*', 'ri.authorized_form_of_name as repository_name')
            ->orderBy('r.priority')
            ->get();

        return view('ahg-dedupe::rules', [
            'rules' => $rules,
        ]);
    }

    /**
     * Scan — form to start a new duplicate scan.
     */
    public function scan()
    {
        if (!$this->tablesExist()) {
            return view('ahg-dedupe::not-configured');
        }

        $culture = app()->getLocale();

        $repositories = DB::table('repository')
            ->join('repository_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'repository_i18n.id')
                    ->where('repository_i18n.culture', '=', $culture);
            })
            ->select('repository.id', 'repository_i18n.authorized_form_of_name as name')
            ->orderBy('repository_i18n.authorized_form_of_name')
            ->get();

        return view('ahg-dedupe::scan', [
            'repositories' => $repositories,
        ]);
    }

    /**
     * Start a new scan job.
     */
    public function scanStart(Request $request)
    {
        if (!$this->tablesExist()) {
            return redirect()->route('dedupe.index');
        }

        DB::table('ahg_dedupe_scan')->insert([
            'scope'             => $request->input('scope', 'all'),
            'repository_id'     => $request->input('scope') === 'repository' ? $request->input('repository_id') : null,
            'status'            => 'pending',
            'processed_records' => 0,
            'total_records'     => 0,
            'duplicates_found'  => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return redirect()->route('dedupe.index')->with('notice', 'Scan job created. Run the CLI command to process it.');
    }

    /**
     * Merge — form to merge two duplicate records.
     */
    public function merge(int $id)
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
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.id', $duplicate->record_a_id)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object_i18n.title',
                'level_term.name as level_of_description',
                'repository_i18n.authorized_form_of_name as repository_name',
                'slug.slug',
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
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.id', $duplicate->record_b_id)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object_i18n.title',
                'level_term.name as level_of_description',
                'repository_i18n.authorized_form_of_name as repository_name',
                'slug.slug',
            ])
            ->first();

        return view('ahg-dedupe::merge', [
            'duplicate' => $duplicate,
            'recordA'   => $recordA,
            'recordB'   => $recordB,
        ]);
    }

    /**
     * Execute merge of two duplicate records.
     */
    public function mergeExecute(Request $request, int $id)
    {
        if (!$this->tablesExist()) {
            return redirect()->route('dedupe.index');
        }

        $duplicate = DB::table('ahg_duplicate_detection')->where('id', $id)->first();
        if (!$duplicate) {
            abort(404);
        }

        $primaryId = $request->input('primary_id');
        $secondaryId = ($primaryId == $duplicate->record_a_id) ? $duplicate->record_b_id : $duplicate->record_a_id;

        DB::table('ahg_duplicate_detection')
            ->where('id', $id)
            ->update([
                'status'      => 'merged',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

        return redirect()->route('dedupe.browse')->with('notice', 'Records have been flagged for merge. A background task will complete the data transfer.');
    }

    /**
     * Rule Create form.
     */
    public function ruleCreate()
    {
        if (!$this->tablesExist()) {
            return view('ahg-dedupe::not-configured');
        }

        $culture = app()->getLocale();

        $repositories = DB::table('repository')
            ->join('repository_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'repository_i18n.id')
                    ->where('repository_i18n.culture', '=', $culture);
            })
            ->select('repository.id', 'repository_i18n.authorized_form_of_name as name')
            ->orderBy('repository_i18n.authorized_form_of_name')
            ->get();

        return view('ahg-dedupe::rule-create', [
            'ruleTypes'    => $this->getRuleTypes(),
            'repositories' => $repositories,
        ]);
    }

    /**
     * Store a new detection rule.
     */
    public function ruleStore(Request $request)
    {
        if (!$this->tablesExist()) {
            return redirect()->route('dedupe.index');
        }

        $request->validate([
            'name'      => 'required|string|max:255',
            'rule_type' => 'required|string|max:50',
            'threshold' => 'required|numeric|min:0|max:1',
        ]);

        DB::table('ahg_duplicate_rule')->insert([
            'name'          => $request->input('name'),
            'rule_type'     => $request->input('rule_type'),
            'threshold'     => $request->input('threshold'),
            'priority'      => (int) $request->input('priority', 100),
            'repository_id' => $request->input('repository_id') ?: null,
            'config_json'   => $request->input('config_json') ?: null,
            'is_enabled'    => $request->has('is_enabled') ? 1 : 0,
            'is_blocking'   => $request->has('is_blocking') ? 1 : 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return redirect()->route('dedupe.rules')->with('notice', 'Detection rule created.');
    }

    /**
     * Rule Edit form.
     */
    public function ruleEdit(int $id)
    {
        if (!$this->tablesExist()) {
            return view('ahg-dedupe::not-configured');
        }

        $rule = DB::table('ahg_duplicate_rule')->where('id', $id)->first();
        if (!$rule) {
            abort(404);
        }

        $culture = app()->getLocale();

        $repositories = DB::table('repository')
            ->join('repository_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'repository_i18n.id')
                    ->where('repository_i18n.culture', '=', $culture);
            })
            ->select('repository.id', 'repository_i18n.authorized_form_of_name as name')
            ->orderBy('repository_i18n.authorized_form_of_name')
            ->get();

        return view('ahg-dedupe::rule-edit', [
            'rule'         => $rule,
            'ruleTypes'    => $this->getRuleTypes(),
            'repositories' => $repositories,
        ]);
    }

    /**
     * Update a detection rule.
     */
    public function ruleUpdate(Request $request, int $id)
    {
        if (!$this->tablesExist()) {
            return redirect()->route('dedupe.index');
        }

        $request->validate([
            'name'      => 'required|string|max:255',
            'rule_type' => 'required|string|max:50',
            'threshold' => 'required|numeric|min:0|max:1',
        ]);

        DB::table('ahg_duplicate_rule')->where('id', $id)->update([
            'name'          => $request->input('name'),
            'rule_type'     => $request->input('rule_type'),
            'threshold'     => $request->input('threshold'),
            'priority'      => (int) $request->input('priority', 100),
            'repository_id' => $request->input('repository_id') ?: null,
            'config_json'   => $request->input('config_json') ?: null,
            'is_enabled'    => $request->has('is_enabled') ? 1 : 0,
            'is_blocking'   => $request->has('is_blocking') ? 1 : 0,
            'updated_at'    => now(),
        ]);

        return redirect()->route('dedupe.rules')->with('notice', 'Detection rule updated.');
    }

    /**
     * Delete a detection rule.
     */
    public function ruleDelete(int $id)
    {
        if (!$this->tablesExist()) {
            return redirect()->route('dedupe.index');
        }

        DB::table('ahg_duplicate_rule')->where('id', $id)->delete();

        return redirect()->route('dedupe.rules')->with('notice', 'Detection rule deleted.');
    }

    /**
     * Available rule types.
     */
    private function getRuleTypes(): array
    {
        return [
            'title_similarity' => 'Title Similarity',
            'identifier_exact' => 'Identifier Exact Match',
            'identifier_fuzzy' => 'Identifier Fuzzy Match',
            'date_creator'     => 'Date + Creator Match',
            'checksum'         => 'File Checksum',
            'combined'         => 'Combined Analysis',
            'custom'           => 'Custom Rule',
        ];
    }

    /**
     * Report — monthly stats and method breakdown.
     */
    public function report()
    {
        if (!$this->tablesExist()) {
            return view('ahg-dedupe::not-configured');
        }

        $culture = app()->getLocale();

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

        $totalDetected = DB::table('ahg_duplicate_detection')->count();
        $totalMerged   = DB::table('ahg_duplicate_detection')->where('status', 'merged')->count();
        $totalDismissed = DB::table('ahg_duplicate_detection')->where('status', 'dismissed')->count();
        $falsePositiveRate = $totalDetected > 0 ? round(($totalDismissed / $totalDetected) * 100, 1) : 0;

        $topClusters = DB::table('ahg_duplicate_detection as d')
            ->leftJoin('information_object_i18n as a_i18n', function ($join) use ($culture) {
                $join->on('d.record_a_id', '=', 'a_i18n.id')
                    ->where('a_i18n.culture', '=', $culture);
            })
            ->where('d.status', 'pending')
            ->select('d.record_a_id', 'a_i18n.title', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('d.record_a_id', 'a_i18n.title')
            ->orderByDesc('duplicate_count')
            ->limit(10)
            ->get();

        return view('ahg-dedupe::report', [
            'monthlyStats'    => $monthlyStats,
            'methodBreakdown' => $methodBreakdown,
            'efficiency'      => [
                'total_detected'     => $totalDetected,
                'total_merged'       => $totalMerged,
                'total_dismissed'    => $totalDismissed,
                'false_positive_rate' => $falsePositiveRate,
            ],
            'topClusters' => $topClusters,
        ]);
    }
}
