<?php

/**
 * TripleStoreTest - store-neutral endpoint, query scoping and the explorer
 * two-step join (heratio#1518). No network: fixtures hold real SPARQL engine
 * output for the previous single query and the new edge + metadata queries.
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
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgRic\Tests\Unit;

use AhgDiscovery\Services\PageIndexService;
use AhgRic\Services\RelationshipService;
use AhgRic\Support\RicGraphJoin;
use AhgRic\Support\TripleStore;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TripleStoreTest extends TestCase
{
    private const G = 'urn:ahg:ric:instance';

    public function test_fuseki_is_the_default_and_urls_are_unchanged(): void
    {
        config(['ahg-ric.triplestore.driver' => null]);

        $this->assertSame('fuseki', TripleStore::driver());
        $this->assertSame('http://h:3030/ric/query', TripleStore::queryUrl('http://h:3030/ric'));
        $this->assertSame('http://h:3030/ric/sparql', TripleStore::queryUrl('http://h:3030/ric', '/sparql'));
        $this->assertSame('http://h:3030/ric/update', TripleStore::updateUrl('http://h:3030/ric'));
        $this->assertTrue(TripleStore::supportsGraphStore());
    }

    public function test_qlever_uses_its_root_endpoint_and_has_no_graph_store(): void
    {
        config(['ahg-ric.triplestore.driver' => 'qlever']);

        $this->assertSame('http://h:7001', TripleStore::queryUrl('http://h:7001/', '/sparql'));
        $this->assertSame('http://h:7001', TripleStore::updateUrl('http://h:7001/'));
        $this->assertFalse(TripleStore::supportsGraphStore());
    }

    public function test_no_instance_graph_returns_the_query_byte_for_byte(): void
    {
        config(['ahg-ric.triplestore.instance_graph' => null]);
        $q = "PREFIX a: <urn:a#>\nSELECT * WHERE { ?s ?p ?o }";

        $this->assertSame($q, TripleStore::scope($q));
    }

    #[DataProvider('scopedQueries')]
    public function test_read_queries_get_a_from_clause(string $query, string $expected): void
    {
        config(['ahg-ric.triplestore.instance_graph' => self::G]);

        $this->assertSame($expected, TripleStore::scope($query));
    }

    public static function scopedQueries(): array
    {
        $from = "\nFROM <".self::G.">\n";

        return [
            'select where' => ["PREFIX r: <urn:r#>\nSELECT ?s WHERE { ?s ?p ?o } LIMIT 5", "PREFIX r: <urn:r#>\nSELECT ?s {$from}WHERE { ?s ?p ?o } LIMIT 5"],
            'select bare group' => ['SELECT * { ?s ?p ?o }', "SELECT * {$from}{ ?s ?p ?o }"],
            'aggregate alias' => ['SELECT (COUNT(?s) AS ?where) WHERE { ?s ?p ?o }', "SELECT (COUNT(?s) AS ?where) {$from}WHERE { ?s ?p ?o }"],
            'ask' => ['ASK { ?s ?p ?o }', "ASK {$from}{ ?s ?p ?o }"],
            'construct template' => ['CONSTRUCT { ?s ?p ?o } WHERE { ?s ?p ?o }', "CONSTRUCT { ?s ?p ?o } {$from}WHERE { ?s ?p ?o }"],
            'construct no keyword' => ['CONSTRUCT { ?s ?p ?o } { ?s ?p ?o }', "CONSTRUCT { ?s ?p ?o } {$from}{ ?s ?p ?o }"],
            'construct where' => ['CONSTRUCT WHERE { ?s ?p ?o }', "CONSTRUCT {$from}WHERE { ?s ?p ?o }"],
            'describe' => ['DESCRIBE <urn:x>', "DESCRIBE <urn:x>{$from}"],
            'keyword in comment' => ["# GRAPH FROM\nSELECT * WHERE { ?s ?p \"GRAPH\" }", "# GRAPH FROM\nSELECT * {$from}WHERE { ?s ?p \"GRAPH\" }"],
            'keyword in iri' => ['SELECT * WHERE { <urn:x#GRAPH> ?p ?o }', "SELECT * {$from}WHERE { <urn:x#GRAPH> ?p ?o }"],
            'comment before where' => ["SELECT ?s # all subjects\nWHERE { ?s ?p ?o }", "SELECT ?s # all subjects\n{$from}WHERE { ?s ?p ?o }"],
            'comment after bare describe' => ['DESCRIBE <urn:x> # note', "DESCRIBE <urn:x> # note{$from}"],
            'variable named graph' => ['SELECT ?graph WHERE { ?graph ?p ?o }', "SELECT ?graph {$from}WHERE { ?graph ?p ?o }"],
        ];
    }

    #[DataProvider('unscopedQueries')]
    public function test_queries_that_must_stay_as_they_are(string $query): void
    {
        config(['ahg-ric.triplestore.instance_graph' => self::G]);

        $this->assertSame($query, TripleStore::scope($query));
    }

    public static function unscopedQueries(): array
    {
        return [
            'named graph' => ['SELECT * WHERE { GRAPH <urn:g> { ?s ?p ?o } }'],
            'graph lower case' => ['select * where { graph ?g { ?s ?p ?o } }'],
            'own dataset' => ['SELECT * FROM <urn:g> WHERE { ?s ?p ?o }'],
            'insert data' => ['INSERT DATA { <urn:s> <urn:p> <urn:o> }'],
            'insert where with subselect' => ['INSERT { ?s <urn:p> 1 } WHERE { SELECT ?s WHERE { ?s ?p ?o } }'],
            'delete where' => ['DELETE WHERE { ?s ?p ?o }'],
        ];
    }

    public function test_a_long_literal_in_the_projection_is_still_scoped(): void
    {
        config(['ahg-ric.triplestore.instance_graph' => self::G]);
        $select = 'SELECT ("'.str_repeat('a', 200000).'" AS ?x) ';

        $this->assertSame($select."\nFROM <".self::G.">\nWHERE { ?s ?p ?o }", TripleStore::scope($select.'WHERE { ?s ?p ?o }'));
    }

    public function test_graph_after_a_long_literal_is_still_seen(): void
    {
        config(['ahg-ric.triplestore.instance_graph' => self::G]);
        $query = 'SELECT * WHERE { ?s ?p "'.str_repeat('a\\n', 50000).'" . GRAPH ?g { ?s ?p ?o } }';

        $this->assertSame($query, TripleStore::scope($query));
    }

    public function test_a_query_that_cannot_be_tokenised_fails_closed(): void
    {
        config(['ahg-ric.triplestore.instance_graph' => self::G]);

        $this->expectException(InvalidArgumentException::class);
        TripleStore::scope("SELECT * WHERE { ?s ?p \"\xff\" }");
    }

    public function test_page_index_reads_use_the_store_url_and_scope(): void
    {
        config(['ahg-ric.triplestore.driver' => 'qlever', 'ahg-ric.triplestore.instance_graph' => self::G]);
        $class = new \ReflectionClass(PageIndexService::class);
        $service = $class->newInstanceWithoutConstructor();
        $class->getProperty('fusekiEndpoint')->setValue($service, 'http://h:7001/');

        [$url, $query] = $class->getMethod('sparqlRequest')->invoke($service, 'SELECT ?p WHERE { ?s ?p ?o }');

        $this->assertSame('http://h:7001', $url);
        $this->assertSame("SELECT ?p \nFROM <".self::G.">\nWHERE { ?s ?p ?o }", $query);
    }

    public function test_an_invalid_instance_graph_fails_closed(): void
    {
        config(['ahg-ric.triplestore.instance_graph' => 'urn:x> { ?s ?p ?o } #']);

        $this->expectException(InvalidArgumentException::class);
        TripleStore::scope('SELECT * WHERE { ?s ?p ?o }');
    }

    #[DataProvider('joinFixtures')]
    public function test_two_step_join_gives_the_rows_of_the_single_query(string $name, callable $slot): void
    {
        $f = $this->fixture($name);

        $joined = RicGraphJoin::enrich($f['edges'], fn () => $f['metadata'], $slot, 100000);

        $this->assertSame(
            $this->canonical($f['old']['results']['bindings']),
            $this->canonical($joined['results']['bindings'])
        );
    }

    public static function joinFixtures(): array
    {
        return [
            'relationship graph' => ['summary', fn (array $r) => $r['dir']['value'] === 'out' ? ['o', 'oLabel', 'oType'] : ['s', 'sLabel', 'sType']],
            'overview graph' => ['overview', fn (array $r) => ['related', 'relLabel', 'relType']],
        ];
    }

    public function test_join_applies_the_row_limit_after_expanding(): void
    {
        $f = $this->fixture('overview');

        $joined = RicGraphJoin::enrich($f['edges'], fn () => $f['metadata'], fn () => ['related', 'relLabel', 'relType'], 3);

        $this->assertCount(3, $joined['results']['bindings']);
    }

    public function test_failed_metadata_query_fails_like_the_single_query(): void
    {
        $f = $this->fixture('overview');

        $this->assertNull(RicGraphJoin::enrich($f['edges'], fn () => null, fn () => ['related', 'relLabel', 'relType'], 200));
    }

    public function test_relationship_service_builds_the_graph_from_both_queries(): void
    {
        $service = new class($this->fixture('summary')) extends RelationshipService
        {
            public array $sent = [];

            public function __construct(private array $stored)
            {
                parent::__construct();
            }

            protected function resolveEntityUri(int $entityId): ?string
            {
                return 'https://example.org/c';
            }

            protected function resolveEntityName(int $entityId): string
            {
                return 'Centre';
            }

            protected function executeSparql(string $query): ?array
            {
                $this->sent[] = $query;

                return $this->stored[count($this->sent) === 1 ? 'edges' : 'metadata'];
            }
        };

        $graph = $service->getGraphSummary(1);
        $nodes = array_column($graph['nodes'], null, 'id');

        $this->assertCount(2, $service->sent);
        $this->assertStringNotContainsString('STRSTARTS', $service->sent[0]);
        $this->assertStringNotContainsString('STRSTARTS', $service->sent[1]);
        $this->assertSame('Plain only', $nodes['https://example.org/plain']['label']);
        $this->assertSame('Unknown', $nodes['https://example.org/plain']['type']);
        $this->assertSame('Unknown', $nodes['https://example.org/foreign']['type']);
        $this->assertSame('Record', $nodes['https://example.org/in1']['type']);
    }

    private function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../fixtures/explorer-join/'.$name.'.json'), true);
    }

    private function canonical(array $rows): array
    {
        $out = array_map(function (array $row) {
            unset($row['dir']);
            ksort($row);

            return json_encode($row);
        }, $rows);
        sort($out);

        return $out;
    }
}
