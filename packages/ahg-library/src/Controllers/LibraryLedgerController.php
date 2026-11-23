<?php

/**
 * LibraryLedgerController - staff screens for the invoicing sub-ledger
 * (heratio#1475 item 5). Rules live in LibraryLedgerService.
 *
 * @author    Dr Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgCore\Support\DropdownOptions;
use AhgLibrary\Services\Ledger\LedgerException;
use AhgLibrary\Services\Ledger\LedgerMoney;
use AhgLibrary\Services\Ledger\LibraryLedgerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryLedgerController extends Controller
{
    private const MONEY = 'regex:/^\d{1,9}(\.\d{1,2})?$/';

    public function __construct(private LibraryLedgerService $ledger) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys($this->statuses()))],
            'doc_type' => ['nullable', Rule::in(array_keys($this->docTypes()))],
            'q' => 'nullable|string|max:100',
        ]);

        return view('ahg-library::ledger.index', [
            'documents' => $this->ledger->listDocuments($filters),
            'filters' => $filters,
            'statuses' => $this->statuses(),
            'docTypes' => $this->docTypes(),
        ]);
    }

    public function create(Request $request): View
    {
        $request->validate(['order_id' => 'nullable|integer|min:1']);
        $order = $request->filled('order_id') ? DB::table('library_order')->where('id', $request->integer('order_id'))->first() : null;
        abort_if($request->filled('order_id') && !$order, 404);

        $lines = [];
        if ($order) {
            foreach ($this->ledger->orderLinesForInvoice((int) $order->id) as $l) {
                if ($l->to_bill > 0) {
                    $lines[] = ['order_line_id' => $l->id, 'description' => trim((string) $l->title) !== '' ? $l->title : ($l->isbn ?: __('Order line :id', ['id' => $l->id])), 'quantity' => $l->to_bill, 'unit_price' => $l->net_unit, 'tax_code' => ''];
                }
            }
        }

        return $this->form(null, $order, $lines);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInvoice($request);

        return $this->attempt(function () use ($data) {
            $id = $this->ledger->saveInvoiceDraft($data, null, Auth::id());

            return redirect()->route('library.ledger.show', $id)->with('success', __('Draft invoice saved.'));
        });
    }

    public function edit(int $id): View
    {
        $doc = $this->document($id);
        abort_unless($doc->status === LibraryLedgerService::STATUS_DRAFT && $doc->doc_type === LibraryLedgerService::TYPE_INVOICE, 404);
        $lines = array_map(fn ($l) => (array) $l, $this->ledger->getLines($id));

        return $this->form($doc, DB::table('library_order')->where('id', $doc->order_id)->first(), $lines);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $this->validateInvoice($request);

        return $this->attempt(function () use ($data, $id) {
            $this->ledger->saveInvoiceDraft($data, $id, Auth::id());

            return redirect()->route('library.ledger.show', $id)->with('success', __('Draft updated.'));
        });
    }

    public function show(int $id): View
    {
        $doc = $this->document($id);
        $isPostedInvoice = $doc->doc_type === LibraryLedgerService::TYPE_INVOICE && $doc->status === LibraryLedgerService::STATUS_POSTED;
        $lines = $this->ledger->getLines($id);

        return view('ahg-library::ledger.show', [
            'doc' => $doc,
            'lines' => $lines,
            'payments' => $this->ledger->getPayments($id),
            'credits' => $doc->doc_type === LibraryLedgerService::TYPE_INVOICE ? $this->ledger->getCredits($id) : [],
            'outstanding' => $isPostedInvoice ? LedgerMoney::fromCents($this->ledger->outstandingCents($doc, '9999-12-31')) : null,
            'isPostedInvoice' => $isPostedInvoice,
            'variances' => $doc->status === LibraryLedgerService::STATUS_DRAFT && $doc->doc_type === LibraryLedgerService::TYPE_INVOICE
                ? $this->ledger->matchVariances($doc, $lines) : [],
            'statuses' => $this->statuses(),
            'docTypes' => $this->docTypes(),
            'creditReasons' => $this->creditReasons(),
        ]);
    }

    public function post(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['variance_reason' => 'nullable|string|max:255']);

        return $this->attempt(function () use ($data, $id) {
            $this->ledger->post($id, $data['variance_reason'] ?? null, Auth::id());

            return redirect()->route('library.ledger.show', $id)->with('success', __('Document posted.'));
        });
    }

    public function void(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['void_reason' => 'required|string|max:255']);

        return $this->attempt(function () use ($data, $id) {
            $this->ledger->void($id, $data['void_reason'], Auth::id());

            return redirect()->route('library.ledger.show', $id)->with('success', __('Document voided.'));
        });
    }

    public function creditForm(int $id): View
    {
        $doc = $this->document($id);
        abort_unless($doc->doc_type === LibraryLedgerService::TYPE_INVOICE && $doc->status === LibraryLedgerService::STATUS_POSTED, 404);

        return view('ahg-library::ledger.credit', ['doc' => $doc, 'lines' => $this->ledger->creditableLines($id), 'creditReasons' => $this->creditReasons()]);
    }

    public function creditStore(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'credit_reason' => ['required', Rule::in(array_keys($this->creditReasons()))],
            'supplier_reference' => 'required|string|max:100',
            'document_date' => 'required|date_format:Y-m-d',
            'notes' => 'required|string|max:2000',
            'lines' => 'required|array|max:' . LibraryLedgerService::MAX_LINES,
            'lines.*.credited_line_id' => 'required|integer|min:1',
            'lines.*.quantity' => 'nullable|integer|min:0|max:65535',
            'lines.*.unit_price' => ['nullable', self::MONEY],
        ]);
        $data['lines'] = array_values(array_filter($data['lines'], fn ($l) => (int) ($l['quantity'] ?? 0) > 0));

        return $this->attempt(function () use ($data, $id) {
            $creditId = $this->ledger->createCreditDraft($id, $data, Auth::id());

            return redirect()->route('library.ledger.show', $creditId)->with('success', __('Draft credit note saved.'));
        });
    }

    public function paymentStore(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => 'required|date_format:Y-m-d',
            'amount' => ['required', self::MONEY],
            'reference' => 'required|string|max:100',
        ]);

        return $this->attempt(function () use ($data, $id) {
            $this->ledger->recordPayment($id, $data, Auth::id());

            return redirect()->route('library.ledger.show', $id)->with('success', __('Payment recorded.'));
        });
    }

    public function paymentReverse(int $id, int $paymentId): RedirectResponse
    {
        return $this->attempt(function () use ($id, $paymentId) {
            $this->ledger->reversePayment($id, $paymentId, Auth::id());

            return redirect()->route('library.ledger.show', $id)->with('success', __('Payment reversed.'));
        });
    }

    public function aged(Request $request): View
    {
        $data = $request->validate(['as_of' => 'nullable|date_format:Y-m-d']);
        $asOf = $data['as_of'] ?? now()->toDateString();

        return view('ahg-library::ledger.aged', ['rows' => $this->ledger->agedCreditors($asOf), 'asOf' => $asOf]);
    }

    public function budgetMatch(): View
    {
        return view('ahg-library::ledger.budget-match', ['rows' => $this->ledger->budgetMatch()]);
    }

    public function exports(): View
    {
        return view('ahg-library::ledger.exports', ['batches' => $this->ledger->listExportBatches()]);
    }

    public function createExport(): RedirectResponse
    {
        $batch = $this->ledger->createExportBatch();

        return redirect()->route('library.ledger.exports')->with(
            $batch ? 'success' : 'info',
            $batch ? __('Export batch created: :batch', ['batch' => $batch]) : __('Nothing new to export.'),
        );
    }

    public function downloadExport(string $batch): StreamedResponse
    {
        abort_unless(DB::table('library_ledger_document')->where('export_batch', $batch)->exists(), 404);

        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            fputcsv($out, LibraryLedgerService::EXPORT_HEADER, ',', '"', '');
            foreach ($this->ledger->exportRows($batch) as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $batch . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function rates(): View
    {
        return view('ahg-library::ledger.rates', [
            'rates' => $this->ledger->listRates(),
            'rounding' => $this->ledger->getRounding(),
            'roundingOptions' => DropdownOptions::resolve('library_tax_rounding', []),
        ]);
    }

    public function rateStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tax_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'label' => 'required|string|max:120',
            'rate_percent' => ['required', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'valid_from' => 'required|date_format:Y-m-d',
            'valid_to' => 'nullable|date_format:Y-m-d|after_or_equal:valid_from',
        ]);

        return $this->attempt(function () use ($data) {
            $this->ledger->addRate($data, Auth::id());

            return redirect()->route('library.ledger.rates')->with('success', __('Tax rate added.'));
        });
    }

    public function rateEnd(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['valid_to' => 'required|date_format:Y-m-d']);

        return $this->attempt(function () use ($data, $id) {
            $this->ledger->endRate($id, $data['valid_to']);

            return redirect()->route('library.ledger.rates')->with('success', __('Tax rate ended.'));
        });
    }

    public function roundingStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rounding' => ['required', Rule::in(array_keys(DropdownOptions::resolve('library_tax_rounding', [])))],
        ]);
        $this->ledger->setRounding($data['rounding']);

        return redirect()->route('library.ledger.rates')->with('success', __('Rounding rule saved.'));
    }

    // ---- Helpers ----------------------------------------------------------

    private function form(?object $doc, ?object $order, array $lines): View
    {
        return view('ahg-library::ledger.form', [
            'doc' => $doc,
            'order' => $order,
            'lines' => $lines,
            'orders' => DB::table('library_order')->where('status', '!=', 'cancelled')->orderByDesc('id')->limit(200)->get(['id', 'order_number', 'vendor_name']),
            'vendors' => DB::table('library_vendor')->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'taxCodes' => $this->ledger->activeTaxCodes(),
        ]);
    }

    private function validateInvoice(Request $request): array
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:library_order,id',
            'vendor_id' => 'required|integer|exists:library_vendor,id',
            'supplier_reference' => 'required|string|max:100',
            'document_date' => 'required|date_format:Y-m-d',
            'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:document_date',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|max:' . LibraryLedgerService::MAX_LINES,
            'lines.*.order_line_id' => 'nullable|integer|min:1',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity' => 'nullable|integer|min:1|max:65535',
            'lines.*.unit_price' => ['nullable', self::MONEY],
            'lines.*.tax_code' => 'nullable|string|max:30',
        ]);
        // Only a wholly empty row is a blank spare. A row with a figure on it
        // must never drop out of the total silently.
        $filled = fn ($l, $f) => trim((string) ($l[$f] ?? '')) !== '';
        $data['lines'] = array_filter($data['lines'], fn ($l) => $filled($l, 'description') || $filled($l, 'quantity') || $filled($l, 'unit_price') || $filled($l, 'tax_code'));
        $missing = [];
        foreach ($data['lines'] as $i => $l) {
            if (!$filled($l, 'description')) {
                $missing["lines.$i.description"] = __('Line :n has figures but no description.', ['n' => $i + 1]);
            }
        }
        if ($missing) {
            throw ValidationException::withMessages($missing);
        }
        $data['lines'] = array_values($data['lines']);

        return $data;
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            return $action();
        } catch (LedgerException $e) {
            return back()->withInput()->with('error', $e->getMessage())->with('ledger_variances', $e->variances);
        }
    }

    private function document(int $id): object
    {
        $doc = $this->ledger->getDocument($id);
        abort_unless($doc, 404);

        return $doc;
    }

    private function statuses(): array
    {
        return DropdownOptions::resolve('library_ledger_status', []);
    }

    private function creditReasons(): array
    {
        return DropdownOptions::resolve('library_ledger_credit_reason', []);
    }

    private function docTypes(): array
    {
        return DropdownOptions::resolve('library_ledger_doc_type', []);
    }
}
