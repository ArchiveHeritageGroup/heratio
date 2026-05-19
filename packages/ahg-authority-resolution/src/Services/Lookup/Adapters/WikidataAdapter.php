<?php

/**
 * WikidataAdapter - Heratio
 *
 * Task 6 of the AHG Authority Resolution Engine. Looks up PERSON / ORG /
 * PLACE candidates in Wikidata.
 *
 * Endpoint: https://www.wikidata.org/w/api.php
 *           action=wbsearchentities&search={q}&language=en&type=item&format=json
 *
 * Each search hit gives us the Q-id + label + (sometimes) a short
 * description. That is enough for pre-fill + provenance; we do NOT recurse
 * into wbgetentities here (would explode call budget). The Pre-fill Engine
 * can later add a deep-fetch path if required.
 *
 * Licence: Wikidata structured data is CC0 1.0 Universal.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup\Adapters;

use AhgAuthorityResolution\Services\Lookup\AbstractLookupAdapter;
use Illuminate\Support\Facades\Http;

class WikidataAdapter extends AbstractLookupAdapter
{
    private const URL = 'https://www.wikidata.org/w/api.php';

    private const PLACE_TYPES = ['GPE', 'LOC', 'PLACE', 'ISAD_PLACE'];

    public function source(): string
    {
        return 'wikidata';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['PERSON', 'ORG'], true)
            || in_array($entityType, self::PLACE_TYPES, true);
    }

    protected function fetchFromSource(string $query, string $entityType, int $limit): array
    {
        $response = Http::timeout($this->httpTimeout())
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Heratio Authority Resolution (https://heratio.theahg.co.za)',
            ])
            ->get(self::URL, [
                'action' => 'wbsearchentities',
                'search' => $query,
                'language' => 'en',
                'uselang' => 'en',
                'type' => 'item',
                'format' => 'json',
                'limit' => max(1, min(20, $limit + 5)),
            ]);

        if (!$response->ok()) {
            return [];
        }

        $json = $response->json();
        $hits = $json['search'] ?? [];
        if (!is_array($hits)) {
            return [];
        }

        $candidates = [];
        $licence = $this->licenceNote() ?? 'CC0-1.0';
        $licenceUrl = $this->licenceUrl() ?? 'https://creativecommons.org/publicdomain/zero/1.0/';
        $retrievedAt = $this->nowIso();

        $isPlace = in_array($entityType, self::PLACE_TYPES, true);

        foreach ($hits as $hit) {
            $qid = $hit['id'] ?? null;
            $label = $hit['label'] ?? $hit['title'] ?? null;
            if (!$qid || !$label) {
                continue;
            }
            $desc = $hit['description'] ?? null;

            $candidates[] = [
                'source' => $this->source(),
                'external_id' => (string) $qid,
                'external_uri' => 'https://www.wikidata.org/wiki/' . $qid,
                'authorized_name' => (string) $label,
                'dates_of_existence' => null,
                'history_snippet' => $desc !== null ? (string) $desc : null,
                'fields' => $isPlace
                    ? [
                        'name' => (string) $label,
                        'description' => $desc,
                        'descriptive_standard' => 'ISDF',
                    ]
                    : [
                        'authorized_form_of_name' => (string) $label,
                        'history' => $desc,
                        'entity_type' => $entityType,
                        'descriptive_standard' => 'ISAAR-CPF',
                    ],
                'licence' => $licence,
                'licence_url' => $licenceUrl,
                'retrieved_at' => $retrievedAt,
            ];
            if (count($candidates) >= $limit) {
                break;
            }
        }

        return $candidates;
    }
}
