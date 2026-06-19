<?php

/**
 * RicDatasetService - #1321 versioned, self-describing Linked Data surface.
 *
 * Builds a DCAT + VoID dataset descriptor (the machine-readable "front door" to
 * the RiC-O Linked Data: title, license, publisher, version, the standards it
 * conforms to, and every distribution - SPARQL, JSON-LD, Turtle, RDF/XML,
 * OAI-PMH) plus a versioned change log. This is the "version the published
 * ontology artifacts + expose the change log" requirement in the governance pin.
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

namespace AhgRic\Services;

class RicDatasetService
{
    /** Governed, pinned standards (governance pin section 1). */
    private const CONFORMS_TO = [
        'https://www.ica.org/standards/RiC/ontology'   => 'RiC-O 1.0.2',
        'http://www.cidoc-crm.org/cidoc-crm/7.1.3/'     => 'CIDOC-CRM 7.1.3',
        'http://www.w3.org/2004/02/skos/core'           => 'SKOS',
        'http://www.w3.org/ns/prov'                     => 'PROV-O',
    ];

    /**
     * Versioned change log of the published Linked Data / ontology artifacts.
     * The canonical narrative lives in the governance pin; this is the
     * consumer-diffable feed. Newest first.
     */
    private const CHANGELOG = [
        ['version' => 'ld-1.2', 'date' => '2026-06-19', 'impact' => 'additive',
            'change' => 'Published a DCAT/VoID dataset descriptor + changelog endpoint; owl:deprecated emitted for superseded entities (deprecate-not-delete).'],
        ['version' => 'ld-1.1', 'date' => '2026-06-19', 'impact' => 'additive',
            'change' => 'Round-trip portability proven (JSON-LD + Turtle); importer accepts rico:title so titles survive export->import.'],
        ['version' => 'pin-1.1', 'date' => '2026-06-13', 'impact' => 'additive',
            'change' => 'CIDOC-CRM pinned at 7.1.3; CRM bridge emits X-CRM-Version.'],
        ['version' => 'pin-1.0', 'date' => '2026-06-13', 'impact' => 'governance',
            'change' => 'Namespaces consolidated; two-layer IRI model (internal urn:ahg:ric vs public ric.theahg.co.za/ric); SHACL conformance gate.'],
    ];

    public function __construct(private RicDeprecationService $deprecation)
    {
    }

    private function base(): string
    {
        return rtrim((string) config('ric.base_uri', 'https://ric.theahg.co.za/ric'), '/');
    }

    private function api(): string
    {
        return rtrim((string) (config('ric.api_url') ?: url('/api/ric/v1')), '/');
    }

    public function version(): string
    {
        try {
            $v = json_decode((string) @file_get_contents(base_path('version.json')), true);

            return (string) ($v['version'] ?? 'unknown');
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /** The DCAT + VoID dataset descriptor as a JSON-LD document. */
    public function descriptor(): array
    {
        $api = $this->api();
        $base = $this->base();

        $distribution = [
            $this->dist('SPARQL query endpoint', $api.'/sparql', 'application/sparql-results+json', 'rico:SPARQL'),
            $this->dist('JSON-LD export', $api.'/records/{slug}/export?format=jsonld', 'application/ld+json'),
            $this->dist('Turtle export', $api.'/records/{slug}/export?format=ttl', 'text/turtle'),
            $this->dist('RDF/XML export', $api.'/records/{slug}/export?format=rdf', 'application/rdf+xml'),
            $this->dist('OAI-PMH harvest', $api.'/oai', 'application/xml'),
        ];

        $conforms = [];
        foreach (self::CONFORMS_TO as $iri => $label) {
            $conforms[] = ['@id' => $iri, 'rdfs:label' => $label];
        }

        return [
            '@context' => [
                'dcat'    => 'http://www.w3.org/ns/dcat#',
                'dcterms' => 'http://purl.org/dc/terms/',
                'void'    => 'http://rdfs.org/ns/void#',
                'foaf'    => 'http://xmlns.com/foaf/0.1/',
                'rdfs'    => 'http://www.w3.org/2000/01/rdf-schema#',
                'owl'     => 'http://www.w3.org/2002/07/owl#',
            ],
            '@id'                => $base.'/dataset',
            '@type'              => ['dcat:Dataset', 'void:Dataset'],
            'dcterms:title'      => 'Heratio RiC-O Linked Data',
            'dcterms:description' => 'Records in Contexts (RiC-O 1.0.2) Linked Data published by Heratio: archival records, agents, places, activities and their relations, with a CIDOC-CRM bridge and PROV-O provenance. Exports round-trip (JSON-LD/Turtle) and validate against the published SHACL shapes.',
            'dcterms:publisher'  => [
                '@type'     => 'foaf:Organization',
                'foaf:name' => 'The Archive and Heritage Group',
            ],
            'dcterms:license'    => 'https://www.gnu.org/licenses/agpl-3.0.html',
            'dcterms:modified'   => now()->toIso8601String(),
            'dcterms:hasVersion' => $this->version(),
            'dcterms:conformsTo' => $conforms,
            'void:uriSpace'      => $base.'/',
            'void:sparqlEndpoint' => ['@id' => $api.'/sparql'],
            'void:rootResource'  => ['@id' => $api.'/records'],
            'dcat:distribution'  => $distribution,
            'ahg:deprecatedEntityCount' => count($this->deprecation->all()),
            'rdfs:seeAlso'       => ['@id' => $api.'/changelog'],
        ];
    }

    /** Versioned change log feed (newest first) + the pinned standard versions. */
    public function changelog(): array
    {
        return [
            'dataset_version' => $this->version(),
            'pinned_standards' => self::CONFORMS_TO,
            'governance_pin'  => 'docs/reference/ontology-governance-pin.md',
            'changes'         => self::CHANGELOG,
        ];
    }

    private function dist(string $title, string $url, string $mediaType, ?string $conforms = null): array
    {
        $d = [
            '@type'            => 'dcat:Distribution',
            'dcterms:title'    => $title,
            'dcat:accessURL'   => ['@id' => $url],
            'dcat:mediaType'   => $mediaType,
        ];
        if ($conforms) {
            $d['dcterms:conformsTo'] = $conforms;
        }

        return $d;
    }
}
