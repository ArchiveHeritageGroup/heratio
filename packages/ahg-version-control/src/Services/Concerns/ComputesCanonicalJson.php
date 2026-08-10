<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgVersionControl\Services\Concerns;

/**
 * Deterministic (key-sorted) JSON encoding for stable byte-wise diffing.
 * Was byte-identical in DiffComputer and VersionWriter.
 */
trait ComputesCanonicalJson
{
    private function canonicalJson(mixed $value): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return '['.implode(',', array_map(fn ($v) => $this->canonicalJson($v), $value)).']';
            }
            ksort($value);
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = json_encode((string) $k, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    .':'.$this->canonicalJson($v);
            }

            return '{'.implode(',', $parts).'}';
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
