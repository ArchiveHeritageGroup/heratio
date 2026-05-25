<?php

declare(strict_types=1);

namespace AhgInferenceReceipts;

use AhgInferenceReceipts\Storage\ChainStore;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * Orchestrator for a tamper-evident receipt chain.
 *
 * append(payload) -> Receipt
 *   - reads head, computes prev_hash (or genesis)
 *   - builds signing view, JCS-encodes, SHA-256 -> entry_hash
 *   - Ed25519 sign entry_hash -> signature (base64)
 *   - assembles Receipt and hands it to the ChainStore
 *
 * verify() walks the persisted chain and:
 *   - recomputes entry_hash from each receipt's signing view
 *   - confirms it matches the stored entry_hash
 *   - confirms signature verifies under the kid's public key
 *   - confirms prev_hash matches the previous receipt's entry_hash
 *   - confirms seq monotonically increments from 0
 *
 * Verification accepts a key resolver so callers can support key rotation
 * (kid -> publicKey lookup). The simplest case is a single static key.
 */
final class ReceiptChain
{
    /**
     * @param callable(string $kid): ?string $publicKeyResolver
     *        Receives a kid, returns 32-byte raw public key or null if unknown.
     */
    public function __construct(
        private ChainStore $store,
        private Signer $signer,
        private $publicKeyResolver,
    ) {
        if (!is_callable($publicKeyResolver)) {
            throw new InvalidArgumentException('ReceiptChain: publicKeyResolver must be callable');
        }
    }

    /**
     * Convenience constructor for the single-key case.
     */
    public static function withSingleKey(ChainStore $store, Signer $signer): self
    {
        $kp = $signer->keyPair();
        $resolver = static fn (string $kid): ?string =>
            $kid === $kp->kid() ? $kp->publicKey() : null;

        return new self($store, $signer, $resolver);
    }

    /**
     * Append a payload to the chain. Returns the persisted Receipt.
     *
     * @param array<string,mixed> $payload
     */
    public function append(array $payload, ?DateTimeImmutable $ts = null): Receipt
    {
        $head = $this->store->head();
        $seq = $head === null ? 0 : $head->seq + 1;
        $prevHash = $head === null ? Receipt::GENESIS_PREV_HASH : $head->entryHash;
        $kp = $this->signer->keyPair();

        $signingView = [
            'v'         => Receipt::VERSION,
            'seq'       => $seq,
            'ts'        => self::formatTimestamp($ts ?? new DateTimeImmutable('now', new DateTimeZone('UTC'))),
            'prev_hash' => $prevHash,
            'payload'   => $payload,
            'kid'       => $kp->kid(),
            'alg'       => Receipt::ALG,
        ];

        $entryHash = Receipt::computeEntryHash($signingView);
        $signature = $this->signer->signBase64(hex2bin($entryHash));

        $receipt = new Receipt(
            seq:       $seq,
            ts:        $signingView['ts'],
            prevHash:  $prevHash,
            payload:   $payload,
            kid:       $signingView['kid'],
            entryHash: $entryHash,
            signature: $signature,
        );

        $this->store->append($receipt);

        return $receipt;
    }

    /**
     * Verify the full persisted chain (or a slice). Returns a VerificationResult.
     */
    public function verify(int $fromSeq = 0, ?int $toSeq = null): VerificationResult
    {
        $expectedSeq = $fromSeq;
        $expectedPrev = null;
        $checked = 0;

        if ($fromSeq > 0) {
            $needed = $fromSeq - 1;
            $prevReceipt = null;
            foreach ($this->store->range($needed, $needed) as $r) {
                $prevReceipt = $r;
            }
            if ($prevReceipt === null) {
                return VerificationResult::fail($fromSeq, "cannot anchor verification at seq {$fromSeq} - previous receipt missing");
            }
            $expectedPrev = $prevReceipt->entryHash;
        } else {
            $expectedPrev = Receipt::GENESIS_PREV_HASH;
        }

        foreach ($this->store->range($fromSeq, $toSeq) as $r) {
            if ($r->seq !== $expectedSeq) {
                return VerificationResult::fail($r->seq, "seq gap: expected {$expectedSeq}, got {$r->seq}");
            }
            if ($r->prevHash !== $expectedPrev) {
                return VerificationResult::fail($r->seq, "prev_hash mismatch at seq {$r->seq}");
            }

            $recomputed = Receipt::computeEntryHash($r->toSigningView());
            if (!hash_equals($r->entryHash, $recomputed)) {
                return VerificationResult::fail($r->seq, "entry_hash mismatch at seq {$r->seq} (record tampered)");
            }

            $publicKey = ($this->publicKeyResolver)($r->kid);
            if ($publicKey === null) {
                return VerificationResult::fail($r->seq, "unknown kid '{$r->kid}' at seq {$r->seq}");
            }

            if (!Signer::verifyBase64($r->signature, hex2bin($r->entryHash), $publicKey)) {
                return VerificationResult::fail($r->seq, "signature invalid at seq {$r->seq}");
            }

            $expectedSeq = $r->seq + 1;
            $expectedPrev = $r->entryHash;
            $checked++;
        }

        return VerificationResult::ok($checked);
    }

    /**
     * RFC 3339 with millisecond precision in UTC.
     * Matches the on-wire format claimed in the Receipt docblock.
     */
    private static function formatTimestamp(DateTimeImmutable $ts): string
    {
        $utc = $ts->setTimezone(new DateTimeZone('UTC'));
        return $utc->format('Y-m-d\TH:i:s.v\Z');
    }
}
