<?php

/*
 * HeritageBridgeController - explicit "sync to Heritage Assets" actions for the
 * Disposal and Insurance procedures (#1460 Phase 2). Gated: each no-ops with a
 * clear message when ahg-heritage-manage is not installed.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgSpectrum\Controllers;

use AhgSpectrum\Services\DisposalHeritageBridge;
use AhgSpectrum\Services\InsuranceHeritageBridge;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HeritageBridgeController extends Controller
{
    public function disposal(Request $request, DisposalHeritageBridge $bridge)
    {
        return $this->run($request, $bridge, 'deaccession', [
            'synced_created' => 'Heritage asset created and disposal recorded as GRAP derecognition.',
            'synced' => 'Disposal recorded as GRAP derecognition on the heritage asset.',
            'empty' => 'No disposal to sync - record the deaccession/disposal first.',
        ]);
    }

    public function insurance(Request $request, InsuranceHeritageBridge $bridge)
    {
        return $this->run($request, $bridge, 'insurance', [
            'synced_created' => 'Heritage asset created and insurance details synced.',
            'synced' => 'Insurance details synced to the heritage asset.',
            'empty' => 'No insurance policy to sync - record the insurance first.',
        ]);
    }

    /** @param object{available:callable,syncObject:callable} $bridge */
    private function run(Request $request, $bridge, string $procedureType, array $messages)
    {
        $slug = (string) $request->input('slug', '');
        $objectId = (int) (DB::table('slug')->where('slug', $slug)->value('object_id') ?? 0);

        if (! $objectId) {
            return back()->with('error', 'Object not found.');
        }
        if (! $bridge->available()) {
            return back()->with('error', 'Heritage Accounting (ahg-heritage-manage) is not installed on this instance.');
        }

        $result = $bridge->syncObject($objectId, true);

        $redirect = redirect()->route('ahgspectrum.workflow', ['slug' => $slug, 'procedure_type' => $procedureType]);

        if ($result['status'] === 'synced') {
            return $redirect->with('success', ! empty($result['created']) ? $messages['synced_created'] : $messages['synced']);
        }

        return $redirect->with('error', $messages['empty']);
    }
}
