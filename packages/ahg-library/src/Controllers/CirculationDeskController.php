<?php

/**
 * CirculationDeskController - Circulation desk for librarians
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
use AhgLibrary\Support\LibrarySettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CirculationDeskController extends Controller
{
    protected LibraryCirculationService $circ;
    protected LibraryPatronService $patrons;
    protected LibrarySettings $settings;

    public function __construct()
    {
        $this->circ = new LibraryCirculationService();
        $this->patrons = new LibraryPatronService();
        $this->settings = new LibrarySettings();
    }

    /**
     * GET /library-manage/circulation
     * Circulation desk home — scan input and active loans summary.
     */
    public function index(Request $request)
    {
        $scanResult = $request->session()->get('circulation_scan_result');
        $scanError = $request->session()->get('circulation_scan_error');
        $request->session()->forget(['circulation_scan_result', 'circulation_scan_error']);

        $loans = collect($this->circ->listCheckouts(['status' => 'active']));

        return view('ahg-library::circulation.index', [
            'scanResult' => $scanResult,
            'scanError'  => $scanError,
            'loans'      => $loans,
        ]);
    }

    /**
     * POST /library-manage/circulation/scan
     * Process a barcode scan. Determines if it is a copy barcode or patron
     * card number and returns the appropriate result data.
     */
    public function scan(Request $request)
    {
        $barcode = trim($request->input('barcode', ''));
        if ($barcode === '') {
            return redirect()
                ->route('library.circulation.index')
                ->with('circulation_scan_error', 'No barcode entered.');
        }

        // Attempt to match a copy barcode first.
        $copy = DB::table('library_copy as cp')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($request) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('cp.barcode', $barcode)
            ->select(
                'cp.id as copy_id',
                'cp.barcode',
                'cp.shelf_location',
                'cp.status as copy_status',
                'li.id as item_id',
                'li.call_number',
                'li.information_object_id',
                'i18n.title',
            )
            ->first();

        if ($copy) {
            $holdCount = DB::table('library_hold')
                ->where('library_item_id', $copy->item_id)
                ->whereIn('status', ['pending', 'ready'])
                ->count();

            $checkout = $copy->copy_status === 'checked_out'
                ? DB::table('library_checkout as c')
                    ->leftJoin('library_patron as p', 'c.patron_id', '=', 'p.id')
                    ->where('c.copy_id', $copy->copy_id)
                    ->where('c.status', 'active')
                    ->select('c.id as checkout_id', 'c.due_date', 'c.renewed_count',
                             'p.first_name', 'p.last_name', 'p.id as patron_id',
                             'p.borrowing_status as patron_status')
                    ->first()
                : null;

            $result = (object) [
                'type'       => 'copy',
                'copy_id'    => (int) $copy->copy_id,
                'barcode'    => $copy->barcode,
                'title'      => $copy->title ?? '',
                'call_number'=> $copy->call_number ?? '',
                'shelf_location' => $copy->shelf_location ?? '',
                'copy_status'=> $copy->copy_status,
                'hold_count' => $holdCount,
                'checkout'   => $checkout
                    ? (object) [
                        'id'           => (int) $checkout->checkout_id,
                        'due_date'     => $checkout->due_date,
                        'renewed_count'=> $checkout->renewed_count ?? 0,
                        'patron_name'  => trim(($checkout->first_name ?? '') . ' ' . ($checkout->last_name ?? '')),
                        'patron_id'    => (int) $checkout->patron_id,
                        'patron_status'=> $checkout->patron_status ?? 'active',
                      ]
                    : null,
            ];

            return redirect()
                ->route('library.circulation.index')
                ->with('circulation_scan_result', $result);
        }

        // Try patron card number.
        $patron = $this->patrons->getByCardNumber($barcode);
        if ($patron) {
            $activeLoans = $this->patrons->getActiveLoans((int) $patron->id);
            $activeHolds = $this->patrons->getActiveHolds((int) $patron->id);
            $overdueCount = collect($activeLoans)->filter(fn($l) => strToTime($l->due_date ?? '') < time())->count();

            $result = (object) [
                'type'        => 'patron',
                'patron_id'   => (int) $patron->id,
                'card_number' => $patron->card_number,
                'name'        => trim(($patron->first_name ?? '') . ' ' . ($patron->last_name ?? '')),
                'patron_type' => $patron->patron_type ?? '',
                'status'      => $patron->borrowing_status ?? 'active',
                'loans_count' => count($activeLoans),
                'holds_count' => count($activeHolds),
                'overdue_count' => $overdueCount,
                'fines'       => (float) ($patron->total_fines_owed ?? 0),
            ];

            return redirect()
                ->route('library.circulation.index')
                ->with('circulation_scan_result', $result);
        }

        return redirect()
            ->route('library.circulation.index')
            ->with('circulation_scan_error', "Barcode '{$barcode}' not found in catalogue.");
    }

    /**
     * GET /library-manage/circulation/checkout/{copyId}
     * Pre-filled checkout form.
     */
    public function checkoutForm(Request $request, int $copyId)
    {
        $copy = DB::table('library_copy as cp')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('cp.id', $copyId)
            ->select('cp.id', 'cp.barcode', 'cp.shelf_location', 'cp.status as copy_status',
                     'li.id as item_id', 'li.call_number', 'li.material_type', 'i18n.title')
            ->first();

        if (!$copy) {
            abort(404);
        }

        $patronId = (int) $request->query('patron', 0);
        $patron = $patronId ? $this->patrons->get($patronId) : null;

        $loanDays = $copy->material_type
            ? $this->circ->resolveLoanDays($copy->material_type, $patron->patron_type ?? 'adult')
            : $this->settings->defaultLoanDays();

        return view('ahg-library::circulation.checkout', [
            'copy'      => $copy,
            'patron'    => $patron,
            'loanDays'  => $loanDays,
        ]);
    }

    /**
     * POST /library-manage/circulation/checkout
     * Process a checkout.
     */
    public function doCheckout(Request $request)
    {
        $validated = $request->validate([
            'copy_id'  => 'required|integer|min:1',
            'patron_id'=> 'required|integer|min:1',
        ]);

        $userId = auth()->check() ? auth()->id() : null;
        $checkoutId = $this->circ->checkout((int) $validated['copy_id'], (int) $validated['patron_id'], $userId);

        if (!$checkoutId) {
            return redirect()
                ->route('library.circulation.do-checkout')
                ->with('error', 'Checkout failed. The copy may already be checked out, or the patron has reached their loan limit, is suspended, or has exceeded the fine threshold.')
                ->withInput();
        }

        return redirect()
            ->route('library.circulation.index')
            ->with('success', 'Item checked out successfully.');
    }

    /**
     * GET /library-manage/circulation/return/{checkoutId}
     * Pre-filled return form.
     */
    public function returnForm(int $checkoutId)
    {
        $checkout = DB::table('library_checkout as c')
            ->leftJoin('library_copy as cp', 'c.copy_id', '=', 'cp.id')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('c.id', $checkoutId)
            ->select('c.id', 'c.copy_id', 'c.due_date', 'c.checkout_date',
                     'cp.barcode', 'li.call_number', 'i18n.title')
            ->first();

        if (!$checkout) {
            abort(404);
        }

        return view('ahg-library::circulation.return', ['checkout' => $checkout]);
    }

    /**
     * POST /library-manage/circulation/return
     * Process a return.
     */
    public function doReturn(Request $request)
    {
        $validated = $request->validate([
            'checkout_id' => 'required|integer|min:1',
            'condition'   => 'nullable|in:good,damaged,lost',
            'notes'       => 'nullable|string|max:1000',
        ]);

        $userId = auth()->check() ? auth()->id() : null;
        $ok = $this->circ->return(
            (int) $validated['checkout_id'],
            $userId,
            $validated['condition'] ?? null,
            $validated['notes'] ?? null,
        );

        if (!$ok) {
            return redirect()
                ->route('library.circulation.do-return')
                ->with('error', 'Return failed. The checkout may have already been processed.')
                ->withInput();
        }

        return redirect()
            ->route('library.circulation.index')
            ->with('success', 'Item returned successfully.');
    }

    /**
     * POST /library-manage/circulation/renew
     * Renew a checkout.
     */
    public function renew(Request $request)
    {
        $validated = $request->validate([
            'checkout_id' => 'required|integer|min:1',
        ]);

        $ok = $this->circ->renew((int) $validated['checkout_id']);

        if (!$ok) {
            return redirect()
                ->route('library.circulation.index')
                ->with('error', 'Renewal failed. The item may not be renewable (max renewals reached or another patron is waiting).');
        }

        return redirect()
            ->route('library.circulation.index')
            ->with('success', 'Item renewed successfully.');
    }

    /**
     * GET /library-manage/circulation/patron/{patronId}
     * Full patron history for circulation desk.
     */
    public function patronHistory(int $patronId)
    {
        $patron = $this->patrons->get($patronId);
        if (!$patron) {
            abort(404);
        }

        $loans = collect($this->patrons->getActiveLoans($patronId));
        $holds = collect($this->patrons->getActiveHolds($patronId));
        $fines = collect($this->getFineHistory($patronId));
        $pastLoans = collect($this->getLoanHistory($patronId));

        return view('ahg-library::circulation.patron-history', [
            'patron'    => $patron,
            'loans'     => $loans,
            'holds'      => $holds,
            'fines'      => $fines,
            'pastLoans' => $pastLoans,
        ]);
    }

    /**
     * GET /library-manage/circulation/loans  (JSON)
     * Active checkouts for AJAX refresh.
     */
    public function getLoans()
    {
        $loans = $this->circ->listCheckouts(['status' => 'active']);
        return response()->json(['success' => true, 'loans' => $loans]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    protected function getFineHistory(int $patronId): array
    {
        return DB::table('library_fine')
            ->where('patron_id', $patronId)
            ->orderByDesc('fine_date')
            ->limit(100)
            ->get()
            ->all();
    }

    protected function getLoanHistory(int $patronId): array
    {
        return DB::table('library_checkout as c')
            ->leftJoin('library_copy as cp', 'c.copy_id', '=', 'cp.id')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('c.patron_id', $patronId)
            ->where('c.status', 'returned')
            ->select('c.id', 'c.checkout_date', 'c.due_date', 'c.return_date',
                     'cp.barcode', 'li.call_number', 'i18n.title')
            ->orderByDesc('c.checkout_date')
            ->limit(100)
            ->get()
            ->all();
    }
}
