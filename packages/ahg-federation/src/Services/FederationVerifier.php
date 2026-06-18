<?php

/**
 * FederationVerifier - verifies a SIGNED peer federation response and pins the
 * peer's key Trust-On-First-Use (the consumer side of the federation trust
 * handshake; T1 of epic heratio#1313, issue heratio#1316).
 *
 * Given the raw bytes a peer returned plus its signature headers
 * (X-Federation-Signature / X-Federation-Key-Id), this service:
 *
 *   1. Resolves the peer's PUBLIC key. The peer's /open-data/protocol federation
 *      block advertises a `public_key_url` (its /.well-known/ai-inference-pubkey);
 *      we fetch that key document through the shared FederationClient so the
 *      SSRF guard + timeouts + FOLLOWLOCATION=false protect this fetch exactly
 *      like every other cross-peer fetch. The key matching the presented kid is
 *      pulled from the document's `keys[]`.
 *   2. Verifies the detached Ed25519 signature over sha256(received bytes) using
 *      the SAME scheme the provider signs with (ed25519-sha256-hex). This reuses
 *      AhgInferenceReceipts\Signer::verify (one verification primitive across
 *      inference receipts, C2PA and federation).
 *   3. Pins the peer's key fingerprint Trust-On-First-Use. On the first
 *      successful verify the kid is stored on federation_peer
 *      (pinned_key_fingerprint + key_pinned_at), matched to the peer by base_url
 *      host. On a later fetch a DIFFERENT presented kid is a key_mismatch:
 *      verified is forced false and flagged - a changed key is NOT auto-trusted
 *      (an admin must re-pin via the governance surface).
 *
 * Returns a small result array { verified, key_fingerprint, reason } the calling
 * federated services merge into their per-node / per-row provenance.
 *
 * Fail-soft + additive, by contract:
 *   - No signature header           -> verified=false, reason=unsigned (NOT an error).
 *   - No public_key_url / no key    -> verified=false, reason=no_peer_key.
 *   - Signature does not verify      -> verified=false, reason=bad_signature.
 *   - Presented kid != pinned kid    -> verified=false, reason=key_mismatch (flagged).
 *   - Any exception                  -> verified=false, reason=error:<msg>.
 * Verification is best-effort on the consume path and must never throw, never
 * block, never 500. T1 only ESTABLISHES sign+verify+pin; what to DO with an
 * unverified peer is deferred to T2.
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

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FederationVerifier
{
    /** Header a peer sets with the detached hex Ed25519 signature. */
    public const HEADER_SIGNATURE = 'x-federation-signature';

    /** Header a peer sets with the signing key id. */
    public const HEADER_KEY_ID = 'x-federation-key-id';

    /** TTL (seconds) for the per-peer cached public-key document. */
    protected int $keyCacheTtl = 1800;

    /**
     * Verify a peer response and (TOFU) pin its key.
     *
     * @param  string  $bytes      the EXACT body bytes received from the peer.
     * @param  array<string,string>  $headers  the peer response headers, lower-cased keys.
     * @param  string  $peerBaseUrl the peer base_url (for the key fetch + the pin row).
     * @return array{verified:bool,key_fingerprint:?string,reason:string}
     */
    public function verifyResponse(string $bytes, array $headers, string $peerBaseUrl): array
    {
        $sigHex = $this->header($headers, self::HEADER_SIGNATURE);
        $kid = $this->header($headers, self::HEADER_KEY_ID);

        // An unsigned peer response is NOT an error - it is simply unverified.
        if ($sigHex === '' || $kid === '') {
            return $this->result(false, null, 'unsigned');
        }

        if (! ctype_xdigit($sigHex)) {
            return $this->result(false, $kid, 'bad_signature');
        }

        try {
            // TOFU pin check FIRST: a key change is never silently re-trusted.
            $pinned = $this->pinnedFingerprint($peerBaseUrl);
            if ($pinned !== null && $pinned !== '' && ! hash_equals($pinned, $kid)) {
                return $this->result(false, $kid, 'key_mismatch');
            }

            // Resolve the peer's public key bytes for the presented kid.
            $publicKey = $this->resolvePeerPublicKey($peerBaseUrl, $kid);
            if ($publicKey === null || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                return $this->result(false, $kid, 'no_peer_key');
            }

            // Verify the detached signature over sha256(received bytes) - the
            // exact scheme the provider (FederationSigner) signs with. Reuse the
            // inference-receipts verification primitive (one primitive).
            $digest = hash('sha256', $bytes, true);
            $ok = $this->verifyHex($sigHex, $digest, $publicKey);

            if (! $ok) {
                return $this->result(false, $kid, 'bad_signature');
            }

            // First good verify: pin the key TOFU (idempotent). Later good
            // verifies with the same kid simply refresh nothing.
            if ($pinned === null || $pinned === '') {
                $this->pin($peerBaseUrl, $kid);
            }

            return $this->result(true, $kid, $pinned === null || $pinned === '' ? 'verified_pinned' : 'verified');
        } catch (\Throwable $e) {
            Log::info('[federation-verify] verify failed: '.$e->getMessage());

            return $this->result(false, $kid, 'error');
        }
    }

    /**
     * Re-pin / clear a peer's pinned key (admin action from the governance
     * surface). Clearing sets the pin back to NULL so the next successful verify
     * re-pins TOFU; this is the deliberate "trust the peer's new key" control.
     */
    public function clearPin(string $peerBaseUrl): bool
    {
        if (! $this->pinColumnsReady()) {
            return false;
        }

        try {
            DB::table('federation_peer')
                ->where('base_url', 'like', '%'.$this->host($peerBaseUrl).'%')
                ->update(['pinned_key_fingerprint' => null, 'key_pinned_at' => null]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // -----------------------------------------------------------------
    // Peer public-key resolution (via the SSRF-guarded FederationClient)
    // -----------------------------------------------------------------

    /**
     * Resolve the raw 32-byte public key for $kid from the peer. We discover the
     * peer's public_key_url from its /open-data/protocol federation block (the
     * descriptor advertises it), then fetch that key document and pick the key
     * whose kid matches. Both fetches go through FederationClient so the SSRF
     * guard applies. The key document is the /.well-known/ai-inference-pubkey
     * shape (keys[] each with kid + public_key.hex|base64). Cached per (peer).
     */
    protected function resolvePeerPublicKey(string $peerBaseUrl, string $kid): ?string
    {
        $client = new FederationClient();

        // SSRF guard the peer base before any fetch.
        if (! $client->hostAllowed($peerBaseUrl)) {
            return null;
        }

        $cacheKey = 'fedverify:pubkey:'.sha1($this->host($peerBaseUrl));
        $doc = Cache::get($cacheKey);

        if (! is_string($doc) || $doc === '') {
            $keyUrl = $this->discoverKeyUrl($peerBaseUrl, $client);
            if ($keyUrl === null) {
                return null;
            }

            $resp = $client->fetchOne($keyUrl, [
                'base_url' => $peerBaseUrl,
                'headers'  => ['Accept: application/json'],
            ]);
            if (($resp['status'] ?? '') !== 'success' || ! is_string($resp['body'] ?? null)) {
                return null;
            }
            $doc = (string) $resp['body'];
            Cache::put($cacheKey, $doc, $this->keyCacheTtl);
        }

        return $this->extractKey($doc, $kid);
    }

    /**
     * Discover the peer's public_key_url. Prefer the federation block of its
     * /open-data/protocol descriptor (the contract this epic adds); fall back to
     * the conventional /.well-known/ai-inference-pubkey path so a peer that
     * serves the key but not yet the descriptor field still verifies.
     */
    protected function discoverKeyUrl(string $peerBaseUrl, FederationClient $client): ?string
    {
        $base = rtrim($peerBaseUrl, '/');

        try {
            $resp = $client->fetchOne($base.'/open-data/protocol', [
                'base_url' => $peerBaseUrl,
                'headers'  => ['Accept: application/json'],
            ]);
            if (($resp['status'] ?? '') === 'success' && is_string($resp['body'] ?? null)) {
                $decoded = json_decode((string) $resp['body'], true);
                $url = $decoded['federation']['public_key_url'] ?? null;
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        } catch (\Throwable $e) {
            // fall through to the well-known default
        }

        return $base.'/.well-known/ai-inference-pubkey';
    }

    /**
     * Pull the raw 32-byte public key matching $kid out of a
     * /.well-known/ai-inference-pubkey document. Understands the keys[] shape
     * (each key has kid + public_key.hex|base64). Returns null on a miss.
     */
    protected function extractKey(string $doc, string $kid): ?string
    {
        $decoded = json_decode($doc, true);
        if (! is_array($decoded) || ! isset($decoded['keys']) || ! is_array($decoded['keys'])) {
            return null;
        }

        foreach ($decoded['keys'] as $key) {
            if (! is_array($key) || (string) ($key['kid'] ?? '') !== $kid) {
                continue;
            }

            $pk = $key['public_key'] ?? null;
            if (is_array($pk)) {
                if (! empty($pk['hex']) && ctype_xdigit((string) $pk['hex'])) {
                    $bin = @hex2bin((string) $pk['hex']);

                    return $bin === false ? null : $bin;
                }
                if (! empty($pk['base64'])) {
                    $bin = base64_decode((string) $pk['base64'], true);

                    return $bin === false ? null : $bin;
                }
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    // TOFU pin storage (federation_peer, matched by base_url host)
    // -----------------------------------------------------------------

    /**
     * The pinned fingerprint for a peer, or null if none / columns absent.
     * The consume-path peer registry is federation_member, but the governance +
     * pin columns live on federation_peer (matching F2's pattern); the two are
     * joined on the base_url host, the stable cross-table key.
     */
    protected function pinnedFingerprint(string $peerBaseUrl): ?string
    {
        if (! $this->pinColumnsReady()) {
            return null;
        }

        try {
            $val = DB::table('federation_peer')
                ->where('base_url', 'like', '%'.$this->host($peerBaseUrl).'%')
                ->whereNotNull('pinned_key_fingerprint')
                ->orderByDesc('id')
                ->value('pinned_key_fingerprint');

            return $val !== null && $val !== '' ? (string) $val : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Pin a peer's key fingerprint TOFU. Idempotent: only writes a row that has
     * no pin yet, so a concurrent verify cannot stomp an existing pin. Matches
     * the peer by base_url host. No-op when no federation_peer row matches (a
     * federation_member-only peer cannot be pinned until it also exists in the
     * governance registry - the verify still succeeds, it just is not persisted).
     */
    protected function pin(string $peerBaseUrl, string $kid): void
    {
        if (! $this->pinColumnsReady()) {
            return;
        }

        try {
            DB::table('federation_peer')
                ->where('base_url', 'like', '%'.$this->host($peerBaseUrl).'%')
                ->where(function ($q) {
                    $q->whereNull('pinned_key_fingerprint')->orWhere('pinned_key_fingerprint', '');
                })
                ->update([
                    'pinned_key_fingerprint' => $kid,
                    'key_pinned_at'          => now(),
                ]);
        } catch (\Throwable $e) {
            // pin is best-effort; verification already succeeded.
        }
    }

    protected function pinColumnsReady(): bool
    {
        try {
            return Schema::hasTable('federation_peer')
                && Schema::hasColumn('federation_peer', 'pinned_key_fingerprint');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // -----------------------------------------------------------------
    // Small helpers
    // -----------------------------------------------------------------

    /**
     * Verify a hex detached Ed25519 signature over $message under $publicKey,
     * reusing the inference-receipts verification primitive when present and
     * falling back to ext-sodium directly otherwise (so federation verifies
     * even on an install without ahg-inference-receipts loaded).
     */
    protected function verifyHex(string $sigHex, string $message, string $publicKey): bool
    {
        $class = \AhgInferenceReceipts\Signer::class;
        if (class_exists($class) && method_exists($class, 'verifyHex')) {
            return $class::verifyHex($sigHex, $message, $publicKey);
        }

        if (strlen($sigHex) !== SODIUM_CRYPTO_SIGN_BYTES * 2) {
            return false;
        }
        $sig = @hex2bin($sigHex);
        if ($sig === false) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($sig, $message, $publicKey);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Lower-cased header value, '' when absent. Accepts either lower- or mixed-
     * case keys in the supplied array.
     */
    protected function header(array $headers, string $name): string
    {
        $name = strtolower($name);
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === $name) {
                if (is_array($v)) {
                    $v = $v[0] ?? '';
                }

                return trim((string) $v);
            }
        }

        return '';
    }

    protected function host(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host !== '' ? strtolower($host) : strtolower(trim($url, '/'));
    }

    /**
     * @return array{verified:bool,key_fingerprint:?string,reason:string}
     */
    protected function result(bool $verified, ?string $kid, string $reason): array
    {
        return [
            'verified'        => $verified,
            'key_fingerprint' => $kid,
            'reason'          => $reason,
        ];
    }
}
