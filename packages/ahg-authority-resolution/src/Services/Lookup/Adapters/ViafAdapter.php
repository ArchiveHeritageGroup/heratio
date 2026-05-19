<?php

/**
 * ViafAdapter - Heratio
 *
 * Task 6 of the AHG Authority Resolution Engine. Looks up PERSON / ORG
 * authority records in VIAF (Virtual International Authority File).
 *
 * Two-step lookup:
 *   1. AutoSuggest:  https://viaf.org/viaf/AutoSuggest?query={q}
 *      Cheap and JSON; returns up to 10 hits with term + viafid.
 *   2. (Implicit)    The detail link is then https://viaf.org/viaf/{id}/ -
 *      we surface that as `external_uri` without fetching it; the Pre-fill
 *      Engine treats the AutoSuggest payload as sufficient for the form's
 *      authorized_form_of_name + a stable URI for provenance.
 *
 * Licence: VIAF data is CC0 1.0 Universal (public domain dedication).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup\Adapters;

use AhgAuthorityResolution\Services\Lookup\AbstractLookupAdapter;
use Illuminate\Support\Facades\Http;

class ViafAdapter extends AbstractLookupAdapter
{
    private const URL = 'https://viaf.org/viaf/AutoSuggest';

    public function source(): string
    {
        return 'viaf';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['PERSON', 'ORG'], true);
    }

    protected function fetchFromSource(string $query, string $entityType, int $limit): array
    {
        $response = Http::timeout($this->httpTimeout())
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Heratio Authority Resolution (https://heratio.theahg.co.za)',
            ])
            ->get(self::URL, ['query' => $query]);

        if (!$response->ok()) {
            return [];
        }

        $json = $response->json();
        $results = $json['result'] ?? null;
        if (!is_array($results)) {
            return [];
        }

        $candidates = [];
        $licence = $this->licenceNote() ?? 'CC0-1.0';
        $licenceUrl = $this->licenceUrl() ?? 'https://creativecommons.org/publicdomain/zero/1.0/';
        $retrievedAt = $this->nowIso();

        foreach ($results as $hit) {
            $viafId = $hit['viafid'] ?? null;
            $name = $hit['term'] ?? $hit['displayForm'] ?? null;
            if (!$viafId || !$name) {
                continue;
            }
            // Best-effort entity-type filter: VIAF nametype is "personal" /
            // "corporate" / "geographic" / "uniformtitle". Map back to ours.
            $nameType = strtolower((string) ($hit['nametype'] ?? ''));
            $isPerson = $nameType === '' || str_contains($nameType, 'personal');
            $isOrg = str_contains($nameType, 'corporate');
            if ($entityType === 'PERSON' && !$isPerson) {
                continue;
            }
            if ($entityType === 'ORG' && !$isOrg) {
                continue;
            }

            $candidates[] = [
                'source' => $this->source(),
                'external_id' => (string) $viafId,
                'external_uri' => 'https://viaf.org/viaf/' . $viafId . '/',
                'authorized_name' => (string) $name,
                'dates_of_existence' => $this->extractDates($hit),
                'history_snippet' => null,
                'fields' => [
                    'authorized_form_of_name' => (string) $name,
                    'dates_of_existence' => $this->extractDates($hit),
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

    /**
     * VIAF AutoSuggest dates are sometimes baked into the term (e.g.
     * "Mandela, Nelson, 1918-2013") - try to peel them out cleanly.
     */
    private function extractDates(array $hit): ?string
    {
        $term = (string) ($hit['term'] ?? '');
        if (preg_match('/(\d{3,4})\s*-\s*(\d{3,4})/', $term, $m)) {
            return $m[1] . '-' . $m[2];
        }
        if (preg_match('/(\d{3,4})\s*-/', $term, $m)) {
            return $m[1] . '-';
        }
        return null;
    }
}
