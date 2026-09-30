<?php

/**
 * SparqlQueryContractTest - SparqlQueryService queries against real RiC data
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Tests\Unit;

use AhgRic\Services\SparqlQueryService;
use Tests\TestCase;

/**
 * heratio#1516. search, getByType, getHierarchy and getTemporalData queried
 * rico:name, rico:description, rico:isPartOf and rico:hasDateRangeSet, none of
 * which the RiC extractor writes, so they returned nothing whatever the store
 * held. The fixture is real extractor output taken from the ric dataset.
 *
 * Contract layer: every rico: predicate a query uses must be one the
 * extractor writes. Live layer: run each query against the configured Fuseki
 * and expect rows; skipped when that store does not hold the fixture data.
 */
class SparqlQueryContractTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/ric-extractor-sample.ttl';

    private const RECORDSET = 'https://archives.theahg.co.za/ric/atom-psis/recordset/829';
    private const RECORD = 'https://archives.theahg.co.za/ric/atom-psis/record/837';
    private const PRODUCTION = 'https://archives.theahg.co.za/ric/atom-psis/production/902924';

    /**
     * Predicates written for entity kinds the small fixture does not carry:
     * the extractor's term and function names (hasOrHadName) and descriptions
     * (scopeAndContent, descriptiveNote, history) - see ric_extractor_v5.py -
     * and rico:name, which ahg:ric:fuseki-load (#139) writes on the
     * urn:ahg:ric:* agents and places in /openric-model.
     */
    private const ALSO_WRITTEN = ['name', 'hasOrHadName', 'scopeAndContent', 'descriptiveNote', 'history'];

    public function test_queries_use_only_predicates_the_extractor_writes(): void
    {
        preg_match_all('/rico:([a-z][A-Za-z]+)/', file_get_contents(self::FIXTURE), $m);
        $written = array_unique(array_merge($m[1], self::ALSO_WRITTEN));

        foreach ($this->recordedQueries() as $method => $sparql) {
            preg_match_all('/rico:([a-z][A-Za-z]+)/', $sparql, $used);
            foreach (array_unique($used[1]) as $predicate) {
                $this->assertContains($predicate, $written, "{$method} queries rico:{$predicate}, which the RiC data does not contain");
            }
        }
    }

    public function test_search_escapes_lucene_and_sparql_syntax(): void
    {
        config(['heratio.ric_text_index' => true]);
        $queries = $this->recordedQueries('a"b OR c:d');

        $this->assertStringContainsString('text:query ("a\\\\\\"b AND or AND c\\\\:d"', $queries['search']);
    }

    public function test_queries_return_rows_from_the_live_store(): void
    {
        // The archival store, searched through its text index as it would be
        // in production; a CONTAINS scan of 17.9M triples takes minutes.
        config(['heratio.ric_text_index' => true]);
        $svc = new SparqlQueryService(env('RIC_TEST_FUSEKI_ENDPOINT', 'http://localhost:3030/ric'));
        if (! $this->holds($svc, self::RECORD)) {
            $this->markTestSkipped('Configured Fuseki does not hold the ric fixture data');
        }

        $hits = $svc->search('Engelbrecht Bible', ['limit' => 5]);
        $this->assertContains(self::RECORD, array_map(fn ($b) => $b['entity']['value'], $hits['bindings']));
        $this->assertSame('Engelbrecht Family Bible', collect($hits['bindings'])->firstWhere('entity.value', self::RECORD)['label']['value']);

        $agents = $svc->getByType('corporatebody', 5);
        $this->assertNotEmpty(array_filter($agents['bindings'], fn ($b) => ! empty($b['name']['value'])));

        $children = $svc->getHierarchy(self::RECORDSET);
        $this->assertContains(self::RECORD, array_map(fn ($b) => $b['child']['value'] ?? null, $children['bindings']));

        $dates = $svc->getTemporalData(self::PRODUCTION);
        $this->assertNotEmpty($dates['bindings']);
        $this->assertArrayHasKey('startDate', $dates['bindings'][0]);
    }

    public function test_default_dataset_finds_heratio_agents_by_rico_name(): void
    {
        // The #139 load in /openric-model: what the authority-resolution
        // adapters and KM grounding (#1320) search.
        $svc = new SparqlQueryService(env('RIC_TEST_FUSEKI_MODEL_ENDPOINT', 'http://localhost:3030/openric-model'));
        $hits = $svc->search('douglass', ['type' => 'person', 'limit' => 5]);
        if (! empty($hits['error']) || $hits['bindings'] === []) {
            $this->markTestSkipped('Configured Fuseki has no #139 agents loaded');
        }

        $first = $hits['bindings'][0];
        $this->assertStringStartsWith('urn:ahg:ric:agent:', $first['entity']['value']);
        $this->assertStringContainsStringIgnoringCase('douglass', $first['label']['value']);
    }

    private function holds(SparqlQueryService $svc, string $iri): bool
    {
        // executeQuery normalises SELECT results only, so count rather than ASK.
        // An unreachable store returns ['error' => ...] and so counts as 0.
        $r = $svc->executeQuery("SELECT (COUNT(*) AS ?n) WHERE { <{$iri}> ?p ?o }", false);

        return (int) ($r['bindings'][0]['n']['value'] ?? 0) > 0;
    }

    /** @return array<string,string> SPARQL each public method sends, without touching a store. */
    private function recordedQueries(string $term = 'museum'): array
    {
        $svc = new class extends SparqlQueryService {
            public array $sent = [];

            public function executeQuery(string $sparql, bool $useCache = true): array
            {
                $this->sent[] = $sparql;

                // One entity row so search/getByType go on to their label query.
                return ['bindings' => [['entity' => ['type' => 'uri', 'value' => 'https://example.org/e/1']]], 'head' => ['entity']];
            }
        };

        $out = [];
        foreach ([
            'search' => fn () => $svc->search($term),
            'getByType' => fn () => $svc->getByType('corporatebody'),
            'getHierarchy' => fn () => $svc->getHierarchy(self::RECORDSET),
            'getTemporalData' => fn () => $svc->getTemporalData(self::PRODUCTION),
        ] as $method => $call) {
            $svc->sent = [];
            $call();
            $out[$method] = implode("\n", $svc->sent);
        }

        return $out;
    }
}
