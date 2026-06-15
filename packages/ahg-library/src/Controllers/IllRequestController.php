<?php

namespace AhgLibrary\Controllers;

use AhgLibrary\Http\Requests\StoreIllRequestRequest;
use AhgLibrary\Models\IllRequest;
use AhgLibrary\Models\TradingPartner;
use AhgLibrary\Services\EdiAdapter;
use AhgLibrary\Services\LibraryIllService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IllRequestController
{
    public function __construct(
        protected LibraryIllService $illService,
        protected EdiAdapter $ediAdapter
    ) {}

    /** Staff list — ILL request management */
    public function index(Request $request): View
    {
        $q = DB::table('library_ill_request');

        if ($request->filled('search')) {
            $needle = '%' . $request->search . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('request_number', 'LIKE', $needle)
                  ->orWhere('title', 'LIKE', $needle)
                  ->orWhere('author', 'LIKE', $needle)
                  ->orWhere('isbn', 'LIKE', $needle);
            });
        }
        if ($request->get('status')) {
            $q->where('status', $request->status);
        }
        if ($request->get('type')) {
            $q->where('type', $request->type);
        }
        if ($request->get('overdue')) {
            $q->whereNotNull('due_date')
              ->where('due_date', '<', now()->toDateString())
              ->whereNotIn('status', $this->illService->terminalStatuses());
        }

        $requests = $q->orderByDesc('request_date')->paginate(25)->withQueryString();

        $statuses = $this->illService::STATUSES;
        $protocols = ['AARC', 'IFM', 'BLDSS', 'RLG', 'CUSTOM'];
        $partners = TradingPartner::active()->orderBy('edi_partner_code')->get(['id', 'edi_partner_code']);

        $counts = $this->illService->countByStatus();

        return view('ahg-library::circulation.ill-requests.index', compact(
            'requests', 'statuses', 'protocols', 'partners', 'counts'
        ));
    }

    /** Create form */
    public function create(): View
    {
        $partners = TradingPartner::active()->orderBy('edi_partner_code')->get(['id', 'edi_partner_code', 'edi_type']);
        $vendors = DB::table('library_vendor')->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'vendor_code as code']);
        $illNumber = $this->illService->generateIllNumber();
        $protocols = ['AARC', 'IFM', 'BLDSS', 'RLG', 'CUSTOM'];

        return view('ahg-library::circulation.ill-requests.create', compact(
            'partners', 'vendors', 'illNumber', 'protocols'
        ));
    }

    /** Store new ILL request */
    public function store(StoreIllRequestRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $id = $this->illService->create([
            'ill_number'           => $data['ill_number'] ?? null,
            'type'                 => $data['type'] ?? 'borrow',
            'title'                => $data['title'],
            'author'               => $data['author'] ?? null,
            'isbn'                => $data['isbn'] ?? null,
            'issn'                => $data['issn'] ?? null,
            'volume'              => $data['volume'] ?? null,
            'issue'               => $data['issue'] ?? null,
            'pages'               => $data['pages'] ?? null,
            'publication_year'    => $data['publication_year'] ?? null,
            'library_name'        => $data['library_name'] ?? null,
            'library_symbol'      => $data['library_symbol'] ?? null,
            'patron_id'           => $data['patron_id'] ?? null,
            'requester_note'      => $data['requester_note'] ?? null,
            'due_date'            => $data['due_date'] ?? null,
        ]);

        if ($id && !empty($data['request_type'])) {
            DB::table('library_ill_request')->where('id', $id)->update([
                'request_type'         => $data['request_type'],
                'borrowing_protocol'   => $data['borrowing_protocol'] ?? 'AARC',
                'material_type'       => $data['material_type'] ?? 'BOOK',
                'responder_library_id'=> $data['responder_library_id'] ?? null,
                'responder_note'      => $data['responder_note'] ?? null,
                'citation'             => $data['citation'] ?? null,
                'lender_string'       => $data['lender_string'] ?? null,
                'needed_by_date'      => $data['needed_by_date'] ?? null,
                'shipping_method'     => $data['shipping_method'] ?? null,
                'max_renewals'        => $data['max_renewals'] ?? 2,
                'trading_partner_id'  => $data['trading_partner_id'] ?? null,
                'cost_amount'          => $data['cost_amount'] ?? null,
                'cost_currency'        => $data['cost_currency'] ?? null,
                'edi_message_id'      => $data['edi_message_id'] ?? null,
            ]);
        }

        return redirect()
            ->route('library.ill-requests.index')
            ->with('success', 'ILL request created.');
    }

    /** Show detail */
    public function show(int $id): View|RedirectResponse
    {
        $ill = $this->illService->get($id);
        if (!$ill) {
            return redirect()->route('library.ill-requests.index')->with('error', 'ILL request not found.');
        }

        $partner = !empty($ill->trading_partner_id)
            ? TradingPartner::find($ill->trading_partner_id)
            : null;

        $availableTransitions = $this->illService->availableTransitions(
            $ill->status ?? 'pending',
            $ill->type ?? 'borrow'
        );

        $protocols = ['AARC', 'IFM', 'BLDSS', 'RLG', 'CUSTOM'];

        return view('ahg-library::circulation.ill-requests.show', compact(
            'ill', 'partner', 'availableTransitions', 'protocols'
        ));
    }

    /** Update ILL request */
    public function update(StoreIllRequestRequest $request, int $id): RedirectResponse
    {
        $data = $request->validated();
        unset($data['ill_number']); // protect ill_number

        $this->illService->update($id, $data);

        return redirect()
            ->route('library.ill-requests.show', $id)
            ->with('success', 'ILL request updated.');
    }

    /** Transition status (state machine) */
    public function transition(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'new_status' => 'required|in:pending,requested,shipped,received,returned,lost,cancelled,overdue,unfulfilled',
            'note'       => 'nullable|string|max:500',
        ]);

        $ok = $this->illService->transitionTo($id, $request->new_status, $request->note);

        if (!$ok) {
            return redirect()
                ->route('library.ill-requests.show', $id)
                ->with('error', 'Invalid status transition. Check the ISO 10160 state machine.');
        }

        return redirect()
            ->route('library.ill-requests.show', $id)
            ->with('success', "Status updated to {$request->new_status}.");
    }

    /** Send via EDI */
    public function sendEdi(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'trading_partner_id' => 'required|integer|exists:library_trading_partner,id',
        ]);

        $ill = $this->illService->get($id);
        if (!$ill) {
            return redirect()->route('library.ill-requests.index')->with('error', 'ILL request not found.');
        }

        $partner = TradingPartner::find($request->trading_partner_id);
        $adapter = new EdiAdapter($partner);

        $result = $adapter->sendIllRequest(new IllRequest((array) $ill));

        if ($result['ok']) {
            if (!empty($result['edi_message_id'])) {
                DB::table('library_ill_request')->where('id', $id)->update([
                    'edi_message_id'       => $result['edi_message_id'],
                    'trading_partner_id'  => $partner->id,
                ]);
            }
            // Transition to 'requested' if still pending
            if ($ill->status === 'pending') {
                $this->illService->transitionTo($id, 'requested', 'EDI message ' . ($result['edi_message_id'] ?? 'sent'));
            }
            return redirect()
                ->route('library.ill-requests.show', $id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('library.ill-requests.show', $id)
            ->with('error', 'EDI send failed: ' . $result['message']);
    }

    /** Delete */
    public function destroy(int $id): RedirectResponse
    {
        $ok = $this->illService->delete($id);
        return redirect()
            ->route('library.ill-requests.index')
            ->with($ok ? 'success' : 'error', $ok ? 'ILL request deleted.' : 'Delete failed.');
    }
}
