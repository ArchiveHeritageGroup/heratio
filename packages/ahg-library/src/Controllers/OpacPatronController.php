<?php

/**
 * OpacPatronController - Patron self-service portal (OPAC login/account)
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

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Services\LibraryPatronService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class OpacPatronController extends Controller
{
    protected LibraryCirculationService $circ;
    protected LibraryPatronService $patrons;

    public function __construct()
    {
        $this->circ = new LibraryCirculationService();
        $this->patrons = new LibraryPatronService();
    }

    /**
     * GET /opac/patron/login
     */
    public function login()
    {
        return view('ahg-library::opac.patron-login');
    }

    /**
     * POST /opac/patron/authenticate
     */
    public function authenticate(Request $request)
    {
        $validated = $request->validate([
            'card_number' => 'required|string|max:50',
            'pin'         => 'nullable|string|max:20',
        ]);

        $patron = DB::table('library_patron')
            ->where('card_number', trim($validated['card_number']))
            ->first();

        if (!$patron) {
            return redirect()
                ->route('opac.patron.login')
                ->with('error', 'No patron found with that card number.')
                ->withInput(['card_number' => trim($validated['card_number'])]);
        }

        if ($patron->borrowing_status === 'suspended') {
            return redirect()
                ->route('opac.patron.login')
                ->with('error', 'Your account is suspended. Please contact a librarian.')
                ->withInput(['card_number' => trim($validated['card_number'])]);
        }

        if ($patron->borrowing_status === 'expired') {
            return redirect()
                ->route('opac.patron.login')
                ->with('error', 'Your membership has expired. Please visit the library to renew.')
                ->withInput(['card_number' => trim($validated['card_number'])]);
        }

        // PIN check when stored in library_patron_pin table.
        $storedPin = DB::table('library_patron_pin')
            ->where('patron_id', $patron->id)
            ->first();
        if ($storedPin) {
            $supplied = trim($validated['pin'] ?? '');
            if ($supplied !== '' && !password_verify($supplied, $storedPin->pin_hash)) {
                return redirect()
                    ->route('opac.patron.login')
                    ->with('error', 'Incorrect PIN.')
                    ->withInput(['card_number' => trim($validated['card_number'])]);
            }
        }

        // Store patron identity in session.
        Session::put('patron_id', (int) $patron->id);
        Session::put('patron_card', $patron->card_number);
        Session::put('patron_name', trim(($patron->first_name ?? '') . ' ' . ($patron->last_name ?? '')));

        $firstName = trim($patron->first_name ?? '');
        return redirect()
            ->route('opac.patron.account')
            ->with('success', $firstName ? "Welcome, {$firstName}." : 'Welcome to your library account.');
    }

    /**
     * GET /opac/patron/account
     */
    public function account(Request $request)
    {
        $patronId = (int) Session::get('patron_id');
        if (!$patronId) {
            return redirect()->route('opac.patron.login');
        }

        $patron = $this->patrons->get($patronId);
        if (!$patron) {
            Session::forget(['patron_id', 'patron_card', 'patron_name']);
            return redirect()->route('opac.patron.login')->with('error', 'Session expired.');
        }

        $loans = collect($this->patrons->getActiveLoans($patronId));
        $holds = collect($this->patrons->getActiveHolds($patronId));
        $finesTotal = (float) ($patron->total_fines_owed ?? 0);

        return view('ahg-library::opac.patron-account', [
            'patron'    => $patron,
            'loans'     => $loans,
            'holds'     => $holds,
            'finesTotal'=> $finesTotal,
        ]);
    }

    /**
     * GET /opac/patron/loans
     */
    public function myLoans(Request $request)
    {
        $patronId = (int) Session::get('patron_id');
        if (!$patronId) {
            return redirect()->route('opac.patron.login');
        }

        $loans = collect($this->patrons->getActiveLoans($patronId));

        return view('ahg-library::opac.patron-loans', ['loans' => $loans]);
    }

    /**
     * GET /opac/patron/holds
     */
    public function myHolds(Request $request)
    {
        $patronId = (int) Session::get('patron_id');
        if (!$patronId) {
            return redirect()->route('opac.patron.login');
        }

        $holds = collect($this->patrons->getActiveHolds($patronId));

        return view('ahg-library::opac.patron-holds', ['holds' => $holds]);
    }

    /**
     * POST /opac/patron/holds/cancel
     */
    public function cancelHold(Request $request)
    {
        $patronId = (int) Session::get('patron_id');
        if (!$patronId) {
            return redirect()->route('opac.patron.login');
        }

        $holdId = (int) $request->input('hold_id', 0);

        $hold = DB::table('library_hold')
            ->where('id', $holdId)
            ->where('patron_id', $patronId)
            ->whereIn('status', ['pending', 'ready'])
            ->first();

        if (!$hold) {
            return redirect()
                ->route('opac.patron.holds')
                ->with('error', 'Hold not found or already cancelled.');
        }

        $ok = $this->circ->cancelHold($holdId, 'Patron self-cancelled');

        return redirect()
            ->route('opac.patron.holds')
            ->with('success', $ok ? 'Hold cancelled.' : 'Could not cancel hold.');
    }

    /**
     * GET /opac/patron/fines
     */
    public function myFines(Request $request)
    {
        $patronId = (int) Session::get('patron_id');
        if (!$patronId) {
            return redirect()->route('opac.patron.login');
        }

        $patron = $this->patrons->get($patronId);
        $finesTotal = (float) ($patron->total_fines_owed ?? 0);

        return view('ahg-library::opac.patron-fines', [
            'patron'    => $patron,
            'finesTotal'=> $finesTotal,
        ]);
    }

    /**
     * GET /opac/patron/logout
     */
    public function logout(Request $request)
    {
        Session::forget(['patron_id', 'patron_card', 'patron_name']);

        return redirect()
            ->route('library.opac')
            ->with('success', 'You have been signed out of your library account.');
    }

    /**
     * POST /opac/patron/renew-all
     * Renew all active checkouts for the session patron.
     */
    public function renewAll(Request $request)
    {
        $patronId = (int) Session::get('patron_id');
        if (!$patronId) {
            return redirect()->route('opac.patron.login');
        }

        $loans = $this->patrons->getActiveLoans($patronId);
        $renewed = 0;
        $failed = 0;

        foreach ($loans as $loan) {
            if ($this->circ->renew((int) $loan->id)) {
                $renewed++;
            } else {
                $failed++;
            }
        }

        if ($failed > 0) {
            return redirect()
                ->route('opac.patron.loans')
                ->with('error', "Renewed {$renewed} item(s); {$failed} could not be renewed.");
        }

        return redirect()
            ->route('opac.patron.loans')
            ->with('success', $renewed > 0
                ? "All {$renewed} item(s) renewed successfully."
                : 'No items to renew.');
    }

    /**
     * POST /opac/patron/renew-one/{checkoutId}
     */
    public function renewOne(Request $request, int $checkoutId)
    {
        $patronId = (int) Session::get('patron_id');
        if (!$patronId) {
            return redirect()->route('opac.patron.login');
        }

        $checkout = DB::table('library_checkout')
            ->where('id', $checkoutId)
            ->where('patron_id', $patronId)
            ->where('status', 'active')
            ->first();

        if (!$checkout) {
            return redirect()
                ->route('opac.patron.loans')
                ->with('error', 'Checkout not found or not yours.');
        }

        $ok = $this->circ->renew($checkoutId);

        return redirect()
            ->route('opac.patron.loans')
            ->with('success', $ok
                ? 'Item renewed.'
                : 'Renewal not allowed (max renewals reached or another patron is waiting).');
    }
}
