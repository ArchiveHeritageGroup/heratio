<?php

/*
 * ValuationHeritageBridge - pushes a Spectrum valuation into GRAP heritage-asset
 * accounting (#1460 Phase 2, "Valuation = Heritage Assets").
 *
 * Fully gated: if ahg-heritage-manage is not installed (no heritage_asset table)
 * every method is a clean no-op, so a minimal install behaves exactly as before.
 * Writes to the heritage tables DIRECTLY, guarded by Schema::hasTable/hasColumn,
 * so ahg-spectrum carries no hard dependency on the heritage package's classes.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValuationHeritageBridge
{
    /** True only when the heritage-accounting component is present. */
    public function available(): bool
    {
        try {
            return Schema::hasTable('heritage_asset');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Sync the object's latest Spectrum valuation into its heritage asset.
     *
     * @param bool $createIfMissing create a minimal heritage_asset when the
     *                              object has none (the explicit "Sync" action);
     *                              false = only update an existing asset (the
     *                              automatic on-edit path, no surprise creates).
     *
     * @return array{status:string, heritage_asset_id?:int, amount?:float, created?:bool}
     */
    public function syncObject(int $objectId, bool $createIfMissing = false): array
    {
        if (! $this->available()) {
            return ['status' => 'unavailable'];
        }
        if (! Schema::hasTable('spectrum_valuation')) {
            return ['status' => 'no_valuation'];
        }

        $val = DB::table('spectrum_valuation')
            ->where('object_id', $objectId)
            ->orderByDesc('is_current')
            ->orderByDesc('valuation_date')
            ->orderByDesc('id')
            ->first();

        if (! $val || $val->valuation_amount === null) {
            return ['status' => 'no_valuation'];
        }

        $asset = DB::table('heritage_asset')
            ->where('information_object_id', $objectId)
            ->orWhere('object_id', $objectId)
            ->first();

        $created = false;
        if (! $asset) {
            if (! $createIfMissing) {
                return ['status' => 'no_asset'];
            }
            $assetId = (int) DB::table('heritage_asset')->insertGetId($this->onlyColumns('heritage_asset', [
                'information_object_id' => $objectId,
                'object_id' => $objectId,
                'recognition_status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]));
            $asset = DB::table('heritage_asset')->where('id', $assetId)->first();
            $created = true;
        }

        $previousAmount = (float) ($asset->last_valuation_amount ?? 0);
        $newAmount = (float) $val->valuation_amount;

        // Prefer the heritage-side write path when ahg-heritage-manage is
        // present: it does the same asset update plus the rich
        // heritage_valuation_history row and the GRAP 103.51 OCI / P&L split,
        // so a Spectrum-driven valuation lands identically to one captured on
        // the heritage Add Valuation form (#1460). Soft-bound via class_exists
        // so this package keeps no hard dependency on that one; if anything
        // goes wrong we fall through to the inline write below.
        if (class_exists(\AhgHeritageManage\Services\HeritageValuationService::class)) {
            try {
                $result = app(\AhgHeritageManage\Services\HeritageValuationService::class)->record((int) $asset->id, [
                    'valuation_date' => $val->valuation_date,
                    'new_value' => $newAmount,
                    'valuation_method' => $val->valuation_type ?: ($asset->valuation_method ?? null),
                    'valuer_name' => $val->valuer_name ?: ($asset->valuer_name ?? null),
                    'valuation_report_reference' => $val->valuation_reference ?: ($asset->valuation_report_reference ?? null),
                    'notes' => 'Synced from Collections Procedure valuation',
                    'user_id' => auth()->id(),
                    'skip_if_unchanged' => true,
                ]);
                if (in_array($result['status'] ?? '', ['recorded', 'unchanged'], true)) {
                    return [
                        'status' => 'synced',
                        'heritage_asset_id' => (int) $asset->id,
                        'amount' => $newAmount,
                        'created' => $created,
                    ];
                }
            } catch (\Throwable $e) {
                // Fall through to the inline write.
            }
        }

        // Update the asset's valuation fields (only columns that exist).
        $update = $this->onlyColumns('heritage_asset', [
            'last_valuation_date' => $val->valuation_date,
            'last_valuation_amount' => $newAmount,
            'valuation_method' => $val->valuation_type ?: ($asset->valuation_method ?? null),
            'valuer_name' => $val->valuer_name ?: ($asset->valuer_name ?? null),
            'valuation_report_reference' => $val->valuation_reference ?: ($asset->valuation_report_reference ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // GRAP revaluation surplus: accumulate the movement (simplified).
        $delta = $newAmount - $previousAmount;
        if ($delta != 0.0 && Schema::hasColumn('heritage_asset', 'revaluation_surplus')) {
            $update['revaluation_surplus'] = (float) ($asset->revaluation_surplus ?? 0) + $delta;
        }
        if (Schema::hasColumn('heritage_asset', 'current_carrying_amount')) {
            $update['current_carrying_amount'] = $newAmount;
        }

        if ($update) {
            DB::table('heritage_asset')->where('id', $asset->id)->update($update);
        }

        // Log a heritage_asset_valuation row (sparse date-log), deduped by date.
        if (Schema::hasTable('heritage_asset_valuation')) {
            $exists = DB::table('heritage_asset_valuation')
                ->where('heritage_asset_id', $asset->id)
                ->where('valuation_date', $val->valuation_date)
                ->exists();
            if (! $exists) {
                DB::table('heritage_asset_valuation')->insert($this->onlyColumns('heritage_asset_valuation', [
                    'heritage_asset_id' => (int) $asset->id,
                    'valuation_date' => $val->valuation_date,
                    'created_at' => date('Y-m-d H:i:s'),
                ]));
            }
        }

        return ['status' => 'synced', 'heritage_asset_id' => (int) $asset->id, 'amount' => $newAmount, 'created' => $created];
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
