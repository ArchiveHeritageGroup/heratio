<?php

/**
 * DSpaceConnector - real-time federated search against a DSpace 7+ repository
 * via its REST API (#1329).
 *
 * DSpace is widely deployed at universities; this lets a Heratio federation peer
 * of peer_type 'dspace' be searched live alongside AtoM/OAI peers. It queries the
 * discovery endpoint
 *   {rest_base}/discover/search/objects?query=...&dsoType=item
 * and maps each item's Dublin Core metadata to a PeerSearchResult.
 *
 * rest_base defaults to {base_url}/server/api (the DSpace 7 backend path); a peer
 * whose base_url already points at the REST root, or a non-standard deployment,
 * can override it with config.rest_base_url. The click-through URL prefers the
 * item's own dc.identifier.uri (its persistent handle URL) and falls back to the
 * frontend handle path.
 *
 * Harvest/import (pulling DSpace items in as information objects) is a separate
 * path: DSpace exposes standard OAI-PMH at {base_url}/server/oai/request, so an
 * oai_pmh harvest peer covers it without new code - see the package README.
 *
 * Mirrors OaiPmhConnector's SSRF host block and point-of-use credential decrypt.
 *
 * @phase F (issue #1329)
 */

namespace AhgFederation\Connectors;

use Illuminate\Support\Facades\Http;

final class DSpaceConnector implements PeerConnector
{
    public const PEER_TYPE = 'dspace';

    /** Block obvious cloud-metadata IPs (SSRF), same list as OaiPmhConnector. */
    private const SSRF_BLOCKED_HOSTS = [
        '169.254.169.254',
        'metadata.google.internal',
        'metadata.internal',
    ];

    private object $peer;

    public function bind(object $peerRow): void
    {
        $this->peer = $peerRow;
    }

    public function peerTypeKey(): string
    {
        return self::PEER_TYPE;
    }

    public function supportsCapability(string $capability): bool
    {
        return in_array($capability, ['full_text_search', 'metadata_filter', 'date_range'], true);
    }

    public function search(string $query, array $filters = [], int $limit = 50): array
    {
        $endpoint = $this->searchEndpoint();
        if ($endpoint === null) {
            return [];
        }

        $params = [
            'query'   => $query,
            'size'    => max(1, min($limit, 100)),
            'dsoType' => 'item',
        ];
        // DSpace discovery date facet: f.dateIssued=[YYYY TO YYYY]. Only the year
        // is reliable across deployments, so we reduce to the leading 4 digits.
        $from = $this->year($filters['date_range']['from'] ?? null);
        $to = $this->year($filters['date_range']['to'] ?? null);
        if ($from !== null || $to !== null) {
            $params['f.dateIssued'] = sprintf('[%s TO %s]', $from ?? '*', $to ?? '*');
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout((int) ceil(((int) ($this->peer->timeout_ms ?? 5000)) / 1000))
                ->connectTimeout(2)
                ->acceptJson()
                ->get($endpoint, $params);
        } catch (\Throwable $e) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $objects = data_get($response->json(), '_embedded.searchResult._embedded.objects', []);
        if (! is_array($objects)) {
            return [];
        }

        $peerName = (string) ($this->peer->peer_name ?? $this->peer->name ?? 'DSpace');
        $badge = sprintf('Federated from %s (DSpace)', $peerName);

        $out = [];
        $i = 0;
        foreach ($objects as $wrapper) {
            $io = data_get($wrapper, '_embedded.indexableObject');
            if (! is_array($io)) {
                continue;
            }

            $uuid = (string) ($io['uuid'] ?? $io['id'] ?? '');
            if ($uuid === '') {
                continue;
            }

            $title = $this->md($io, 'dc.title') ?? (string) ($io['name'] ?? 'Untitled');
            $snippet = $this->md($io, 'dc.description.abstract') ?? $this->md($io, 'dc.description');
            $handle = (string) ($io['handle'] ?? '');
            $url = $this->md($io, 'dc.identifier.uri') ?? $this->handleUrl($handle);

            $out[] = new PeerSearchResult(
                sourceId: $uuid,
                title: $title,
                snippet: $snippet !== null ? mb_substr($snippet, 0, 280) : null,
                url: $url,
                peerType: self::PEER_TYPE,
                sourceBadge: $badge,
                // DSpace returns items in relevance order but no per-item score;
                // preserve the order as a gently decaying score for the merge.
                score: max(0.1, 1.0 - ($i * 0.02)),
                dedupeKey: $handle !== '' ? 'hdl:'.$handle : null,
                date: $this->md($io, 'dc.date.issued'),
                extras: [
                    'peer_id'   => (int) ($this->peer->peer_id ?? $this->peer->id ?? 0),
                    'peer_name' => $peerName,
                    'handle'    => $handle ?: null,
                    'author'    => $this->md($io, 'dc.contributor.author'),
                    'type'      => $this->md($io, 'dc.type') ?? ($io['entityType'] ?? null),
                    'reference' => $handle ?: null,
                ],
            );
            $i++;
        }

        return array_slice($out, 0, $limit);
    }

