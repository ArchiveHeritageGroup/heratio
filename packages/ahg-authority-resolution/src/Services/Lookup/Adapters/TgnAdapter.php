<?php

/**
 * TgnAdapter - Heratio  (STUB)
 *
 * Task 6 of the AHG Authority Resolution Engine. Getty Thesaurus of
 * Geographic Names (TGN). Getty's public SPARQL endpoint has changed URL /
 * shape several times; rather than ship a half-working integration we
 * register the adapter but return an empty result set. Enabling
 * `lookup.tgn.enabled` is harmless - no HTTP fires until this class is
 * fleshed out with a stable endpoint and SPARQL query.
 *
 * Licence (when wired): ODC-BY 1.0 + attribution to Getty Research Institute.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup\Adapters;

use AhgAuthorityResolution\Services\Lookup\AbstractLookupAdapter;

class TgnAdapter extends AbstractLookupAdapter
{
    private const PLACE_TYPES = ['GPE', 'LOC', 'PLACE', 'ISAD_PLACE'];

    public function source(): string
    {
        return 'tgn';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PLACE_TYPES, true);
    }

    protected function fetchFromSource(string $query, string $entityType, int $limit): array
    {
        // STUB: Getty SPARQL endpoint integration pending. Returning [] so
        // graceful degrade is preserved even if a settings admin enables
        // this source by mistake.
        return [];
    }
}
