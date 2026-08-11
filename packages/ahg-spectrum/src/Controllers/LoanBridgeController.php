<?php

/*
 * LoanBridgeController - explicit "Create / sync loan record" action for the
 * Spectrum loan_in / loan_out procedures (#1460 Phase 2). Gated: no-ops with a
 * clear message when ahg-loan is not installed.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgSpectrum\Controllers;

use AhgSpectrum\Services\LoanBridge;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanBridgeController extends Controller
{
    public function __construct(private LoanBridge $bridge) {}

    public function sync(Request $request)
    {
        $slug = (string) $request->input('slug', '');
        $direction = (string) $request->input('procedure_type', '');
        $objectId = (int) (DB::table('slug')->where('slug', $slug)->value('object_id') ?? 0);

        if (! $objectId || ! in_array($direction, ['loan_in', 'loan_out'], true)) {
            return back()->with('error', 'Invalid loan request.');
        }
        if (! $this->bridge->available()) {
            return back()->with('error', 'The Loans module (ahg-loan) is not installed on this instance.');
        }

        $result = $this->bridge->syncObject($objectId, $direction, true);

        $message = match ($result['status']) {
            'synced' => ($result['created'] ? 'Loan record created' : 'Loan record updated')
                .' ('.$result['loan_number'].') in the Loans module.',
            'no_loan' => 'No loan procedure to hand off - record the loan details first.',
            default => 'Nothing was synced.',
        };

        $redirect = redirect()->route('ahgspectrum.workflow', ['slug' => $slug, 'procedure_type' => $direction]);

        return $result['status'] === 'synced'
            ? $redirect->with('success', $message)
            : $redirect->with('error', $message);
    }
}
