<?php

/**
 * ReifiedStatement - Heratio
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

namespace AhgCore\Support;

/**
 * Plain RDF 1.1 statement nodes, in place of RDF-star quoted triples (heratio#1517).
 *
 * A statement that carries provenance is minted as its own node:
 *
 *   <urn:ahg:provenance-ai:statement:<hash>> a rdf:Statement ;
 *       rdf:subject <s> ; rdf:predicate <p> ; rdf:object <o> .
 *
 * and the provenance hangs on that node instead of on << s p o >>. Every
 * store and exporter reads it; QLever and other non-Jena stores reject '<<'.
 *
 * The node IRI is deterministic: a hash of (graph, s, p, o) with the terms in
 * canonical N-Triples form. That mirrors RDF-star, where the same triple in the
 * same graph is the same term, so re-writes and the one-off migration
 * (ahg:provenance-ai:migrate-star) all land on the same node.
 *
 * Terms are passed and returned in N-Triples form: '<iri>', '"lex"',
 * '"lex"^^<datatype>' or '"lex"@lang'. Build them with iri() and literal().
 */
final class ReifiedStatement
{
    public const RDF_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    private const XSD_STRING = 'http://www.w3.org/2001/XMLSchema#string';

    public static function iri(string $iri): string
    {
        return '<'.$iri.'>';
    }

    /**
     * Canonical N-Triples literal. xsd:string is the RDF 1.1 default and is
     * dropped, so a typed and an untyped string hash the same.
     */
    public static function literal(string $lexical, ?string $datatype = null, ?string $lang = null): string
    {
        $out = '"'.strtr($lexical, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]).'"';

        if ($lang !== null && $lang !== '') {
            return $out.'@'.strtolower($lang);
        }
        if ($datatype !== null && $datatype !== '' && $datatype !== self::XSD_STRING) {
            return $out.'^^<'.$datatype.'>';
        }

        return $out;
    }

    /**
     * One SPARQL 1.1 JSON results binding as an N-Triples term. Returns null
     * for blank nodes and quoted triples, which cannot be written back with
     * INSERT DATA - callers skip what they cannot carry over.
     */
    public static function fromBinding(?array $b): ?string
    {
        $type = $b['type'] ?? null;
        if ($type === 'uri') {
            return self::iri((string) $b['value']);
        }
        if ($type === 'literal' || $type === 'typed-literal') {
            return self::literal((string) $b['value'], $b['datatype'] ?? null, $b['xml:lang'] ?? null);
        }

        return null;
    }

    /**
     * The statement node IRI for (s, p, o) in a named graph. The namespace
     * follows the graph: urn:<tenant>:<module>:... graphs mint
     * urn:<tenant>:<module>:statement:<hash>, so provenance-ai statements
     * stay under urn:ahg:provenance-ai: and authority-resolution ones under
     * urn:heratio:auth-res:.
     */
    public static function node(string $graphUri, string $s, string $p, string $o): string
    {
        $ns = preg_match('/^(urn:[^:]+:[^:]+):/', $graphUri, $m) ? $m[1] : 'urn:ahg:provenance';

        return $ns.':statement:'.sha1($graphUri."\n".$s."\n".$p."\n".$o);
    }

    /**
     * The four triples that describe the statement, as Turtle with full IRIs
     * (no prefixes needed). Provenance goes on the node in a separate block.
     */
    public static function describe(string $node, string $s, string $p, string $o): string
    {
        $rdf = self::RDF_NS;

        return "<{$node}> <{$rdf}type> <{$rdf}Statement> ;\n"
             ."    <{$rdf}subject> {$s} ;\n"
             ."    <{$rdf}predicate> {$p} ;\n"
             ."    <{$rdf}object> {$o} .\n";
    }
}
