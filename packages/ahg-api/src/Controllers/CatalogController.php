<?php

/**
 * CatalogController - the DCAT-AP data catalogue of the open-data offering.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). Where ProtocolController publishes the platform's open surfaces
 * as a bespoke "capabilities" document, this controller re-describes the SAME
 * surface list using the W3C Data Catalog Vocabulary (DCAT, DCAT-AP aligned),
 * the lingua franca of open-data portals (CKAN, the European Data Portal,
 * data.gov, ...). A DCAT-aware harvester can ingest this one document and learn
 * every dataset this platform offers, its distributions (the actual download
 * URLs and their media types), its licence, publisher and landing page.
 *
 *   GET /data/catalog        - content-negotiated:
 *                                application/ld+json -> JSON-LD (machine default)
 *                                text/turtle         -> Turtle
 *                                application/rdf+xml -> RDF/XML
 *                                text/html (browser) -> a human catalogue page
 *   GET /data/catalog.jsonld - JSON-LD, explicitly
 *   GET /data/catalog.ttl    - Turtle, explicitly
 *   GET /data/catalog.rdf    - RDF/XML, explicitly
 *   (?format=jsonld|turtle|rdf|html overrides the Accept header.)
 *
 * The catalogue models:
 *   - dcat:Catalog          the offering itself (title, description, licence,
 *                           publisher, the homepage as dcat:landingPage).
 *   - dcat:Dataset          one per open-data surface enumerated by
 *                           ProtocolController (the graph dumps, the per-entity
 *                           linked-data endpoints, the VoID description, the
 *                           feeds, OAI-PMH, the sitemaps, the OpenAPI spec).
 *   - dcat:Distribution     each dataset's concrete access form: dcat:accessURL
 *                           (or dcat:downloadURL) + dcat:mediaType. A surface
 *                           that serves several media types yields several
 *                           distributions.
 *
 * STAYS IN SYNC: the dataset list is built from ProtocolController::surfaces()
 * - the very same array the capabilities document renders - so the two views
 * can never drift. Add a surface in one place and it appears in both.
 *
 * Honest + safe: read-only; performs NO database access and NO AI calls. It
 * only resolves route URLs (via url(), never a hardcoded host), so it can never
 * 500 over data. Every serialisation is well-formed even for an empty surface
 * list. Permissive open-data CORS. Jurisdiction-neutral - the publisher name,
 * licence and base URI all come from config / url(), never a tenant constant.
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

namespace AhgApi\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CatalogController extends Controller
{
    /** The open-data licence the whole offering is published under. */
    private const LICENSE = 'https://creativecommons.org/licenses/by/4.0/';

    /**
     * Namespace table for the catalogue serialisations. DCAT + DCTERMS + FOAF +
     * the literal media-type vocabulary (IANA). Kept local to this controller
     * because DCAT is catalogue-specific and not part of the graph serializer's
     * descriptive vocabulary.
     *
     * @return array<string,string>
     */
    protected function namespaces(): array
    {
        return [
            'dcat' => 'http://www.w3.org/ns/dcat#',
            'dcterms' => 'http://purl.org/dc/terms/',
            'foaf' => 'http://xmlns.com/foaf/0.1/',
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
        ];
    }

    /**
     * OPTIONS preflight for the catalogue endpoint.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /data/catalog (and the explicit .jsonld/.ttl/.rdf forms).
     *
     * Content negotiation: a browser (text/html) gets the human page; an
     * Accept of text/turtle -> Turtle, application/rdf+xml -> RDF/XML; everyone
     * else (including a bare curl and ?format=jsonld) gets JSON-LD. The path
     * suffix, when present, forces that format regardless of Accept.
     */
    public function index(Request $request, ?string $suffix = null): Response
    {
        $format = $this->negotiateFormat($request, $suffix);

        // HTML is only served on an explicit suffix-less browser request.
        if ($suffix === null && $format === 'jsonld' && $this->wantsHtml($request)) {
            return $this->withCors(response($this->html(), 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]));
        }

        $catalog = $this->catalog();

        return match ($format) {
            'turtle' => $this->withCors(response(
                $this->toTurtle($catalog), 200,
                ['Content-Type' => 'text/turtle; charset=utf-8']
            )),
            'rdfxml' => $this->withCors(response(
                $this->toRdfXml($catalog), 200,
                ['Content-Type' => 'application/rdf+xml; charset=utf-8']
            )),
            default => $this->withCors(response(
                $this->toJsonLd($catalog), 200,
                ['Content-Type' => 'application/ld+json; charset=utf-8']
            )),
        };
    }

    // -----------------------------------------------------------------
    // Neutral catalogue model (one source -> three serialisations + HTML)
    // -----------------------------------------------------------------

    /**
     * Build the catalogue as a neutral PHP array:
     *   ['catalog' => [...catalogue-level metadata...],
     *    'datasets' => [ ['title','description','landingPage','distributions'=>[...]], ... ]]
     *
     * The dataset list is derived from ProtocolController::surfaces() so the
     * DCAT view and the capabilities view share ONE list. Each protocol surface
     * becomes one dcat:Dataset; its media types become its distributions.
     *
     * Resolved defensively: if ProtocolController is unavailable for any reason
     * the catalogue degrades to an empty (but valid) dcat:Catalog.
     *
     * @return array<string,mixed>
     */
    protected function catalog(): array
    {
        $base = $this->base();
        $datasets = [];

        try {
            $surfaces = app(ProtocolController::class)->surfaces();
        } catch (\Throwable $e) {
            $surfaces = [];
        }

        foreach ($surfaces as $surface) {
            $datasets[] = $this->datasetFromSurface($base, $surface);
        }

        // Drop any dataset that ended up with no distribution at all (a surface
        // that resolved to neither a URL nor a template). Never a dead entry.
        $datasets = array_values(array_filter($datasets, static function (array $d): bool {
            return ! empty($d['distributions']) || ! empty($d['landingPage']);
        }));

        return [
            'uri' => $base.'/data/catalog',
            'title' => (string) config('app.name', 'Heratio').' open-data catalogue',
            'description' => 'A DCAT (DCAT-AP aligned) catalogue of every open-data dataset this '
                .'platform offers: the bulk linked-data and CSV dumps, the per-entity '
                .'linked-data identity endpoints, the VoID dataset description, the '
                .'OAI-PMH harvesting endpoint, the XML sitemaps, the syndication feeds, '
                .'and the OpenAPI specification. Each dataset lists its distributions '
                .'(the actual access URLs and media types). All datasets are read-only, '
                .'expose published records only, and are open data under CC-BY-4.0.',
            'license' => self::LICENSE,
            'landingPage' => $base.'/open-data',
            'homepage' => $base,
            'protocol' => $base.'/open-data/protocol',
            'modified' => now()->toIso8601String(),
            'publisher' => $this->publisherName(),
            'datasets' => $datasets,
        ];
    }

    /**
     * Map one protocol surface to a dcat:Dataset array. A surface that exposes a
     * concrete URL is described by a downloadable/accessible distribution per
     * media type; a surface that is only a URL TEMPLATE (per-entity endpoints)
     * is described by an access distribution at the template base + a note, so a
     * harvester still discovers it.
     *
     * @param  array<string,mixed>  $surface
     * @return array<string,mixed>
     */
    protected function datasetFromSurface(string $base, array $surface): array
    {
        $url = $surface['url'] ?? null;
        $template = $surface['urlTemplate'] ?? null;
        $mediaTypes = is_array($surface['mediaTypes'] ?? null) ? $surface['mediaTypes'] : [];
        if (! $mediaTypes) {
            $mediaTypes = ['application/octet-stream'];
        }

        $isTemplate = $url === null && ! empty($template);

        // A URL template (e.g. /id/{slug}) is NOT a dereferenceable IRI - the
        // "{slug}" braces are illegal in an IRIREF and a stripped "/id/slug" is
        // a misleading fake. For a template surface the distribution's
        // dcat:accessURL therefore points at the protocol capabilities document
        // (a real, dereferenceable URL), and the literal template form is
        // carried verbatim in the distribution description so a consumer still
        // learns how to build a request. A concrete surface keeps its own URL.
        $protocolUrl = $base.'/open-data/protocol';
        $accessUrl = $url ?? $protocolUrl;

        $distributions = [];
        if (! empty($accessUrl)) {
            foreach ($mediaTypes as $mt) {
                $dist = [
                    'uri' => $this->distributionUri(($url ?? $template ?? $accessUrl), (string) $mt),
                    'mediaType' => (string) $mt,
                    'accessURL' => (string) $accessUrl,
                    // Only a concrete (non-template) URL is a true download.
                    'downloadURL' => $url !== null ? (string) $url : null,
                    'isTemplate' => $isTemplate,
                ];
                if ($isTemplate) {
                    $dist['description'] = 'URL template: '.$template
                        .' (substitute the placeholder; see /open-data/protocol).';
                }
                $distributions[] = $dist;
            }
        }

        // The dataset landingPage must be dereferenceable: a concrete surface
        // uses its own URL; a template surface points at the protocol document.
        $landingPage = $url ?? $protocolUrl;

        return [
            'id' => (string) ($surface['id'] ?? ''),
            'uri' => $this->datasetUri($base, (string) ($surface['id'] ?? '')),
            'title' => (string) ($surface['title'] ?? '[Untitled dataset]'),
            'description' => (string) ($surface['description'] ?? ''),
            'isTemplate' => $isTemplate,
            'template' => $isTemplate ? (string) $template : null,
            'landingPage' => $landingPage,
            'license' => self::LICENSE,
            'distributions' => $distributions,
        ];
    }

    /**
     * The publisher (foaf:Agent / dcterms:publisher). Name from config; the
     * homepage is this host. Jurisdiction-neutral - no tenant constant.
     *
     * @return array<string,string>
     */
    protected function publisherName(): array
    {
        return [
            'uri' => $this->base().'/#publisher',
            'name' => (string) config('app.name', 'Heratio'),
            'homepage' => $this->base(),
        ];
    }

    // -----------------------------------------------------------------
    // JSON-LD serialisation
    // -----------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $catalog
     */
    protected function toJsonLd(array $catalog): string
    {
        $ns = $this->namespaces();

        $datasets = [];
        foreach ($catalog['datasets'] as $ds) {
            $dists = [];
            foreach ($ds['distributions'] as $d) {
                $dist = [
                    '@id' => $d['uri'],
                    '@type' => 'dcat:Distribution',
                    'mediaType' => $d['mediaType'],
                    'accessURL' => ['@id' => $d['accessURL']],
                ];
                if (! empty($d['downloadURL'])) {
                    $dist['downloadURL'] = ['@id' => $d['downloadURL']];
                }
                if (! empty($d['description'])) {
                    $dist['description'] = $d['description'];
                }
                $dist['license'] = ['@id' => self::LICENSE];
                $dists[] = $dist;
            }

            $node = [
                '@id' => $ds['uri'],
                '@type' => 'dcat:Dataset',
                'title' => $ds['title'],
            ];
            if ($ds['description'] !== '') {
                $node['description'] = $ds['description'];
            }
            if (! empty($ds['landingPage'])) {
                $node['landingPage'] = ['@id' => $ds['landingPage']];
            }
            $node['license'] = ['@id' => self::LICENSE];
            $node['publisher'] = ['@id' => $catalog['publisher']['uri']];
            if ($dists) {
                $node['distribution'] = $dists;
            }
            $datasets[] = $node;
        }

        $doc = [
            '@context' => [
                'dcat' => $ns['dcat'],
                'dcterms' => $ns['dcterms'],
                'foaf' => $ns['foaf'],
                'rdfs' => $ns['rdfs'],
                'title' => 'dcterms:title',
                'description' => 'dcterms:description',
                'modified' => 'dcterms:modified',
                'license' => ['@id' => 'dcterms:license', '@type' => '@id'],
                'publisher' => ['@id' => 'dcterms:publisher', '@type' => '@id'],
                'landingPage' => ['@id' => 'dcat:landingPage', '@type' => '@id'],
                'homepage' => ['@id' => 'foaf:homepage', '@type' => '@id'],
                'mediaType' => 'dcat:mediaType',
                'accessURL' => ['@id' => 'dcat:accessURL', '@type' => '@id'],
                'downloadURL' => ['@id' => 'dcat:downloadURL', '@type' => '@id'],
                'distribution' => ['@id' => 'dcat:distribution', '@type' => '@id'],
                'dataset' => ['@id' => 'dcat:dataset', '@type' => '@id'],
                'name' => 'foaf:name',
            ],
            '@id' => $catalog['uri'],
            '@type' => 'dcat:Catalog',
            'title' => $catalog['title'],
            'description' => $catalog['description'],
            'license' => ['@id' => self::LICENSE],
            'landingPage' => ['@id' => $catalog['landingPage']],
            'modified' => $catalog['modified'],
            'publisher' => [
                '@id' => $catalog['publisher']['uri'],
                '@type' => 'foaf:Agent',
                'name' => $catalog['publisher']['name'],
                'homepage' => ['@id' => $catalog['publisher']['homepage']],
            ],
            'dataset' => $datasets,
        ];

        return (string) json_encode(
            $doc,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    // -----------------------------------------------------------------
    // Turtle serialisation
    // -----------------------------------------------------------------

    /**
     * Always valid Turtle, even for an empty catalogue (just the @prefix header
     * and the catalogue stanza). Literals and IRIs are escaped.
     *
     * @param  array<string,mixed>  $catalog
     */
    protected function toTurtle(array $catalog): string
    {
        $lines = [];
        foreach ($this->namespaces() as $prefix => $uri) {
            $lines[] = '@prefix '.$prefix.': <'.$this->ttlIri($uri).'> .';
        }
        $lines[] = '';

        // Publisher (foaf:Agent).
        $pub = $catalog['publisher'];
        $lines[] = '<'.$this->ttlIri($pub['uri']).'>';
        $lines[] = '    a foaf:Agent ;';
        $lines[] = '    foaf:name "'.$this->ttlLiteral($pub['name']).'" ;';
        $lines[] = '    foaf:homepage <'.$this->ttlIri($pub['homepage']).'> .';
        $lines[] = '';

        // Catalogue stanza.
        $cat = [];
        $cat[] = 'a dcat:Catalog';
        $cat[] = 'dcterms:title "'.$this->ttlLiteral($catalog['title']).'"';
        $cat[] = 'dcterms:description "'.$this->ttlLiteral($catalog['description']).'"';
        $cat[] = 'dcterms:license <'.$this->ttlIri(self::LICENSE).'>';
        $cat[] = 'dcterms:publisher <'.$this->ttlIri($pub['uri']).'>';
        $cat[] = 'dcterms:modified "'.$this->ttlLiteral($catalog['modified']).'"^^xsd:dateTime';
        $cat[] = 'dcat:landingPage <'.$this->ttlIri($catalog['landingPage']).'>';
        foreach ($catalog['datasets'] as $ds) {
            $cat[] = 'dcat:dataset <'.$this->ttlIri($ds['uri']).'>';
        }
        $lines[] = '<'.$this->ttlIri($catalog['uri']).'>';
        $lines[] = '    '.implode(" ;\n    ", $cat).' .';
        $lines[] = '';

        // One stanza per dataset, then its distributions.
        foreach ($catalog['datasets'] as $ds) {
            $stmt = [];
            $stmt[] = 'a dcat:Dataset';
            $stmt[] = 'dcterms:title "'.$this->ttlLiteral($ds['title']).'"';
            if ($ds['description'] !== '') {
                $stmt[] = 'dcterms:description "'.$this->ttlLiteral($ds['description']).'"';
            }
            $stmt[] = 'dcterms:license <'.$this->ttlIri(self::LICENSE).'>';
            $stmt[] = 'dcterms:publisher <'.$this->ttlIri($pub['uri']).'>';
            if (! empty($ds['landingPage'])) {
                $stmt[] = 'dcat:landingPage <'.$this->ttlIri($ds['landingPage']).'>';
            }
            foreach ($ds['distributions'] as $d) {
                $stmt[] = 'dcat:distribution <'.$this->ttlIri($d['uri']).'>';
            }
            $lines[] = '<'.$this->ttlIri($ds['uri']).'>';
            $lines[] = '    '.implode(" ;\n    ", $stmt).' .';
            $lines[] = '';

            foreach ($ds['distributions'] as $d) {
                $dist = [];
                $dist[] = 'a dcat:Distribution';
                $dist[] = 'dcat:mediaType "'.$this->ttlLiteral($d['mediaType']).'"';
                $dist[] = 'dcat:accessURL <'.$this->ttlIri($d['accessURL']).'>';
                if (! empty($d['downloadURL'])) {
                    $dist[] = 'dcat:downloadURL <'.$this->ttlIri($d['downloadURL']).'>';
                }
                if (! empty($d['description'])) {
                    $dist[] = 'dcterms:description "'.$this->ttlLiteral($d['description']).'"';
                }
                $dist[] = 'dcterms:license <'.$this->ttlIri(self::LICENSE).'>';
                $lines[] = '<'.$this->ttlIri($d['uri']).'>';
                $lines[] = '    '.implode(" ;\n    ", $dist).' .';
                $lines[] = '';
            }
        }

        return implode("\n", $lines)."\n";
    }

    // -----------------------------------------------------------------
    // RDF/XML serialisation
    // -----------------------------------------------------------------

    /**
     * Always well-formed XML, even for an empty catalogue (just the rdf:RDF
     * envelope + the catalogue description). Literals/IRIs are XML-escaped.
     *
     * @param  array<string,mixed>  $catalog
     */
    protected function toRdfXml(array $catalog): string
    {
        $ns = $this->namespaces();

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<rdf:RDF';
        foreach ($ns as $prefix => $uri) {
            $out .= "\n    xmlns:{$prefix}=\"".$this->xmlAttr($uri).'"';
        }
        $out .= '>'."\n";

        $pub = $catalog['publisher'];

        // Publisher.
        $out .= '  <foaf:Agent rdf:about="'.$this->xmlAttr($pub['uri']).'">'."\n";
        $out .= '    <foaf:name>'.$this->xmlText($pub['name']).'</foaf:name>'."\n";
        $out .= '    <foaf:homepage rdf:resource="'.$this->xmlAttr($pub['homepage']).'"/>'."\n";
        $out .= '  </foaf:Agent>'."\n";

        // Catalogue.
        $out .= '  <dcat:Catalog rdf:about="'.$this->xmlAttr($catalog['uri']).'">'."\n";
        $out .= '    <dcterms:title>'.$this->xmlText($catalog['title']).'</dcterms:title>'."\n";
        $out .= '    <dcterms:description>'.$this->xmlText($catalog['description']).'</dcterms:description>'."\n";
        $out .= '    <dcterms:license rdf:resource="'.$this->xmlAttr(self::LICENSE).'"/>'."\n";
        $out .= '    <dcterms:publisher rdf:resource="'.$this->xmlAttr($pub['uri']).'"/>'."\n";
        $out .= '    <dcterms:modified rdf:datatype="'.$this->xmlAttr($ns['xsd'].'dateTime').'">'
            .$this->xmlText($catalog['modified']).'</dcterms:modified>'."\n";
        $out .= '    <dcat:landingPage rdf:resource="'.$this->xmlAttr($catalog['landingPage']).'"/>'."\n";
        foreach ($catalog['datasets'] as $ds) {
            $out .= '    <dcat:dataset rdf:resource="'.$this->xmlAttr($ds['uri']).'"/>'."\n";
        }
        $out .= '  </dcat:Catalog>'."\n";

        // Datasets + distributions.
        foreach ($catalog['datasets'] as $ds) {
            $out .= '  <dcat:Dataset rdf:about="'.$this->xmlAttr($ds['uri']).'">'."\n";
            $out .= '    <dcterms:title>'.$this->xmlText($ds['title']).'</dcterms:title>'."\n";
            if ($ds['description'] !== '') {
                $out .= '    <dcterms:description>'.$this->xmlText($ds['description']).'</dcterms:description>'."\n";
            }
            $out .= '    <dcterms:license rdf:resource="'.$this->xmlAttr(self::LICENSE).'"/>'."\n";
            $out .= '    <dcterms:publisher rdf:resource="'.$this->xmlAttr($pub['uri']).'"/>'."\n";
            if (! empty($ds['landingPage'])) {
                $out .= '    <dcat:landingPage rdf:resource="'.$this->xmlAttr($ds['landingPage']).'"/>'."\n";
            }
            foreach ($ds['distributions'] as $d) {
                $out .= '    <dcat:distribution rdf:resource="'.$this->xmlAttr($d['uri']).'"/>'."\n";
            }
            $out .= '  </dcat:Dataset>'."\n";

            foreach ($ds['distributions'] as $d) {
                $out .= '  <dcat:Distribution rdf:about="'.$this->xmlAttr($d['uri']).'">'."\n";
                $out .= '    <dcat:mediaType>'.$this->xmlText($d['mediaType']).'</dcat:mediaType>'."\n";
                $out .= '    <dcat:accessURL rdf:resource="'.$this->xmlAttr($d['accessURL']).'"/>'."\n";
                if (! empty($d['downloadURL'])) {
                    $out .= '    <dcat:downloadURL rdf:resource="'.$this->xmlAttr($d['downloadURL']).'"/>'."\n";
                }
                if (! empty($d['description'])) {
                    $out .= '    <dcterms:description>'.$this->xmlText($d['description']).'</dcterms:description>'."\n";
                }
                $out .= '    <dcterms:license rdf:resource="'.$this->xmlAttr(self::LICENSE).'"/>'."\n";
                $out .= '  </dcat:Distribution>'."\n";
            }
        }

        $out .= '</rdf:RDF>'."\n";

        return $out;
    }

    // -----------------------------------------------------------------
    // Human HTML view (built from the SAME catalogue array)
    // -----------------------------------------------------------------

    /**
     * A small, dependency-free human page listing the datasets + distributions.
     * Built from the same catalog() array so the views can never drift. No Blade
     * (this package has no public layout); escaped inline HTML only.
     */
    protected function html(): string
    {
        $cat = $this->catalog();
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($cat['datasets'] as $ds) {
            $dists = '';
            foreach ($ds['distributions'] as $d) {
                $href = $d['accessURL'];
                if (! empty($d['isTemplate']) && ! empty($ds['template'])) {
                    // Show the literal template form; link to the protocol doc.
                    $dists .= '<div><code>'.$e($d['mediaType']).'</code> &rarr; '
                        .'<code>'.$e($ds['template']).'</code> '
                        .'<small>(template, <a href="'.$e($href).'">how to use</a>)</small></div>';
                } else {
                    $dists .= '<div><code>'.$e($d['mediaType']).'</code> &rarr; '
                        .'<a href="'.$e($href).'">'.$e($href).'</a></div>';
                }
            }
            $rows .= '<tr><td><strong>'.$e($ds['title']).'</strong><br><small>'.$e($ds['description']).'</small></td>'
                .'<td>'.$dists.'</td></tr>'."\n";
        }

        $title = $e($cat['title']);
        $desc = $e($cat['description']);
        $jsonUrl = $e($cat['uri'].'.jsonld');
        $ttlUrl = $e($cat['uri'].'.ttl');

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.$title.'</title>'
            .'<style>body{font-family:system-ui,Arial,sans-serif;max-width:62rem;margin:2rem auto;padding:0 1rem;color:#1a1a1a}'
            .'h1{font-size:1.5rem}table{border-collapse:collapse;width:100%}'
            .'td,th{border:1px solid #ddd;padding:.6rem;vertical-align:top;text-align:left}'
            .'th{background:#f5f5f5}code{background:#f2f2f2;padding:.1rem .3rem;border-radius:3px}'
            .'small{color:#555}.meta{color:#555;margin:.3rem 0 1.2rem}</style></head><body>'
            .'<h1>'.$title.'</h1>'
            .'<p>'.$desc.'</p>'
            .'<p class="meta">Vocabulary: <strong>DCAT (DCAT-AP aligned)</strong> &middot; '
            .'Licence: <a href="'.$e(self::LICENSE).'">CC-BY-4.0</a> &middot; '
            .'Publisher: '.$e($cat['publisher']['name']).' &middot; '
            .'Machine views: <a href="'.$jsonUrl.'">JSON-LD</a>, <a href="'.$ttlUrl.'">Turtle</a> &middot; '
            .'Capabilities: <a href="'.$e($cat['protocol']).'">/open-data/protocol</a></p>'
            .'<table><thead><tr><th>Dataset</th><th>Distributions</th></tr></thead>'
            .'<tbody>'."\n".$rows.'</tbody></table>'
            .'</body></html>';
    }

    // -----------------------------------------------------------------
    // URI minting (stable, url()-based)
    // -----------------------------------------------------------------

    /**
     * Stable dataset URI: a fragment under the catalogue document keyed by the
     * surface id (e.g. .../data/catalog#dataset-graph-dataset). Resolves to the
     * catalogue itself, where the dataset is described.
     */
    protected function datasetUri(string $base, string $id): string
    {
        $slug = $id !== '' ? $id : 'dataset';

        return $base.'/data/catalog#dataset-'.$slug;
    }

    /**
     * Stable distribution URI: a fragment that encodes the access URL and media
     * type so each (dataset, media-type) pair has a unique, deterministic node.
     */
    protected function distributionUri(string $accessUrl, string $mediaType): string
    {
        $hash = substr(sha1($accessUrl.'|'.$mediaType), 0, 12);

        return $this->base().'/data/catalog#dist-'.$hash;
    }

    // -----------------------------------------------------------------
    // Format negotiation
    // -----------------------------------------------------------------

    /**
     * Format selection: a path suffix wins, then ?format=, then the Accept
     * header; JSON-LD is the default so a bare curl gets machine output.
     */
    protected function negotiateFormat(Request $request, ?string $suffix = null): string
    {
        $sfx = strtolower((string) $suffix);
        if ($sfx === 'ttl') {
            return 'turtle';
        }
        if ($sfx === 'rdf') {
            return 'rdfxml';
        }
        if ($sfx === 'jsonld') {
            return 'jsonld';
        }

        $param = strtolower((string) $request->query('format', ''));
        if (in_array($param, ['turtle', 'ttl'], true)) {
            return 'turtle';
        }
        if (in_array($param, ['rdf', 'rdfxml', 'rdf-xml', 'rdf/xml'], true)) {
            return 'rdfxml';
        }
        if (in_array($param, ['jsonld', 'json-ld', 'json'], true)) {
            return 'jsonld';
        }
        if ($param === 'html') {
            // ?format=html is handled by the HTML branch in index() only when no
            // suffix is present; fall through to jsonld for the negotiation here.
            return 'jsonld';
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        // JSON-LD / JSON explicitly requested beats a wildcard.
        if (str_contains($accept, 'application/ld+json') || str_contains($accept, 'application/json')) {
            return 'jsonld';
        }
        if (str_contains($accept, 'text/turtle') || str_contains($accept, 'application/x-turtle')) {
            return 'turtle';
        }
        if (str_contains($accept, 'application/rdf+xml')) {
            return 'rdfxml';
        }

        return 'jsonld';
    }

    /**
     * Whether the client prefers HTML (a browser). A bare curl's catch-all
     * Accept does NOT count as HTML, so a plain curl gets JSON-LD.
     */
    protected function wantsHtml(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));

        if ($request->query('format') === 'html') {
            return true;
        }
        if (str_contains($accept, 'application/json') || str_contains($accept, 'application/ld+json')
            || str_contains($accept, 'text/turtle') || str_contains($accept, 'application/rdf+xml')) {
            return false;
        }

        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml');
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * Strip characters illegal in a Turtle IRIREF so the document stays
     * parseable even for an unexpected URL.
     */
    protected function ttlIri(string $iri): string
    {
        return (string) preg_replace('/[\x00-\x20<>"{}|\^`\\\\]/u', '', $iri);
    }

    /**
     * Escape a Turtle string-literal body (the content between the quotes).
     */
    protected function ttlLiteral(string $value): string
    {
        return str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $value
        );
    }

    protected function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function xmlAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Apply permissive open-data CORS headers. The catalogue is intentionally
     * world-readable (open data), so any origin may fetch it.
     */
    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('Vary', 'Accept');
        $response->headers->set('X-Open-Data', 'true');

        return $response;
    }
}
