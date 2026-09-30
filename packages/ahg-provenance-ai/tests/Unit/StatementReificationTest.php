<?php

/**
 * StatementReificationTest - Unit test for Heratio
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

namespace AhgProvenanceAi\Tests\Unit;

use AhgProvenanceAi\Console\Commands\MigrateStarCommand;
use AhgProvenanceAi\DTO\InferenceRecord;
use AhgProvenanceAi\Services\InferenceService;
use AhgCore\Support\ReifiedStatement;
use EasyRdf\Graph;
use PHPUnit\Framework\TestCase;

/**
 * heratio#1517 - inference provenance is plain RDF 1.1, not RDF-star. Pure:
 * composeInferenceTurtle() and planGraph() need no Laravel bootstrap.
 * EasyRdf's Turtle parser is RDF 1.1 only, so a '<<' would fail the parse.
 */
class StatementReificationTest extends TestCase
{
    private const NS = 'https://heratio.org/ns/provenance-ai#';

    private const RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    private const PROV = 'http://www.w3.org/ns/prov#';

    private const TARGET = '<urn:ahg:entity:information_object:913849:access_points>';

    private function record(): InferenceRecord
    {
        return new InferenceRecord(
            serviceName: 'NER',
            modelName: 'spaCy "en_core_web_sm"',
            modelVersion: '3.8.0',
            inputHash: str_repeat('a', 64),
            outputHash: str_repeat('b', 64),
            targetEntityType: 'information_object',
            targetEntityId: 913849,
            targetField: 'access_points',
            confidence: 0.82,
            standard: 'ICIP',
        );
    }

    private function compose(string $uuid = 'u-1'): string
    {
        return InferenceService::composeInferenceTurtle('ahg', self::NS, $uuid, self::TARGET, $this->record(), '2026-09-30T10:00:00Z');
    }

    public function test_inference_turtle_is_plain_rdf_with_a_statement_node(): void
    {
        $turtle = $this->compose();
        $this->assertStringNotContainsString('<<', $turtle);

        $graph = new Graph;
        $graph->parse($turtle, 'turtle');
        $nt = $graph->serialise('ntriples');
        $this->assertStringNotContainsString('<<', $nt);

        $statements = $graph->allOfType('rdf:Statement');
        $this->assertCount(1, $statements);
        $st = $statements[0];

        $this->assertMatchesRegularExpression('/^urn:ahg:provenance-ai:statement:[0-9a-f]{40}$/', $st->getUri());
        $this->assertSame('urn:ahg:entity:information_object:913849:access_points', $st->getResource('rdf:subject')->getUri());
        $this->assertSame(self::NS.'hasGenerated', $st->getResource('rdf:predicate')->getUri());
        $this->assertSame('urn:ahg:provenance-ai:output:'.str_repeat('b', 64), $st->getResource('rdf:object')->getUri());
        $this->assertSame('urn:ahg:provenance-ai:inference:u-1', $st->getResource('prov:wasGeneratedBy')->getUri());

        // The activity still carries its fields, escaping intact.
        $this->assertSame('spaCy "en_core_web_sm"', (string) $graph->resource('urn:ahg:provenance-ai:inference:u-1')->getLiteral('<'.self::NS.'model>'));
    }

    public function test_statement_iri_is_deterministic(): void
    {
        preg_match('/<(urn:ahg:provenance-ai:statement:[0-9a-f]+)>/', $this->compose('u-1'), $a);
        preg_match('/<(urn:ahg:provenance-ai:statement:[0-9a-f]+)>/', $this->compose('u-1'), $b);
        preg_match('/<(urn:ahg:provenance-ai:statement:[0-9a-f]+)>/', $this->compose('u-2'), $c);

        $this->assertSame($a[1], $b[1], 're-writing the same inference lands on the same node');
        $this->assertNotSame($a[1], $c[1], 'a different inference (graph) gets its own node');
    }

    public function test_migration_mints_the_same_node_the_writer_does(): void
    {
        $graphUri = 'urn:ahg:provenance-ai:inference:u-1';
        $uri = fn (string $v) => ['type' => 'uri', 'value' => $v];
        $rows = [[
            's' => $uri('urn:ahg:entity:information_object:913849:access_points'),
            'p' => $uri(self::NS.'hasGenerated'),
            'o' => $uri('urn:ahg:provenance-ai:output:'.str_repeat('b', 64)),
            'ap' => $uri(self::PROV.'wasGeneratedBy'),
            'ao' => $uri($graphUri),
        ]];

        $plan = MigrateStarCommand::planGraph($graphUri, $rows);
        $this->assertNotNull($plan);
        $this->assertSame(['quoted' => 1, 'statements' => 1, 'triples' => 5], array_diff_key($plan, ['insert' => 1]));
        $this->assertStringNotContainsString('<<', $plan['insert']);

        $migrated = new Graph;
        $migrated->parse($plan['insert'], 'turtle');
        $written = new Graph;
        $written->parse($this->compose('u-1'), 'turtle');

        $this->assertSame(
            $written->allOfType('rdf:Statement')[0]->getUri(),
            $migrated->allOfType('rdf:Statement')[0]->getUri()
        );
    }

    public function test_migration_leaves_a_graph_with_blank_nodes_alone(): void
    {
        $uri = fn (string $v) => ['type' => 'uri', 'value' => $v];
        $rows = [[
            's' => $uri('urn:x:s'), 'p' => $uri('urn:x:p'), 'o' => ['type' => 'bnode', 'value' => 'b0'],
            'ap' => $uri(self::PROV.'wasGeneratedBy'), 'ao' => $uri('urn:x:a'),
        ]];

        $this->assertNull(MigrateStarCommand::planGraph('urn:x:g', $rows));
    }

    public function test_literals_hash_canonically(): void
    {
        $this->assertSame(ReifiedStatement::literal('x'), ReifiedStatement::literal('x', 'http://www.w3.org/2001/XMLSchema#string'));
        $this->assertSame(
            ReifiedStatement::literal('true', 'http://www.w3.org/2001/XMLSchema#boolean'),
            ReifiedStatement::fromBinding(['type' => 'literal', 'value' => 'true', 'datatype' => 'http://www.w3.org/2001/XMLSchema#boolean'])
        );
        $this->assertSame('urn:heratio:auth-res:statement:', substr(ReifiedStatement::node('urn:heratio:auth-res:graph:decisions', '<a>', '<b>', '"c"'), 0, 31));
    }
}