    /** Resolve and SSRF-guard the discovery search endpoint. */
    private function searchEndpoint(): ?string
    {
        $config = $this->peerConfig();
        $restBase = $config['rest_base_url'] ?? null;
        if (! is_string($restBase) || $restBase === '') {
            $base = rtrim((string) ($this->peer->base_url ?? ''), '/');
            if ($base === '') {
                return null;
            }
            // If the base already includes the REST path, don't double it.
            $restBase = str_contains($base, '/server/api') ? $base : $base.'/server/api';
        }
        $restBase = rtrim($restBase, '/');

        $host = strtolower((string) (parse_url($restBase, PHP_URL_HOST) ?? ''));
        if ($host === '' || in_array($host, self::SSRF_BLOCKED_HOSTS, true)) {
            return null;
        }

        return $restBase.'/discover/search/objects';
    }

    /** @return array<string,mixed> */
    private function peerConfig(): array
    {
        $raw = $this->peer->config ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * First value of a Dublin Core metadata field on a DSpace indexableObject.
     *
     * NB: DSpace metadata keys contain literal dots (`dc.title`, `dc.date.issued`),
     * so the array must be indexed by the whole key - `data_get()` dot-notation
     * would wrongly split `dc.title` into nested `dc` -> `title` and always miss.
     */
    private function md(array $io, string $key): ?string
    {
        $meta = $io['metadata'] ?? null;
        if (! is_array($meta) || ! isset($meta[$key][0]['value'])) {
            return null;
        }
        $val = $meta[$key][0]['value'];

        return (is_string($val) && $val !== '') ? $val : null;
    }

    private function handleUrl(string $handle): string
    {
        if ($handle === '') {
            return rtrim((string) ($this->peer->base_url ?? ''), '/');
        }
        $frontend = rtrim((string) ($this->peerConfig()['frontend_url'] ?? $this->peer->base_url ?? ''), '/');
        // Strip a trailing REST path if base_url pointed at the backend.
        $frontend = preg_replace('#/server/api/?$#', '', $frontend) ?? $frontend;

        return $frontend.'/handle/'.$handle;
    }

    private function year(?string $date): ?string
    {
        if (! is_string($date) || ! preg_match('/(\d{4})/', $date, $m)) {
            return null;
        }

        return $m[1];
    }

    /** @return array<string,string> */
    private function headers(): array
    {
        $headers = ['User-Agent' => 'AHG-Federation-DSpace-Connector/1.0'];

        // Optional auth (a protected/private DSpace). Point-of-use decrypt, same
        // as OaiPmhConnector; public repositories need none.
        $key = \AhgFederation\Support\PeerSecret::decrypt($this->peer->search_api_key ?? $this->peer->api_key ?? null);
        if (is_string($key) && $key !== '') {
            $headers['Authorization'] = str_starts_with($key, 'Bearer ') ? $key : 'Bearer '.$key;
        }

        return $headers;
    }
}
