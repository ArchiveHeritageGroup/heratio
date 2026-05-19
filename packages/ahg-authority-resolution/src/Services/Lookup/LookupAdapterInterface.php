<?php

/**
 * LookupAdapterInterface - Heratio
 *
 * Task 6 of the AHG Authority Resolution Engine. Common contract for every
 * external authority source we pre-fill against (VIAF, Wikidata, GeoNames,
 * TGN, GND, ISNI, SAGNC, ...). Other markets can add their own adapters
 * (Brazil IBGE, Australia Gazetteer, etc) by implementing this interface
 * and registering with the AhgAuthorityResolutionServiceProvider.
 *
 * Contract:
 *   - source(): the lower-cased source identifier (matches ahg_settings keys
 *     and ahg_authority_lookup_cache.source).
 *   - supports($entityType): does this adapter speak this entity type?
 *     ('PERSON', 'ORG', 'PLACE', 'GPE', 'LOC').
 *   - search($query, $entityType, $limit): returns an array of candidates,
 *     each shaped like:
 *       [
 *         'source'             => 'viaf',
 *         'external_id'        => '12345',
 *         'external_uri'       => 'https://viaf.org/viaf/12345/',
 *         'authorized_name'    => 'Nelson Mandela',
 *         'dates_of_existence' => '1918-2013',
 *         'history_snippet'    => 'South African anti-apartheid revolutionary...',
 *         'fields'             => [...source-specific],
 *         'licence'            => 'CC0-1.0',
 *         'licence_url'        => 'https://creativecommons.org/publicdomain/zero/1.0/',
 *         'retrieved_at'       => '2026-05-19T12:00:00Z',
 *       ]
 *
 * Adapter implementation rules:
 *   - If the source's `enabled` setting is 0, return [] without any HTTP.
 *   - Honour the rate_limit setting (best-effort, per-process timestamps OK).
 *   - Honour the cache_ttl setting via ahg_authority_lookup_cache.
 *   - Wrap any HTTP / parse error in try/catch and return [] on failure.
 *     External flakiness must never bubble up to the controller.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup;

interface LookupAdapterInterface
{
    public function source(): string;

    public function supports(string $entityType): bool;

    /**
     * @return list<array<string,mixed>>
     */
    public function search(string $query, string $entityType, int $limit = 5): array;
}
