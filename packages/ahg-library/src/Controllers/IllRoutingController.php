<?php

/**
 * IllRoutingController - staff rota, per-request routing and the partner inbound endpoint (#1475).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgCore\Services\AhgSettingsService;
use AhgLibrary\Models\TradingPartner;
use AhgLibrary\Services\IllInboundEdiService;
use AhgLibrary\Services\IllRoutingService;
use AhgLibrary\Services\LibraryIllService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IllRoutingController
{
    private const MAX_INBOUND_BYTES = 262144;

    public function __construct(
        protected IllRoutingService $routing,
        protected IllInboundEdiService $inbound,
        protected LibraryIllService $ill,
    ) {}

    public function index(): View
    {
        $entries = DB::table('library_ill_rota_entry as e')
            ->join('library_trading_partner as p', 'p.id', '=', 'e.trading_partner_id')
            ->orderBy('e.priority')->orderBy('p.edi_partner_code')
            ->get(['e.id', 'e.trading_partner_id', 'e.priority', 'e.timeout_hours', 'e.material_types',
                'e.is_active', 'p.edi_partner_code', 'p.test_mode', 'p.is_active as partner_active',
                DB::raw('CASE WHEN e.inbound_token_hash IS NULL THEN 0 ELSE 1 END AS has_token')]);

        $available = TradingPartner::active()
            ->whereNotIn('id', $entries->pluck('trading_partner_id'))
            ->orderBy('edi_partner_code')
            ->get(['id', 'edi_partner_code']);

        $recent = DB::table('library_ill_edi_inbound as i')
            ->leftJoin('library_trading_partner as p', 'p.id', '=', 'i.trading_partner_id')
            ->orderByDesc('i.id')->limit(25)
            ->get(['i.id', 'i.ill_request_id', 'i.ill_number', 'i.raw_status', 'i.outcome', 'i.detail',
                'i.created_at', 'p.edi_partner_code']);

        return view('ahg-library::ill-routing.index', [
            'entries'     => $entries,
            'available'   => $available,
            'recent'      => $recent,
            'autoEnabled' => $this->routing->autoRoutingEnabled(),
            'skipped'     => $this->routing->skippedSuppliers(),
            'attention'   => $this->routing->attentionList(),
            'outcomes'    => AhgSettingsService::getDropdownChoices('library_ill_inbound_outcome', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEntry($request) + $request->validate([
            'trading_partner_id' => ['required', 'integer', 'exists:library_trading_partner,id',
                Rule::unique('library_ill_rota_entry', 'trading_partner_id')],
        ]);

        DB::table('library_ill_rota_entry')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('library.ill-routing.index')->with('success', __('Supplier added to the rota.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless(DB::table('library_ill_rota_entry')->where('id', $id)->exists(), 404);
        DB::table('library_ill_rota_entry')->where('id', $id)
            ->update($this->validateEntry($request) + ['updated_at' => now()]);

        return redirect()->route('library.ill-routing.index')->with('success', __('Rota entry updated.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::table('library_ill_rota_entry')->where('id', $id)->delete();

        return redirect()->route('library.ill-routing.index')->with('success', __('Supplier removed from the rota.'));
    }

    /** Issue a new inbound token. It is shown once and only its hash is stored. */
    public function rotateToken(int $id): RedirectResponse
    {
        abort_unless(DB::table('library_ill_rota_entry')->where('id', $id)->exists(), 404);
        $token = Str::random(48);
        DB::table('library_ill_rota_entry')->where('id', $id)->update([
            'inbound_token_hash' => hash('sha256', $token),
            'updated_at'         => now(),
        ]);

        return redirect()->route('library.ill-routing.index')->with('ill_inbound_token', $token);
    }

    public function toggleAuto(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        AhgSettingsService::set(IllRoutingService::SETTING_AUTO, $data['enabled'] ? '1' : '0', 'library');

        return redirect()->route('library.ill-routing.index')->with('success',
            $data['enabled'] ? __('Automatic routing is on.') : __('Automatic routing is off.'));
    }

    public function requestRouting(int $id): View
    {
        $ill = $this->ill->get($id);
        abort_unless($ill, 404);

        $attempts = $this->routing->attemptsFor($id);
        $state = $this->routing->routingState($ill, $attempts);

        return view('ahg-library::ill-routing.request', [
            'ill'      => $ill,
            'attempts' => $attempts,
            'next'     => $state['next'],
            'state'    => $state,
            'skipped'  => $this->routing->skippedSuppliers(),
            'statuses' => AhgSettingsService::getDropdownChoices('library_ill_attempt_status', false),
        ]);
    }

    public function route(int $id): RedirectResponse
    {
        $result = $this->routing->route($id);

        return redirect()->route('library.ill-routing.request', $id)
            ->with(in_array($result['outcome'], ['sent', 'exhausted'], true) ? 'success' : 'error', $result['message']);
    }

    public function stopWaiting(int $id): RedirectResponse
    {
        return $this->backToRequest($id, $this->routing->stopWaiting($id), 'withdrawn');
    }

    public function retry(int $id): RedirectResponse
    {
        return $this->backToRequest($id, $this->routing->retryFailed($id), 'sent');
    }

    public function closeUnreachable(int $id): RedirectResponse
    {
        return $this->backToRequest($id, $this->routing->closeUnreachable($id), 'exhausted');
    }

    private function backToRequest(int $id, array $result, string $successOutcome): RedirectResponse
    {
        return redirect()->route('library.ill-routing.request', $id)
            ->with($result['outcome'] === $successOutcome ? 'success' : 'error', $result['message']);
    }

    /** Staff enter a reply that arrived outside the endpoint (post, email, file). */
    public function recordReply(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'trading_partner_id' => ['required', 'integer', Rule::exists('library_ill_routing_attempt', 'trading_partner_id')
                ->where('ill_request_id', $id)],
            'message' => 'required|string|max:' . self::MAX_INBOUND_BYTES,
        ]);

        $result = $this->inbound->process(TradingPartner::findOrFail($data['trading_partner_id']), $data['message'], $id);
        $applied = $result['outcome'] === IllInboundEdiService::OUTCOME_APPLIED;

        return redirect()->route('library.ill-routing.request', $id)->with(
            $applied ? 'success' : 'error',
            trim(__('Reply result: :outcome.', ['outcome' => $result['outcome']]) . ' ' . ($result['detail'] ?? ''))
        );
    }

    /** Machine endpoint. Authenticated by the partner's own bearer token. */
    public function receive(Request $request): JsonResponse
    {
        $token = (string) $request->bearerToken();
        $entry = $token === '' ? null : DB::table('library_ill_rota_entry')
            ->where('inbound_token_hash', hash('sha256', $token))
            ->where('is_active', 1)
            ->first(['trading_partner_id']);
        $partner = $entry ? TradingPartner::active()->find($entry->trading_partner_id) : null;
        if (!$partner) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $raw = $request->getContent();
        if (strlen($raw) > self::MAX_INBOUND_BYTES) {
            return response()->json(['ok' => false, 'error' => 'payload_too_large'], 413);
        }
        if (trim($raw) === '' || !mb_check_encoding($raw, 'UTF-8')) {
            return response()->json(['ok' => false, 'error' => 'invalid_payload'], 400);
        }

        $result = $this->inbound->process($partner, $raw);
        $status = match ($result['outcome']) {
            IllInboundEdiService::OUTCOME_NO_MATCH, IllInboundEdiService::OUTCOME_UNMAPPED => 422,
            IllInboundEdiService::OUTCOME_INVALID, IllInboundEdiService::OUTCOME_STALE,
            IllInboundEdiService::OUTCOME_PROCESSING => 409,
            default => 200,
        };

        return response()->json([
            'ok'        => $status === 200,
            'outcome'   => $result['outcome'],
            'duplicate' => $result['duplicate'],
        ], $status);
    }

    private function validateEntry(Request $request): array
    {
        $data = $request->validate([
            'priority'       => 'required|integer|min:1|max:999',
            'timeout_hours'  => 'required|integer|min:1|max:720',
            'material_types' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_, -]*$/'],
            'is_active'      => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active') ? 1 : 0;
        $data['material_types'] = trim((string) ($data['material_types'] ?? '')) ?: null;

        return $data;
    }
}
