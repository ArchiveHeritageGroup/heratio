<?php

/**
 * OciMovementService - Other Comprehensive Income / Revaluation Reserve movement ledger.
 *
 * Heritage asset revaluations don't always go to profit-and-loss. Under
 * GRAP 103.51 + IPSAS 45.74 revaluation surpluses flow to OCI (the
 * Revaluation Reserve in equity) until the asset is disposed; reversals
 * of prior surpluses also unwind through OCI to the extent of the
 * accumulated surplus, then through P&L. This service centralises that
 * split so callers don't have to know the standard by heart.
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
use Illuminate\Support\Facades\Schema;

class OciMovementService
{
    /**
     * Record a revaluation. Direction is determined by sign of $newValue - $previousValue.
     * Surpluses post to OCI; deficits eat the existing reserve first then spill to P&L.
     *
     * @return int[] List of inserted ahg_heritage_oci_movement row ids (1 or 2 rows).
     */
    public function recordRevaluation(
        int $heritageAssetId,
        float $previousValue,
        float $newValue,
        string $valuationDate,
        ?int $valuerId = null,
        ?string $valuationMethod = null,
        ?string $reason = null,
        ?int $informationObjectId = null,
        ?int $userId = null,
        string $currency = 'ZAR'
    ): array {
        $delta = round($newValue - $previousValue, 2);
        if (abs($delta) < 0.005) {
            return [];
        }

        if ($delta > 0) {
            // Surplus - post entire amount to OCI
            return [$this->insertRow([
                'heritage_asset_id'    => $heritageAssetId,
                'information_object_id'=> $informationObjectId,
                'movement_type'        => 'revaluation_up',
                'amount'               => $delta,
                'currency'             => $currency,
                'valuation_date'       => $valuationDate,
                'valuer_id'            => $valuerId,
                'valuation_method'     => $valuationMethod,
                'reason'               => $reason,
                'posted_to'            => 'OCI',
                'created_by_user_id'   => $userId,
            ])];
        }

        // Deficit - reduce OCI surplus first, remainder hits P&L (GRAP 103.51 / IPSAS 45.74)
        $deficitAbs = abs($delta);
        $existingSurplus = $this->getAccumulatedOciSurplus($heritageAssetId);
        $ociPortion = min($deficitAbs, max($existingSurplus, 0));
        $plPortion = $deficitAbs - $ociPortion;

        $ids = [];
        if ($ociPortion > 0) {
            $ids[] = $this->insertRow([
                'heritage_asset_id'    => $heritageAssetId,
                'information_object_id'=> $informationObjectId,
                'movement_type'        => 'revaluation_down',
                'amount'               => -$ociPortion,
                'currency'             => $currency,
                'valuation_date'       => $valuationDate,
                'valuer_id'            => $valuerId,
                'valuation_method'     => $valuationMethod,
                'reason'               => $reason,
                'posted_to'            => 'OCI',
                'created_by_user_id'   => $userId,
            ]);
        }
        if ($plPortion > 0) {
            $ids[] = $this->insertRow([
                'heritage_asset_id'    => $heritageAssetId,
                'information_object_id'=> $informationObjectId,
                'movement_type'        => 'revaluation_down',
                'amount'               => -$plPortion,
                'currency'             => $currency,
                'valuation_date'       => $valuationDate,
                'valuer_id'            => $valuerId,
                'valuation_method'     => $valuationMethod,
                'reason'               => $reason,
                'posted_to'            => 'P&L',
                'created_by_user_id'   => $userId,
            ]);
        }
        return $ids;
    }

    /**
     * Record an impairment loss. Per GRAP 103 / IPSAS 45 impairments first
     * reduce any accumulated revaluation surplus in OCI for the same asset,
     * then the balance hits P&L.
     */
    public function recordImpairment(
        int $heritageAssetId,
        float $impairmentLoss,
        string $valuationDate,
        ?string $reason = null,
        ?int $informationObjectId = null,
        ?int $userId = null,
        string $currency = 'ZAR'
    ): array {
        $loss = abs($impairmentLoss);
        if ($loss < 0.005) {
            return [];
        }
        $existingSurplus = $this->getAccumulatedOciSurplus($heritageAssetId);
        $ociPortion = min($loss, max($existingSurplus, 0));
        $plPortion = $loss - $ociPortion;

        $ids = [];
        if ($ociPortion > 0) {
            $ids[] = $this->insertRow([
                'heritage_asset_id'    => $heritageAssetId,
                'information_object_id'=> $informationObjectId,
                'movement_type'        => 'impairment',
                'amount'               => -$ociPortion,
                'currency'             => $currency,
                'valuation_date'       => $valuationDate,
                'reason'               => $reason,
                'posted_to'            => 'OCI',
                'created_by_user_id'   => $userId,
            ]);
        }
        if ($plPortion > 0) {
            $ids[] = $this->insertRow([
                'heritage_asset_id'    => $heritageAssetId,
                'information_object_id'=> $informationObjectId,
                'movement_type'        => 'impairment',
                'amount'               => -$plPortion,
                'currency'             => $currency,
                'valuation_date'       => $valuationDate,
                'reason'               => $reason,
                'posted_to'            => 'P&L',
                'created_by_user_id'   => $userId,
            ]);
        }
        return $ids;
    }

    /**
     * Record a reversal of a prior impairment. Reverses through P&L up to the
     * amount of prior P&L impairment for the same asset, then any remainder
     * cycles back to OCI to restore the revaluation surplus.
     */
    public function recordReversal(
        int $heritageAssetId,
        float $reversalAmount,
        string $valuationDate,
        ?string $reason = null,
        ?int $informationObjectId = null,
        ?int $userId = null,
        string $currency = 'ZAR'
    ): array {
        $amount = abs($reversalAmount);
        if ($amount < 0.005) {
            return [];
        }
        $priorPlImpairment = $this->getAccumulatedPlImpairment($heritageAssetId);
        $plPortion = min($amount, max($priorPlImpairment, 0));
        $ociPortion = $amount - $plPortion;

        $ids = [];
        if ($plPortion > 0) {
            $ids[] = $this->insertRow([
                'heritage_asset_id'    => $heritageAssetId,
                'information_object_id'=> $informationObjectId,
                'movement_type'        => 'reversal',
                'amount'               => $plPortion,
                'currency'             => $currency,
                'valuation_date'       => $valuationDate,
                'reason'               => $reason,
                'posted_to'            => 'P&L',
                'created_by_user_id'   => $userId,
            ]);
        }
        if ($ociPortion > 0) {
            $ids[] = $this->insertRow([
                'heritage_asset_id'    => $heritageAssetId,
                'information_object_id'=> $informationObjectId,
                'movement_type'        => 'reversal',
                'amount'               => $ociPortion,
                'currency'             => $currency,
                'valuation_date'       => $valuationDate,
                'reason'               => $reason,
                'posted_to'            => 'OCI',
                'created_by_user_id'   => $userId,
            ]);
        }
        return $ids;
    }

    /**
     * Record disposal. Any remaining revaluation surplus in OCI for the asset
     * is transferred to retained earnings (booked as a Reserve movement out
     * of OCI, not through P&L per GRAP 103 / IPSAS 45).
     */
    public function recordDisposal(
        int $heritageAssetId,
        float $disposalProceeds,
        float $carryingAmountAtDisposal,
        string $valuationDate,
        ?string $reason = null,
        ?int $informationObjectId = null,
        ?int $userId = null,
        string $currency = 'ZAR'
    ): array {
        $ids = [];
        $gainOrLoss = round($disposalProceeds - $carryingAmountAtDisposal, 2);
        if (abs($gainOrLoss) >= 0.005) {
            $ids[] = $this->insertRow([
                'heritage_asset_id'    => $heritageAssetId,
                'information_object_id'=> $informationObjectId,
                'movement_type'        => 'disposal',
                'amount'               => $gainOrLoss,
                'currency'             => $currency,
                'valuation_date'       => $valuationDate,
                'reason'               => $reason,
                'posted_to'            => 'P&L',
                'created_by_user_id'   => $userId,
            ]);
        }
        // Recycle any residual revaluation surplus out of OCI to retained earnings.
        $residualSurplus = $this->getAccumulatedOciSurplus($heritageAssetId);
        if ($residualSurplus > 0.005) {
            $ids[] = $this->insertRow([
                'heritage_asset_id'    => $heritageAssetId,
                'information_object_id'=> $informationObjectId,
                'movement_type'        => 'disposal',
                'amount'               => -$residualSurplus,
                'currency'             => $currency,
                'valuation_date'       => $valuationDate,
                'reason'               => ($reason ?: '') . ' (OCI surplus transferred to retained earnings on disposal)',
                'posted_to'            => 'Reserve',
                'created_by_user_id'   => $userId,
            ]);
        }
        return $ids;
    }

    /**
     * Current accumulated OCI surplus for an asset (positive = surplus available,
     * negative or zero = none).
     */
    public function getAccumulatedOciSurplus(int $heritageAssetId): float
    {
        if (! Schema::hasTable('ahg_heritage_oci_movement')) {
            return 0.0;
        }
        $total = DB::table('ahg_heritage_oci_movement')
            ->where('heritage_asset_id', $heritageAssetId)
            ->where('posted_to', 'OCI')
            ->sum('amount');
        return (float) $total;
    }

    /**
     * Net cumulative impairment that has already passed through P&L for the asset.
     * Used to size reversal P&L recycling.
     */
    public function getAccumulatedPlImpairment(int $heritageAssetId): float
    {
        if (! Schema::hasTable('ahg_heritage_oci_movement')) {
            return 0.0;
        }
        $impairments = DB::table('ahg_heritage_oci_movement')
            ->where('heritage_asset_id', $heritageAssetId)
            ->where('posted_to', 'P&L')
            ->whereIn('movement_type', ['impairment', 'revaluation_down'])
            ->sum('amount'); // negative
        $reversals = DB::table('ahg_heritage_oci_movement')
            ->where('heritage_asset_id', $heritageAssetId)
            ->where('posted_to', 'P&L')
            ->where('movement_type', 'reversal')
            ->sum('amount'); // positive
        return (float) max(0.0, -1 * $impairments - $reversals);
    }

    /**
     * Period summary aggregated for disclosure-note generation.
     *
     * @return array{
     *   opening: array, additions: float, revaluation_up: float, revaluation_down: float,
     *   impairment: float, reversal: float, disposal: float, closing: float, by_posting: array
     * }
     */
    public function summariseForPeriod(string $periodStart, string $periodEnd): array
    {
        $out = [
            'period_start'    => $periodStart,
            'period_end'      => $periodEnd,
            'revaluation_up'  => 0.0,
            'revaluation_down'=> 0.0,
            'impairment'      => 0.0,
            'reversal'        => 0.0,
            'disposal'        => 0.0,
            'by_posting'      => ['OCI' => 0.0, 'P&L' => 0.0, 'Reserve' => 0.0],
            'count'           => 0,
        ];
        if (! Schema::hasTable('ahg_heritage_oci_movement')) {
            return $out;
        }
        $rows = DB::table('ahg_heritage_oci_movement')
            ->whereBetween('valuation_date', [$periodStart, $periodEnd])
            ->get();
        foreach ($rows as $r) {
            $type = $r->movement_type;
            if (isset($out[$type])) {
                $out[$type] += (float) $r->amount;
            }
            if (isset($out['by_posting'][$r->posted_to])) {
                $out['by_posting'][$r->posted_to] += (float) $r->amount;
            }
            $out['count']++;
        }
        return $out;
    }

    /**
     * Persist the row + emit audit-trail entry (#676 Phase 5 chain).
     */
    protected function insertRow(array $data): int
    {
        $data['valuation_date'] = $data['valuation_date'] ?? date('Y-m-d');
        $data['currency'] = $data['currency'] ?? 'ZAR';
        $data['amount'] = round((float) $data['amount'], 2);
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');

        $id = DB::table('ahg_heritage_oci_movement')->insertGetId($data);

        // Audit-trail integration (best-effort)
        try {
            if (class_exists(\AhgAuditTrail\Services\AuditService::class)) {
                /** @var \AhgAuditTrail\Services\AuditService $auditor */
                $auditor = app(\AhgAuditTrail\Services\AuditService::class);
                $auditor->log(
                    'heritage.oci_movement.recorded',
                    $data['information_object_id'] ?? null,
                    $data['created_by_user_id'] ?? null,
                    [
                        'oci_movement_id'   => $id,
                        'heritage_asset_id' => $data['heritage_asset_id'] ?? null,
                        'movement_type'     => $data['movement_type'],
                        'amount'            => $data['amount'],
                        'currency'          => $data['currency'],
                        'posted_to'         => $data['posted_to'],
                        'valuer_id'         => $data['valuer_id'] ?? null,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Audit chain unavailable - don't block the financial movement.
        }
        return (int) $id;
    }
}
