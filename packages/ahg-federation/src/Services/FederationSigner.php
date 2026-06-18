<?php

/**
 * FederationSigner - signs PEER-FACING federation responses with THIS instance's
 * existing Ed25519 key (the federation trust handshake, T1 of epic heratio#1313
 * "federation backbone hardening", issue heratio#1316).
 *
 * It does NOT mint a key. It reuses the ONE Ed25519 key the platform already
 * owns: the inference-receipts signer bound as the AhgInferenceReceipts\Signer
 * singleton (registered authoritatively by ahg-ai-compliance, shared by
 * ahg-c2pa). That same key's public half is already served at
 * /.well-known/ai-inference-pubkey and registered in ai_inference_key, so a
 * consumer verifies federation, inference receipts and C2PA manifests against
 * the SAME key material. One key, three uses.
 *
 * Signature scheme ("ed25519-sha256-hex"), identical in spirit to the C2PA
 * claim signer (ahg-c2pa C2paSigner):
 *   - SHA-256 the exact response body bytes -> a fixed-length 32-byte digest.
 *   - Detached Ed25519 over that digest (sodium_crypto_sign_detached).
 *   - Hex-encode the detached signature for an HTTP header.
 *
 * The signature is attached as a DETACHED response HEADER, never written into
 * the JSON body, so existing consumers that ignore the header are unaffected
 * (full back-compat). The signed bytes are the exact bytes transmitted.
 *
 *   X-Federation-Signature: <hex detached Ed25519 over sha256(body)>
 *   X-Federation-Key-Id:    <16-hex-char kid = the inference-receipts kid>
 *   X-Federation-Sig-Alg:   ed25519-sha256-hex
 *
 * Fail-soft: if the signer cannot be resolved (e.g. ai-compliance absent on a
 * slimmer install) signing is skipped silently and the response is returned
 * UNSIGNED - a peer simply treats it as unverified. Signing must never break a
 * federation response.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @author     Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgFederation\Services;

use Symfony\Component\HttpFoundation\Response;

class FederationSigner
{
    /** Response header carrying the detached hex Ed25519 signature. */
    public const HEADER_SIGNATURE = 'X-Federation-Signature';

    /** Response header carrying the signing key id (the inference-receipts kid). */
    public const HEADER_KEY_ID = 'X-Federation-Key-Id';

    /** Response header naming the signature scheme. */
    public const HEADER_SIG_ALG = 'X-Federation-Sig-Alg';

    /** The one signature scheme this instance uses. */
    public const SIG_ALG = 'ed25519-sha256-hex';

    /**
     * Produce a detached hex Ed25519 signature over the EXACT bytes given,
     * reusing the platform's inference-receipts signer (the one key). The bytes
     * are SHA-256'd first (fixed-length 32-byte digest), mirroring the C2PA
     * claim signer, then Ed25519-signed detached and hex-encoded.
     *
     * Returns null (no signature) when the signer is unavailable, so the caller
     * can fail soft and emit an unsigned response.
     */
    public function signHex(string $bytes): ?string
    {
        $signer = $this->signer();
        if ($signer === null) {
            return null;
        }

        try {
            $digest = hash('sha256', $bytes, true);

            return bin2hex($signer->sign($digest));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The signing key id (kid) - the SAME 16-hex-char id the inference-receipts
     * signer and the public-key endpoint use, so a peer resolves it through
     * /.well-known/ai-inference-pubkey. Null when the signer is unavailable.
     */
    public function keyId(): ?string
    {
        $signer = $this->signer();
        if ($signer === null) {
            return null;
        }

        try {
            return $signer->keyPair()->kid();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Attach the detached signature + key-id + scheme headers to a response,
     * signing its EXACT current body bytes. No-op (returns the response
     * unchanged, unsigned) when the signer is unavailable - back-compat + fail
     * soft. Never mutates the response body.
     */
    public function attach(Response $response): Response
    {
        // Sign the exact bytes that will be transmitted.
        $body = (string) $response->getContent();
        $sig = $this->signHex($body);
        $kid = $this->keyId();

        if ($sig !== null && $kid !== null) {
            $response->headers->set(self::HEADER_SIGNATURE, $sig);
            $response->headers->set(self::HEADER_KEY_ID, $kid);
            $response->headers->set(self::HEADER_SIG_ALG, self::SIG_ALG);
            // So a CORS consumer (the peer-facing surfaces are CORS-open) can
            // actually read the verification headers from JS.
            $existing = (string) $response->headers->get('Access-Control-Expose-Headers', '');
            $expose = array_filter(array_map('trim', explode(',', $existing)));
            foreach ([self::HEADER_SIGNATURE, self::HEADER_KEY_ID, self::HEADER_SIG_ALG] as $h) {
                if (! in_array($h, $expose, true)) {
                    $expose[] = $h;
                }
            }
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $expose));
        }

        return $response;
    }

    /**
     * Resolve the shared inference-receipts signer from the container. Returns
     * null when the class or the binding is absent (slimmer install) so the
     * caller fails soft. We resolve lazily per call (cheap singleton lookup)
     * rather than via constructor injection so a controller can depend on this
     * service even where ai-compliance is not installed.
     */
    protected function signer(): ?\AhgInferenceReceipts\Signer
    {
        $class = \AhgInferenceReceipts\Signer::class;
        if (! class_exists($class)) {
            return null;
        }

        try {
            $signer = app($class);

            return $signer instanceof $class ? $signer : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
