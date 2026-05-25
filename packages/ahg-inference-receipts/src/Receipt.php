<?php

declare(strict_types=1);

namespace AhgInferenceReceipts;

use InvalidArgumentException;

/**
 * One immutable entry in a tamper-evident receipt chain.
 *
 * The on-wire shape is:
 *
 *   {
 *     "v":          1,                          # format version
 *     "seq":        <int>,                      # 0-based monotonic counter
 *     "ts":         "2026-05-25T13:45:01.234Z", # RFC 3339 with millisecond precision, UTC
 *     "prev_hash":  "<64 hex chars>",           # entry_hash of previous receipt, or "0000…0000" for genesis
 *     "payload":    { ... arbitrary JSON ... }, # caller-supplied (input/output fingerprints, model id, etc)
 *     "kid":        "<16 hex chars>",           # signing key id
 *     "alg":        "ed25519"
 *   }
 *
 * The `entry_hash` is SHA-256 of JCS(toSigningView()) and the `signature`
 * is Ed25519(entry_hash). Both are stored alongside the receipt but are
 * NOT part of the signing input.
 */
final class Receipt
{
    public const VERSION = 1;
    public const ALG = 'ed25519';
    public const GENESIS_PREV_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(
        public readonly int $seq,
        public readonly string $ts,
        public readonly string $prevHash,
        public readonly array $payload,
        public readonly string $kid,
        public readonly string $entryHash,
        public readonly string $signature,
        public readonly int $version = self::VERSION,
        public readonly string $alg = self::ALG,
    ) {
        if ($seq < 0) {
            throw new InvalidArgumentException('Receipt: seq must be non-negative');
        }
        if (!self::looksLikeSha256Hex($prevHash)) {
            throw new InvalidArgumentException('Receipt: prev_hash must be 64 lowercase hex chars');
        }
        if (!self::looksLikeSha256Hex($entryHash)) {
            throw new InvalidArgumentException('Receipt: entry_hash must be 64 lowercase hex chars');
        }
        if ($kid === '' || !ctype_xdigit($kid)) {
            throw new InvalidArgumentException('Receipt: kid must be a non-empty hex string');
        }
    }

    /**
     * The canonical JSON-able view that is hashed + signed.
     * Order is irrelevant - JCS sorts keys deterministically.
     *
     * @return array<string,mixed>
     */
    public function toSigningView(): array
    {
        return [
            'v'         => $this->version,
            'seq'       => $this->seq,
            'ts'        => $this->ts,
            'prev_hash' => $this->prevHash,
            'payload'   => $this->payload,
            'kid'       => $this->kid,
            'alg'       => $this->alg,
        ];
    }

    /**
     * Full on-disk / on-wire representation including hash + signature.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'v'          => $this->version,
            'seq'        => $this->seq,
            'ts'         => $this->ts,
            'prev_hash'  => $this->prevHash,
            'payload'    => $this->payload,
            'kid'        => $this->kid,
            'alg'        => $this->alg,
            'entry_hash' => $this->entryHash,
            'signature'  => $this->signature,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['seq', 'ts', 'prev_hash', 'payload', 'kid', 'entry_hash', 'signature'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw new InvalidArgumentException("Receipt: missing required field '{$required}'");
            }
        }

        return new self(
            seq:       (int) $data['seq'],
            ts:        (string) $data['ts'],
            prevHash:  (string) $data['prev_hash'],
            payload:   (array) $data['payload'],
            kid:       (string) $data['kid'],
            entryHash: (string) $data['entry_hash'],
            signature: (string) $data['signature'],
            version:   isset($data['v']) ? (int) $data['v'] : self::VERSION,
            alg:       isset($data['alg']) ? (string) $data['alg'] : self::ALG,
        );
    }

    /**
     * Compute the SHA-256 hex digest over the canonical (JCS) form of a
     * signing view. This is the value stored as entry_hash AND the value
     * that gets signed.
     *
     * @param array<string,mixed> $signingView
     */
    public static function computeEntryHash(array $signingView): string
    {
        return hash('sha256', JcsEncoder::encode($signingView));
    }

    private static function looksLikeSha256Hex(string $h): bool
    {
        return strlen($h) === 64 && ctype_xdigit($h) && strtolower($h) === $h;
    }
}
