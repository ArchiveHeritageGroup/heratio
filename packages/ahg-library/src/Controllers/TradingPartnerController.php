<?php

namespace AhgLibrary\Controllers;

use AhgLibrary\Http\Requests\StoreTradingPartnerRequest;
use AhgLibrary\Models\TradingPartner;
use AhgLibrary\Services\EdiAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TradingPartnerController
{
    public function __construct(
        protected EdiAdapter $ediAdapter
    ) {}

    /** Staff list */
    public function index(Request $request): View
    {
        $q = TradingPartner::query()->with('vendor');

        if ($request->filled('search')) {
            $q->where('edi_partner_code', 'LIKE', '%' . $request->search . '%');
        }
        if ($request->get('edi_type')) {
            $q->where('edi_type', $request->edi_type);
        }
        if ($request->get('active') === '1') {
            $q->where('is_active', true);
        } elseif ($request->get('active') === '0') {
            $q->where('is_active', false);
        }

        $partners = $q->orderBy('edi_partner_code')->paginate(25)->withQueryString();
        $stats = [
            'total'   => TradingPartner::count(),
            'active'  => TradingPartner::where('is_active', true)->count(),
            'errors'  => TradingPartner::whereNotNull('last_error_at')->count(),
            'sftp'    => TradingPartner::where('endpoint_type', 'SFTP')->count(),
            'as2'     => TradingPartner::where('endpoint_type', 'AS2')->count(),
        ];

        return view('ahg-library::circulation.trading-partners.index', compact('partners', 'stats'));
    }

    /** Create form */
    public function create(): View
    {
        $vendors = DB::table('library_vendor')->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'vendor_code as code']);
        return view('ahg-library::circulation.trading-partners.create', compact('vendors'));
    }

    /** Store new partner */
    public function store(StoreTradingPartnerRequest $request): RedirectResponse
    {
        TradingPartner::create($request->validated());
        return redirect()
            ->route('library.trading-partners.index')
            ->with('success', 'Trading partner created.');
    }

    /** Edit form */
    public function edit(TradingPartner $partner): View
    {
        $vendors = DB::table('library_vendor')->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'vendor_code as code']);
        return view('ahg-library::circulation.trading-partners.edit', compact('partner', 'vendors'));
    }

    /** Update partner */
    public function update(StoreTradingPartnerRequest $request, TradingPartner $partner): RedirectResponse
    {
        $partner->update($request->validated());
        return redirect()
            ->route('library.trading-partners.index')
            ->with('success', 'Trading partner updated.');
    }

    /** Delete partner */
    public function destroy(TradingPartner $partner): RedirectResponse
    {
        $inUse = DB::table('library_ill_request')
            ->where('trading_partner_id', $partner->id)
            ->exists();

        if ($inUse) {
            return redirect()
                ->route('library.trading-partners.index')
                ->with('error', 'Cannot delete: partner is linked to ILL requests. Deactivate instead.');
        }

        $partner->delete();
        return redirect()
            ->route('library.trading-partners.index')
            ->with('success', 'Trading partner deleted.');
    }

    /** Test connection */
    public function test(TradingPartner $partner): \Illuminate\Http\JsonResponse
    {
        $result = $this->ediAdapter->testConnection($partner);
        return response()->json($result);
    }

    /** Toggle active / inactive */
    public function toggle(TradingPartner $partner): RedirectResponse
    {
        $partner->update(['is_active' => !$partner->is_active]);
        $msg = $partner->is_active ? 'activated.' : 'deactivated.';
        return redirect()
            ->route('library.trading-partners.index')
            ->with('success', "Partner {$msg}");
    }

    /** Preview EDI message for a given ILL request (id param) */
    public function previewMessage(Request $request, TradingPartner $partner): \Illuminate\Http\JsonResponse
    {
        $illId = $request->get('ill_request_id');
        if (!$illId) {
            return response()->json(['ok' => false, 'message' => 'ill_request_id required.'], 422);
        }

        $ill = DB::table('library_ill_request')->find($illId);
        if (!$ill) {
            return response()->json(['ok' => false, 'message' => 'ILL request not found.'], 404);
        }

        $adapter = new EdiAdapter($partner);
        $msg = $adapter->buildIllRequestMessage(new \AhgLibrary\Models\IllRequest((array) $ill));

        return response()->json([
            'ok'  => true,
            'msg_ref' => $msg['msg_ref'],
            'type' => $msg['type'],
            'envelope' => $msg['envelope'],
            'preview' => $msg['raw'],
        ]);
    }
}
