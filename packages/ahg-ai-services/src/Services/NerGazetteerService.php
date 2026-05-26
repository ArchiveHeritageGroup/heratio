<?php
/**
 * NerGazetteerService - operator-curated NER gazetteer pre-pass.
 *
 * Issue #667 Phase 1.
 *
 * Runs BEFORE the ML extractor in NerService::extract(). Exact label match
 * + case-insensitive alias substring match against ahg_ner_custom_entity
 * produces a deterministic set of "found" entities that the operator has
 * explicitly told us to care about (project codenames, micro-locations,
 * organisation acronyms, etc.) - these are entities the ML model would
 * very likely miss because they are domain-specific.
 *
 * Returns a list normalised to NerService's bucket shape (persons /
 * organizations / places / dates / customs) plus a detailed v2 list that
 * mirrors entities_v2 (with offset_start/offset_end) so downstream
 * consumers can highlight matches in-place.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 */

declare(strict_types=1);

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class NerGazetteerService
{
    /** Map gazetteer entity_type to the NER buckets the rest of the system uses. */
    private const TYPE_BUCKET = [
        'person'       => 'persons',
        'persons'      => 'persons',
        'organization' => 'organizations',
        'organisations'=> 'organizations',
        'org'          => 'organizations',
        'place'        => 'places',
        'places'       => 'places',
        'location'     => 'places',
        'date'         => 'dates',
        'dates'        => 'dates',
    ];

    /**
     * Run the gazetteer over the given text.
     *
     * @return array{
     *     buckets: array{persons: list<string>, organizations: list<string>, places: list<string>, dates: list<string>, customs: list<string>},
     *     detailed: list<array{value:string,type:string,offset_start:int,offset_end:int,score:null,source:string,uri:?string}>
     * }
     */
    public function scan(string $text): array
    {
        $empty = [
            'buckets' => [
                'persons' => [],
                'organizations' => [],
                'places' => [],
                'dates' => [],
                'customs' => [],
            ],
            'detailed' => [],
        ];

        if (trim($text) === '') {
            return $empty;
        }

        try {
            if (!Schema::hasTable('ahg_ner_custom_entity')) {
                return $empty;
            }
            $rows = DB::table('ahg_ner_custom_entity')->where('is_active', 1)->get();
            if ($rows->isEmpty()) {
                return $empty;
            }
        } catch (Throwable $e) {
            Log::warning('[ahg-ai] NerGazetteer load failed: ' . $e->getMessage());
            return $empty;
        }

        $buckets  = $empty['buckets'];
        $detailed = [];

        foreach ($rows as $row) {
            $needles = [$row->label];
            if (!empty($row->aliases)) {
                $aliases = json_decode((string) $row->aliases, true);
                if (is_array($aliases)) {
                    foreach ($aliases as $a) {
                        if (is_string($a) && trim($a) !== '') {
                            $needles[] = $a;
                        }
                    }
                }
            }

            // Longest-first to prefer "New York City" over "New York".
            usort($needles, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

            foreach ($needles as $needle) {
                $offset = 0;
                $needleLen = strlen($needle);
                if ($needleLen === 0) {
                    continue;
                }
                while (($pos = stripos($text, $needle, $offset)) !== false) {
                    $detailed[] = [
                        'value'        => $row->label, // canonical label, not alias
                        'type'         => (string) $row->entity_type,
                        'offset_start' => $pos,
                        'offset_end'   => $pos + $needleLen,
                        'score'        => null,
                        'source'       => 'gazetteer',
                        'uri'          => $row->target_uri ?: null,
                    ];
                    $offset = $pos + $needleLen;
                }
            }

            $bucket = self::TYPE_BUCKET[strtolower((string) $row->entity_type)] ?? 'customs';
            // Only add the canonical label to the bucket if we found
            // at least one occurrence above.
            foreach ($detailed as $d) {
                if ($d['value'] === $row->label) {
                    if (!in_array($row->label, $buckets[$bucket], true)) {
                        $buckets[$bucket][] = $row->label;
                    }
                    break;
                }
            }
        }

        return [
            'buckets'  => $buckets,
            'detailed' => $detailed,
        ];
    }

    /**
     * Merge gazetteer results into ML-derived buckets. Gazetteer wins on
     * dedup (case-insensitive). Returns the same bucket shape NerService
     * already produces, plus a `customs` bucket for non-canonical types.
     *
     * @param array<string,list<string>> $mlBuckets
     * @param array<string,list<string>> $gazBuckets
     * @return array<string,list<string>>
     */
    public function merge(array $mlBuckets, array $gazBuckets): array
    {
        $out = [
            'persons'       => $mlBuckets['persons']       ?? [],
            'organizations' => $mlBuckets['organizations'] ?? [],
            'places'        => $mlBuckets['places']        ?? [],
            'dates'         => $mlBuckets['dates']         ?? [],
            'customs'       => $mlBuckets['customs']       ?? [],
        ];
        foreach (['persons', 'organizations', 'places', 'dates', 'customs'] as $k) {
            foreach (($gazBuckets[$k] ?? []) as $v) {
                $found = false;
                foreach ($out[$k] as $existing) {
                    if (strcasecmp((string) $existing, (string) $v) === 0) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $out[$k][] = $v;
                }
            }
        }
        return $out;
    }
}
