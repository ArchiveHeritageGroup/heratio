<?php

/**
 * OaiPmhConnector — wraps an OAI-PMH peer's JSON search API as a PeerConnector.
 *
 * The existing federation peers expose a JSON search endpoint at
 * federation_peer_search.search_api_url (falling back to base_url/api/search).
 * Despite the "OAI" name, the search path is JSON-over-HTTP, not OAI-PMH XML.
 * The OAI-PMH side handles the harvest path, not the real-time search path.
 *
 * This connector accepts the JOINed federation_peer + federation_peer_search row
 * via bind() and issues a single cURL request to its search_url, parsing the
 * JSON and mapping to PeerSearchResult value objects.
 *
 * Equivalent to the legacy `createSearchRequest` + `processPeerResponse` flow
 * in FederatedSearchService — but isolated so the dispatcher can register
 * connectors by peer_type without a giant switch.
 *
 * @phase C
 */

namespace AhgFederation\Connectors;

final class OaiPmhConnector implements PeerConnector
{
    public const PEER_TYPE = 'oai_pmh';

    /** Block obvious cloud-metadata IPs (SSRF). */
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
        $url = $this->buildUrl($query, $filters, $limit);
        if ($url === null) {
            return [];
        }

        $handle = curl_init($url);
        if ($handle === false) {
            return [];
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER     => true,
            CURLOPT_TIMEOUT_MS         => (int) ($this->peer->timeout_ms ?? 5000),
            CURLOPT_CONNECTTIMEOUT_MS  => (int) min(2000, $this->peer->timeout_ms ?? 5000),
            CURLOPT_FOLLOWLOCATION     => true,
            CURLOPT_MAXREDIRS          => 3,
            CURLOPT_SSL_VERIFYPEER     => true,
            CURLOPT_SSL_VERIFYHOST     => 2,
            CURLOPT_HTTPHEADER         => $this->buildHeaders(),
        ]);

        $response = curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error    = curl_error($handle);
        curl_close($handle);

        if ($error !== '' || $httpCode !== 200 || !is_string($response)) {
            return [];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = $decoded['results'] ?? $decoded['items'] ?? $decoded['records'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $peerName = (string) ($this->peer->peer_name ?? $this->peer->name ?? 'peer');
        $badge = sprintf('Federated from %s', $peerName);

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $sourceId = (string) ($item['id'] ?? $item['identifier'] ?? '');
            if ($sourceId === '') { continue; }
            $title = (string) ($item['title'] ?? $item['name'] ?? 'Untitled');
            $snippet = $item['description'] ?? $item['scopeAndContent'] ?? null;
            $url = $item['url'] ?? $item['permalink'] ?? rtrim((string) $this->peer->base_url, '/');
            $score = is_numeric($item['score'] ?? $item['relevance'] ?? null)
                ? (float) ($item['score'] ?? $item['relevance'])
                : 1.0;

            $out[] = new PeerSearchResult(
                sourceId: $sourceId,
                title: $title,
                snippet: is_string($snippet) ? $snippet : null,
                url: (string) $url,
                peerType: self::PEER_TYPE,
                sourceBadge: $badge,
                score: $this->normaliseScore($score),
                dedupeKey: $item['dedupeKey'] ?? null,
                date: $item['date'] ?? $item['dateDisplay'] ?? null,
                extras: [
                    'peer_id'     => (int) ($this->peer->peer_id ?? $this->peer->id),
                    'peer_name'   => $peerName,
                    'reference'   => $item['referenceCode'] ?? $item['identifier'] ?? null,
                    'level'       => $item['levelOfDescription'] ?? $item['level'] ?? null,
                    'thumbnail'   => $item['thumbnailUrl'] ?? $item['thumbnail'] ?? null,
                ],
            );
        }
        return array_slice($out, 0, $limit);
    }

    private function buildUrl(string $query, array $filters, int $limit): ?string
    {
        $base = $this->peer->search_url
            ?? ($this->peer->search_api_url
                ?? rtrim((string) ($this->peer->base_url ?? ''), '/') . '/api/search');
        if (!is_string($base) || $base === '') {
            return null;
        }

        $host = strtolower(parse_url($base, PHP_URL_HOST) ?? '');
        if (in_array($host, self::SSRF_BLOCKED_HOSTS, true)) {
            return null;
        }

        $params = [
            'q'      => $query,
            'limit'  => $limit,
            'format' => 'json',
        ];
        if (!empty($filters['date_range']['from'])) { $params['dateFrom'] = $filters['date_range']['from']; }
        if (!empty($filters['date_range']['to']))   { $params['dateTo']   = $filters['date_range']['to']; }
        if (!empty($filters['source']))             { $params['source']   = $filters['source']; }

        return $base . '?' . http_build_query($params);
    }

    /** @return array<int,string> */
    private function buildHeaders(): array
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: AHG-Federation-OAI-Connector/1.0',
        ];
        $key = $this->peer->search_api_key ?? $this->peer->api_key ?? null;
        if (is_string($key) && $key !== '') {
            $headers[] = 'X-API-Key: ' . $key;
        }
        return $headers;
    }

    /** Clamp score into 0..1. Peers commonly return >1 raw scores. */
    private function normaliseScore(float $score): float
    {
        if ($score <= 0)  { return 0.0; }
        if ($score <= 1)  { return $score; }
        if ($score <= 10) { return $score / 10.0; }
        return 1.0;
    }
}
