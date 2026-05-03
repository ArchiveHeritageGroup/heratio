<?php

/**
 * ClassificationController — auto-classification rules CRUD + run (P4.2).
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgRecordsManage\Controllers;

use AhgRecordsManage\Services\ClassificationRuleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassificationController extends Controller
{
    public function __construct(protected ClassificationRuleService $svc)
    {
    }

    /** GET /admin/records/classification */
    public function index(Request $request)
    {
        $filters = [
            'rule_type'        => $request->query('rule_type'),
            'is_active'        => $request->query('is_active', ''),
            'fileplan_node_id' => $request->query('fileplan_node_id'),
        ];
        $rules     = $this->svc->listRules($filters);
        $counts    = $this->svc->counts();
        $stats     = $this->svc->ruleStats();
        $ruleTypes = $this->ruleTypeOptions();

        return view('ahg-records::classification.index', [
            'rules'     => $rules,
            'counts'    => $counts,
            'stats'     => $stats,
            'filters'   => $filters,
            'ruleTypes' => $ruleTypes,
        ]);
    }

    /** GET /admin/records/classification/create */
    public function create()
    {
        return view('ahg-records::classification.edit', [
            'rule'             => null,
            'ruleTypes'        => $this->ruleTypeOptions(),
            'fileplanNodes'    => $this->fileplanOptions(),
            'disposalClasses'  => $this->disposalClassOptions(),
        ]);
    }

    /** POST /admin/records/classification */
    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());
        $id   = $this->svc->createRule($data, auth()->id() ?? 0);
        return redirect()->route('records.classification.show', $id)
            ->with('success', 'Classification rule created.');
    }

    /** GET /admin/records/classification/{id} */
    public function show(int $id)
    {
        $rule = $this->svc->getRule($id);
        if (! $rule) {
            abort(404, 'Rule not found');
        }

        $logCount = DB::table('rm_classification_log')->where('rule_id', $id)->count();
        $recent = DB::table('rm_classification_log as l')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'l.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'l.information_object_id')
            ->where('l.rule_id', $id)
            ->orderByDesc('l.classified_at')
            ->limit(20)
            ->select('l.id', 'l.information_object_id', 'l.match_detail', 'l.classified_at', 'ioi.title', 'slug.slug')
            ->get();

        return view('ahg-records::classification.show', [
            'rule'      => $rule,
            'logCount'  => $logCount,
            'recent'    => $recent,
            'ruleTypes' => $this->ruleTypeOptions(),
        ]);
    }

    /** GET /admin/records/classification/{id}/edit */
    public function edit(int $id)
    {
        $rule = $this->svc->getRule($id);
        if (! $rule) {
            abort(404, 'Rule not found');
        }
        return view('ahg-records::classification.edit', [
            'rule'            => $rule,
            'ruleTypes'       => $this->ruleTypeOptions(),
            'fileplanNodes'   => $this->fileplanOptions(),
            'disposalClasses' => $this->disposalClassOptions(),
        ]);
    }

    /** PUT /admin/records/classification/{id} */
    public function update(Request $request, int $id)
    {
        $data = $request->validate($this->validationRules());
        $this->svc->updateRule($id, $data);
        return redirect()->route('records.classification.show', $id)
            ->with('success', 'Rule updated.');
    }

    /** DELETE /admin/records/classification/{id} */
    public function destroy(int $id)
    {
        $this->svc->deleteRule($id);
        return redirect()->route('records.classification.index')
            ->with('success', 'Rule deleted.');
    }

    /** POST /admin/records/classification/{id}/test */
    public function testRule(Request $request, int $id)
    {
        $meta = [
            'folder_path' => (string) $request->input('folder_path', ''),
            'workspace'   => (string) $request->input('workspace', ''),
            'department'  => (string) $request->input('department', ''),
            'mime_type'   => (string) $request->input('mime_type', ''),
            'tags'        => array_filter(array_map('trim', explode(',', (string) $request->input('tags', '')))),
            'custom'      => [],
        ];
        if ($request->filled('custom_key')) {
            $meta['custom'][trim((string) $request->input('custom_key'))] = (string) $request->input('custom_value', '');
        }

        $result = $this->svc->testRule($id, $meta);
        return redirect()->route('records.classification.show', $id)
            ->with('test_result', $result)
            ->with('test_meta', $meta);
    }

    /** POST /admin/records/classification/run-batch */
    public function runBatch(Request $request)
    {
        $limit = (int) $request->input('limit', 1000);
        $r = $this->svc->classifyBatch(max(1, min($limit, 5000)));
        return redirect()->route('records.classification.index')
            ->with('success', "Batch run: classified={$r['classified']}, skipped={$r['skipped']}, failed={$r['failed']}");
    }

    /** POST /admin/records/classification/classify-io/{ioId} */
    public function classifyIO(int $ioId)
    {
        $r = $this->svc->classifyIO($ioId);
        if ($r) {
            return redirect()->back()->with('success',
                "Classified IO #{$ioId} via rule #{$r['rule_id']} → file plan node #{$r['fileplan_node_id']}");
        }
        return redirect()->back()->with('error', "No rule matched IO #{$ioId}.");
    }

    /* -------------------------------------------------------------------- */

    private function ruleTypeOptions()
    {
        // Issue #59 Tier 3 - culture-aware via the COALESCE helper.
        return \AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes('rm_classification_rule_type');
    }

    private function fileplanOptions()
    {
        return DB::table('rm_fileplan_node')
            ->where('status', 'active')
            ->orderBy('lft')
            ->limit(500)
            ->get(['id', 'code', 'title', 'depth']);
    }

    private function disposalClassOptions()
    {
        return DB::table('rm_disposal_class')
            ->where('is_active', 1)
            ->orderBy('class_ref')
            ->get(['id', 'class_ref', 'title']);
    }

    private function validationRules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'rule_type'         => 'required|string|max:30',
            'match_pattern'     => 'required|string|max:1024',
            'fileplan_node_id'  => 'required|integer|exists:rm_fileplan_node,id',
            'disposal_class_id' => 'nullable|integer|exists:rm_disposal_class,id',
            'priority'          => 'nullable|integer',
            'is_active'         => 'nullable|boolean',
            'apply_on'          => 'nullable|string|in:upload,declare,both',
        ];
    }
}
