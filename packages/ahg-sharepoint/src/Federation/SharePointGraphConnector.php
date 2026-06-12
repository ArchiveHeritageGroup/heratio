<?php

/**
 * SharePointGraphConnector — federation peer implemented against the Microsoft
 * Graph search API.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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
 *
 * -----------------------------------------------------------------------------
 * INERT SCAFFOLD — issue #1221, step 1 (non-destructive).
 *
 * This is the new canonical home for the SharePoint federation connector. It is
 * a re-namespaced copy of the F3 connector that currently lives (as canonical
 * upstream source) at:
 *   /opt/ahg-sp-integration/F3/heratio/src/Connectors/SharePointGraphConnector.php
 * under namespace AhgFederation\Connectors.
 *
 * IMPORTANT — this class is INERT until the cutover described in MIGRATION.md:
 *   * It is NOT registered in connectorClassFor() of any live FederatedSearchService.
 *     The live ahg-federation FederatedSearchService on disk is the pre-F3 build
 *     and has no connector dispatch at all, so nothing references this class.
 *   * Its FQCN (AhgSharePoint\Federation\SharePointGraphConnector) differs from
 *     the upstream F3 FQCN (AhgFederation\Connectors\SharePointGraphConnector),
 *     so there is no class-name collision and no double registration.
 *   * It registers no routes and is not constructed at boot.
 *
 * Functionally identical to the upstream F3 connector. The only edits are:
 *   1. namespace AhgFederation\Connectors  ->  namespace AhgSharePoint\Federation
 *   2. it implements the local AhgSharePoint\Federation\PeerConnector and returns
 *      the local AhgSharePoint\Federation\PeerSearchResult
 *   3. GraphClientService is already in this package's namespace (no `use` change)
 *
 * Microsoft Graph KQL query construction:
 *   Base:     {query}
 *   Scope:    AND (siteId:<id> OR siteId:<id> ...)  when default_site_ids configured
 *   Or:       AND (driveId:<id> ...)                when default_drive_ids configured
 *   Filters:  AND Modified>=<from> AND Modified<=<to>  for date_range
 *
 * Result shape:
 *   Graph returns hitsContainers[].hits[].resource — typically driveItem or listItem.
 *   We map: title=name, snippet=summary, url=webUrl, dedupeKey=id, date=lastModifiedDateTime.
 *
 * Auth: app-only via GraphClientService::post(tenantId, ...). On-behalf-of mode
 * deferred to a later release per the F3 locked decision set.
 *
 * Peer config (JSON in federation_peer.config):
 *   {
 *     "tenant_id": 1,                       // FK to sharepoint_tenant.id (NOT the AAD tenant GUID)
 *     "default_site_ids": ["site1-guid"],   // optional KQL scope
 *     "default_drive_ids": ["drive1-id"],   // optional KQL scope
 *     "max_results_per_query": 50
 *   }
 * -----------------------------------------------------------------------------
 */

namespace AhgSharePoint\Federation;

use AhgSharePoint\Services\GraphClientService;

final class SharePointGraphConnector implements PeerConnector
{
    public const PEER_TYPE = 'sharepoint_graph_search';

    private object $peer;

    /** @var array<string,mixed> */
    private array $config = [];

    public function __construct(
        private readonly GraphClientService $graph,
    ) {
    }

