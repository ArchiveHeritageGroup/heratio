<?php

/**
 * GrapComplianceService - GRAP 103 compliance checks for heritage assets
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgHeritageManage\Services;

use Illuminate\Support\Facades\DB;

/**
 * Per-market module: the GRAP 103 checklist (recognition, measurement,
 * disclosure, documentation) used by the GRAP compliance dashboard, the
 * single-asset check and the batch check.
 */
class GrapComplianceService
{
    const STANDARD_CODE = 'GRAP103';
    
    // Compliance categories
    const CAT_RECOGNITION = 'recognition';
    const CAT_MEASUREMENT = 'measurement';
    const CAT_DISCLOSURE = 'disclosure';
    const CAT_DOCUMENTATION = 'documentation';
    const CAT_NARSSA = 'narssa';
    const CAT_PFMA = 'pfma';

    // Severity levels
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_LOW = 'low';

    /**
     * Get GRAP 103 standard ID
     */
    public function getStandardId(): ?int
    {
        $standard = DB::table('heritage_accounting_standard')
            ->where('code', self::STANDARD_CODE)
            ->first();
        return $standard ? $standard->id : null;
    }

    /**
     * Run compliance check on an asset
     */
    public function checkCompliance(int $assetId): array
    {
        $asset = DB::table('heritage_asset as ha')
            ->leftJoin('heritage_asset_class as hc', 'ha.asset_class_id', '=', 'hc.id')
            ->where('ha.id', $assetId)
            ->select('ha.*', 'hc.name as class_name')
            ->first();

        if (!$asset) {
            return ['error' => 'Asset not found'];
        }

        $checks = [];
        
        // Recognition checks (GRAP 103.14-25)
        $checks[] = $this->checkRecognitionStatus($asset);
        $checks[] = $this->checkRecognitionDate($asset);
        $checks[] = $this->checkAssetClass($asset);
        $checks[] = $this->checkMeasurementBasis($asset);
        
        // Measurement checks (GRAP 103.26-51)
        $checks[] = $this->checkInitialCost($asset);
        $checks[] = $this->checkCarryingAmount($asset);
        $checks[] = $this->checkValuationFrequency($asset);
        
        // Disclosure checks (GRAP 103.74-82)
        $checks[] = $this->checkSignificanceStatement($asset);
        $checks[] = $this->checkRestrictions($asset);
        
        // Documentation checks
        $checks[] = $this->checkInsurance($asset);
        $checks[] = $this->checkLocation($asset);

        // Calculate summary
        $summary = $this->calculateSummary($checks);

        return [
            'asset_id' => $assetId,
            'checks' => $checks,
            'summary' => $summary,
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }

    protected function checkRecognitionStatus($asset): array
    {
        $passed = in_array($asset->recognition_status, ['recognised', 'not_recognised']);
        return [
            'code' => 'REC001',
            'title' => 'Recognition Status',
            'reference' => 'GRAP 103.14',
            'category' => self::CAT_RECOGNITION,
            'severity' => self::SEVERITY_CRITICAL,
            'status' => $passed ? 'passed' : 'failed',
            'message' => $passed ? 'Recognition status determined' : 'Recognition status must be determined'
        ];
    }

    protected function checkRecognitionDate($asset): array
    {
        $passed = !empty($asset->recognition_date);
        return [
            'code' => 'REC002',
            'title' => 'Recognition Date',
            'reference' => 'GRAP 103.14',
            'category' => self::CAT_RECOGNITION,
            'severity' => self::SEVERITY_HIGH,
            'status' => $passed ? 'passed' : 'warning',
            'message' => $passed ? 'Recognition date recorded' : 'Recognition date should be recorded'
        ];
    }

    protected function checkAssetClass($asset): array
    {
        $passed = !empty($asset->asset_class_id);
        return [
            'code' => 'REC003',
            'title' => 'Asset Classification',
            'reference' => 'GRAP 103.74',
            'category' => self::CAT_RECOGNITION,
            'severity' => self::SEVERITY_HIGH,
            'status' => $passed ? 'passed' : 'failed',
            'message' => $passed ? 'Asset class assigned' : 'Asset must be classified'
        ];
    }

    protected function checkMeasurementBasis($asset): array
    {
        $passed = !empty($asset->measurement_basis);
        return [
            'code' => 'MEA001',
            'title' => 'Measurement Basis',
            'reference' => 'GRAP 103.26',
            'category' => self::CAT_MEASUREMENT,
            'severity' => self::SEVERITY_CRITICAL,
            'status' => $passed ? 'passed' : 'failed',
            'message' => $passed ? 'Measurement basis specified' : 'Measurement basis must be specified'
        ];
    }

    protected function checkInitialCost($asset): array
    {
        $passed = (float) $asset->acquisition_cost > 0 || (float) $asset->fair_value_at_acquisition > 0 || (float) $asset->nominal_value > 0;
        return [
            'code' => 'MEA002',
            'title' => 'Initial Measurement',
            'reference' => 'GRAP 103.26-28',
            'category' => self::CAT_MEASUREMENT,
            'severity' => self::SEVERITY_CRITICAL,
            'status' => $passed ? 'passed' : 'warning',
            'message' => $passed ? 'Initial value recorded' : 'Initial cost/fair value should be recorded'
        ];
    }

    protected function checkCarryingAmount($asset): array
    {
        $passed = $asset->current_carrying_amount !== null && (float) $asset->current_carrying_amount >= 0;
        return [
            'code' => 'MEA003',
            'title' => 'Carrying Amount',
            'reference' => 'GRAP 103.36',
            'category' => self::CAT_MEASUREMENT,
            'severity' => self::SEVERITY_HIGH,
            'status' => $passed ? 'passed' : 'failed',
            'message' => $passed ? 'Carrying amount recorded' : 'Current carrying amount required'
        ];
    }

    protected function checkValuationFrequency($asset): array
    {
        if (empty($asset->last_valuation_date)) {
            return [
                'code' => 'MEA004',
                'title' => 'Valuation Currency',
                'reference' => 'GRAP 103.38',
                'category' => self::CAT_MEASUREMENT,
                'severity' => self::SEVERITY_MEDIUM,
                'status' => 'warning',
                'message' => 'No valuation recorded yet'
            ];
        }
        
        $lastValuation = strtotime($asset->last_valuation_date);
        $threeYearsAgo = strtotime('-3 years');
        $passed = $lastValuation > $threeYearsAgo;
        
        return [
            'code' => 'MEA004',
            'title' => 'Valuation Currency',
            'reference' => 'GRAP 103.38',
            'category' => self::CAT_MEASUREMENT,
            'severity' => self::SEVERITY_MEDIUM,
            'status' => $passed ? 'passed' : 'warning',
            'message' => $passed ? 'Valuation is current' : 'Valuation may need updating (>3 years old)'
        ];
    }

    protected function checkSignificanceStatement($asset): array
    {
        $passed = !empty($asset->significance_statement) || !empty($asset->heritage_significance);
        return [
            'code' => 'DIS001',
            'title' => 'Heritage Significance',
            'reference' => 'GRAP 103.74(a)',
            'category' => self::CAT_DISCLOSURE,
            'severity' => self::SEVERITY_MEDIUM,
            'status' => $passed ? 'passed' : 'warning',
            'message' => $passed ? 'Significance documented' : 'Heritage significance should be documented'
        ];
    }

    protected function checkRestrictions($asset): array
    {
        // This is informational - restrictions may or may not exist
        $hasRestrictions = !empty($asset->restrictions_on_use) || !empty($asset->restrictions_on_disposal);
        return [
            'code' => 'DIS002',
            'title' => 'Restrictions Disclosure',
            'reference' => 'GRAP 103.74(b)',
            'category' => self::CAT_DISCLOSURE,
            'severity' => self::SEVERITY_LOW,
            'status' => 'passed',
            'message' => $hasRestrictions ? 'Restrictions documented' : 'No restrictions recorded (review if applicable)'
        ];
    }

    protected function checkInsurance($asset): array
    {
        if (!$asset->insurance_required) {
            return [
                'code' => 'DOC001',
                'title' => 'Insurance Coverage',
                'reference' => 'PFMA',
                'category' => self::CAT_DOCUMENTATION,
                'severity' => self::SEVERITY_LOW,
                'status' => 'passed',
                'message' => 'Insurance not required for this asset'
            ];
        }
        
        $passed = !empty($asset->insurance_value) && !empty($asset->insurance_policy_number);
        return [
            'code' => 'DOC001',
            'title' => 'Insurance Coverage',
            'reference' => 'PFMA',
            'category' => self::CAT_DOCUMENTATION,
            'severity' => self::SEVERITY_HIGH,
            'status' => $passed ? 'passed' : 'warning',
            'message' => $passed ? 'Insurance documented' : 'Insurance details should be recorded'
        ];
    }

    protected function checkLocation($asset): array
    {
        $passed = !empty($asset->current_location);
        return [
            'code' => 'DOC002',
            'title' => 'Asset Location',
            'reference' => 'Asset Management',
            'category' => self::CAT_DOCUMENTATION,
            'severity' => self::SEVERITY_MEDIUM,
            'status' => $passed ? 'passed' : 'warning',
            'message' => $passed ? 'Location recorded' : 'Current location should be documented'
        ];
    }

    protected function calculateSummary(array $checks): array
    {
        $total = count($checks);
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        
        foreach ($checks as $check) {
            switch ($check['status']) {
                case 'passed': $passed++; break;
                case 'failed': $failed++; break;
                case 'warning': $warnings++; break;
            }
        }
        
        $score = $total > 0 ? round(($passed / $total) * 100) : 0;
        
        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'score' => $score,
            'status' => $failed > 0 ? 'non_compliant' : ($warnings > 0 ? 'partially_compliant' : 'compliant')
        ];
    }

    /**
     * Get compliance summary for all GRAP assets
     */
    public function getComplianceSummary(): array
    {
        $standardId = $this->getStandardId();
        if (! $standardId) {
            return ['total_assets' => 0, 'compliant' => 0, 'partially_compliant' => 0, 'non_compliant' => 0, 'not_checked' => 0];
        }

        $assets = DB::table('heritage_asset')
            ->where('accounting_standard_id', $standardId)
            ->get(['id']);
        
        $results = [
            'total_assets' => count($assets),
            'compliant' => 0,
            'partially_compliant' => 0,
            'non_compliant' => 0,
            'not_checked' => 0
        ];
        
        foreach ($assets as $asset) {
            $check = $this->checkCompliance($asset->id);
            switch ($check['summary']['status']) {
                case 'compliant': $results['compliant']++; break;
                case 'partially_compliant': $results['partially_compliant']++; break;
                case 'non_compliant': $results['non_compliant']++; break;
            }
        }
        
        return $results;
    }
}
