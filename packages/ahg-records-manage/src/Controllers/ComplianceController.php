<?php

/**
 * ComplianceController — automated compliance assessments (P2.8).
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgRecordsManage\Controllers;

use AhgRecordsManage\Services\ComplianceReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplianceController extends Controller
{
    public function __construct(protected ComplianceReportService $compliance)
    {
    }

    /** GET /admin/records/compliance */
    public function index(Request $request)
    {
        $filters = [
            'framework' => $request->query('framework'),
            'status'    => $request->query('status'),
        ];
        $rows       = $this->compliance->listAssessments($filters);
        // Issue #59 Tier 3 - culture-aware via the COALESCE helper.
        $frameworks = \AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes('rm_compliance_framework');

        return view('ahg-records::compliance.index', [
            'rows'       => $rows,
            'filters'    => $filters,
            'frameworks' => $frameworks,
        ]);
    }

    /** GET /admin/records/compliance/create */
    public function create()
    {
        // Issue #59 Tier 3 - culture-aware via the COALESCE helper.
        $frameworks = \AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes('rm_compliance_framework');
        return view('ahg-records::compliance.create', ['frameworks' => $frameworks]);
    }

    /** POST /admin/records/compliance */
    public function store(Request $request)
    {
        $data = $request->validate([
            'framework'      => 'required|string|max:50',
            'assessment_ref' => 'required|string|max:100|unique:rm_compliance_assessment,assessment_ref',
            'title'          => 'required|string|max:255',
            'scope'          => 'nullable|string',
            'period_start'   => 'nullable|date',
            'period_end'     => 'nullable|date',
        ]);

        $id = $this->compliance->create($data, auth()->id() ?? 0);
        $this->compliance->runChecks($id);

        return redirect()->route('records.compliance.show', $id)
            ->with('success', 'Assessment created and checks run.');
    }

    /** GET /admin/records/compliance/{id} */
    public function show(int $id)
    {
        $assessment = $this->compliance->get($id);
        if (! $assessment) {
            abort(404, 'Assessment not found');
        }

        $checks          = $assessment->findings_json ? json_decode($assessment->findings_json, true) : [];
        $recommendations = $assessment->recommendations_json ? json_decode($assessment->recommendations_json, true) : [];

        return view('ahg-records::compliance.show', [
            'assessment'      => $assessment,
            'checks'          => $checks,
            'recommendations' => $recommendations,
        ]);
    }

    /** POST /admin/records/compliance/{id}/run-checks */
    public function runChecks(int $id)
    {
        $ok = $this->compliance->runChecks($id);
        return redirect()->route('records.compliance.show', $id)
            ->with($ok ? 'success' : 'error', $ok ? 'Checks re-run.' : 'Could not run checks.');
    }

    /** POST /admin/records/compliance/{id}/finalize */
    public function finalize(Request $request, int $id)
    {
        $data = $request->validate([
            'signed_off_by' => 'required|string|max:255',
        ]);
        $this->compliance->finalize($id, $data['signed_off_by'], auth()->id() ?? 0);
        return redirect()->route('records.compliance.show', $id)->with('success', 'Assessment finalised.');
    }
}