    public function bind(object $peerRow): void
    {
        $this->peer = $peerRow;
        $raw = $peerRow->config ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $this->config = is_array($decoded) ? $decoded : [];
        } elseif (is_array($raw)) {
            $this->config = $raw;
        } else {
            $this->config = [];
        }
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
        $tenantId = (int) ($this->config['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return [];
        }

        $configuredLimit = (int) ($this->config['max_results_per_query'] ?? $limit);
        $effectiveLimit = max(1, min($limit, $configuredLimit, 50));

        $kql = $this->buildKql($query, $filters);
        $body = [
            'requests' => [
                [
                    'entityTypes' => ['driveItem', 'listItem'],
                    'query'       => ['queryString' => $kql],
                    'from'        => 0,
                    'size'        => $effectiveLimit,
                ],
            ],
        ];

        try {
            $response = $this->graph->post($tenantId, '/search/query', $body);
        } catch (\Throwable $e) {
            \Log::warning('SharePointGraphConnector search failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            return [];
        }

        $hits = $this->extractHits($response);
        if (empty($hits)) {
            return [];
        }

        $maxRank = max(array_column($hits, 'rank') ?: [1]);

        $out = [];
        foreach ($hits as $hit) {
            $resource = $hit['resource'] ?? [];
            if (!is_array($resource)) { continue; }
            $sourceId = (string) ($resource['id'] ?? '');
            if ($sourceId === '') { continue; }

            $title = (string) ($resource['name'] ?? $resource['title'] ?? $resource['displayName'] ?? 'Untitled');
            $summary = $hit['summary'] ?? null;
            if (is_string($summary)) {
                $summary = strip_tags($summary, '<mark>');
            }
            $url = (string) ($resource['webUrl'] ?? '');
            $modified = $resource['lastModifiedDateTime'] ?? $resource['fileSystemInfo']['lastModifiedDateTime'] ?? null;

            $rank = isset($hit['rank']) ? (int) $hit['rank'] : 1;
            $score = $maxRank > 0 ? max(0.0, 1.0 - ($rank - 1) / max(1, $maxRank)) : 1.0;

            $siteName = $resource['parentReference']['siteId'] ?? '';
            $badge = $siteName !== ''
                ? sprintf('Active in SharePoint · %s', $this->siteLabel($siteName))
                : 'Active in SharePoint';

            $out[] = new PeerSearchResult(
                sourceId: $sourceId,
                title: $title,
                snippet: is_string($summary) ? $summary : null,
                url: $url,
                peerType: self::PEER_TYPE,
                sourceBadge: $badge,
                score: $score,
                dedupeKey: $sourceId,
                date: is_string($modified) ? $modified : null,
                extras: [
                    'tenant_id'   => $tenantId,
                    'site_id'     => $resource['parentReference']['siteId'] ?? null,
                    'drive_id'    => $resource['parentReference']['driveId'] ?? null,
                    'item_id'     => $resource['id'] ?? null,
                    'mime_type'   => $resource['file']['mimeType'] ?? null,
                    'size_bytes'  => $resource['size'] ?? null,
                ],
            );
        }
        return $out;
    }

    private function buildKql(string $query, array $filters): string
    {
        $clauses = [trim($query) !== '' ? '(' . $query . ')' : '*'];

        $siteIds = (array) ($this->config['default_site_ids'] ?? []);
        if (!empty($siteIds)) {
            // siteIds contain commas and punctuation — KQL tokenizes on these, so wrap in quotes.
            // Embedded double-quotes are stripped (not legal in Graph siteIds).
            $siteClauses = array_map(
                static fn ($s) => 'siteId:"' . str_replace('"', '', (string) $s) . '"',
                $siteIds,
            );
            $clauses[] = '(' . implode(' OR ', $siteClauses) . ')';
        }

        $driveIds = (array) ($this->config['default_drive_ids'] ?? []);
        if (!empty($driveIds)) {
            $driveClauses = array_map(
                static fn ($d) => 'driveId:"' . str_replace('"', '', (string) $d) . '"',
                $driveIds,
            );
            $clauses[] = '(' . implode(' OR ', $driveClauses) . ')';
        }

        if (!empty($filters['date_range']['from'])) {
            $clauses[] = 'Modified>=' . substr((string) $filters['date_range']['from'], 0, 10);
        }
        if (!empty($filters['date_range']['to'])) {
            $clauses[] = 'Modified<=' . substr((string) $filters['date_range']['to'], 0, 10);
        }

        return implode(' AND ', $clauses);
    }

    private function extractHits(array $response): array
    {
        $values = $response['value'] ?? [];
        if (!is_array($values)) {
            return [];
        }

        $allHits = [];
        foreach ($values as $value) {
            $containers = $value['hitsContainers'] ?? [];
            if (!is_array($containers)) { continue; }
            foreach ($containers as $container) {
                $hits = $container['hits'] ?? [];
                if (is_array($hits)) {
                    foreach ($hits as $hit) {
                        if (is_array($hit)) {
                            $allHits[] = $hit;
                        }
                    }
                }
            }
        }
        return $allHits;
    }

    private function siteLabel(string $siteId): string
    {
        // siteId format: {hostname},{site-guid},{web-guid}. We surface the hostname.
        $parts = explode(',', $siteId, 2);
        return $parts[0] !== '' ? $parts[0] : $siteId;
    }
}
