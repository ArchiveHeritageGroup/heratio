<?php

/**
 * CashDeskController - fine payment, waiver, receipts and cash sessions (#1474).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgCore\Services\AclService;
use AhgCore\Support\DropdownOptions;
use AhgLibrary\Services\LibraryCashDeskService;
use AhgLibrary\Services\LibraryFineException;
use AhgLibrary\Services\LibraryFineService;
use AhgLibrary\Services\LibraryPatronService;
use AhgLibrary\Support\LibraryBranch;
use AhgLibrary\Support\LibrarySettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CashDeskController extends Controller
{
    private const MONEY = 'numeric|min:0|max:99999999.99|decimal:0,2';

    public function __construct(
        private LibraryFineService $fines = new LibraryFineService(),
        private LibraryCashDeskService $desk = new LibraryCashDeskService(),
        private LibraryPatronService $patrons = new LibraryPatronService(),
    ) {
    }

    public function fines(int $patronId)
    {
        $patron = $this->patrons->get($patronId);
        abort_unless($patron, 404);

        $session = $this->desk->current((int) auth()->id());
        $operator = LibraryBranch::operatorBranchId();
        $currency = $session->currency ?? LibrarySettings::currency();
        $fines = $this->fines->outstanding($patronId);
        foreach ($fines as $f) {
            $f->payable = $f->currency === $currency;
            $f->can_waive = $operator === null || $this->fines->fineBranch($f) === $operator;
        }
        $tenders = $this->fines->tenders();

        // no-store: a page restored by Back must not resubmit a spent form key.
        return response()->view('ahg-library::cash-desk.fines', [
            'patron' => $patron,
            'fines' => $fines,
            'payableBalance' => array_sum(array_map(fn ($f) => $f->payable ? $f->balance : 0, $fines)),
            'otherCurrencies' => array_values(array_unique(array_map(fn ($f) => $f->currency, array_filter($fines, fn ($f) => ! $f->payable)))),
            'currency' => $currency,
            'tenders' => array_filter($tenders, fn ($t) => $t['is_cash'] !== null),
            'unsetTenders' => array_column(array_filter($tenders, fn ($t) => $t['is_cash'] === null), 'label'),
            'session' => $session,
            'sessionElsewhere' => $session && ! LibraryCashDeskService::atSessionBranch($session, $operator),
            'canWaive' => AclService::hasPermission((int) auth()->id(), 'delete'),
            'formKey' => (string) Str::uuid(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function pay(Request $request, int $patronId)
    {
        $v = $request->validate([
            'fine_ids' => 'required|array|min:1|max:200',
            'fine_ids.*' => 'integer|min:1',
            'amount' => 'required|' . self::MONEY,
            'payment_method' => 'required|string|max:30',
            'payment_reference' => 'nullable|string|max:100',
            'tendered' => 'nullable|' . self::MONEY,
            'form_key' => 'required|uuid',
        ]);

        try {
            $id = $this->fines->pay(
                $patronId, $v['fine_ids'], (float) $v['amount'], $v['payment_method'],
                $v['payment_reference'] ?? null,
                isset($v['tendered']) ? (float) $v['tendered'] : null,
                (int) auth()->id(), $v['form_key']
            );
        } catch (LibraryFineException $e) {
            // The form key stays behind, so the corrected form is a fresh attempt.
            return redirect()->route('library.fines.index', $patronId)->with('error', $e->getMessage())->withInput($request->except('form_key'));
        }

        return $this->fines->replayed
            ? redirect()->route('library.fines.receipt', $id)->with('warning', __('This payment was already recorded. No new payment was taken.'))
            : redirect()->route('library.fines.receipt', $id)->with('success', __('Payment recorded.'));
    }

    public function waive(Request $request, int $fineId)
    {
        $v = $request->validate(['waive_reason' => 'required|string|min:3|max:1000']);
        $fine = DB::table('library_fine')->where('id', $fineId)->first();
        abort_unless($fine, 404);
        $operator = LibraryBranch::operatorBranchId();
        abort_unless($operator === null || $this->fines->fineBranch($fine) === $operator, 403);
        $patronId = (int) $fine->patron_id;

        try {
            $this->fines->waive($fineId, $v['waive_reason'], (int) auth()->id());
        } catch (LibraryFineException $e) {
            return redirect()->route('library.fines.index', $patronId)->with('error', $e->getMessage());
        }

        return redirect()->route('library.fines.index', $patronId)->with('success', __('Fine waived.'));
    }

    public function receipt(int $id)
    {
        $receipt = $this->fines->receipt($id);
        abort_unless($receipt, 404);
        abort_unless((int) $receipt->issued_by === (int) auth()->id()
            || LibraryCashDeskService::canAccess($receipt, LibraryBranch::operatorBranchId()), 403);

        return view('ahg-library::cash-desk.receipt', [
            'receipt' => $receipt,
            'tenders' => $this->fines->tenders(),
            'issuer' => $this->userName((int) $receipt->issued_by),
        ]);
    }

    public function index()
    {
        $branch = LibraryBranch::operatorBranchId();
        $session = $this->desk->current((int) auth()->id());

        return view('ahg-library::cash-desk.index', [
            'session' => $session,
            'sessionElsewhere' => $session && ! LibraryCashDeskService::atSessionBranch($session, $branch),
            'currencies' => $this->desk->openableCurrencies(),
            'defaultCurrency' => LibrarySettings::currency(),
            'sessions' => $this->desk->recent($branch),
            'statuses' => DropdownOptions::resolve('library_cash_session_status', []),
            'branchName' => LibraryBranch::name($branch),
        ]);
    }

    public function open(Request $request)
    {
        $v = $request->validate([
            'opening_float' => 'required|' . self::MONEY,
            'currency' => ['nullable', 'string', Rule::in($this->desk->openableCurrencies())],
        ]);

        try {
            $id = $this->desk->open((int) auth()->id(), (float) $v['opening_float'], LibraryBranch::operatorBranchId(), $v['currency'] ?? null);
        } catch (LibraryFineException $e) {
            return redirect()->route('library.cash-desk.index')->with('error', $e->getMessage());
        }

        return redirect()->route('library.cash-desk.session', $id)->with('success', __('Cash session opened.'));
    }

    public function session(int $id)
    {
        $session = $this->accessible($id);
        $userId = (int) auth()->id();
        $supervisor = AclService::hasPermission($userId, 'delete');

        return view('ahg-library::cash-desk.session', [
            'session' => $session,
            'takings' => $this->desk->takings($id),
            'receipts' => DB::table('library_fine_receipt')->where('session_id', $id)->orderByDesc('id')->limit(200)->get(),
            'tenders' => $this->fines->tenders(),
            'statuses' => DropdownOptions::resolve('library_cash_session_status', []),
            'canClose' => $session->status === LibraryCashDeskService::OPEN && ((int) $session->opened_by === $userId || $supervisor),
            'canReconcile' => $session->status === LibraryCashDeskService::CLOSED && $supervisor
                && (int) $session->closed_by !== $userId && (int) $session->opened_by !== $userId,
            'names' => $this->userNames([$session->opened_by, $session->closed_by, $session->reconciled_by]),
        ]);
    }

    public function close(Request $request, int $id)
    {
        $this->accessible($id);
        $v = $request->validate([
            'counted_cash' => 'required|' . self::MONEY,
            'close_notes' => 'nullable|string|max:2000',
        ]);
        $userId = (int) auth()->id();

        try {
            $this->desk->close($id, (float) $v['counted_cash'], $v['close_notes'] ?? null, $userId, AclService::hasPermission($userId, 'delete'));
        } catch (LibraryFineException $e) {
            return redirect()->route('library.cash-desk.session', $id)->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('library.cash-desk.session', $id)->with('success', __('Cash session closed.'));
    }

    public function reconcile(Request $request, int $id)
    {
        $this->accessible($id);
        $v = $request->validate(['reconcile_notes' => 'nullable|string|max:2000']);

        try {
            $this->desk->reconcile($id, $v['reconcile_notes'] ?? null, (int) auth()->id());
        } catch (LibraryFineException $e) {
            return redirect()->route('library.cash-desk.session', $id)->with('error', $e->getMessage());
        }

        return redirect()->route('library.cash-desk.session', $id)->with('success', __('Session reconciled.'));
    }

    public function endOfDay(Request $request)
    {
        $v = $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $date = $v['date'] ?? now()->toDateString();
        $branch = LibraryBranch::operatorBranchId();

        return view('ahg-library::cash-desk.end-of-day', [
            'date' => $date,
            'report' => $this->desk->endOfDay($date, $branch),
            'tenders' => $this->fines->tenders(),
            'statuses' => DropdownOptions::resolve('library_cash_session_status', []),
            'branchName' => LibraryBranch::name($branch),
        ]);
    }

    private function accessible(int $id): object
    {
        $session = $this->desk->find($id);
        abort_unless($session, 404);
        // The opener can always reach their own session, or a drawer could be
        // stranded when they move branch or are locked to another one.
        abort_unless((int) $session->opened_by === (int) auth()->id()
            || LibraryCashDeskService::canAccess($session, LibraryBranch::operatorBranchId()), 403);

        return $session;
    }

    private function userName(int $id): string
    {
        return $this->userNames([$id])[$id] ?? ('#' . $id);
    }

    private function userNames(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        return $ids === [] ? [] : DB::table('user')->whereIn('id', $ids)->pluck('username', 'id')->all();
    }
}
