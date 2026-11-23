<?php

namespace AhgHeritageManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only comparison of the two asset registers, the evidence needed before
 * deciding how to consolidate them. It changes nothing.
 */
class HeritageRegisterReconciliationService
{
    public function compare(): array
    {
        if (! Schema::hasTable('heritage_asset') || ! Schema::hasTable('ipsas_heritage_asset')) {
            return ['available' => false];
        }

        $heritage = DB::table('heritage_asset')->whereNotNull('information_object_id')
            ->pluck('current_carrying_amount', 'information_object_id');
        $ipsas = DB::table('ipsas_heritage_asset')->get(['id', 'asset_number', 'information_object_id', 'current_value']);

        $matched = 0;
        $unlinked = 0;
        $onlyIpsas = [];
        $valueDifferences = [];
        $seen = [];
        foreach ($ipsas as $row) {
            if ($row->information_object_id === null) {
                $unlinked++;
                continue;
            }
            $seen[$row->information_object_id] = true;
            if (! $heritage->has($row->information_object_id)) {
                $onlyIpsas[] = $row->asset_number;
                continue;
            }
            $matched++;
            $a = round((float) $heritage[$row->information_object_id], 2);
            $b = round((float) $row->current_value, 2);
            if ($a !== $b) {
                $valueDifferences[] = ['information_object_id' => (int) $row->information_object_id, 'asset_number' => $row->asset_number, 'heritage_asset' => $a, 'ipsas' => $b];
            }
        }

        return [
            'available' => true,
            'heritage_total' => DB::table('heritage_asset')->count(),
            'ipsas_total' => $ipsas->count(),
            'matched' => $matched,
            'ipsas_without_record' => $unlinked,
            'only_in_ipsas' => $onlyIpsas,
            'only_in_heritage' => $heritage->keys()->reject(fn ($id) => isset($seen[$id]))->count(),
            'value_differences' => $valueDifferences,
        ];
    }
}
