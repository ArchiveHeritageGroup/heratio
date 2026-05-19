<?php

/**
 * GndAdapter - Heratio  (STUB)
 *
 * Task 6 of the AHG Authority Resolution Engine. GND (Gemeinsame
 * Normdatei), Deutsche Nationalbibliothek. Stub - DNB's lobid/Linked-Data
 * endpoint at https://lobid.org/gnd/search?q={q}&format=json is the
 * obvious target when this is fleshed out. Returns [] until then.
 *
 * Licence: CC0 1.0 Universal (DNB metadata dedication).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup\Adapters;

use AhgAuthorityResolution\Services\Lookup\AbstractLookupAdapter;

class GndAdapter extends AbstractLookupAdapter
{
    public function source(): string
    {
        return 'gnd';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['PERSON', 'ORG'], true);
    }

    protected function fetchFromSource(string $query, string $entityType, int $limit): array
    {
        // STUB: lobid.org GND integration pending. Returns [].
        return [];
    }
}
