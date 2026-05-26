<?php
/**
 * Heratio - Ed25519 signer for C2PA claims. Thin wrapper over the
 * inference-receipts Signer so we sign with the same key material we
 * already use for the EU AI Act Article 12 chain.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Manifest;

use AhgInferenceReceipts\Signer as ReceiptSigner;

/**
 * C2PA-flavoured signer. Wraps AhgInferenceReceipts\Signer.
 *
 * Per C2PA 2.1, ed25519 is a permitted COSE algorithm (alongside es256/
 * es384/ps256/etc.). We declare it as 'Ed25519' in the manifest header.
 *
 * Signature format: detached Ed25519 over SHA-256(JCS(claim)).
 *   - JCS canonicalisation so cross-language verifiers agree on bytes.
 *   - SHA-256 first so signature input is fixed-length 32 bytes regardless
 *     of claim size; matches the pattern used by ahg-inference-receipts.
 *   - Hex-encoded signature for embedding in JSON manifests.
 */
final class C2paSigner
{
    public const ALG_LABEL = 'Ed25519';

    public function __construct(private ReceiptSigner $signer)
    {
    }

    /**
     * Sign a claim. Returns the signed-manifest structure ready to JSON-encode.
     *
     * @return array<string,mixed>
     */
    public function sign(Claim $claim): array
    {
        $claimBytes = $claim->canonicalBytes();
        $digest = hash('sha256', $claimBytes, true);
        $sigBytes = $this->signer->sign($digest);

        return [
            'claim'           => $claim->toArray(),
            'claim_signature' => [
                'alg'         => self::ALG_LABEL,
                'kid'         => $this->signer->keyPair()->kid(),
                'sig'         => bin2hex($sigBytes),
                'pad'         => '',
            ],
        ];
    }

    /**
     * Verify a signed manifest under a resolver-supplied public key.
     *
     * @param array<string,mixed> $signedManifest
     * @param callable(string $kid): ?string $publicKeyResolver returns raw 32-byte key
     */
    public static function verify(array $signedManifest, callable $publicKeyResolver): bool
    {
        if (!isset($signedManifest['claim']) || !is_array($signedManifest['claim'])) {
            return false;
        }
        if (!isset($signedManifest['claim_signature']) || !is_array($signedManifest['claim_signature'])) {
            return false;
        }
        $sig = $signedManifest['claim_signature'];
        if (($sig['alg'] ?? null) !== self::ALG_LABEL) {
            return false;
        }
        $kid = $sig['kid'] ?? '';
        $sigHex = $sig['sig'] ?? '';
        if (!is_string($kid) || !is_string($sigHex) || !ctype_xdigit($sigHex)) {
            return false;
        }

        $publicKey = $publicKeyResolver($kid);
        if (!is_string($publicKey) || $publicKey === '') {
            return false;
        }

        $claimBytes = \AhgInferenceReceipts\JcsEncoder::encode($signedManifest['claim']);
        $digest = hash('sha256', $claimBytes, true);

        return ReceiptSigner::verifyHex($sigHex, $digest, $publicKey);
    }

    public function kid(): string
    {
        return $this->signer->keyPair()->kid();
    }
}
