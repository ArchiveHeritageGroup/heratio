<?php
/**
 * Heratio - the central signed C2PA claim structure.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Manifest;

use AhgInferenceReceipts\JcsEncoder;
use InvalidArgumentException;

/**
 * A C2PA "claim" - the one structure inside a manifest that gets signed.
 *
 * The claim references every assertion by URI + content hash, declares
 * the claim_generator + the title + the format of the asset, and pins
 * the asset's own hash so any post-signature edit invalidates the manifest.
 *
 * Spec: https://c2pa.org/specifications/specifications/2.1/specs/C2PA_Specification.html#_claim
 *
 * What we DON'T do here:
 *   - JWS/COSE binary envelope (the spec allows multiple signature transports;
 *     we ship a JCS+Ed25519+detached-signature combo that's spec-conformant
 *     for the JSON serialisation path, and convert to CBOR/COSE only when
 *     embedding into media via the optional c2pa-tools CLI subprocess).
 *   - Time-stamping authority. Our `ts` is wall-clock UTC.
 */
final class Claim
{
    private string $instanceId;

    /**
     * @param list<Assertion> $assertions
     * @param array<string,mixed> $extra extra top-level claim fields (e.g. instanceID, claim_generator_info)
     */
    public function __construct(
        public readonly string $title,
        public readonly string $format,
        public readonly string $claimGenerator,
        public readonly array $assertions,
        public readonly string $assetHash,
        public readonly string $ts,
        public readonly array $extra = [],
    ) {
        if ($title === '') {
            throw new InvalidArgumentException('Claim: title must not be empty');
        }
        if ($format === '') {
            throw new InvalidArgumentException('Claim: format must not be empty');
        }
        foreach ($assertions as $a) {
            if (!$a instanceof Assertion) {
                throw new InvalidArgumentException('Claim: assertions must all be Assertion instances');
            }
        }
        if ($assetHash === '' || !ctype_xdigit($assetHash)) {
            throw new InvalidArgumentException('Claim: assetHash must be a hex string');
        }
        // Generate the instance id ONCE per Claim. Stable across repeated
        // toArray() calls (signing + canonical-bytes use the same value).
        $this->instanceId = isset($extra['instanceID']) && is_string($extra['instanceID']) && $extra['instanceID'] !== ''
            ? $extra['instanceID']
            : ('xmp:iid:' . bin2hex(random_bytes(8)));
    }

    /**
     * The claim as it will be serialised for signing. Stable across calls
     * (assertions are listed in input order; hashed-uri arrays use
     * JCS-canonical key order at encode time).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $base = [
            'claim_generator' => $this->claimGenerator,
            'title'           => $this->title,
            'format'          => $this->format,
            'instanceID'      => $this->instanceId,
            'signature'       => 'self#jumbf=c2pa.signature',
            'alg'             => 'sha256',
            'created'         => $this->ts,
            'asset_hash'      => [
                'alg'  => 'sha256',
                'hash' => $this->assetHash,
            ],
            'assertions' => array_map(fn (Assertion $a) => $a->hashedUri(), $this->assertions),
        ];

        foreach ($this->extra as $k => $v) {
            if ($k === 'instanceID') {
                continue;
            }
            $base[$k] = $v;
        }

        return $base;
    }

    /**
     * Canonical (RFC 8785 JCS) byte form of the claim. This is the input
     * to SHA-256 + Ed25519.
     */
    public function canonicalBytes(): string
    {
        return JcsEncoder::encode($this->toArray());
    }

    /**
     * SHA-256 hex of the canonical claim bytes. Used as the "digest" that
     * the Ed25519 signature is computed over (matches the inference-receipts
     * pattern: signature is over the digest, not the raw payload).
     */
    public function digestHex(): string
    {
        return hash('sha256', $this->canonicalBytes());
    }
}
