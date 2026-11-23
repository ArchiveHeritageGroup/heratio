<?php

/**
 * IllInboundEdiService - apply a partner's EDI reply to its ILL request (#1475).
 *
 * The sender is already authenticated when this runs. A partner can only affect
 * a request it was actually asked to supply, and only while its attempt is live.
 * An identical payload is processed once it has taken effect; one that could
 * not be applied may be sent again.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use AhgLibrary\Models\TradingPartner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IllInboundEdiService
{
    public const OUTCOME_PROCESSING = 'processing';
    public const OUTCOME_APPLIED    = 'applied';
    public const OUTCOME_STALE      = 'stale';
    public const OUTCOME_NO_MATCH   = 'no_match';
    public const OUTCOME_UNMAPPED   = 'unmapped';
    public const OUTCOME_IGNORED    = 'ignored';
    public const OUTCOME_INVALID    = 'invalid_transition';

    /** Nothing was applied, so the same payload may legitimately arrive again. */
    private const RETRYABLE_OUTCOMES = [self::OUTCOME_NO_MATCH, self::OUTCOME_INVALID, self::OUTCOME_UNMAPPED];

    /** Status changes a supplier may report on a borrowing request. */
    private const SUPPLIER_STATUSES = [
        LibraryIllService::STATUS_SHIPPED,
        LibraryIllService::STATUS_LOST,
        LibraryIllService::STATUS_RETURNED,
    ];

    public function __construct(
        protected EdiDecoderService $decoder,
        protected LibraryIllService $ill,
        protected IllRoutingService $routing,
    ) {}

    /**
     * @return array{outcome:string, duplicate:bool, detail:?string, ill_request_id:?int}
     */
    public function process(TradingPartner $partner, string $raw, ?int $expectedRequestId = null): array
    {
        $hash = hash('sha256', $raw);

        try {
            $logId = (int) DB::table('library_ill_edi_inbound')->insertGetId([
                'trading_partner_id' => $partner->id,
                'payload_hash'       => $hash,
                'outcome'            => self::OUTCOME_PROCESSING,
                'raw_message'        => $raw,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (QueryException $e) {
            $prior = DB::table('library_ill_edi_inbound')
                ->where('trading_partner_id', $partner->id)
                ->where('payload_hash', $hash)
                ->first();
            if (!$prior) {
                throw $e;
            }

            // Conditional, so of two concurrent retries only one reprocesses.
            $reclaimed = in_array($prior->outcome, self::RETRYABLE_OUTCOMES, true)
                && DB::table('library_ill_edi_inbound')
                    ->where('id', $prior->id)
                    ->where('outcome', $prior->outcome)
                    ->update(['outcome' => self::OUTCOME_PROCESSING, 'updated_at' => now()]) === 1;

            if (!$reclaimed) {
                return [
                    'outcome'        => $prior->outcome,
                    'duplicate'      => true,
                    'detail'         => $prior->detail,
                    'ill_request_id' => $prior->ill_request_id ? (int) $prior->ill_request_id : null,
                ];
            }
            $logId = (int) $prior->id;
        }

        try {
            return $this->handle($partner, $raw, $logId, $expectedRequestId);
        } catch (\Throwable $e) {
            // Free the hash, or the partner's retry would be swallowed as a duplicate.
            DB::table('library_ill_edi_inbound')->where('id', $logId)->delete();
            throw $e;
        }
    }

    private function handle(TradingPartner $partner, string $raw, int $logId, ?int $expectedRequestId): array
    {
        try {
            $decoded = $this->decoder->decode($raw);
        } catch (\Throwable $e) {
            Log::warning('ILL inbound EDI could not be decoded', ['log' => $logId, 'error' => $e->getMessage()]);

            return $this->finish($logId, [], self::OUTCOME_UNMAPPED, 'Message could not be decoded.', null);
        }

        $attempt = $this->findAttempt($partner, $decoded);
        if (!$attempt || ($expectedRequestId !== null && (int) $attempt->ill_request_id !== $expectedRequestId)) {
            return $this->finish($logId, $decoded, self::OUTCOME_NO_MATCH, 'No request of ours is open with this partner under that reference.', null);
        }
        $requestId = (int) $attempt->ill_request_id;

        if (empty($decoded['status'])) {
            return $this->finish($logId, $decoded, self::OUTCOME_UNMAPPED, 'Status not understood.', $requestId);
        }

        [$outcome, $detail, $reroute] = DB::transaction(
            fn () => $this->apply($requestId, (int) $attempt->id, $decoded)
        );

        // The change is committed, so its log row must survive anything after this.
        $result = $this->finish($logId, $decoded, $outcome, $detail, $requestId);
        DB::table('library_trading_partner')->where('id', $partner->id)->update(['last_inbound_at' => now()]);

        // After commit: this may transmit to the next partner.
        if ($reroute && $this->routing->autoRoutingEnabled()) {
            try {
                $message = $this->routing->route($requestId)['message'];
            } catch (\Throwable $e) {
                Log::error('ILL rerouting failed after a decline', ['request' => $requestId, 'log' => $logId, 'error' => $e->getMessage()]);
                $message = 'Automatic rerouting failed: route this request by hand.';
            }
            $result['detail'] = trim(($detail ?? '') . ' ' . $message);
            DB::table('library_ill_edi_inbound')->where('id', $logId)
                ->update(['detail' => mb_substr($result['detail'], 0, 500)]);
        }

        return $result;
    }

    /** Scoped to this partner, so one partner cannot reach another's request. */
    private function findAttempt(TradingPartner $partner, array $decoded): ?object
    {
        $number = trim((string) ($decoded['ill_number'] ?? ''));
        if ($number !== '') {
            $requestId = DB::table('library_ill_request')
                ->where($this->ill->column('ill_number'), $number)
                ->value('id');

            return $requestId
                ? DB::table('library_ill_routing_attempt')
                    ->where('ill_request_id', $requestId)
                    ->where('trading_partner_id', $partner->id)
                    ->first()
                : null;
        }

        $ref = trim((string) ($decoded['msg_ref'] ?? ''));

        return $ref === '' ? null : DB::table('library_ill_routing_attempt')
            ->where('trading_partner_id', $partner->id)
            ->where('edi_message_id', $ref)
            ->first();
    }

    /** @return array{0:string, 1:?string, 2:bool} outcome, detail, reroute */
    private function apply(int $requestId, int $attemptId, array $decoded): array
    {
        DB::table('library_ill_request')->where('id', $requestId)->lockForUpdate()->first(['id']);
        $attempt = DB::table('library_ill_routing_attempt')->where('id', $attemptId)->first();
        $ill = $this->ill->get($requestId);
        $status = $decoded['status'];

        if (!in_array($attempt->status, [IllRoutingService::ATTEMPT_SENT, IllRoutingService::ATTEMPT_ACCEPTED], true)) {
            return [self::OUTCOME_STALE, 'This supplier is no longer active on the request.', false];
        }

        if (in_array($status, [LibraryIllService::STATUS_UNFULFILLED, LibraryIllService::STATUS_CANCELLED], true)) {
            if ($ill->status !== LibraryIllService::STATUS_REQUESTED) {
                return [self::OUTCOME_INVALID, 'Decline received after the item moved on.', false];
            }
            $this->closeAttempt($attemptId, IllRoutingService::ATTEMPT_DECLINED, $decoded['note'] ?? null);

            return [self::OUTCOME_APPLIED, 'Supplier declined.', true];
        }

        if ($status === LibraryIllService::STATUS_REQUESTED) {
            if ($ill->status !== LibraryIllService::STATUS_REQUESTED) {
                return [self::OUTCOME_INVALID, 'Acceptance received after the request moved on.', false];
            }
            if ($attempt->status === IllRoutingService::ATTEMPT_SENT) {
                $this->closeAttempt($attemptId, IllRoutingService::ATTEMPT_ACCEPTED, $decoded['note'] ?? null, $this->routing->fulfilmentDeadline());
                $this->storeReply($requestId, $decoded);
            }

            return [self::OUTCOME_APPLIED, 'Supplier accepted.', false];
        }

        if (!in_array($status, self::SUPPLIER_STATUSES, true)) {
            return [self::OUTCOME_IGNORED, 'Status ' . $status . ' is not one a supplier can set.', false];
        }

        if (!$this->ill->transitionTo($requestId, $status, 'EDI reply: ' . ($decoded['raw_status'] ?? $status))) {
            return [self::OUTCOME_INVALID, 'Cannot move from ' . $ill->status . ' to ' . $status . '.', false];
        }

        if ($attempt->status === IllRoutingService::ATTEMPT_SENT) {
            $this->closeAttempt($attemptId, IllRoutingService::ATTEMPT_ACCEPTED, $decoded['note'] ?? null);
        } else {
            // The item has moved, so the fulfilment deadline no longer applies.
            DB::table('library_ill_routing_attempt')->where('id', $attemptId)
                ->update(['expires_at' => null, 'updated_at' => now()]);
        }
        $this->storeReply($requestId, $decoded);

        return [self::OUTCOME_APPLIED, 'Status moved to ' . $status . '.', false];
    }

    private function closeAttempt(int $attemptId, string $status, ?string $note, ?\DateTimeInterface $expiresAt = null): void
    {
        DB::table('library_ill_routing_attempt')->where('id', $attemptId)->update([
            'status'       => $status,
            'responded_at' => now(),
            'expires_at'   => $expiresAt,
            'note'         => $note !== null ? mb_substr($note, 0, 500) : null,
            'updated_at'   => now(),
        ]);
    }

    private function storeReply(int $requestId, array $decoded): void
    {
        $fields = [];
        if ($this->isRealDate($decoded['due_date'] ?? null)) {
            $fields['due_date'] = $decoded['due_date'];
        }
        if (!empty($decoded['note'])) {
            $fields['responder_note'] = mb_substr((string) $decoded['note'], 0, 1000);
        }
        if ($fields) {
            $this->ill->update($requestId, $fields);
        }
    }

    private function isRealDate(?string $date): bool
    {
        return $date !== null
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function finish(int $logId, array $decoded, string $outcome, ?string $detail, ?int $requestId): array
    {
        DB::table('library_ill_edi_inbound')->where('id', $logId)->update([
            'ill_request_id' => $requestId,
            'msg_ref'        => $this->clip($decoded['msg_ref'] ?? null, 100),
            'ill_number'     => $this->clip($decoded['ill_number'] ?? null, 50),
            'profile'        => $this->clip($decoded['profile'] ?? null, 20),
            'raw_status'     => $this->clip($decoded['raw_status'] ?? null, 100),
            'decoded_status' => $this->clip($decoded['status'] ?? null, 30),
            'outcome'        => $outcome,
            'detail'         => $this->clip($detail, 500),
            'updated_at'     => now(),
        ]);

        return ['outcome' => $outcome, 'duplicate' => false, 'detail' => $detail, 'ill_request_id' => $requestId];
    }

    private function clip(mixed $value, int $max): ?string
    {
        return $value === null || is_array($value) ? null : mb_substr((string) $value, 0, $max);
    }
}
