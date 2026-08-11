<?php

/*
 * ValuationBridgeController - explicit "Sync valuation to Heritage Assets"
 * action (#1460 Phase 2). Gated: no-ops with a clear message when
 * ahg-heritage-manage is not installed.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgSpectrum\Controllers;

use AhgSpectrum\Services\ValuationHeritageBridge;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValuationBridgeController extends Controller
{
    public function __construct(private ValuationHeritageBridge $bridge) {}

    public function sync(Request $request)
    {
        $slug = (string) $request->input('slug', '');
        $objectId = (int) (DB::table('slug')->where('slug', $slug)->value('object_id') ?? 0);

        if (! $objectId) {
            return back()->with('error', 'Object not found.');
        }
        if (! $this->bridge->available()) {
            return back()->with('error', 'Heritage Accounting (ahg-heritage-manage) is not installed on this instance.');
        }

        // Explicit action - create the heritage asset if the object has none yet.
        $result = $this->bridge->syncObject($objectId, true);

        $message = match ($result['status']) {
            'synced' => $result['created']
                ? 'Heritage asset created and valuation synced to Heritage Accounting.'
                : 'Valuation synced to the object\'s Heritage Accounting record.',
            'no_valuation' => 'No valuation to sync - record a valuation first.',
            default => 'Nothing was synced.',
        };

        $redirect = redirect()->route('ahgspectrum.workflow', ['slug' => $slug, 'procedure_type' => 'valuation']);

        return $result['status'] === 'synced'
            ? $redirect->with('success', $message)
            : $redirect->with('error', $message);
    }
}
