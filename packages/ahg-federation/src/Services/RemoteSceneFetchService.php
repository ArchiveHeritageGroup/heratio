<?php

/**
 * RemoteSceneFetchService - Heratio ahg-federation
 *
 * heratio#1155 federated-twin foundation. Fetches a PEER institution's exhibition
 * "scene.json" export (the #1151 ahg-exhibition-scene 1.0 manifest, which is CORS-enabled)
 * and normalises its objects into placement-ready "remote stops", so a curator can borrow a
 * single remote object into a local exhibition as a READ-ONLY, attributed walkthrough stop
 * with a link back to the owning institution. Media (images / 3D models) is referenced by the
 * peer's absolute URL - nothing is harvested or copied here. Read-only by design; cross-node
 * rights/identity enforcement is out of scope (tracked under #1246 / #1155).
 *
 * This is a NEW service: the existing federation connectors + FederatedSearchService (the
 * search/harvest layer) are deliberately left untouched.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgFederation\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RemoteSceneFetchService
{
    /**
     * Fetch + normalise a peer exhibition's scene.json export.
     *
     * @return array{ok:bool, name:?string, slug:?string, source:string, objects:array<int,array<string,mixed>>, error:?string}
     */
    public function fetchScene(string $baseUrl, string $slug, ?string $apiKey = null, int $timeoutSeconds = 10): array
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $slug = trim($slug);
        $out = ['ok' => false, 'name' => null, 'slug' => $slug, 'source' => $baseUrl, 'objects' => [], 'error' => null];

        if (! $this->isHttpUrl($baseUrl) || $slug === '') {
            $out['error'] = 'invalid peer base URL or slug';

            return $out;
        }

        $url = $baseUrl.'/exhibition-space/'.rawurlencode($slug).'/scene.json';
        $headers = ['Accept' => 'application/json'];
        if ($apiKey !== null && trim($apiKey) !== '') {
            $headers['X-API-Key'] = trim($apiKey);   // same per-peer auth header the federation connectors use
        }

        try {
            $resp = Http::withHeaders($headers)
                ->connectTimeout(min($timeoutSeconds, 5))
                ->timeout(max(1, $timeoutSeconds))
                ->get($url);
            if (! $resp->successful()) {
                $out['error'] = 'peer HTTP '.$resp->status();

                return $out;
            }
            $data = $resp->json();
        } catch (\Throwable $e) {
            Log::debug('[ahg-federation] RemoteSceneFetchService: peer scene fetch failed: '.$e->getMessage());
            $out['error'] = 'fetch failed';

            return $out;
        }

        if (! is_array($data) || (($data['format'] ?? null) !== 'ahg-exhibition-scene')) {
            $out['error'] = 'not an ahg-exhibition-scene manifest';

            return $out;
        }

        $peerName = trim((string) ($data['exhibition']['name'] ?? '')) ?: $slug;
        $out['name'] = $peerName;
        $out['slug'] = trim((string) ($data['exhibition']['slug'] ?? '')) ?: $slug;

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
