<?php

/**
 * SimpleSparqlEngineTest - DB-free unit tests for the in-memory SPARQL
 * engine that backs the /admin/sparql endpoint. Builds a tiny synthetic
 * triple list and asserts SELECT / DISTINCT / LIMIT / prefix resolution
 * all behave as expected.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\Sparql\SimpleSparqlEngine;
use Tests\TestCase;

class SimpleSparqlEngineTest extends TestCase
{
    private function fixtureTriples(): array
    {
        $ahg = 'https://heratio.theahg.co.za/ns/ahg/';
        $prov = 'http://www.w3.org/ns/prov#';
        $rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        return [
            [$ahg.'event/1', $rdf.'type', ['type' => 'uri', 'value' => $prov.'Activity']],
            [$ahg.'event/1', $ahg.'eventType', ['type' => 'literal', 'value' => 'ingestion']],
            [$ahg.'event/2', $rdf.'type', ['type' => 'uri', 'value' => $prov.'Activity']],
            [$ahg.'event/2', $ahg.'eventType', ['type' => 'literal', 'value' => 'fixity check']],
            [$ahg.'event/3', $rdf.'type', ['type' => 'uri', 'value' => $prov.'Activity']],
            [$ahg.'event/3', $ahg.'eventType', ['type' => 'literal', 'value' => 'ingestion']],
        ];
    }

    public function test_select_returns_matching_bindings(): void
    {
        $engine = new SimpleSparqlEngine($this->fixtureTriples());
        $result = $engine->querySelect('SELECT ?a ?t WHERE { ?a a prov:Activity ; ahg:eventType ?t . }');

        $this->assertSame(['a', 't'], $result['head']['vars']);
        $this->assertCount(3, $result['results']['bindings']);
        $types = array_map(fn ($b) => $b['t']['value'], $result['results']['bindings']);
        sort($types);
        $this->assertSame(['fixity check', 'ingestion', 'ingestion'], $types);
    }

    public function test_distinct_collapses_duplicates(): void
    {
        $engine = new SimpleSparqlEngine($this->fixtureTriples());
        $result = $engine->querySelect('SELECT DISTINCT ?t WHERE { ?a ahg:eventType ?t . }');
        $values = array_map(fn ($b) => $b['t']['value'], $result['results']['bindings']);
        sort($values);
        $this->assertSame(['fixity check', 'ingestion'], $values);
    }

    public function test_limit_caps_bindings(): void
    {
        $engine = new SimpleSparqlEngine($this->fixtureTriples());
        $result = $engine->querySelect('SELECT ?a WHERE { ?a a prov:Activity . } LIMIT 2');
        $this->assertCount(2, $result['results']['bindings']);
    }

    public function test_custom_prefix_resolves(): void
    {
        $engine = new SimpleSparqlEngine($this->fixtureTriples());
        $result = $engine->querySelect(
            'PREFIX h: <https://heratio.theahg.co.za/ns/ahg/> '.
            'SELECT ?t WHERE { ?a h:eventType ?t . } LIMIT 100'
        );
        $this->assertGreaterThanOrEqual(3, count($result['results']['bindings']));
    }

    public function test_unknown_prefix_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SimpleSparqlEngine([]))->querySelect('SELECT ?x WHERE { ?x missing:thing ?y . }');
    }
}
