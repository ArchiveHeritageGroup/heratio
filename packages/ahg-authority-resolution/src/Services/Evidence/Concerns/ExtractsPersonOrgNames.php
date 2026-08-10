<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgAuthorityResolution\Services\Evidence\Concerns;

/**
 * Extract distinct person/organisation names from a co-occurring-entities
 * JSON blob. Byte-identical in CoOccurringPersonEvaluator and
 * RelationalEvaluator. Uses the host's PERSON_ORG_TYPES constant.
 */
trait ExtractsPersonOrgNames
{
    private function coOccurringPersonOrgNames($cooccurringJson): array
    {
        $rows = \AhgAuthorityResolution\Services\Evidence\EvidenceDateUtil::decodeJsonish($cooccurringJson);
        if (! is_array($rows)) {
            return [];
        }
        $names = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = (string) ($row['type'] ?? '');
            if (! in_array($type, self::PERSON_ORG_TYPES, true)) {
                continue;
            }
            $value = (string) ($row['value'] ?? '');
            if ($value === '') {
                continue;
            }
            $names[] = $value;
        }

        return array_values(array_unique($names));
    }
}
