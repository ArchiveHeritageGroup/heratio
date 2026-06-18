<?php

/**
 * RemoteSceneFetchService - Heratio ahg-federation
 *
 * heratio#1155 federated-twin foundation. Fetches a PEER institution's exhibition
 * "scene.json" export (the #1151 ahg-exhibition-scene 1.0 manifest, which is CORS-enabled)
 * and normalises its objects into placement-ready "remote stops", so a curator can borrow a
 * single remote object into a local exhibition as a READ-ONLY, attributed walkthrough stop
 * with a link back to the owning institution. Media (images / 3D models) is referenced by the
 * peer's absolute URL - nothing is harvested or copied here.
 *
 * F3 federation twin (heratio#1246 on epic #1313): the raw Http::get() this service used to
 * fetch a peer scene predated the federation backbone - no SSRF guard, no signature
 * verification. It now fetches through the shared, audited backbone:
 *
 *   - FederationClient::fetchOne()    - SSRF host-guard (cloud-metadata / loopback /
 *                                       link-local / private / reserved-IP rejection),
 *                                       FOLLOWLOCATION=false, short cache + per-peer
 *                                       rate-limit. The same client T1/F1 use for graph,
 *                                       endangered and search.
 *   - FederationVerifier::verifyResponse() - verifies the peer's detached
 *                                       X-Federation-Signature over the EXACT received bytes
 *                                       and pins its key TOFU. An unsigned / unverifiable peer
 *                                       scene is NOT an error: it is simply verified=false.
 *
 * Every normalised object is stamped with the verification verdict + source_peer (mirroring
 * how FederationGraphService tags remote nodes), so the borrow side can flag unverified
 * borrowed objects and the require-verified policy can refuse to place them.
 *
 * This is the federated-twin fetch service: the existing federation connectors +
 * FederatedSearchService (the search/harvest layer) are deliberately left untouched.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @author     Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgFederation\Services;

use Illuminate\Support\Facades\Log;

class RemoteSceneFetchService
{
    /**
     * Fetch + verify + normalise a peer exhibition's scene.json export.
     *
     * The returned array carries the scene-level trust verdict (verified /
     * key_fingerprint / trust_reason / source_peer) AND stamps the same verdict
     * onto every normalised object, so a single borrowed object retains its
     * cryptographic provenance once detached from the scene response.
     *
     * @return array{
     *   ok:bool, name:?string, slug:?string, source:string,
     *   verified:bool, key_fingerprint:?string, trust_reason:string,
     *   source_peer:array<string,mixed>, objects:array<int,array<string,mixed>>, error:?string
     * }
     */
    public function fetchScene(string $baseUrl, string $slug, ?string $apiKey = null, int $timeoutSeconds = 10): array
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $slug = trim($slug);

        $sourcePeer = ['base_url' => $baseUrl, 'name' => null];
        $out = [
            'ok' => false,
            'name' => null,
            'slug' => $slug,
            'source' => $baseUrl,
            'verified' => false,
            'key_fingerprint' => null,
            'trust_reason' => 'not_fetched',
            'source_peer' => $sourcePeer,
            'objects' => [],
            'error' => null,
        ];

        if (! $this->isHttpUrl($baseUrl) || $slug === '') {
            $out['error'] = 'invalid peer base URL or slug';

            return $out;
        }

        $url = $baseUrl.'/exhibition-space/'.rawurlencode($slug).'/scene.json';

        // ---- Fetch via the shared, SSRF-guarded federation backbone (F1 #1314) ----
        // No raw Http::get() here any more: the SSRF host-guard, FOLLOWLOCATION=false,
        // cache and per-peer rate-limit all live in FederationClient. A blocked /
        // errored / rate-limited peer comes back as a non-success status (never fatal).
        // FederationClient feeds these straight to CURLOPT_HTTPHEADER, so they are
        // a numeric list of "Name: value" strings (NOT an associative map).
        $headers = [
            'Accept: application/json',
            'User-Agent: Heratio-Federation-Scene/1.0',
        ];
        if ($apiKey !== null && trim($apiKey) !== '') {
            $headers[] = 'X-API-Key: '.trim($apiKey);   // same per-peer auth header the federation connectors use
        }

        try {
            $client = (new FederationClient())
                ->withTimeouts(max(1000, $timeoutSeconds * 1000), min($timeoutSeconds, 5) * 1000)
                ->withCacheTtl(120)        // short cache: a peer scene is fairly static within a borrow session
                ->withRateLimit(5)         // per-peer cool-down so the picker cannot hammer a peer
                ->withHeaders($headers);

            $keyHash = substr(hash('sha256', $url), 0, 24);
            $resp = $client->fetchOne($url, [
                'base_url'  => $baseUrl,
                'cache_key' => 'fed:scene:'.$keyHash,
                'rate_key'  => 'fed:scene:rl:'.substr(hash('sha256', $baseUrl), 0, 24),
                'headers'   => $headers,
            ]);
        } catch (\Throwable $e) {
            Log::debug('[ahg-federation] RemoteSceneFetchService: peer scene fetch failed: '.$e->getMessage());
            $out['error'] = 'fetch failed';
            $out['trust_reason'] = 'fetch_failed';

            return $out;
        }

        if (($resp['status'] ?? '') !== 'success') {
            // SSRF-blocked / timed-out / rate-limited / non-2xx: report, never throw.
            $code = (int) ($resp['http_code'] ?? 0);
            $out['error'] = $resp['error'] ?: ($code > 0 ? ('peer HTTP '.$code) : 'peer unreachable');
            $out['trust_reason'] = 'fetch_failed';

            return $out;
        }

        $body = (string) ($resp['body'] ?? '');
        $respHeaders = is_array($resp['headers'] ?? null) ? $resp['headers'] : [];

        // ---- Verify the peer's detached signature + pin its key TOFU (T1 #1316) ----
        // verifyResponse() is fail-soft: an unsigned peer scene returns
        // verified=false / reason="unsigned" rather than throwing. We carry the
        // verdict through so the borrow side can flag / gate on it.
        $verdict = ['verified' => false, 'key_fingerprint' => null, 'reason' => 'unsigned'];
        try {
            $verdict = (new FederationVerifier())->verifyResponse($body, $respHeaders, $baseUrl);
        } catch (\Throwable $e) {
            Log::info('[ahg-federation] RemoteSceneFetchService: verify failed: '.$e->getMessage());
            $verdict = ['verified' => false, 'key_fingerprint' => null, 'reason' => 'error'];
        }
        $out['verified'] = (bool) ($verdict['verified'] ?? false);
        $out['key_fingerprint'] = $verdict['key_fingerprint'] ?? null;
        $out['trust_reason'] = (string) ($verdict['reason'] ?? 'unsigned');

        $data = json_decode($body, true);

        if (! is_array($data) || (($data['format'] ?? null) !== 'ahg-exhibition-scene')) {
            $out['error'] = 'not an ahg-exhibition-scene manifest';

            return $out;
        }

        $peerName = trim((string) ($data['exhibition']['name'] ?? '')) ?: $slug;
        $out['name'] = $peerName;
        $out['slug'] = trim((string) ($data['exhibition']['slug'] ?? '')) ?: $slug;

        // source_peer: the provenance envelope the borrow side stamps onto each
        // stored placement (mirrors FederationGraphService::source_peer).
        $sourcePeer = [
            'base_url' => $baseUrl,
            'name' => $peerName,
            'verified' => $out['verified'],
            'key_fingerprint' => $out['key_fingerprint'],
            'trust_reason' => $out['trust_reason'],
        ];
        $out['source_peer'] = $sourcePeer;

        foreach (($data['objects'] ?? []) as $o) {
            if (! is_array($o)) {
                continue;
            }
            $title = trim((string) ($o['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $out['objects'][] = [
                'ref' => (string) ($o['information_object_id'] ?? ''),   // the peer's object id (opaque to us)
                'title' => $title,
                'description' => trim((string) ($o['description'] ?? '')),
                'kind' => (string) ($o['media_kind'] ?? 'image'),
                'image_url' => $this->absOnly($o['image_url'] ?? null),
                'model_url' => $this->absOnly($o['model_url'] ?? null),
                'model_format' => (string) ($o['model_format'] ?? ''),
                'record_url' => $this->absOnly($o['record_url'] ?? null),
                'peer_name' => $peerName,
                'peer_base' => $baseUrl,
                // Cryptographic provenance carried per object so a single borrowed
                // object keeps its verdict once detached from the scene response.
                'verified' => $out['verified'],
                'key_fingerprint' => $out['key_fingerprint'],
                'source_peer' => $sourcePeer,
            ];
        }

        $out['ok'] = true;

        return $out;
    }

    /** Encode a normalised remote object into the JSON stored on ahg_exhibition_placement.remote_payload. */
    public function remotePayload(array $normalisedObject): string
    {
        return (string) json_encode($normalisedObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function isHttpUrl(string $url): bool
    {
        return (bool) preg_match('~^https?://~i', $url);
    }

    /** Only accept absolute http(s) media URLs from a peer (never a relative path that would resolve against the local host). */
    private function absOnly($url): ?string
    {
        $url = trim((string) ($url ?? ''));

        return ($url !== '' && $this->isHttpUrl($url)) ? $url : null;
    }
}
