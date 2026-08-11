<?php

/**
 * HeritageValuationService - the single write path for a heritage-asset revaluation.
 *
 * Recording a valuation is not one write. Under GRAP 103 / IPSAS 45 it is a
 * history row, a set of asset carrying-value fields, and an OCI / P&L split
 * of the movement. Before this service each caller did its own subset - the
 * Add Valuation UI did nothing at all (it was a stub), and the Spectrum
 * valuation bridge (#1460) updated the asset fields but never posted the OCI
 * movement. Both now come through here so the GRAP arithmetic lives in one
 * place.
 *
 * Every write is column-guarded: on an install where a table or column is
 * absent that part is skipped rather than fataling, matching the gated
 * pattern the rest of the heritage/Spectrum bridges use.
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

class HeritageValuationService
{
    /**
     * GRAP 103.42 measurement bases offered on the Add Valuation form. Kept as
     * a plain map (not an ahg_dropdown taxonomy) because these are the terms
     * named in the standard, not a site-configurable vocabulary.
     */
    public const METHODS = [
        'market_value'        => 'Market value',
        'depreciated_replacement_cost' => 'Depreciated replacement cost',
        'replacement_cost'    => 'Replacement cost',
        'income_approach'     => 'Income approach',
        'cost'                => 'Cost',
        'insurance_valuation' => 'Insurance valuation',
        'expert_appraisal'    => 'Expert appraisal',
        'nominal'             => 'Nominal / R1',
    ];

    /** Is the accounting side installed at all? */
    public function available(): bool
    {
        try {
            return Schema::hasTable('heritage_asset');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Record a valuation against an existing heritage asset.
     *
     * $input keys: valuation_date, new_value (required); valuation_method,
     * valuer_name, valuer_credentials, valuer_organization, valuer_id,
     * valuation_report_reference, notes, currency, user_id.
     *
     * @return array{status:string, heritage_asset_id?:int, previous_value?:float,
     *               new_value?:float, change?:float, history_id?:int, oci_movement_ids?:int[]}
     */
    public function record(int $assetId, array $input): array
    {
        if (! $this->available()) {
            return ['status' => 'unavailable'];
        }

        $asset = DB::table('heritage_asset')->where('id', $assetId)->first();
        if (! $asset) {
            return ['status' => 'no_asset'];
        }

        $newValue = round((float) ($input['new_value'] ?? 0), 2);
        $date = $input['valuation_date'] ?? date('Y-m-d');

        // The value being replaced: the last valuation if there is one, else the
        // carrying amount the asset was recognised at.
        $previous = $asset->last_valuation_amount ?? ($asset->current_carrying_amount ?? 0);
        $previous = round((float) $previous, 2);
        $change = round($newValue - $previous, 2);

        // Only a surplus movement adds to the revaluation reserve; a deficit
        // unwinds it (floored at zero - the balance below that is a P&L expense,
        // which OciMovementService splits out on the ledger side).
        $existingSurplus = round((float) ($asset->revaluation_surplus ?? 0), 2);
        $surplusChange = $change > 0 ? $change : max(-$existingSurplus, $change);

        // Automated callers (the Spectrum valuation bridge re-runs on every
        // procedure PATCH) must not append an identical history row each time.
        // An operator submitting the form always writes - re-valuing at the
        // same figure on the same day is a real, deliberate act there.
        if (! empty($input['skip_if_unchanged'])
            && $change == 0.0
            && (string) ($asset->last_valuation_date ?? '') === (string) $date) {
            return [
                'status'            => 'unchanged',
                'heritage_asset_id' => $assetId,
                'previous_value'    => $previous,
                'new_value'         => $newValue,
                'change'            => 0.0,
                'oci_movement_ids'  => [],
            ];
        }

        $historyId = null;
        if (Schema::hasTable('heritage_valuation_history')) {
            $historyId = (int) DB::table('heritage_valuation_history')->insertGetId(
                $this->onlyColumns('heritage_valuation_history', [
                    'heritage_asset_id'          => $assetId,
                    'valuation_date'             => $date,
                    'previous_value'             => $previous,
                    'new_value'                  => $newValue,
                    'valuation_change'           => $change,
                    'valuation_method'           => $input['valuation_method'] ?? null,
                    'valuer_name'                => $input['valuer_name'] ?? null,
                    'valuer_credentials'         => $input['valuer_credentials'] ?? null,
                    'valuer_organization'        => $input['valuer_organization'] ?? null,
                    'valuer_id'                  => $input['valuer_id'] ?? null,
                    'valuation_report_reference' => $input['valuation_report_reference'] ?? null,
                    'revaluation_surplus_change' => $surplusChange,
                    'notes'                      => $input['notes'] ?? null,
                    'created_by'                 => $input['user_id'] ?? null,
                    'created_at'                 => date('Y-m-d H:i:s'),
                ])
            );
        }

        // Sparse date-log kept in step with the history table - the Spectrum
        // bridge has written this since v1.154.568, so dedupe by date.
        if (Schema::hasTable('heritage_asset_valuation')) {
            $exists = DB::table('heritage_asset_valuation')
                ->where('heritage_asset_id', $assetId)
                ->whereDate('valuation_date', $date)
                ->exists();
            if (! $exists) {
                DB::table('heritage_asset_valuation')->insert($this->onlyColumns('heritage_asset_valuation', [
                    'heritage_asset_id' => $assetId,
                    'valuation_date'    => $date,
                    'created_at'        => date('Y-m-d H:i:s'),
                ]));
            }
        }

        $update = $this->onlyColumns('heritage_asset', [
            'last_valuation_date'        => $date,
            'last_valuation_amount'      => $newValue,
            'current_carrying_amount'    => $newValue,
            'revaluation_surplus'        => round($existingSurplus + $surplusChange, 2),
            // Blank fields on the form leave the asset's existing value alone.
            'valuation_method'           => ($input['valuation_method'] ?? null) ?: ($asset->valuation_method ?? null),
            'valuer_name'                => ($input['valuer_name'] ?? null) ?: ($asset->valuer_name ?? null),
            'valuer_credentials'         => ($input['valuer_credentials'] ?? null) ?: ($asset->valuer_credentials ?? null),
            'valuation_report_reference' => ($input['valuation_report_reference'] ?? null) ?: ($asset->valuation_report_reference ?? null),
            'updated_at'                 => date('Y-m-d H:i:s'),
        ]);
        if (! empty($input['user_id']) && Schema::hasColumn('heritage_asset', 'updated_by')) {
            $update['updated_by'] = $input['user_id'];
        }
        if ($update) {
            DB::table('heritage_asset')->where('id', $assetId)->update($update);
        }

        // Post the OCI / P&L split. Gated: the ledger table ships in the
        // package install.sql but is absent on installs that predate it.
        $ociIds = [];
        try {
            if (Schema::hasTable('ahg_heritage_oci_movement')) {
                $ociIds = app(OciMovementService::class)->recordRevaluation(
                    $assetId,
                    $previous,
                    $newValue,
                    $date,
                    $input['valuer_id'] ?? null,
                    $input['valuation_method'] ?? null,
                    $input['notes'] ?? null,
                    $asset->information_object_id ?? ($asset->object_id ?? null),
                    $input['user_id'] ?? null,
                    $input['currency'] ?? 'ZAR'
                );
            }
        } catch (\Throwable $e) {
            // The ledger posting must never lose the valuation itself.
        }

        return [
            'status'            => 'recorded',
            'heritage_asset_id' => $assetId,
            'previous_value'    => $previous,
            'new_value'         => $newValue,
            'change'            => $change,
            'history_id'        => $historyId,
            'oci_movement_ids'  => $ociIds,
        ];
    }

    /** Valuation history for an asset, newest first. */
    public function history(int $assetId, int $limit = 20)
    {
        if (! Schema::hasTable('heritage_valuation_history')) {
            return collect();
        }

        return DB::table('heritage_valuation_history')
            ->where('heritage_asset_id', $assetId)
            ->orderByDesc('valuation_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** Filter an assoc array to columns that actually exist on the table. */
    private function onlyColumns(string $table, array $data): array
    {
        $out = [];
        foreach ($data as $col => $v) {
            try {
                if (Schema::hasColumn($table, $col)) {
                    $out[$col] = $v;
                }
            } catch (\Throwable $e) {
            }
        }

        return $out;
    }
}
