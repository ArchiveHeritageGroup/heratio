<?php

/**
 * SparqlInsertWrappingTest - the INSERT DATA wrapping that every Fuseki
 * write goes through.
 *
 * Turtle '@prefix' directives are illegal inside INSERT DATA; Fuseki
 * rejects the whole update with a 400. Two live callers pass turtle that
 * opens with '@prefix' (AiController's DONUT provenance write and
 * InferenceService::buildInferenceTurtle, replayed by FusekiReplayCommand),
 * so the wrapping has to hoist them into a PREFIX prologue.
 *
 * No DB or network: buildInsertUpdate() is pure string work, so the
 * service is built without its constructor.
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

use AhgRic\Services\SparqlUpdateService;
use ReflectionClass;
use Tests\TestCase;

class SparqlInsertWrappingTest extends TestCase
{
    private function service(): SparqlUpdateService
    {
        return (new ReflectionClass(SparqlUpdateService::class))->newInstanceWithoutConstructor();
    }

    /** The shape AiController and InferenceService actually emit. */
    private function turtleWithPrefixes(): string
    {
        return "@prefix prov: <http://www.w3.org/ns/prov#> .\n"
             . "@prefix ex: <https://ld.example.org/prov/> .\n"
             . "<urn:ahg:provenance-ai:inference:abc> a prov:Activity ;\n"
             . "    ex:service \"DONUT\" .\n";
    }

    public function test_at_prefix_directives_are_hoisted_out_of_insert_data(): void
    {
        $update = $this->service()->buildInsertUpdate('urn:g', $this->turtleWithPrefixes());

        $insertPos = strpos($update, 'INSERT DATA');
        $this->assertNotFalse($insertPos);

        // Nothing after INSERT DATA may carry Turtle directive syntax.
        $this->assertStringNotContainsString('@prefix', substr($update, $insertPos));

        // ...and each one survives as a SPARQL PREFIX in the prologue.
        $this->assertStringContainsString('PREFIX prov: <http://www.w3.org/ns/prov#>', $update);
        $this->assertStringContainsString('PREFIX ex: <https://ld.example.org/prov/>', $update);
        $this->assertLessThan($insertPos, strpos($update, 'PREFIX '));
    }

    public function test_graph_body_is_wrapped_in_braces(): void
    {
        // FusekiSyncService built this string itself and omitted the inner
        // braces entirely; it was masked because the enqueue always threw.
        $update = $this->service()->buildInsertUpdate('urn:g', $this->turtleWithPrefixes());

        $this->assertMatchesRegularExpression('/INSERT DATA \{ GRAPH <urn:g> \{/', $update);
        $this->assertStringEndsWith('} }', trim($update));
    }

    public function test_turtle_without_prefixes_is_passed_through_unchanged(): void
    {
        $body = '<urn:s> <urn:p> "o" .';
        $update = $this->service()->buildInsertUpdate('urn:g', $body);

        $this->assertSame("INSERT DATA { GRAPH <urn:g> {\n{$body}\n} }", $update);
        $this->assertStringNotContainsString('PREFIX', $update);
    }

    public function test_triples_are_preserved_verbatim(): void
    {
        $update = $this->service()->buildInsertUpdate('urn:g', $this->turtleWithPrefixes());

        $this->assertStringContainsString('<urn:ahg:provenance-ai:inference:abc> a prov:Activity ;', $update);
        $this->assertStringContainsString('ex:service "DONUT" .', $update);
    }

    /**
     * CrmGraphSyncService::buildReplaceGraphUpdate() and insertRdfStar()
     * must keep splitting prefixes the same way - they used to hold two
     * copies of this logic.
     */
    public function test_split_prefixes_returns_prologue_and_stripped_body(): void
    {
        [$prologue, $body] = SparqlUpdateService::splitPrefixes($this->turtleWithPrefixes());

        $this->assertSame(
            "PREFIX prov: <http://www.w3.org/ns/prov#>\nPREFIX ex: <https://ld.example.org/prov/>",
            $prologue
        );
        $this->assertStringNotContainsString('@prefix', $body);
        $this->assertStringStartsWith('<urn:ahg:provenance-ai:inference:abc>', $body);
    }
}
