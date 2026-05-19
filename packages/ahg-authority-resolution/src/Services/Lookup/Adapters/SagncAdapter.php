<?php

/**
 * SagncAdapter - Heratio  (STUB)
 *
 * Task 6 of the AHG Authority Resolution Engine. SAGNC - South African
 * Geographical Names Council. Stub. The official SAGNC database does not
 * yet expose a stable JSON/SPARQL API; this class is a placeholder so
 * other jurisdictional adapters (Brazil IBGE, Australia Gazetteer,
 * Aotearoa New Zealand Gazetteer, etc) can be added alongside it without
 * disturbing the core. Returns [] until the SAGNC endpoint is settled.
 *
 * Per the international-positioning rule (feedback_international_positioning):
 * Heratio is jurisdiction-neutral. SAGNC is one of many gazetteers; not a
 * default. Each market adds its own adapter following this same shape.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup\Adapters;

use AhgAuthorityResolution\Services\Lookup\AbstractLookupAdapter;

class SagncAdapter extends AbstractLookupAdapter
{
    private const PLACE_TYPES = ['GPE', 'LOC', 'PLACE', 'ISAD_PLACE'];

    public function source(): string
    {
        return 'sagnc';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PLACE_TYPES, true);
    }

    protected function fetchFromSource(string $query, string $entityType, int $limit): array
    {
        // STUB: SAGNC endpoint TBD. Brazil / other markets may register a
        // sibling adapter here. Returns [].
        return [];
    }
}
