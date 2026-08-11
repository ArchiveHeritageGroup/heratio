<?php

/*
 * InsuranceHeritageBridge - copies a Spectrum object-insurance policy onto the
 * object's GRAP heritage asset (#1460 Phase 2).
 *
 * Fully gated: no-op when ahg-heritage-manage is absent. Writes heritage_asset
 * directly behind Schema::hasTable/hasColumn guards - no cross-package class
 * dependency.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InsuranceHeritageBridge
{
    public function available(): bool
    {
        try {
            return Schema::hasTable('heritage_asset');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{status:string, heritage_asset_id?:int, value?:float, created?:bool}
     */
    public function syncObject(int $objectId, bool $createIfMissing = false): array
    {
        if (! $this->available()) {
            return ['status' => 'unavailable'];
        }
        if (! Schema::hasTable('spectrum_object_insurance')) {
            return ['status' => 'no_insurance'];
        }

        $ins = DB::table('spectrum_object_insurance')
            ->where('object_id', $objectId)
            ->orderByDesc('is_active')
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->first();
        if (! $ins) {
            return ['status' => 'no_insurance'];
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

        $value = $ins->coverage_amount !== null ? (float) $ins->coverage_amount : null;

        $update = $this->onlyColumns('heritage_asset', [
            'insurance_value' => $value,
            'insurance_policy_number' => $ins->policy_number ?: null,
            'insurance_provider' => $ins->insurer ?: null,
            'insurance_expiry_date' => $ins->end_date ?: null,
            'insurance_required' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($update) {
            DB::table('heritage_asset')->where('id', $asset->id)->update($update);
        }

        return ['status' => 'synced', 'heritage_asset_id' => (int) $asset->id, 'value' => $value, 'created' => $created];
    }

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
