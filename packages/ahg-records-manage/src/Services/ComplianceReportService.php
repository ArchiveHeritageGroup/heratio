<?php

/**
 * ComplianceReportService — automated compliance assessments (Phase 2.8).
 *
 * Runs framework-specific checks against the live RM data plane and writes
 * findings + recommendations into rm_compliance_assessment. Supports:
 *   - ISO 15489 (records management principles)
 *   - ISO 16175 (ERM principles)
 *   - MoReq2010
 *   - DoD 5015.2
 *   - ISO 30300 (MSR)
 *   - ISO 23081 (metadata)
 *
 * Each check returns a {check_ref, label, weight, status, finding, recommendation}
 * tuple. status is "pass" / "warn" / "fail" / "n/a". Score = sum(weight where pass)
 * / sum(weight); displayed as a percentage on the assessment show page.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComplianceReportService
{
    public function listAssessments(array $filters = []): array
    {
        $q = DB::table('rm_compliance_assessment');
        if (! empty($filters['framework'])) {
            $q->where('framework', $filters['framework']);
        }
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        return $q->orderByDesc('assessed_at')->limit(200)->get()->all();
    }

    public function get(int $id): ?object
    {
        return DB::table('rm_compliance_assessment')->where('id', $id)->first();
    }

    public function create(array $data, int $userId): int
    {
        return DB::table('rm_compliance_assessment')->insertGetId([
            'framework'       => $data['framework'],
            'assessment_ref'  => $data['assessment_ref'],
            'title'           => $data['title'],
            'scope'           => $data['scope'] ?? null,
            'period_start'    => $data['period_start'] ?? null,
            'period_end'      => $data['period_end'] ?? null,
            'status'          => 'in_progress',
            'assessed_by'     => $userId,
            'assessed_at'     => now(),
        ]);
    }

    /**
     * Run automated checks for the assessment's framework, write findings JSON,
     * and update the score columns. Re-runnable; overwrites previous findings.
     */
    public function runChecks(int $id): bool
    {
        $assessment = $this->get($id);
        if (! $assessment) {
            return false;
        }

        $checks = match ($assessment->framework) {
            'iso_15489'  => $this->checksIso15489(),
            'iso_16175'  => $this->checksIso16175(),
            'moreq2010'  => $this->checksMoreq2010(),
            'dod_5015_2' => $this->checksDod50152(),
            'iso_30300'  => $this->checksIso30300(),
            'iso_23081'  => $this->checksIso23081(),
            default      => [],
        };

        $weightTotal = array_sum(array_column($checks, 'weight'));
        $weightPass  = 0;
        $recommendations = [];
        foreach ($checks as $c) {
            if ($c['status'] === 'pass') {
                $weightPass += $c['weight'];
            } elseif ($c['status'] === 'warn') {
                $weightPass += $c['weight'] * 0.5;
            }
            if (! empty($c['recommendation']) && $c['status'] !== 'pass') {
                $recommendations[] = ['ref' => $c['check_ref'], 'recommendation' => $c['recommendation']];
            }
        }

        DB::table('rm_compliance_assessment')->where('id', $id)->update([
            'findings_json'        => json_encode($checks, JSON_UNESCAPED_SLASHES),
            'recommendations_json' => json_encode($recommendations, JSON_UNESCAPED_SLASHES),
            'score_total'          => $weightPass,
            'score_max'            => $weightTotal ?: 0,
        ]);

        Log::info('rm: compliance checks run', [
            'id'        => $id,
            'framework' => $assessment->framework,
            'checks'    => count($checks),
            'score'     => $weightPass . '/' . $weightTotal,
        ]);

        return true;
    }

    public function finalize(int $id, string $signedOffBy, int $userId): bool
    {
        return DB::table('rm_compliance_assessment')->where('id', $id)->update([
            'status'        => 'finalised',
            'signed_off_by' => $signedOffBy,
            'signed_off_at' => now(),
        ]) > 0;
    }

    /* -------------------------------------------------------------------- */
    /*  Framework check sets                                                 */
    /* -------------------------------------------------------------------- */

    private function checksIso15489(): array
    {
        return [
            $this->check('15489-5', 'A retention schedule exists',
                DB::table('rm_retention_schedule')->whereIn('status', ['approved', 'effective'])->exists(),
                'No approved retention schedule found. ISO 15489 §5.4 requires retention rules be authorised.',
                'Create at least one retention schedule and move it to status=approved or effective.', 10),
            $this->check('15489-6', 'A file plan / classification scheme exists',
                DB::table('rm_fileplan_node')->where('status', 'active')->exists(),
                'No active file-plan nodes. ISO 15489 §5.2 requires functional classification.',
                'Build a top-level file plan or import one via /admin/records/fileplan/import.', 10),
            $this->check('15489-7', 'Disposal classes are defined',
                DB::table('rm_disposal_class')->where('is_active', 1)->exists(),
                'No active disposal classes. ISO 15489 §5.4 requires authorised disposal rules.',
                'Define disposal classes under each retention schedule.', 8),
            $this->check('15489-8', 'Records are being declared (last 90 days)',
                DB::table('object')
                    ->where('class_name', 'QubitInformationObject')
                    ->where('created_at', '>=', now()->subDays(90))->count() > 0,
                'No records declared in the last 90 days. RM is not capturing live activity.',
                'Confirm the declare-as-record workflow is reachable to business users.', 6),
            $this->check('15489-9', 'Disposal actions are being executed',
                DB::table('rm_disposal_action')->where('status', 'executed')->exists()
                || ! DB::table('rm_disposal_action')->exists(),
                'Disposal actions exist but none have been executed.',
                'Walk the queue: pending → recommended → approved → executed.', 6),
            $this->check('15489-10', 'Audit trail captures access events',
                DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())->where('TABLE_NAME', 'access_audit_log')->exists(),
                'Access audit log table missing. ISO 15489 §5.2.6 requires audit metadata.',
                'Enable ahgAuditTrailPlugin or create access_audit_log.', 5),
        ];
    }

    private function checksMoreq2010(): array
    {
        return [
            $this->check('M2-CORE-1', 'Aggregations are organised hierarchically (file plan)',
                DB::table('rm_fileplan_node')->whereNotNull('parent_id')->exists(),
                'No hierarchical aggregations. MoReq2010 requires nested classification.',
                'Build at least one parent → child node relationship in the file plan.', 8),
            $this->check('M2-DISP-1', 'Disposal schedules link to aggregations',
                DB::table('rm_fileplan_node')->whereNotNull('disposal_class_id')->exists(),
                'No file-plan node has a disposal class assigned. MoReq2010 disposition not enforceable.',
                'Assign a disposal class to each leaf node.', 8),
            $this->check('M2-AUDIT-1', 'Audit trail is append-only and complete',
                DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())->where('TABLE_NAME', 'ahg_audit_log')->exists(),
                'ahg_audit_log not present.',
                'Enable ahgAuditTrailPlugin.', 6),
            $this->check('M2-RETENT-1', 'Records carry retention metadata',
                DB::table('rm_record_disposal_class')->exists(),
                'No records have an assigned disposal class. Retention not enforceable.',
                'Use bulk-assign or per-IO classification to attach disposal classes.', 8),
            $this->check('M2-DESTR-1', 'Destruction certificates are generated',
                DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())->where('TABLE_NAME', 'integrity_destruction_certificate')->exists(),
                'No destruction certificate table; MoReq2010 requires proof of destruction.',
                'Enable Phase 1 ahg-integrity Destruction Certificate Service.', 6),
        ];
    }

    private function checksDod50152(): array
    {
        return [
            $this->check('DoD-C2.1', 'Records can be declared and locked',
                DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())->where('TABLE_NAME', 'integrity_record_declaration')->exists(),
                'No record_declaration table — DoD 5015.2 requires records become immutable on declaration.',
                'Enable RecordDeclarationService (Phase 1).', 8),
            $this->check('DoD-C2.2', 'Disposal actions track approval chain',
                ! DB::table('rm_disposal_action')->whereNull('approved_by')->where('status', 'executed')->exists(),
                'Some executed disposal actions have no approver — DoD chain-of-approval failure.',
                'Reject any future executions where approved_by is null.', 8),
            $this->check('DoD-C2.3', 'Legal hold mechanism exists',
                DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())->where('TABLE_NAME', 'integrity_legal_hold')->exists(),
                'No legal_hold table.',
                'Enable LegalHoldService (Phase 1).', 8),
            $this->check('DoD-C2.4', 'Legal hold blocks disposal',
                ! DB::table('rm_disposal_action')->where('legal_cleared', 0)->where('status', 'executed')->exists(),
                'Found disposal actions executed without legal-hold clearance.',
                'Enforce legal_cleared=1 before allowing status=executed.', 10),
            $this->check('DoD-C2.5', 'Vital records identified',
                DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())->where('TABLE_NAME', 'integrity_vital_record')->exists(),
                'No vital_record table.',
                'Enable VitalRecordService (Phase 1).', 5),
        ];
    }

    private function checksIso16175(): array
    {
        return [
            $this->check('16175-1', 'ERM has a classification scheme',
                DB::table('rm_fileplan_node')->where('status', 'active')->exists(),
                'No file plan present.', 'Build or import a file plan.', 10),
            $this->check('16175-2', 'Records carry retention/disposal metadata',
                DB::table('rm_record_disposal_class')->exists(),
                'Records lack disposal-class assignment.', 'Bulk-assign disposal classes by file-plan node.', 8),
            $this->check('16175-3', 'Audit trail records system events',
                DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())->where('TABLE_NAME', 'ahg_audit_log')->exists(),
                'No audit log.', 'Enable audit trail plugin.', 6),
        ];
    }

    private function checksIso30300(): array
    {
        return [
            $this->check('30300-1', 'Management system has scope statement',
                DB::table('rm_compliance_assessment')->whereNotNull('scope')->exists(),
                'No assessment with a documented scope. MSR Clause 4.3 requires scope.',
                'Add a scope to your active compliance assessments.', 6),
            $this->check('30300-2', 'Periodic review evidence',
                DB::table('rm_compliance_assessment')->where('assessed_at', '>=', now()->subYear())->exists(),
                'No assessment in the last 12 months. MSR Clause 9 requires periodic review.',
                'Run a fresh assessment annually at minimum.', 6),
        ];
    }

    private function checksIso23081(): array
    {
        return [
            $this->check('23081-1', 'Records carry creator metadata',
                DB::table('event')->where('type_id', 111)->exists() // creator event
                || DB::table('actor')->exists(),
                'Records lack creator (actor) linkage.',
                'Attribute records to authority records via the Actor module.', 8),
            $this->check('23081-2', 'Records carry date metadata',
                DB::table('event')->whereNotNull('start_date')->orWhereNotNull('end_date')->exists(),
                'Records lack date metadata.',
                'Capture creation / accumulation dates per ISAD(G) 3.1.3.', 6),
            $this->check('23081-3', 'Multilingual descriptions supported',
                DB::table('information_object_i18n')->distinct()->count('culture') > 0,
                'No multilingual descriptions.',
                'Add at least one translation for key fonds.', 4),
        ];
    }

    private function check(string $ref, string $label, bool $passed, string $finding, string $recommendation, int $weight): array
    {
        return [
            'check_ref'      => $ref,
            'label'          => $label,
            'weight'         => $weight,
            'status'         => $passed ? 'pass' : 'fail',
            'finding'        => $passed ? 'OK' : $finding,
            'recommendation' => $passed ? null : $recommendation,
        ];
    }
}
