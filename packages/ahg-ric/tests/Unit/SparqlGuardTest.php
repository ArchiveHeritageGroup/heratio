<?php

/**
 * SparqlGuardTest - the RiC SPARQL endpoints refuse SERVICE and updates on the
 * query's syntax: a keyword inside a literal or IRI does not count, and a "#"
 * inside an IRI does not hide the rest of the line (26 Nov 2026).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Unit;

use AhgRic\Support\SparqlGuard as G;
use PHPUnit\Framework\TestCase;

class SparqlGuardTest extends TestCase
{
    public function test_service_is_refused(): void
    {
        $this->assertSame(403, G::refusal('SELECT * WHERE { SERVICE <http://127.0.0.1:9/> { ?s ?p ?o } }')[0] ?? null);
        $this->assertSame(403, G::refusal("select * where { service\n<http://x/> { ?s ?p ?o } }")[0] ?? null);
    }

    public function test_a_hash_inside_an_iri_does_not_hide_the_rest_of_the_line(): void
    {
        $q = 'SELECT * WHERE { ?s a <https://www.ica.org/standards/RiC/ontology#Record> . SERVICE <http://10.0.0.1/> { ?s ?p ?o } }';
        $this->assertSame(403, G::refusal($q)[0] ?? null);
        $this->assertSame(403, G::refusal('INSERT DATA { <http://x/#a> <http://x/#b> "c" }')[0] ?? null);
    }

    public function test_keywords_in_literals_iris_comments_and_names_are_not_operations(): void
    {
        $this->assertNull(G::refusal('SELECT ?s WHERE { ?s ?p "please DELETE this, SERVICE desk" } # INSERT later'));
        $this->assertNull(G::refusal('PREFIX ex: <http://x/service/> SELECT ?add WHERE { ?add ex:Create ?o }'));
    }

    public function test_only_query_forms_run(): void
    {
        $this->assertSame(400, G::refusal('# SELECT')[0] ?? null);
        $this->assertNull(G::refusal('ASK { ?s ?p ?o }'));
    }
}
