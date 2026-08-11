<?php

/*
 * DisposalHeritageBridge - records a Spectrum disposal/deaccession as GRAP
 * derecognition on the object's heritage asset (#1460 Phase 2).
 *
 * Fully gated: no-op when ahg-heritage-manage is absent. Writes heritage_asset
 * directly behind Schema::hasTable/hasColumn guards - no cross-package class
 * dependency. GRAP gain/loss on derecognition = proceeds - carrying amount.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DisposalHeritageBridge
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
     * @return array{status:string, heritage_asset_id?:int, proceeds?:float, gain_loss?:float, created?:bool}
     */
    public function syncObject(int $objectId, bool $createIfMissing = false): array
    {
        if (! $this->available()) {
            return ['status' => 'unavailable'];
        }
        if (! Schema::hasTable('spectrum_deaccession')) {
            return ['status' => 'no_disposal'];
        }

        $d = DB::table('spectrum_deaccession')
            ->where('object_id', $objectId)
            ->orderByDesc('id')
            ->first();
        if (! $d) {
            return ['status' => 'no_disposal'];
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

        $proceeds = $d->disposal_price !== null ? (float) $d->disposal_price : null;
        $carrying = (float) ($asset->current_carrying_amount ?? $asset->last_valuation_amount ?? 0);
        $gainLoss = $proceeds !== null ? ($proceeds - $carrying) : null;

        $update = $this->onlyColumns('heritage_asset', [
            'derecognition_date' => $d->disposal_date ?: $d->deaccession_date,
            'derecognition_reason' => trim((string) ($d->deaccession_reason ?: '').(($d->disposal_method) ? ' ('.$d->disposal_method.')' : '')) ?: null,
            'derecognition_proceeds' => $proceeds,
            'gain_loss_on_derecognition' => $gainLoss,
            'recognition_status' => 'derecognised',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($update) {
            DB::table('heritage_asset')->where('id', $asset->id)->update($update);
        }

        return ['status' => 'synced', 'heritage_asset_id' => (int) $asset->id, 'proceeds' => $proceeds, 'gain_loss' => $gainLoss, 'created' => $created];
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
