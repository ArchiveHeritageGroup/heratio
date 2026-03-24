<?php

namespace AhgActorManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Authority Lookup Service.
 *
 * Server-side proxy for external authority source APIs.
 * Searches Wikidata, VIAF, ULAN, LCNAF and returns results.
 */
class AuthorityLookupService
{
    protected int $timeout = 10;

    /**
     * Get plugin config value.
     */
    protected function getConfig(string $key, string $default = ''): string
    {
        try {
            $row = DB::table('ahg_authority_config')
                ->where('config_key', $key)
                ->first();

            return $row ? ($row->config_value ?? $default) : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Check if a source is enabled.
     */
    public function isSourceEnabled(string $source): bool
    {
        return $this->getConfig($source . '_enabled', '0') === '1';
    }

    // =========================================================================
    // WIKIDATA
    // =========================================================================

    /**
     * Search Wikidata for authority records.
     */
    public function searchWikidata(string $query, string $language = 'en', int $limit = 10): array
    {
        if (!$this->isSourceEnabled('wikidata')) {
            return ['error' => 'Wikidata is not enabled'];
        }

        $url = 'https://www.wikidata.org/w/api.php?' . http_build_query([
            'action'   => 'wbsearchentities',
            'search'   => $query,
            'language' => $language,
            'limit'    => $limit,
            'format'   => 'json',
            'type'     => 'item',
        ]);

        $response = $this->httpGet($url);
        if (!$response) {
            return ['error' => 'Failed to connect to Wikidata'];
        }

        $data = json_decode($response, true);
        if (!isset($data['search'])) {
            return ['results' => []];
        }

        $results = [];
        foreach ($data['search'] as $item) {
            $results[] = [
                'id'          => $item['id'] ?? '',
                'label'       => $item['label'] ?? '',
                'description' => $item['description'] ?? '',
                'uri'         => $item['concepturi'] ?? sprintf('https://www.wikidata.org/wiki/%s', $item['id']),
                'source'      => 'wikidata',
            ];
        }

        return ['results' => $results];
    }

    // =========================================================================
    // VIAF
    // =========================================================================

    /**
     * Search VIAF for authority records.
     */
    public function searchViaf(string $query, int $limit = 10): array
    {
        if (!$this->isSourceEnabled('viaf')) {
            return ['error' => 'VIAF is not enabled'];
        }

        $url = 'https://viaf.org/viaf/AutoSuggest?' . http_build_query([
            'query' => $query,
        ]);

        $response = $this->httpGet($url);
        if (!$response) {
            return ['error' => 'Failed to connect to VIAF'];
        }

        $data = json_decode($response, true);
        if (!isset($data['result'])) {
            return ['results' => []];
        }

        $results = [];
        foreach (array_slice($data['result'], 0, $limit) as $item) {
            $viafId = $item['viafid'] ?? '';
            $results[] = [
                'id'          => $viafId,
                'label'       => $item['term'] ?? '',
                'description' => $item['nametype'] ?? '',
                'uri'         => 'https://viaf.org/viaf/' . $viafId,
                'source'      => 'viaf',
            ];
        }

        return ['results' => $results];
    }

    // =========================================================================
    // ULAN (Getty Union List of Artist Names)
    // =========================================================================

    /**
     * Search ULAN via Getty SPARQL endpoint.
     */
    public function searchUlan(string $query, int $limit = 10): array
    {
        if (!$this->isSourceEnabled('ulan')) {
            return ['error' => 'ULAN is not enabled'];
        }

        $sparql = sprintf(
            'SELECT ?subject ?name ?bio WHERE {
                ?subject a gvp:PersonConcept ;
                         gvp:prefLabelGVP/xl:literalForm ?name ;
                         foaf:focus/gvp:biographyPreferred/schema:description ?bio .
                FILTER(CONTAINS(LCASE(?name), "%s"))
            } LIMIT %d',
            strtolower(addslashes($query)),
            $limit
        );

        $url = 'https://vocab.getty.edu/sparql.json?' . http_build_query([
            'query' => $sparql,
        ]);

        $response = $this->httpGet($url);
        if (!$response) {
            return ['error' => 'Failed to connect to ULAN'];
        }

        $data = json_decode($response, true);
        $bindings = $data['results']['bindings'] ?? [];

        $results = [];
        foreach ($bindings as $item) {
            $uri = $item['subject']['value'] ?? '';
            $id  = basename($uri);
            $results[] = [
                'id'          => $id,
                'label'       => $item['name']['value'] ?? '',
                'description' => $item['bio']['value'] ?? '',
                'uri'         => $uri,
                'source'      => 'ulan',
            ];
        }

        return ['results' => $results];
    }

    // =========================================================================
    // LCNAF (Library of Congress Name Authority File)
    // =========================================================================

    /**
     * Search LCNAF via id.loc.gov suggest API.
     */
    public function searchLcnaf(string $query, int $limit = 10): array
    {
        if (!$this->isSourceEnabled('lcnaf')) {
            return ['error' => 'LCNAF is not enabled'];
        }

        $url = 'https://id.loc.gov/authorities/names/suggest2?' . http_build_query([
            'q'      => $query,
            'count'  => $limit,
        ]);

        $response = $this->httpGet($url);
        if (!$response) {
            return ['error' => 'Failed to connect to LCNAF'];
        }

        $data = json_decode($response, true);
        $hits = $data['hits'] ?? [];

        $results = [];
        foreach ($hits as $item) {
            $results[] = [
                'id'          => $item['token'] ?? basename($item['uri'] ?? ''),
                'label'       => $item['aLabel'] ?? '',
                'description' => $item['vLabel'] ?? '',
                'uri'         => $item['uri'] ?? '',
                'source'      => 'lcnaf',
            ];
        }

        return ['results' => $results];
    }

    // =========================================================================
    // HELPER
    // =========================================================================

    /**
     * Simple HTTP GET with timeout.
     */
    protected function httpGet(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => $this->timeout,
                'header'  => "Accept: application/json\r\nUser-Agent: Heratio/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $ctx);

        return $result !== false ? $result : null;
    }
}
