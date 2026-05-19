<?php

/**
 * GeoNamesAdapter - Heratio
 *
 * Task 6 of the AHG Authority Resolution Engine. Looks up PLACE candidates
 * in GeoNames.
 *
 * Endpoint: http://api.geonames.org/searchJSON?q={q}&maxRows={n}&username={u}
 *           (the username is required by the API; configured via
 *           ahg_settings.lookup.geonames.username - default 'demo' is
 *           rate-limited / discouraged. Replace with a registered account
 *           before serious use.)
 *
 * Licence: GeoNames data is CC BY 4.0 - attribution required.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services\Lookup\Adapters;

use AhgAuthorityResolution\Services\Lookup\AbstractLookupAdapter;
use Illuminate\Support\Facades\Http;

class GeoNamesAdapter extends AbstractLookupAdapter
{
    private const URL = 'http://api.geonames.org/searchJSON';

    private const PLACE_TYPES = ['GPE', 'LOC', 'PLACE', 'ISAD_PLACE'];

    public function source(): string
    {
        return 'geonames';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PLACE_TYPES, true);
    }

    protected function fetchFromSource(string $query, string $entityType, int $limit): array
    {
        $username = (string) ($this->settingValue('lookup.geonames.username', 'demo') ?: 'demo');

        $response = Http::timeout($this->httpTimeout())
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Heratio Authority Resolution (https://heratio.theahg.co.za)',
            ])
            ->get(self::URL, [
                'q' => $query,
                'maxRows' => max(1, min(20, $limit + 5)),
                'username' => $username,
                'style' => 'MEDIUM',
            ]);

        if (!$response->ok()) {
            return [];
        }

        $json = $response->json();
        // GeoNames returns {status: {message, value}} on errors - notably
        // 'user does not exist' for unregistered demo usage.
        if (isset($json['status']['message'])) {
            return [];
        }
        $hits = $json['geonames'] ?? [];
        if (!is_array($hits)) {
            return [];
        }

        $candidates = [];
        $licence = $this->licenceNote() ?? 'CC-BY-4.0';
        $licenceUrl = $this->licenceUrl() ?? 'https://creativecommons.org/licenses/by/4.0/';
        $retrievedAt = $this->nowIso();

        foreach ($hits as $hit) {
            $gid = $hit['geonameId'] ?? null;
            $name = $hit['name'] ?? $hit['toponymName'] ?? null;
            if (!$gid || !$name) {
                continue;
            }
            $lat = isset($hit['lat']) ? (float) $hit['lat'] : null;
            $lng = isset($hit['lng']) ? (float) $hit['lng'] : null;
            $countryName = $hit['countryName'] ?? null;
            $adminName = $hit['adminName1'] ?? null;

            $candidates[] = [
                'source' => $this->source(),
                'external_id' => (string) $gid,
                'external_uri' => 'https://www.geonames.org/' . $gid,
                'authorized_name' => (string) $name,
                'dates_of_existence' => null,
                'history_snippet' => trim(($adminName ? $adminName . ', ' : '') . ($countryName ?? '')) ?: null,
                'fields' => [
                    'name' => (string) $name,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'country' => $countryName,
                    'admin_region' => $adminName,
                    'feature_class' => $hit['fcl'] ?? null,
                    'feature_code' => $hit['fcode'] ?? null,
                    'descriptive_standard' => 'ISDF',
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
