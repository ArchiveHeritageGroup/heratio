<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDataMigration\SourceMigration;

/**
 * heratio#1495: identifies a vendor export from its column headings, using the
 * signature each mapping file declares. Adding a vendor adds a file, not code.
 */
class SourceDetector
{
    /** @return array{source:string,label:string,confidence:float}|null */
    public function detect(array $headers): ?array
    {
        $have = array_flip(array_map(fn ($h) => mb_strtolower(trim((string) $h)), $headers));
        $best = null;
        foreach (MappingFile::available() as $name) {
            $mapping = MappingFile::load($name);
            $required = $mapping['signature']['required'] ?? [];
            $supporting = $mapping['signature']['supporting'] ?? [];
            if ($required === []) {
                continue;
            }
            foreach ($required as $column) {
                if (! isset($have[mb_strtolower($column)])) {
                    continue 2;
                }
            }
            $found = count(array_filter($supporting, fn ($c) => isset($have[mb_strtolower($c)])));
            $confidence = round((count($required) + $found) / (count($required) + count($supporting)), 2);
            if ($best === null || $confidence > $best['confidence']) {
                $best = ['source' => $mapping['source'], 'label' => $mapping['label'] ?? $mapping['source'], 'confidence' => $confidence];
            }
        }

        return $best;
    }
}
