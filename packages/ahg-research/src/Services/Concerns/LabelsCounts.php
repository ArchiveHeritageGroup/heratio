<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Services\Concerns;

/**
 * Join a code=>count map with a code=>label map into a list of
 * {code,label,count} rows (unknown codes get a humanised label). Byte-
 * identical in Funding/Ethics/Milestone/Team services.
 */
trait LabelsCounts
{
    private function labelCounts(array $counts, array $labels): array
    {
        $out = [];
        foreach ($labels as $code => $label) {
            if (isset($counts[$code])) {
                $out[] = ['code' => (string) $code, 'label' => (string) $label, 'count' => (int) $counts[$code]];
            }
        }
        foreach ($counts as $code => $c) {
            if (! isset($labels[$code])) {
                $out[] = ['code' => (string) $code, 'label' => ucfirst(str_replace('_', ' ', (string) $code)), 'count' => (int) $c];
            }
        }

        return $out;
    }
}
