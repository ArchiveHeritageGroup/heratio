<?php

/**
 * GraphSerializerService - RDF rendering for the Open Memory Protocol graph.
 *
 * Part of north-star #1204 ("the world heritage graph / open memory
 * protocol"). The GraphController assembles a record's graph neighbourhood
 * into a neutral PHP array (the same shape that already backs the JSON-LD
 * @graph). This service turns that one array into the three crawlable
 * serialisations the protocol advertises:
 *
 *   - framed JSON-LD   (application/ld+json)  - the default, unchanged shape
 *   - Turtle           (text/turtle)
 *   - RDF/XML          (application/rdf+xml)
 *
 * It also owns the single source of truth for the JSON-LD @context (so the
 * inline context and the stand-alone /graph/context.jsonml document never
 * drift) and the namespace table (so Turtle @prefix headers and RDF/XML
 * xmlns declarations stay in lock-step with the JSON-LD context).
 *
 * Namespaces are read from config('heratio.ld') (tenant / provenance_ns /
 * ric_ns) so a fresh install on its own domain self-describes without any
 * hardcoded AHG tenant URI. Heratio is jurisdiction-neutral.
 *
 * Read-only: this service performs NO database access and mutates nothing.
 * It is a pure function from the controller's graph array to bytes.
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

namespace AhgApi\Services;

class GraphSerializerService
{
    /**
     * Stable namespace table. Keys are the CURIE prefixes; values are the
     * full namespace URIs. The four standards-based namespaces are constants;
     * the project-local "omp" (Open Memory Protocol) vocabulary derives from
     * config so a relocated install self-resolves.
     *
     * @return array<string,string>
     */
    public function namespaces(): array
    {
        return [
            'schema' => 'https://schema.org/',
            'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            'crm' => 'http://www.cidoc-crm.org/cidoc-crm/',
            'dcterms' => 'http://purl.org/dc/terms/',
            'skos' => 'http://www.w3.org/2004/02/skos/core#',
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'omp' => $this->ompNamespace(),
        ];
    }

    /**
     * Project-local Open Memory Protocol vocabulary URI. Derives from the
     * configured base URL so it self-resolves on any install; never a
     * hardcoded tenant.
     */
    public function ompNamespace(): string
    {
        $base = rtrim((string) (function_exists('url') ? url('/') : config('app.url', 'http://localhost')), '/');

        return $base.'/ns/omp#';
    }

    /**
     * The JSON-LD @context, shared by the inline document and the stand-alone
     * /api/v1/graph/context.jsonld surface. Maps the protocol's predicates to
     * rico:, crm: (CIDOC-CRM) and schema.org terms.
     *
     * @return array<string,mixed>
     */
    public function context(): array
    {
        $ns = $this->namespaces();

        return [
            '@vocab' => $ns['schema'],
            'schema' => $ns['schema'],
            'rico' => $ns['rico'],
            'crm' => $ns['crm'],
            'dcterms' => $ns['dcterms'],
            'skos' => $ns['skos'],
            'rdfs' => $ns['rdfs'],
            'omp' => $ns['omp'],
            // Core descriptive terms -> schema.org.
            'name' => 'schema:name',
            'identifier' => 'schema:identifier',
            'description' => 'schema:description',
            'dateModified' => 'schema:dateModified',
            'additionalType' => ['@id' => 'schema:additionalType', '@type' => '@id'],
            'sameAs' => ['@id' => 'schema:sameAs', '@type' => '@id'],
            // Entity-description predicates shared by the /id/{slug} record,
            // actor and term endpoints (so all three RDF serialisations render
            // them, not just JSON-LD - the serializer is the single source).
            'temporalCoverage' => 'schema:temporalCoverage',
            'seeAlso' => ['@id' => 'rdfs:seeAlso', '@type' => '@id'],
            'relation' => ['@id' => 'dcterms:relation', '@type' => '@id'],
            'creator' => ['@id' => 'dcterms:creator'],
            'subject' => ['@id' => 'dcterms:subject'],
            'spatial' => ['@id' => 'dcterms:spatial'],
            'publisher' => ['@id' => 'dcterms:publisher'],
            'isPartOf' => ['@id' => 'dcterms:isPartOf', '@type' => '@id'],
            // SKOS terms for the controlled-vocabulary (term) entity surface.
            'prefLabel' => 'skos:prefLabel',
            'broader' => ['@id' => 'skos:broader', '@type' => '@id'],
            'narrower' => ['@id' => 'skos:narrower', '@type' => '@id'],
            // CIDOC-CRM cross-walk for the descriptive core, so a CRM-aware
            // consumer can read the same node.
            'crmTitle' => 'crm:P102_has_title',
            'crmIdentifier' => 'crm:P1_is_identified_by',
            // RiC record typing carried as additionalType values (rico:Record,
            // rico:RecordSet) but also exposed as a first-class predicate.
            'ricType' => ['@id' => 'rico:hasRecordSetType', '@type' => '@id'],
            // Open Memory Protocol discovery predicates.
            'isRelatedTo' => ['@id' => 'omp:isRelatedTo', '@type' => '@id'],
            'relationshipDomain' => 'omp:relationshipDomain',
        ];
    }

    /**
     * Build the framed JSON-LD document for a graph array.
     *
     * @param  array<string,mixed>  $graph  ['@graph' => [...nodes...]] shape,
     *                                      or a bare list of node arrays.
     * @return array<string,mixed>
     */
    public function toJsonLd(array $graph): array
    {
        $nodes = $this->nodesOf($graph);

        return [
            '@context' => $this->context(),
            '@graph' => array_values($nodes),
        ];
    }

    /**
     * Serialise a graph array to Turtle. Always valid Turtle, even for an
     * empty graph (just the @prefix header). Literals are escaped.
     *
     * @param  array<string,mixed>  $graph
     */
    public function toTurtle(array $graph): string
    {
        $out = '';
        foreach ($this->namespaces() as $prefix => $uri) {
            $out .= "@prefix {$prefix}: <{$uri}> .\n";
        }
        $out .= "\n";

        foreach ($this->nodesOf($graph) as $node) {
            $out .= $this->turtleNode($node);
        }

        return $out;
    }

    /**
     * Serialise a graph array to RDF/XML. Always well-formed XML, even for an
     * empty graph (just the rdf:RDF envelope). Literals are XML-escaped.
     *
     * @param  array<string,mixed>  $graph
     */
    public function toRdfXml(array $graph): string
    {
        $ns = $this->namespaces();

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<rdf:RDF';
        foreach ($ns as $prefix => $uri) {
            $out .= "\n    xmlns:{$prefix}=\"".$this->xmlAttr($uri).'"';
        }
        $out .= '>'."\n";

        foreach ($this->nodesOf($graph) as $node) {
            $out .= $this->rdfXmlNode($node);
        }

        $out .= '</rdf:RDF>'."\n";

        return $out;
    }

    // -----------------------------------------------------------------
    // Turtle node rendering
    // -----------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $node
     */
    protected function turtleNode(array $node): string
    {
        $subject = $this->iriRef($node['@id'] ?? null);
        if ($subject === null) {
            return '';
        }

        $lines = [];

        // rdf:type from @type (one or many).
        foreach ($this->typesOf($node) as $type) {
            $lines[] = 'a '.$this->turtleType($type);
        }

        foreach ($node as $key => $value) {
            if ($key === '@id' || $key === '@type') {
                continue;
            }
            $predicate = $this->turtlePredicate($key);
            if ($predicate === null) {
                continue;
            }
            foreach ($this->valuesOf($value) as $v) {
                if ($this->isIriPredicate($key)) {
                    $obj = $this->turtleIriObject((string) $v);
                    if ($obj !== null) {
                        $lines[] = $predicate.' '.$obj;
                    }
                } else {
                    $lines[] = $predicate.' '.$this->turtleLiteral((string) $v);
                }
            }
        }

        if (! $lines) {
            // Subject with no predicates: still emit a bare triple-less anchor
            // by typing it rdfs:Resource so the document stays meaningful.
            $lines[] = 'a rdfs:Resource';
        }

        return $subject."\n    ".implode(" ;\n    ", $lines)." .\n\n";
    }

    protected function turtleType(string $type): string
    {
        // CURIE (prefix:local) passes through; full IRI gets bracketed.
        if ($this->isCurie($type)) {
            return $type;
        }

        return '<'.$this->escapeIri($type).'>';
    }

    protected function turtlePredicate(string $key): ?string
    {
        $curie = $this->predicateCurie($key);

        return $curie === null ? null : $curie;
    }

    protected function turtleLiteral(string $value): string
    {
        return '"'.$this->escapeTurtleString($value).'"';
    }

    /**
     * Render an IRI object. A known CURIE (e.g. rico:RecordSet) is emitted as
     * a prefixed name; anything else is bracketed as a full IRIREF.
     */
    protected function turtleIriObject(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if ($this->isCurie($value)) {
            return $value;
        }

        return $this->iriRef($value);
    }

    // -----------------------------------------------------------------
    // RDF/XML node rendering
    // -----------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $node
     */
    protected function rdfXmlNode(array $node): string
    {
        $id = $node['@id'] ?? null;
        if ($id === null || $id === '') {
            return '';
        }

        $out = '  <rdf:Description rdf:about="'.$this->xmlAttr((string) $id).'">'."\n";

        foreach ($this->typesOf($node) as $type) {
            $qname = $this->curieToQName($type);
            if ($qname !== null) {
                $out .= '    <rdf:type rdf:resource="'.$this->xmlAttr($this->expandCurie($type)).'"/>'."\n";
            }
        }

        foreach ($node as $key => $value) {
            if ($key === '@id' || $key === '@type') {
                continue;
            }
            $qname = $this->predicateQName($key);
            if ($qname === null) {
                continue;
            }
            foreach ($this->valuesOf($value) as $v) {
                if ($this->isIriPredicate($key)) {
                    // A known CURIE object expands to its full URI; anything
                    // else is taken as an absolute IRI.
                    $resource = $this->isCurie((string) $v)
                        ? $this->expandCurie((string) $v)
                        : (string) $v;
                    $out .= '    <'.$qname.' rdf:resource="'.$this->xmlAttr($resource).'"/>'."\n";
                } else {
                    $out .= '    <'.$qname.'>'.$this->xmlText((string) $v).'</'.$qname.'>'."\n";
                }
            }
        }

        $out .= '  </rdf:Description>'."\n";

        return $out;
    }

    // -----------------------------------------------------------------
    // Predicate / CURIE resolution (driven by the shared @context)
    // -----------------------------------------------------------------

    /**
     * Resolve a JSON-LD context key to a CURIE (prefix:local). Returns null
     * for a key not present in the context (so unknown keys are skipped from
     * RDF rather than producing junk triples).
     */
    protected function predicateCurie(string $key): ?string
    {
        // Built-in JSON-LD keywords are not RDF predicates here.
        if (str_starts_with($key, '@')) {
            return null;
        }

        $ctx = $this->context();
        if (! array_key_exists($key, $ctx)) {
            return null;
        }
        $mapped = $ctx[$key];
        $curie = is_array($mapped) ? ($mapped['@id'] ?? null) : $mapped;
        if (! is_string($curie) || $curie === '') {
            return null;
        }

        return $this->isCurie($curie) ? $curie : null;
    }

    protected function predicateQName(string $key): ?string
    {
        $curie = $this->predicateCurie($key);

        return $curie === null ? null : $this->curieToQName($curie);
    }

    /**
     * Whether a context key maps to an @id-typed (IRI object) term.
     */
    protected function isIriPredicate(string $key): bool
    {
        $ctx = $this->context();
        $mapped = $ctx[$key] ?? null;
        if (is_array($mapped)) {
            return ($mapped['@type'] ?? null) === '@id';
        }

        return false;
    }

    protected function isCurie(string $value): bool
    {
        if (! str_contains($value, ':')) {
            return false;
        }
        // A scheme like http: / https: / urn: is NOT a CURIE for our table.
        [$prefix] = explode(':', $value, 2);

        return array_key_exists($prefix, $this->namespaces());
    }

    protected function curieToQName(string $curie): ?string
    {
        return $this->isCurie($curie) ? $curie : null;
    }

    protected function expandCurie(string $curie): string
    {
        if (! $this->isCurie($curie)) {
            return $curie;
        }
        [$prefix, $local] = explode(':', $curie, 2);

        return $this->namespaces()[$prefix].$local;
    }

    // -----------------------------------------------------------------
    // Shape helpers
    // -----------------------------------------------------------------

    /**
     * Normalise the input to a flat list of node arrays. Accepts either a
     * full {'@graph' => [...]} document or a bare list of nodes.
     *
     * @param  array<string,mixed>  $graph
     * @return array<int,array<string,mixed>>
     */
    protected function nodesOf(array $graph): array
    {
        if (isset($graph['@graph']) && is_array($graph['@graph'])) {
            $list = $graph['@graph'];
        } else {
            $list = $graph;
        }

        $nodes = [];
        foreach ($list as $node) {
            if (is_array($node) && (isset($node['@id']) || isset($node['@type']))) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  array<string,mixed>  $node
     * @return array<int,string>
     */
    protected function typesOf(array $node): array
    {
        $t = $node['@type'] ?? null;
        if ($t === null) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $this->valuesOf($t)), fn ($s) => $s !== ''));
    }

    /**
     * @return array<int,mixed>
     */
    protected function valuesOf(mixed $value): array
    {
        if (is_array($value)) {
            // A nested object value (e.g. {'@id' => ...}) collapses to its @id.
            if (isset($value['@id'])) {
                return [$value['@id']];
            }

            return array_values($value);
        }

        return [$value];
    }

    protected function iriRef(?string $iri): ?string
    {
        if ($iri === null || $iri === '') {
            return null;
        }

        return '<'.$this->escapeIri($iri).'>';
    }

    // -----------------------------------------------------------------
    // Escaping
    // -----------------------------------------------------------------

    protected function escapeTurtleString(string $value): string
    {
        return str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $value
        );
    }

    protected function escapeIri(string $iri): string
    {
        // Strip characters illegal in a Turtle IRIREF; angle brackets, quotes,
        // whitespace and control chars must not appear unescaped.
        $iri = preg_replace('/[\x00-\x20<>"{}|\^`\\\\]/u', '', $iri);

        return $iri ?? '';
    }

    protected function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function xmlAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
