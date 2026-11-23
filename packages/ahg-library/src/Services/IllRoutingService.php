<?php

/**
 * IllRoutingService - supplier rota for borrowing requests (#1475).
 *
 * Sends a request to one supplier at a time, in rank order, and moves to the
 * next when a supplier declines, times out or cannot be reached. Rank is the
 * staff-set priority, then observed fill rate, then whoever was asked least
 * recently, so equal partners share the load.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use AhgCore\Services\AhgSettingsService;
use AhgLibrary\Models\IllRequest;
use AhgLibrary\Models\TradingPartner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IllRoutingService
{
    public const ATTEMPT_SENT        = 'sent';
    public const ATTEMPT_ACCEPTED    = 'accepted';
    public const ATTEMPT_DECLINED    = 'declined';
    public const ATTEMPT_TIMED_OUT   = 'timed_out';
    public const ATTEMPT_SEND_FAILED = 'send_failed';
    public const ATTEMPT_WITHDRAWN   = 'withdrawn';
    /** Staff sent this one by hand, which takes the request off the rota. */
    public const ATTEMPT_MANUAL      = 'manual';

    /** Off by default: nothing goes to a partner unasked until staff enable it. */
    public const SETTING_AUTO = 'library_ill_auto_routing';

    /** A partner that fails this many sends is skipped for that request. */
    public const MAX_SEND_FAILURES = 3;

    /** Days an accepting supplier has to ship before staff are alerted. */
    public const SETTING_FULFILMENT_DAYS = 'library_ill_fulfilment_days';
    private const DEFAULT_FULFILMENT_DAYS = 14;

    private const FILL_RATE_WINDOW_DAYS = 365;

    public function __construct(protected LibraryIllService $ill) {}

    public function autoRoutingEnabled(): bool
    {
        return AhgSettingsService::getBool(self::SETTING_AUTO, false);
    }

    public function fulfilmentDeadline(): \Illuminate\Support\Carbon
    {
        return now()->addDays(max(1, AhgSettingsService::getInt(self::SETTING_FULFILMENT_DAYS, self::DEFAULT_FULFILMENT_DAYS)));
    }

    /**
     * Eligible suppliers for a request, best first. Test-mode partners and
     * transports this install cannot use are left out: nothing would reach them.
     *
     * @param  int[] $exclude trading partner ids
     */
    public function rankedSuppliers(object $ill, array $exclude = []): Collection
    {
        $material = strtoupper(trim((string) ($ill->material_type ?? '')));

        $entries = DB::table('library_ill_rota_entry as e')
            ->join('library_trading_partner as p', 'p.id', '=', 'e.trading_partner_id')
            ->where('e.is_active', 1)
            ->where('p.is_active', 1)
            ->where('p.test_mode', 0)
            ->when($exclude, fn ($q) => $q->whereNotIn('e.trading_partner_id', $exclude))
            ->get(['e.*', 'p.edi_partner_code', 'p.endpoint_type'])
            ->filter(function ($entry) use ($material) {
                if (!EdiAdapter::canTransmit($entry->endpoint_type)) {
                    return false;
                }

                $allowed = array_filter(array_map(
                    fn ($m) => strtoupper(trim($m)),
                    explode(',', (string) $entry->material_types)
                ));

                return !$allowed || in_array($material, $allowed, true);
            });

        if ($entries->isEmpty()) {
            return collect();
        }

        $stats = DB::table('library_ill_routing_attempt')
            ->whereIn('trading_partner_id', $entries->pluck('trading_partner_id'))
            ->where('created_at', '>=', now()->subDays(self::FILL_RATE_WINDOW_DAYS))
            ->groupBy('trading_partner_id')
            ->selectRaw('trading_partner_id,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS filled,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS unfilled,
                MAX(sent_at) AS last_sent', [self::ATTEMPT_ACCEPTED, self::ATTEMPT_DECLINED, self::ATTEMPT_TIMED_OUT])
            ->get()
            ->keyBy('trading_partner_id');

        return $entries->map(function ($entry) use ($stats) {
            $s = $stats[$entry->trading_partner_id] ?? null;
            $filled = (int) ($s->filled ?? 0);
            $unfilled = (int) ($s->unfilled ?? 0);
            // Smoothed, so one early decline does not bury a new partner.
            $entry->fill_rate = ($filled + 1) / ($filled + $unfilled + 2);
            $entry->last_sent = $s->last_sent ?? null;

            return $entry;
        })->sort(fn ($a, $b) => [(int) $a->priority, -$a->fill_rate, (string) $a->last_sent, (int) $a->id]
            <=> [(int) $b->priority, -$b->fill_rate, (string) $b->last_sent, (int) $b->id])->values();
    }

    /**
     * Send a request to its next supplier.
     *
     * @return array{outcome:string, message:string, partner?:string, attempt_id?:int}
     */
    public function route(int $id): array
    {
        $triedThisRun = [];

        while (true) {
            $claim = DB::transaction(fn () => $this->claimNext($id, $triedThisRun));
            if ($claim['outcome'] !== 'claimed') {
                return $claim;
            }

            $partner = TradingPartner::find($claim['partner_id']);
            $triedThisRun[] = $claim['partner_id'];

            try {
                $result = $partner
                    ? $this->transmit($partner, $claim['ill'])
                    : ['ok' => false, 'message' => 'Trading partner no longer exists.'];
            } catch (\Throwable $e) {
                Log::error('ILL routing send failed', ['attempt' => $claim['attempt_id'], 'error' => $e->getMessage()]);
                $result = ['ok' => false, 'message' => 'Transmission error, see the application log.'];
            }

            // A partner switched to test mode after the claim: nothing was sent.
            if (!empty($result['test_mode'])) {
                $result = ['ok' => false, 'message' => 'Partner is in test mode, nothing was transmitted.'];
            }

            if (!empty($result['ok'])) {
                return $this->recordSent($id, $claim, $partner, $result);
            }

            DB::table('library_ill_routing_attempt')->where('id', $claim['attempt_id'])->update([
                'status'        => self::ATTEMPT_SEND_FAILED,
                'send_failures' => DB::raw('send_failures + 1'),
                'expires_at'    => null,
                'note'          => mb_substr((string) ($result['message'] ?? 'Send failed'), 0, 500),
                'updated_at'    => now(),
            ]);
        }
    }

    /** Close expired attempts and fail over. Only runs when auto routing is on. */
    public function sweepTimeouts(bool $dryRun = false): array
    {
        $summary = ['timed_out' => 0, 'withdrawn' => 0, 'rerouted' => 0, 'retried' => 0, 'exhausted' => 0,
            'overdue_accepted' => $this->overdueAcceptedQuery()->count()];

        $expired = DB::table('library_ill_routing_attempt')
            ->where('status', self::ATTEMPT_SENT)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->get(['id', 'ill_request_id']);

        foreach ($expired as $attempt) {
            $ill = $this->ill->get((int) $attempt->ill_request_id);
            $open = $ill && $this->isOpen($ill);

            if ($dryRun) {
                $summary[$open ? 'timed_out' : 'withdrawn']++;
                continue;
            }

            // Conditional, so a reply landing mid-sweep wins.
            $changed = DB::table('library_ill_routing_attempt')
                ->where('id', $attempt->id)
                ->where('status', self::ATTEMPT_SENT)
                ->update([
                    'status'       => $open ? self::ATTEMPT_TIMED_OUT : self::ATTEMPT_WITHDRAWN,
                    'responded_at' => now(),
                    'updated_at'   => now(),
                ]);
            if (!$changed) {
                continue;
            }

            $summary[$open ? 'timed_out' : 'withdrawn']++;
            if ($open) {
                $outcome = $this->route((int) $attempt->ill_request_id)['outcome'];
                $summary['rerouted'] += $outcome === 'sent' ? 1 : 0;
                $summary['exhausted'] += $outcome === 'exhausted' ? 1 : 0;
            }
        }

        if ($dryRun) {
            return $summary;
        }

        // Open requests on the rota with nobody working on them: a failed send,
        // or a decline recorded while automatic routing was off.
        foreach ($this->idleRequestIds() as $requestId) {
            $outcome = $this->route($requestId)['outcome'];
            $summary['retried'] += $outcome === 'sent' ? 1 : 0;
            $summary['exhausted'] += $outcome === 'exhausted' ? 1 : 0;
        }

        return $summary;
    }

    /**
     * What staff can do with a request on the routing screen.
     *
     * @return array{next:Collection, live:?object, on_rota:bool, can_send:bool, can_exhaust:bool, unreachable:bool, overdue:bool}
     */
    public function routingState(object $ill, Collection $attempts): array
    {
        $open = ($ill->type ?? LibraryIllService::TYPE_BORROW) === LibraryIllService::TYPE_BORROW && $this->isOpen($ill);
        $live = $attempts->first(fn ($a) => in_array($a->status, [self::ATTEMPT_SENT, self::ATTEMPT_ACCEPTED], true));
        $onRota = !$attempts->contains('status', self::ATTEMPT_MANUAL)
            && !($ill->status === LibraryIllService::STATUS_REQUESTED && $attempts->isEmpty());
        $next = $open && $onRota ? $this->nextSuppliers($ill, $attempts) : collect();
        $idle = $open && $onRota && !$live;

        return [
            'next'        => $next,
            'live'        => $live,
            'on_rota'     => $onRota,
            'can_send'    => $idle && $next->isNotEmpty(),
            'can_exhaust' => $idle && $next->isEmpty() && $ill->status === LibraryIllService::STATUS_REQUESTED,
            'unreachable' => $idle && $next->isEmpty() && $ill->status === LibraryIllService::STATUS_PENDING && $attempts->isNotEmpty(),
            'overdue'     => $open && $live && $live->status === self::ATTEMPT_ACCEPTED
                && $live->expires_at && $live->expires_at <= now()->toDateTimeString(),
        ];
    }

    /** Active rota partners that routing skips, and why. */
    public function skippedSuppliers(): Collection
    {
        return DB::table('library_ill_rota_entry as e')
            ->join('library_trading_partner as p', 'p.id', '=', 'e.trading_partner_id')
            ->where('e.is_active', 1)
            ->where('p.is_active', 1)
            ->orderBy('p.edi_partner_code')
            ->get(['p.edi_partner_code', 'p.test_mode', 'p.endpoint_type'])
            ->filter(fn ($p) => $p->test_mode || !EdiAdapter::canTransmit($p->endpoint_type))
            ->map(function ($p) {
                $p->reason = $p->test_mode
                    ? __('test mode, nothing is transmitted')
                    : __('the :type transport is not available on this install', ['type' => $p->endpoint_type]);

                return $p;
            })->values();
    }

    /** Open requests that are waiting on a staff decision. */
    public function attentionList(int $limit = 50): Collection
    {
        $overdue = $this->overdueAcceptedQuery()->limit($limit)->pluck('a.ill_request_id')
            ->map(fn ($id) => [(int) $id, __('Supplier accepted but has not shipped by the deadline.')]);

        $unreachable = DB::table('library_ill_routing_attempt as a')
            ->join('library_ill_request as r', 'r.id', '=', 'a.ill_request_id')
            ->where('r.status', LibraryIllService::STATUS_PENDING)
            ->distinct()->limit($limit)->pluck('a.ill_request_id')
            ->filter(function ($id) {
                $ill = $this->ill->get((int) $id);

                return $ill && $this->routingState($ill, $this->attemptsFor((int) $id))['unreachable'];
            })
            ->map(fn ($id) => [(int) $id, __('No supplier could be reached. Nothing was delivered.')]);

        return $overdue->concat($unreachable)->take($limit)->map(function ($row) {
            $ill = $this->ill->get($row[0]);

            return (object) ['id' => $row[0], 'ill_number' => $ill->ill_number ?? (string) $row[0], 'reason' => $row[1]];
        })->values();
    }

    /** Staff give up on the current supplier so the request can move on. */
    public function stopWaiting(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $locked = DB::table('library_ill_request')->where('id', $id)->lockForUpdate()->first(['id']);
            $ill = $locked ? $this->ill->get($id) : null;
            if (!$ill || !$this->isOpen($ill)) {
                return ['outcome' => 'not_routable', 'message' => __('Only open requests can be changed here.')];
            }

            $live = DB::table('library_ill_routing_attempt as a')
                ->leftJoin('library_trading_partner as p', 'p.id', '=', 'a.trading_partner_id')
                ->where('a.ill_request_id', $id)
                ->whereIn('a.status', [self::ATTEMPT_SENT, self::ATTEMPT_ACCEPTED])
                ->first(['a.id', 'a.expires_at', 'p.edi_partner_code']);
            if (!$live) {
                return ['outcome' => 'no_live', 'message' => __('No supplier is currently handling this request.')];
            }

            $expired = $live->expires_at && $live->expires_at <= now()->toDateTimeString();
            DB::table('library_ill_routing_attempt')->where('id', $live->id)->update([
                'status'       => $expired ? self::ATTEMPT_TIMED_OUT : self::ATTEMPT_WITHDRAWN,
                'responded_at' => now(),
                'expires_at'   => null,
                'note'         => 'Staff stopped waiting for this supplier.',
                'updated_at'   => now(),
            ]);

            return ['outcome' => 'withdrawn', 'message' => __('Stopped waiting for :partner. The supplier has not been told, so contact them if the item may still be coming.', ['partner' => $live->edi_partner_code])];
        });
    }

    /** Give suppliers that could not be reached a fresh set of tries. */
    public function retryFailed(int $id): array
    {
        DB::table('library_ill_routing_attempt')
            ->where('ill_request_id', $id)
            ->where('status', self::ATTEMPT_SEND_FAILED)
            ->update(['send_failures' => 0, 'updated_at' => now()]);

        return $this->route($id);
    }

    /** Staff close a request no supplier could be reached for. */
    public function closeUnreachable(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $locked = DB::table('library_ill_request')->where('id', $id)->lockForUpdate()->first(['id']);
            $ill = $locked ? $this->ill->get($id) : null;
            if (!$ill || !$this->routingState($ill, $this->attemptsFor($id))['unreachable']) {
                return ['outcome' => 'not_routable', 'message' => __('This request cannot be closed from here.')];
            }

            $reason = 'No rota supplier could be reached';
            $this->ill->transitionTo($id, LibraryIllService::STATUS_UNFULFILLED, $reason);
            $this->ill->update($id, ['closed_at' => now(), 'closed_reason' => $reason]);

            return ['outcome' => 'exhausted', 'message' => __('Request marked unfulfilled.')];
        });
    }

    /**
     * Claim a hand send. Returns null while a rota supplier is handling the
     * request. Holding the row first stops a sweep asking a second library.
     *
     * @return array{attempt_id:int, previous:?array}|null
     */
    public function beginHandSend(int $id, int $partnerId): ?array
    {
        return DB::transaction(function () use ($id, $partnerId) {
            DB::table('library_ill_request')->where('id', $id)->lockForUpdate()->first(['id']);
            $attempts = DB::table('library_ill_routing_attempt')->where('ill_request_id', $id)->get();
            if ($attempts->whereIn('status', [self::ATTEMPT_SENT, self::ATTEMPT_ACCEPTED])->isNotEmpty()) {
                return null;
            }

            $now = now();
            $values = ['status' => self::ATTEMPT_MANUAL, 'sent_at' => $now, 'expires_at' => null,
                'note' => 'Sent by hand, off the rota.', 'updated_at' => $now];
            $existing = $attempts->firstWhere('trading_partner_id', $partnerId);
            if ($existing) {
                DB::table('library_ill_routing_attempt')->where('id', $existing->id)->update($values);

                return ['attempt_id' => (int) $existing->id, 'previous' => (array) $existing];
            }

            return ['attempt_id' => (int) DB::table('library_ill_routing_attempt')->insertGetId($values + [
                'ill_request_id'     => $id,
                'trading_partner_id' => $partnerId,
                'sequence'           => $attempts->count() + 1,
                'created_at'         => $now,
            ]), 'previous' => null];
        });
    }

    public function finishHandSend(array $claim, bool $sent, ?string $messageId = null): void
    {
        $row = DB::table('library_ill_routing_attempt')->where('id', $claim['attempt_id']);
        if ($sent) {
            $row->update(['edi_message_id' => $messageId !== null ? mb_substr($messageId, 0, 50) : null, 'updated_at' => now()]);
        } elseif ($claim['previous']) {
            $row->update(array_intersect_key($claim['previous'], array_flip(['status', 'sent_at', 'expires_at', 'note', 'edi_message_id', 'updated_at'])));
        } else {
            $row->delete();
        }
    }

    public function attemptsFor(int $id): Collection
    {
        return DB::table('library_ill_routing_attempt as a')
            ->leftJoin('library_trading_partner as p', 'p.id', '=', 'a.trading_partner_id')
            ->where('a.ill_request_id', $id)
            ->orderBy('a.sequence')
            ->get(['a.*', 'p.edi_partner_code']);
    }

    /** Overridable transport seam. */
    protected function transmit(TradingPartner $partner, object $ill): array
    {
        $model = new IllRequest((array) $ill);
        $model->id = $ill->id;

        return (new EdiAdapter($partner))->sendIllRequest($model);
    }

    private function isOpen(object $ill): bool
    {
        return in_array($ill->status, [LibraryIllService::STATUS_PENDING, LibraryIllService::STATUS_REQUESTED], true);
    }

    private function overdueAcceptedQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('library_ill_routing_attempt as a')
            ->join('library_ill_request as r', 'r.id', '=', 'a.ill_request_id')
            ->where('a.status', self::ATTEMPT_ACCEPTED)
            ->whereNotNull('a.expires_at')
            ->where('a.expires_at', '<=', now())
            ->whereIn('r.status', [LibraryIllService::STATUS_PENDING, LibraryIllService::STATUS_REQUESTED]);
    }

    /** @return int[] */
    private function idleRequestIds(): array
    {
        return DB::table('library_ill_routing_attempt as a')
            ->join('library_ill_request as r', 'r.id', '=', 'a.ill_request_id')
            ->whereIn('r.status', [LibraryIllService::STATUS_PENDING, LibraryIllService::STATUS_REQUESTED])
            ->whereNotExists(function ($q) {
                $q->from('library_ill_routing_attempt as o')
                    ->whereColumn('o.ill_request_id', 'a.ill_request_id')
                    ->whereIn('o.status', [self::ATTEMPT_SENT, self::ATTEMPT_ACCEPTED, self::ATTEMPT_MANUAL]);
            })
            ->distinct()
            ->pluck('a.ill_request_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Suppliers still available: never asked, or failed but under the limit. */
    private function nextSuppliers(object $ill, Collection $attempts, array $alsoExclude = []): Collection
    {
        $exclude = $attempts
            ->reject(fn ($a) => $a->status === self::ATTEMPT_SEND_FAILED && (int) $a->send_failures < self::MAX_SEND_FAILURES)
            ->pluck('trading_partner_id')->merge($alsoExclude)->unique()->all();

        return $this->rankedSuppliers($ill, $exclude);
    }

    /** Runs inside a transaction holding the request row lock. */
    private function claimNext(int $id, array $triedThisRun): array
    {
        $locked = DB::table('library_ill_request')->where('id', $id)->lockForUpdate()->first(['id']);
        $ill = $locked ? $this->ill->get($id) : null;
        if (!$ill) {
            return ['outcome' => 'not_found', 'message' => __('ILL request not found.')];
        }
        if (($ill->type ?? LibraryIllService::TYPE_BORROW) !== LibraryIllService::TYPE_BORROW || !$this->isOpen($ill)) {
            return ['outcome' => 'not_routable', 'message' => __('Only open borrowing requests can be routed.')];
        }

        $attempts = DB::table('library_ill_routing_attempt')->where('ill_request_id', $id)->get();

        if ($attempts->whereIn('status', [self::ATTEMPT_SENT, self::ATTEMPT_ACCEPTED])->isNotEmpty()) {
            return ['outcome' => 'already_open', 'message' => __('A supplier is already handling this request.')];
        }
        if ($attempts->contains('status', self::ATTEMPT_MANUAL)
            || ($ill->status === LibraryIllService::STATUS_REQUESTED && $attempts->isEmpty())) {
            // Sent by hand outside the rota; routing it would ask a second library.
            return ['outcome' => 'not_routable', 'message' => __('This request was sent manually and is not on the rota.')];
        }

        $next = $this->nextSuppliers($ill, $attempts, $triedThisRun)->first();
        if (!$next) {
            // Anyone left once this run's tries are ignored can be retried later.
            $canRetry = $triedThisRun && $this->nextSuppliers($ill, $attempts)->isNotEmpty();

            return $this->noSupplierLeft($id, $ill, $canRetry, $attempts->isNotEmpty());
        }

        $now = now();
        $values = [
            'status'     => self::ATTEMPT_SENT,
            'sent_at'    => $now,
            'expires_at' => $now->copy()->addHours((int) $next->timeout_hours),
            'note'       => null,
            'updated_at' => $now,
        ];
        $existing = $attempts->firstWhere('trading_partner_id', $next->trading_partner_id);
        if ($existing) {
            DB::table('library_ill_routing_attempt')->where('id', $existing->id)->update($values);
            $attemptId = (int) $existing->id;
        } else {
            $attemptId = (int) DB::table('library_ill_routing_attempt')->insertGetId($values + [
                'ill_request_id'     => $id,
                'trading_partner_id' => $next->trading_partner_id,
                'sequence'           => $attempts->count() + 1,
                'created_at'         => $now,
            ]);
        }

        return [
            'outcome'    => 'claimed',
            'attempt_id' => $attemptId,
            'partner_id' => (int) $next->trading_partner_id,
            'ill'        => $ill,
        ];
    }

    private function noSupplierLeft(int $id, object $ill, bool $canRetry, bool $wasAttempted): array
    {
        if ($canRetry) {
            return ['outcome' => 'needs_attention', 'message' => __('No supplier could be reached. The send will be retried.')];
        }
        if ($ill->status !== LibraryIllService::STATUS_REQUESTED) {
            // Nobody has seen this request, so an outage must not close it unattended.
            return $wasAttempted
                ? ['outcome' => 'unreachable', 'message' => __('No supplier could be reached and nothing was delivered. Retry, or mark the request unfulfilled.')]
                : ['outcome' => 'no_supplier', 'message' => __('No eligible supplier on the rota.')];
        }

        $this->ill->transitionTo($id, LibraryIllService::STATUS_UNFULFILLED, 'All rota suppliers exhausted');
        $this->ill->update($id, ['closed_at' => now(), 'closed_reason' => 'All rota suppliers exhausted']);

        return ['outcome' => 'exhausted', 'message' => __('All suppliers exhausted. Request marked unfulfilled.')];
    }

    private function recordSent(int $id, array $claim, TradingPartner $partner, array $result): array
    {
        $messageId = isset($result['edi_message_id']) ? mb_substr((string) $result['edi_message_id'], 0, 50) : null;

        DB::table('library_ill_routing_attempt')->where('id', $claim['attempt_id'])->update([
            'edi_message_id' => $messageId,
            'updated_at'     => now(),
        ]);

        $this->ill->update($id, [
            'trading_partner_id' => $partner->id,
            'edi_message_id'     => $messageId,
            'library_name'       => $partner->edi_partner_code,
        ]);

        if ($claim['ill']->status === LibraryIllService::STATUS_PENDING) {
            $this->ill->transitionTo($id, LibraryIllService::STATUS_REQUESTED, 'Routed to ' . $partner->edi_partner_code);
        }

        return [
            'outcome'    => 'sent',
            'message'    => $partner->endpoint_type === 'MANUAL'
                ? __('Queued for :partner. Deliver the file from the outbound folder by hand.', ['partner' => $partner->edi_partner_code])
                : __('Sent to :partner.', ['partner' => $partner->edi_partner_code]),
            'partner'    => $partner->edi_partner_code,
            'attempt_id' => $claim['attempt_id'],
        ];
    }
}
