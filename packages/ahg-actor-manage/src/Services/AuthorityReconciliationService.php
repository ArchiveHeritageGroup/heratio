<?php

/**
 * AuthorityReconciliationService - External authority file reconciliation
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgActorManage\Services;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuthorityReconciliationService
{
    private AuthorityIdentifierService $identifierService;

    private const SOURCES = [
        'wikidata' => [
            'setting' => 'authority_wikidata_enabled',
            'label'   => 'Wikidata',
            'search'  => 'https://www.wikidata.org/w/api.php',
            'uri'     => 'https://www.wikidata.org/wiki/%s',
        ],
        'viaf' => [
            'setting' => 'authority_viaf_enabled',
            'label'   => 'VIAF',
            'search'  => 'https://viaf.org/viaf/AutoSuggest',
            'uri'     => 'https://viaf.org/viaf/%s',
        ],
        'ulan' => [
            'setting' => 'authority_ulan_enabled',
            'label'   => 'Getty ULAN',
            'search'  => 'https://vocab.getty.edu/sparql.json',
            'uri'     => 'https://vocab.getty.edu/ulan/%s',
        ],
        'lcnaf' => [
            'setting' => 'authority_lcnaf_enabled',
            'label'   => 'LCNAF',
            'search'  => 'https://id.loc.gov/authorities/names/suggest2',
            'uri'     => 'https://id.loc.gov/authorities/names/%s',
        ],
        'isni' => [
            'setting' => 'authority_isni_enabled',
            'label'   => 'ISNI',
            'search'  => 'https://isni.org/isni/search',
            'uri'     => 'https://isni.org/isni/%s',
        ],
    ];

    public function __construct()
    {
        $this->identifierService = new AuthorityIdentifierService();
    }

    /**
     * Get list of enabled sources.
     */
    public function getEnabledSources(): array
    {
        $enabled = [];
        foreach (self::SOURCES as $key => $source) {
            if (AhgSettingsService::getBool($source['setting'], false)) {
                $enabled[$key] = $source;
            }
        }
        return $enabled;
    }

    /**
     * Search all enabled sources for matches to an actor name.
     *
     * @return array ['wikidata' => [...matches], 'viaf' => [...matches], ...]
     */
    public function searchAll(string $name): array
    {
        $results = [];
        foreach ($this->getEnabledSources() as $key => $source) {
            try {
                $results[$key] = $this->searchSource($key, $name);
            } catch (\Throwable $e) {
                Log::warning("Authority reconciliation failed for {$key}: " . $e->getMessage());
                $results[$key] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * Search a single source.
     *
     * @return array of ['id' => ..., 'label' => ..., 'description' => ..., 'uri' => ...]
     */
    public function searchSource(string $source, string $name): array
    {
        return match ($source) {
            'wikidata' => $this->searchWikidata($name),
            'viaf'     => $this->searchViaf($name),
            'ulan'     => $this->searchUlan($name),
            'lcnaf'    => $this->searchLcnaf($name),
            'isni'     => $this->searchIsni($name),
            default    => [],
        };
    }

    /**
     * Link an actor to an external identifier (save to ahg_actor_identifier).
     * If authority_auto_verify_wikidata is enabled and source is wikidata, auto-verify.
     */
    public function linkActor(int $actorId, string $source, string $identifierValue, string $label = ''): int
    {
        $uri = isset(self::SOURCES[$source])
            ? sprintf(self::SOURCES[$source]['uri'], $identifierValue)
            : null;

        $autoVerify = ($source === 'wikidata' && AhgSettingsService::getBool('authority_auto_verify_wikidata', false));

        $id = $this->identifierService->save($actorId, [
            'identifier_type'  => $source,
            'identifier_value' => $identifierValue,
            'uri'              => $uri,
            'label'            => $label,
            'source'           => 'reconciliation',
            'is_verified'      => $autoVerify ? 1 : 0,
            'verified_at'      => $autoVerify ? now() : null,
            'verified_by'      => $autoVerify ? auth()->id() : null,
        ]);

        return $id;
    }

    // ── Source-specific search implementations ──────────────────────

    private function searchWikidata(string $name): array
    {
        $response = Http::timeout(10)->get('https://www.wikidata.org/w/api.php', [
            'action'   => 'wbsearchentities',
            'search'   => $name,
            'language' => 'en',
            'format'   => 'json',
            'limit'    => 10,
            'type'     => 'item',
        ]);

        if (!$response->successful()) return [];

        $data = $response->json();
        $results = [];
        foreach ($data['search'] ?? [] as $item) {
            $results[] = [
                'id'          => $item['id'],
                'label'       => $item['label'] ?? $item['id'],
                'description' => $item['description'] ?? '',
                'uri'         => sprintf(self::SOURCES['wikidata']['uri'], $item['id']),
            ];
        }
        return $results;
    }

    private function searchViaf(string $name): array
    {
        $response = Http::timeout(10)->get('https://viaf.org/viaf/AutoSuggest', [
            'query' => $name,
        ]);

        if (!$response->successful()) return [];

        $data = $response->json();
        $results = [];
        foreach ($data['result'] ?? [] as $item) {
            $viafId = $item['viafid'] ?? null;
            if (!$viafId) continue;
            $results[] = [
                'id'          => $viafId,
                'label'       => $item['term'] ?? $viafId,
                'description' => $item['nametype'] ?? '',
                'uri'         => sprintf(self::SOURCES['viaf']['uri'], $viafId),
            ];
        }
        return $results;
    }

    private function searchUlan(string $name): array
    {
        $sparql = "SELECT ?s ?name WHERE { ?s a gvp:PersonConcept; gvp:prefLabelGVP/xl:literalForm ?name. FILTER(CONTAINS(LCASE(?name), LCASE(\"" . addslashes($name) . "\"))) } LIMIT 10";

        $response = Http::timeout(15)->get('https://vocab.getty.edu/sparql.json', [
            'query' => $sparql,
        ]);

        if (!$response->successful()) return [];

        $data = $response->json();
        $results = [];
        foreach ($data['results']['bindings'] ?? [] as $row) {
            $uri = $row['s']['value'] ?? '';
            $ulanId = basename($uri);
            $results[] = [
                'id'          => $ulanId,
                'label'       => $row['name']['value'] ?? $ulanId,
                'description' => 'Getty ULAN',
                'uri'         => $uri,
            ];
        }
        return $results;
    }

    private function searchLcnaf(string $name): array
    {
        $response = Http::timeout(10)->get('https://id.loc.gov/authorities/names/suggest2', [
            'q' => $name,
        ]);

        if (!$response->successful()) return [];

        $data = $response->json();
        $results = [];
        foreach ($data['hits'] ?? [] as $hit) {
            $uri = $hit['uri'] ?? '';
            $lcnafId = basename($uri);
            $results[] = [
                'id'          => $lcnafId,
                'label'       => $hit['aLabel'] ?? $lcnafId,
                'description' => $hit['vLabel'] ?? '',
                'uri'         => $uri ?: sprintf(self::SOURCES['lcnaf']['uri'], $lcnafId),
            ];
        }
        return $results;
    }

    private function searchIsni(string $name): array
    {
        // ISNI doesn't have a free public JSON API — use the SRU endpoint
        $query = 'pica.nw="' . addslashes($name) . '"';
        $response = Http::timeout(10)->get('https://isni.org/sru/', [
            'query'          => $query,
            'operation'      => 'searchRetrieve',
            'recordSchema'   => 'isni-b',
            'maximumRecords' => 10,
        ]);

        if (!$response->successful()) return [];

        // Parse XML response
        $results = [];
        try {
            $xml = simplexml_load_string($response->body());
            if (!$xml) return [];
            $ns = $xml->getNamespaces(true);
            foreach ($xml->xpath('//srw:record') ?: $xml->xpath('//record') ?: [] as $record) {
                $data = (string) ($record->recordData ?? '');
                if (preg_match('/isniURI[^>]*>([^<]+)/', $data, $m)) {
                    $isniId = trim($m[1]);
                    $isniId = preg_replace('/[^0-9X]/', '', $isniId);
                    $label = $name; // ISNI SRU doesn't always return the name cleanly
                    if (preg_match('/forename[^>]*>([^<]+)/', $data, $fn) && preg_match('/surname[^>]*>([^<]+)/', $data, $sn)) {
                        $label = trim($fn[1]) . ' ' . trim($sn[1]);
                    }
                    $results[] = [
                        'id'          => $isniId,
                        'label'       => $label,
                        'description' => 'ISNI',
                        'uri'         => sprintf(self::SOURCES['isni']['uri'], $isniId),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("ISNI XML parse error: " . $e->getMessage());
        }

        return $results;
    }
}
