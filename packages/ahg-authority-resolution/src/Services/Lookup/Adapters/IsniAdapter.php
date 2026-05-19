<?php

/**
 * IsniAdapter - Heratio  (STUB)
 *
 * Task 6 of the AHG Authority Resolution Engine. ISNI - International
 * Standard Name Identifier. Stub - ISNI's SRU endpoint at
 * https://isni.oclc.org/sru/?... is the target when this is fleshed out.
 * Returns [] until then.
 *
 * Licence (when wired): ODC-BY 1.0.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup\Adapters;

use AhgAuthorityResolution\Services\Lookup\AbstractLookupAdapter;

class IsniAdapter extends AbstractLookupAdapter
{
    public function source(): string
    {
        return 'isni';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['PERSON', 'ORG'], true);
    }

    protected function fetchFromSource(string $query, string $entityType, int $limit): array
    {
        // STUB: ISNI SRU integration pending. Returns [].
        return [];
    }
}
