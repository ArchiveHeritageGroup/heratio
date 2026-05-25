<?php
/**
 * Heratio - Eloquent-backed ChainStore for the AI inference receipt chain.
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Storage;

use AhgAiCompliance\Models\AiInferenceLog;
use AhgInferenceReceipts\Receipt;
use AhgInferenceReceipts\Storage\ChainStore;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentChainStore implements ChainStore
{
    public function append(Receipt $receipt): void
    {
        $payload = $receipt->payload;

        DB::transaction(function () use ($receipt, $payload): void {
            $existingHead = AiInferenceLog::query()
                ->orderByDesc('seq')
                ->lockForUpdate()
                ->first(['seq']);
            $expectedSeq = $existingHead === null ? 0 : ((int) $existingHead->seq) + 1;
            if ($receipt->seq !== $expectedSeq) {
                throw new RuntimeException("EloquentChainStore: expected seq {$expectedSeq}, got {$receipt->seq}");
            }

            AiInferenceLog::create([
                'seq'                => $receipt->seq,
                'ts'                 => $receipt->ts,
                'prev_hash'          => $receipt->prevHash,
                'entry_hash'         => $receipt->entryHash,
                'signature'          => $receipt->signature,
                'kid'                => $receipt->kid,
                'v'                  => $receipt->version,
                'alg'                => $receipt->alg,
                'service'            => (string) ($payload['service'] ?? 'unknown'),
                'model_id'           => (string) ($payload['model_id'] ?? 'unknown'),
                'model_version'      => $payload['model_version'] ?? null,
                'input_fingerprint'  => $payload['input_fingerprint'] ?? null,
                'output_fingerprint' => $payload['output_fingerprint'] ?? null,
                'request_id'         => $payload['request_id'] ?? null,
                'user_id'            => $payload['user_id'] ?? null,
                'tenant_id'          => $payload['tenant_id'] ?? null,
                'latency_ms'         => $payload['latency_ms'] ?? null,
                'tokens_in'          => $payload['tokens_in'] ?? null,
                'tokens_out'         => $payload['tokens_out'] ?? null,
                'payload_json'       => $payload,
            ]);
        });
    }

    public function head(): ?Receipt
    {
        $row = AiInferenceLog::query()
            ->orderByDesc('seq')
            ->first();
        return $row === null ? null : $this->rowToReceipt($row);
    }

    public function count(): int
    {
        return (int) AiInferenceLog::query()->count();
    }

    public function range(int $fromSeq = 0, ?int $toSeq = null): iterable
    {
        $query = AiInferenceLog::query()
            ->where('seq', '>=', $fromSeq)
            ->orderBy('seq');
        if ($toSeq !== null) {
            $query->where('seq', '<=', $toSeq);
        }

        foreach ($query->cursor() as $row) {
            yield $this->rowToReceipt($row);
        }
    }

    private function rowToReceipt(AiInferenceLog $row): Receipt
    {
        $ts = $row->ts instanceof DateTimeImmutable
            ? $row->ts
            : new DateTimeImmutable((string) $row->ts, new DateTimeZone('UTC'));

        $payload = is_array($row->payload_json) ? $row->payload_json : [];

        return new Receipt(
            seq:       (int) $row->seq,
            ts:        $ts->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            prevHash:  (string) $row->prev_hash,
            payload:   $payload,
            kid:       (string) $row->kid,
            entryHash: (string) $row->entry_hash,
            signature: (string) $row->signature,
            version:   (int) ($row->v ?? 1),
            alg:       (string) ($row->alg ?? 'ed25519'),
        );
    }
}
