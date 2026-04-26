<?php

/**
 * RdfImportService - Service for Heratio
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

use Illuminate\Support\Facades\DB;

/**
 * RDF inbound pipeline. Parses TTL / JSON-LD / RDF-XML and maps RiC-O / Dublin Core /
 * SKOS predicates onto Heratio's information_object and actor tables.
 *
 * Design notes
 * ------------
 * - The TTL parser is intentionally minimal: it covers PREFIX/@prefix declarations,
 *   IRIs (full + prefixed + relative <…>), literals with language tags and datatypes,
 *   and ; , . separators. It does *not* cover RDF collections () or full blank-node
 *   graphs ([ ... ]) — those round-trip via JSON-LD (preferred for complex docs).
 * - JSON-LD parsing handles flat or @graph-style docs with @id / @type / @value.
 * - RDF-XML parsing covers the typed-node form with rdf:about / rdf:resource / rdf:datatype.
 * - Mapping is predicate-driven and table-aware. Unknown predicates are reported but
 *   ignored on commit — they show up under "unmapped" in the dry-run summary.
 */
class RdfImportService
{
    public const FORMATS = ['turtle', 'jsonld', 'rdfxml'];

    /** Predicate → mapping descriptor. is_i18n marks i18n columns; bucket selects table. */
    private const IO_PREDICATE_MAP = [
        'rico:name'                  => ['col' => 'title',                              'is_i18n' => true],
        'dc:title'                   => ['col' => 'title',                              'is_i18n' => true],
        'dcterms:title'              => ['col' => 'title',                              'is_i18n' => true],
        'rdfs:label'                 => ['col' => 'title',                              'is_i18n' => true],
        'rico:description'           => ['col' => 'scope_and_content',                  'is_i18n' => true],
        'dc:description'             => ['col' => 'scope_and_content',                  'is_i18n' => true],
        'dcterms:description'        => ['col' => 'scope_and_content',                  'is_i18n' => true],
        'rico:hasContentOfType'      => ['col' => 'extent_and_medium',                  'is_i18n' => true],
        'dc:format'                  => ['col' => 'extent_and_medium',                  'is_i18n' => true],
        'rico:history'               => ['col' => 'archival_history',                   'is_i18n' => true],
        'rico:scopeAndContent'       => ['col' => 'scope_and_content',                  'is_i18n' => true],
        'rico:identifier'            => ['col' => 'identifier',                         'is_i18n' => false],
        'dc:identifier'              => ['col' => 'identifier',                         'is_i18n' => false],
        'rico:hasOrHadLanguage'      => ['col' => 'language',                           'is_i18n' => true, 'transform' => 'iri_local'],
    ];

    private const ACTOR_PREDICATE_MAP = [
        'rico:name'                       => ['col' => 'authorized_form_of_name', 'is_i18n' => true],
        'rico:authorizedFormOfName'       => ['col' => 'authorized_form_of_name', 'is_i18n' => true],
        'rdfs:label'                      => ['col' => 'authorized_form_of_name', 'is_i18n' => true],
        'rico:history'                    => ['col' => 'history',                 'is_i18n' => true],
        'rico:descriptiveNote'            => ['col' => 'history',                 'is_i18n' => true],
        'rico:hasOrHadCorporateBodyType'  => ['col' => 'corporate_body_identifiers', 'is_i18n' => true, 'transform' => 'iri_local'],
    ];

    private const RDF_TYPE = 'rdf:type';

    private const IO_TYPES = [
        'rico:Record', 'rico:RecordResource', 'rico:RecordSet', 'rico:Item',
        'dcmitype:Text', 'dcmitype:Image', 'dcmitype:Collection',
    ];

    private const ACTOR_TYPES = [
        'rico:Agent', 'rico:Person', 'rico:CorporateBody', 'rico:Family',
        'foaf:Person', 'foaf:Organization', 'foaf:Agent',
    ];

    private array $prefixes = [
        'rico'     => 'https://www.ica.org/standards/RiC/ontology#',
        'rdf'      => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs'     => 'http://www.w3.org/2000/01/rdf-schema#',
        'xsd'      => 'http://www.w3.org/2001/XMLSchema#',
        'dc'       => 'http://purl.org/dc/elements/1.1/',
        'dcterms'  => 'http://purl.org/dc/terms/',
        'dcmitype' => 'http://purl.org/dc/dcmitype/',
        'skos'     => 'http://www.w3.org/2004/02/skos/core#',
        'foaf'     => 'http://xmlns.com/foaf/0.1/',
        'owl'      => 'http://www.w3.org/2002/07/owl#',
    ];

    /**
     * Parse a payload and return triples + summary.
     *
     * @return array{triples: list<array{s:string,p:string,o:array{type:string,value:string,lang?:string,datatype?:string}}>, prefixes: array<string,string>, format: string}
     */
    public function parse(string $payload, string $format): array
    {
        $format = strtolower($format);
        $triples = match ($format) {
            'turtle'                  => $this->parseTurtle($payload),
            'jsonld', 'json-ld', 'json' => $this->parseJsonLd($payload),
            'rdfxml', 'rdf+xml', 'xml' => $this->parseRdfXml($payload),
            default => throw new \InvalidArgumentException("Unknown RDF format: {$format}"),
        };
        return [
            'triples'  => $triples,
            'prefixes' => $this->prefixes,
            'format'   => $format,
        ];
    }

    /**
     * Group triples by subject and classify (information_object | actor | unknown).
     *
     * @return array<string, array{type:string, classes:list<string>, predicates:array<string,list<array<string,mixed>>>}>
     */
    public function classify(array $triples): array
    {
        $bySubject = [];
        foreach ($triples as $t) {
            $bySubject[$t['s']]['predicates'][$t['p']][] = $t['o'];
        }
        foreach ($bySubject as $s => &$bag) {
            $classes = $this->localCurieList($bag['predicates'][self::RDF_TYPE] ?? []);
            $bag['classes'] = $classes;
            if (array_intersect($classes, self::IO_TYPES)) {
                $bag['type'] = 'information_object';
            } elseif (array_intersect($classes, self::ACTOR_TYPES)) {
                $bag['type'] = 'actor';
            } else {
                $bag['type'] = 'unknown';
            }
        }
        unset($bag);
        return $bySubject;
    }

    /**
     * Run a dry-run import. Returns counts + lists, performs no DB writes.
     *
     * @return array{
     *   format:string,
     *   subjects:int,
     *   triples:int,
     *   would_create:array{information_object:int,actor:int,unknown:int},
     *   mapped_predicates:array<string,int>,
     *   unmapped_predicates:array<string,int>,
     *   classes_seen:array<string,int>,
     *   sample:list<array<string,mixed>>,
     * }
     */
    public function dryRun(string $payload, string $format): array
    {
        $parsed = $this->parse($payload, $format);
        $bySubject = $this->classify($parsed['triples']);

        $counts = ['information_object' => 0, 'actor' => 0, 'unknown' => 0];
        $mapped = [];
        $unmapped = [];
        $classesSeen = [];
        $sample = [];

        foreach ($bySubject as $subject => $bag) {
            $counts[$bag['type']]++;
            foreach ($bag['classes'] as $c) {
                $classesSeen[$c] = ($classesSeen[$c] ?? 0) + 1;
            }
            $rules = $bag['type'] === 'information_object'
                ? self::IO_PREDICATE_MAP
                : ($bag['type'] === 'actor' ? self::ACTOR_PREDICATE_MAP : []);
            foreach ($bag['predicates'] as $pCurie => $objects) {
                if ($pCurie === self::RDF_TYPE) continue;
                if (isset($rules[$pCurie])) {
                    $mapped[$pCurie] = ($mapped[$pCurie] ?? 0) + count($objects);
                } else {
                    $unmapped[$pCurie] = ($unmapped[$pCurie] ?? 0) + count($objects);
                }
            }
            if (count($sample) < 5 && $bag['type'] !== 'unknown') {
                $sample[] = [
                    'subject' => $subject,
                    'type'    => $bag['type'],
                    'title'   => $this->extractTitle($bag, $bag['type']),
                ];
            }
        }

        return [
            'format'              => $parsed['format'],
            'subjects'            => count($bySubject),
            'triples'             => count($parsed['triples']),
            'would_create'        => $counts,
            'mapped_predicates'   => $mapped,
            'unmapped_predicates' => $unmapped,
            'classes_seen'        => $classesSeen,
            'sample'              => $sample,
        ];
    }

    /**
     * Commit the import. Creates new IO / actor rows and returns the created IDs.
     *
     * @return array{created_io:list<int>, created_actor:list<int>, skipped:int, errors:list<string>}
     */
    public function commit(string $payload, string $format, string $culture = 'en'): array
    {
        $parsed = $this->parse($payload, $format);
        $bySubject = $this->classify($parsed['triples']);

        $createdIo = [];
        $createdActor = [];
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($bySubject as $subject => $bag) {
                if ($bag['type'] === 'unknown') { $skipped++; continue; }
                if ($bag['type'] === 'information_object') {
                    $id = $this->writeInformationObject($bag, $culture);
                    if ($id) $createdIo[] = $id;
                } elseif ($bag['type'] === 'actor') {
                    $id = $this->writeActor($bag, $culture);
                    if ($id) $createdActor[] = $id;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = 'Commit failed: ' . $e->getMessage();
        }

        return [
            'created_io'    => $createdIo,
            'created_actor' => $createdActor,
            'skipped'       => $skipped,
            'errors'        => $errors,
        ];
    }

    // -------- writers -----------------------------------------------------------

    private function writeInformationObject(array $bag, string $culture): ?int
    {
        $cols = ['source_culture' => $culture, 'parent_id' => 1, 'lft' => 0, 'rgt' => 0];
        $i18n = ['culture' => $culture];

        foreach ($bag['predicates'] as $pCurie => $objects) {
            if (!isset(self::IO_PREDICATE_MAP[$pCurie])) continue;
            $rule = self::IO_PREDICATE_MAP[$pCurie];
            $value = $this->collapseObjectValues($objects, $rule, $culture);
            if ($value === null) continue;
            if ($rule['is_i18n']) {
                $i18n[$rule['col']] = $value;
            } else {
                $cols[$rule['col']] = $value;
            }
        }

        if (empty($i18n['title']) && empty($cols['identifier'])) return null;

        // Insert object → information_object → information_object_i18n
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('information_object')->insert(array_merge(['id' => $objectId], $cols));
        DB::table('information_object_i18n')->insert(array_merge(['id' => $objectId], $i18n));

        return $objectId;
    }

    private function writeActor(array $bag, string $culture): ?int
    {
        $cols = ['source_culture' => $culture, 'parent_id' => 1, 'lft' => 0, 'rgt' => 0];
        $i18n = ['culture' => $culture];

        foreach ($bag['predicates'] as $pCurie => $objects) {
            if (!isset(self::ACTOR_PREDICATE_MAP[$pCurie])) continue;
            $rule = self::ACTOR_PREDICATE_MAP[$pCurie];
            $value = $this->collapseObjectValues($objects, $rule, $culture);
            if ($value === null) continue;
            if ($rule['is_i18n']) {
                $i18n[$rule['col']] = $value;
            } else {
                $cols[$rule['col']] = $value;
            }
        }

        if (empty($i18n['authorized_form_of_name'])) return null;

        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitActor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('actor')->insert(array_merge(['id' => $objectId], $cols));
        DB::table('actor_i18n')->insert(array_merge(['id' => $objectId], $i18n));

        return $objectId;
    }

    // -------- TTL parser --------------------------------------------------------

    private function parseTurtle(string $payload): array
    {
        // 1. Lift @prefix / PREFIX declarations into $this->prefixes
        if (preg_match_all('/@?(?:prefix|PREFIX)\s+([A-Za-z][\w-]*)?:\s*<([^>]+)>\s*\.?/m', $payload, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $this->prefixes[$row[1] ?: ''] = $row[2];
            }
        }

        // Strip prefix/base declarations and # comments before tokenising.
        // The greedy "[^\.]+\." form would stop at the first dot inside the IRI
        // (e.g. <http://purl.org/dc/elements/1.1/>), so match the IRI explicitly.
        $body = preg_replace('/@?(?:prefix|PREFIX)\s+(?:[A-Za-z][\w-]*)?:\s*<[^>]+>\s*\.\s*/', '', $payload);
        $body = preg_replace('/@?(?:base|BASE)\s+<[^>]+>\s*\.?\s*/', '', $body);
        $body = preg_replace('/#[^\n]*/', '', $body);

        $triples = [];
        $tokens = $this->turtleTokens($body);
        $i = 0;
        $count = count($tokens);
        $currentSubject = null;
        $currentPredicate = null;

        while ($i < $count) {
            $tok = $tokens[$i];
            if ($tok === '.') {
                $currentSubject = null;
                $currentPredicate = null;
                $i++; continue;
            }
            if ($tok === ';') {
                $currentPredicate = null;
                $i++; continue;
            }
            if ($tok === ',') {
                $i++; continue;
            }

            if ($currentSubject === null) {
                $currentSubject = $this->normaliseIri($tok);
                $i++; continue;
            }
            if ($currentPredicate === null) {
                $currentPredicate = $tok === 'a' ? self::RDF_TYPE : $this->normaliseCurie($tok);
                $i++; continue;
            }
            // object position
            $object = $this->parseTtlObject($tokens, $i);
            $triples[] = [
                's' => $currentSubject,
                'p' => $currentPredicate,
                'o' => $object,
            ];
        }

        return $triples;
    }

    /**
     * Tokenise TTL: keeps quoted literals, IRIs, prefixed names, "a", and structural punctuation.
     *
     * @return list<string>
     */
    private function turtleTokens(string $s): array
    {
        $tokens = [];
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            $c = $s[$i];
            if (ctype_space($c)) { $i++; continue; }

            if ($c === '<') {
                $end = strpos($s, '>', $i);
                if ($end === false) break;
                $tokens[] = substr($s, $i, $end - $i + 1);
                $i = $end + 1;
                continue;
            }
            if ($c === '"' || $c === "'") {
                // Triple-quoted ("""...""") and single-quoted literals
                $isTriple = substr($s, $i, 3) === str_repeat($c, 3);
                if ($isTriple) {
                    $end = strpos($s, str_repeat($c, 3), $i + 3);
                    if ($end === false) break;
                    $lit = substr($s, $i, $end - $i + 3);
                    $i = $end + 3;
                } else {
                    $j = $i + 1;
                    while ($j < $len) {
                        if ($s[$j] === '\\') { $j += 2; continue; }
                        if ($s[$j] === $c) break;
                        $j++;
                    }
                    $lit = substr($s, $i, $j - $i + 1);
                    $i = $j + 1;
                }
                // Pull optional language tag (@en) or datatype (^^xsd:string)
                if ($i < $len && $s[$i] === '@') {
                    $j = $i + 1;
                    while ($j < $len && (ctype_alpha($s[$j]) || $s[$j] === '-')) $j++;
                    $lit .= substr($s, $i, $j - $i);
                    $i = $j;
                } elseif ($i + 1 < $len && substr($s, $i, 2) === '^^') {
                    $j = $i + 2;
                    if ($s[$j] === '<') {
                        $end = strpos($s, '>', $j);
                        if ($end !== false) { $lit .= substr($s, $i, $end - $i + 1); $i = $end + 1; continue; }
                    }
                    while ($j < $len && !ctype_space($s[$j]) && !in_array($s[$j], ['.', ';', ','], true)) $j++;
                    $lit .= substr($s, $i, $j - $i);
                    $i = $j;
                }
                $tokens[] = $lit;
                continue;
            }

            if ($c === '.' || $c === ';' || $c === ',') {
                // Disambiguate "." inside numbers like 3.14 — only treat as separator if next is space/EOF
                $next = $s[$i + 1] ?? '';
                if ($c === '.' && ctype_digit($next)) {
                    $j = $i; while ($j < $len && (ctype_digit($s[$j]) || $s[$j] === '.')) $j++;
                    $tokens[] = substr($s, $i, $j - $i);
                    $i = $j; continue;
                }
                $tokens[] = $c;
                $i++; continue;
            }

            // IRI / prefixed name / "a" / numeric / boolean
            $j = $i;
            while ($j < $len && !ctype_space($s[$j]) && !in_array($s[$j], ['.', ';', ',', '<', '"', "'"], true)) $j++;
            $tok = substr($s, $i, $j - $i);
            if ($tok !== '') $tokens[] = $tok;
            $i = $j;
        }
        return $tokens;
    }

    /**
     * Read one TTL object position and advance $i past it (including any language/datatype suffix).
     *
     * @return array{type:string,value:string,lang?:string,datatype?:string}
     */
    private function parseTtlObject(array $tokens, int &$i): array
    {
        $tok = $tokens[$i];
        $i++;

        // Quoted literal — may already include @lang or ^^datatype suffix
        if (strlen($tok) > 0 && ($tok[0] === '"' || $tok[0] === "'")) {
            return $this->splitTtlLiteral($tok);
        }

        // IRI / prefixed name / blank node
        return [
            'type'  => 'iri',
            'value' => $this->normaliseIri($tok),
        ];
    }

    private function splitTtlLiteral(string $tok): array
    {
        $value = '';
        $lang = null;
        $datatype = null;

        $quote = $tok[0];
        if (substr($tok, 0, 3) === str_repeat($quote, 3)) {
            $end = strrpos($tok, str_repeat($quote, 3));
            $value = substr($tok, 3, $end - 3);
            $tail = substr($tok, $end + 3);
        } else {
            // Find closing quote
            $j = 1;
            $len = strlen($tok);
            while ($j < $len) {
                if ($tok[$j] === '\\') { $j += 2; continue; }
                if ($tok[$j] === $quote) break;
                $j++;
            }
            $value = stripcslashes(substr($tok, 1, $j - 1));
            $tail = substr($tok, $j + 1);
        }

        if (str_starts_with($tail, '@')) {
            $lang = substr($tail, 1);
        } elseif (str_starts_with($tail, '^^')) {
            $datatype = $this->normaliseIri(substr($tail, 2));
        }

        return array_filter([
            'type'     => 'literal',
            'value'    => $value,
            'lang'     => $lang,
            'datatype' => $datatype,
        ], fn ($v) => $v !== null);
    }

    // -------- JSON-LD parser ----------------------------------------------------

    private function parseJsonLd(string $payload): array
    {
        $doc = json_decode($payload, true);
        if (!is_array($doc)) return [];

        // Honour @context for prefixes
        $ctx = $doc['@context'] ?? null;
        if (is_array($ctx)) {
            foreach ($ctx as $k => $v) {
                if (is_string($v) && filter_var($v, FILTER_VALIDATE_URL)) {
                    $this->prefixes[$k] = $v;
                }
            }
        }

        $nodes = [];
        if (isset($doc['@graph']) && is_array($doc['@graph'])) {
            $nodes = $doc['@graph'];
        } elseif (isset($doc['@id']) || isset($doc['@type'])) {
            $nodes = [$doc];
        } elseif (array_is_list($doc)) {
            $nodes = $doc;
        }

        $triples = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) continue;
            $subject = $node['@id'] ?? ('_:' . md5(json_encode($node)));
            $subject = $this->normaliseIri($subject);

            foreach ($node as $key => $val) {
                if (in_array($key, ['@context', '@id'], true)) continue;
                $pred = $key === '@type' ? self::RDF_TYPE : $this->normaliseCurie($key);
                $vals = is_array($val) && array_is_list($val) ? $val : [$val];
                foreach ($vals as $v) {
                    if (is_array($v) && isset($v['@value'])) {
                        $obj = ['type' => 'literal', 'value' => (string) $v['@value']];
                        if (isset($v['@language'])) $obj['lang'] = $v['@language'];
                        if (isset($v['@type'])) $obj['datatype'] = $this->normaliseIri($v['@type']);
                    } elseif (is_array($v) && isset($v['@id'])) {
                        $obj = ['type' => 'iri', 'value' => $this->normaliseIri($v['@id'])];
                    } elseif (is_string($v)) {
                        // For @type, value is a class IRI; for everything else heuristic:
                        // if it parses as URL or curie, treat as IRI, else literal
                        $obj = $key === '@type' || $this->looksLikeIri($v)
                            ? ['type' => 'iri',     'value' => $this->normaliseIri($v)]
                            : ['type' => 'literal', 'value' => $v];
                    } else {
                        $obj = ['type' => 'literal', 'value' => (string) $v];
                    }
                    $triples[] = ['s' => $subject, 'p' => $pred, 'o' => $obj];
                }
            }
        }
        return $triples;
    }

    // -------- RDF-XML parser ----------------------------------------------------

    private function parseRdfXml(string $payload): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($payload);
        if (!$xml) return [];

        $namespaces = $xml->getDocNamespaces(true);
        foreach ($namespaces as $prefix => $uri) {
            if ($prefix !== '') $this->prefixes[$prefix] = $uri;
        }

        $rdfNs = $namespaces['rdf'] ?? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $triples = [];

        foreach ($xml->children() as $node) {
            $subjectAttr = $node->attributes($rdfNs);
            $subject = (string) ($subjectAttr['about'] ?? $subjectAttr['ID'] ?? '');
            if (!$subject) {
                $subject = '_:' . md5($node->asXML());
            } else {
                $subject = $this->normaliseIri($subject);
            }

            // typed-node form: the element name itself implies an rdf:type
            $name = $node->getName();
            $typeQ = $this->qnameOfNode($node, $namespaces);
            if ($typeQ) {
                $triples[] = ['s' => $subject, 'p' => self::RDF_TYPE, 'o' => ['type' => 'iri', 'value' => $typeQ]];
            }

            foreach ($node->children() as $prop) {
                $pName = $this->qnameOfNode($prop, $namespaces);
                if (!$pName) continue;
                $attrs = $prop->attributes($rdfNs);
                $resource = (string) ($attrs['resource'] ?? '');
                $datatype = (string) ($attrs['datatype'] ?? '');
                $lang = (string) $prop->attributes('http://www.w3.org/XML/1998/namespace')['lang'] ?? '';
                if ($resource) {
                    $triples[] = ['s' => $subject, 'p' => $pName, 'o' => ['type' => 'iri', 'value' => $this->normaliseIri($resource)]];
                } else {
                    $obj = ['type' => 'literal', 'value' => (string) $prop];
                    if ($lang) $obj['lang'] = $lang;
                    if ($datatype) $obj['datatype'] = $this->normaliseIri($datatype);
                    $triples[] = ['s' => $subject, 'p' => $pName, 'o' => $obj];
                }
            }
        }
        return $triples;
    }

    private function qnameOfNode(\SimpleXMLElement $node, array $namespaces): ?string
    {
        $name = $node->getName();
        foreach ($namespaces as $prefix => $uri) {
            $children = $node->children($uri);
            if ($prefix !== '' && $children !== null && count($children) >= 0) {
                // SimpleXML doesn't expose node prefix directly; pick first ns matching the node's namespace
                $nodeNs = $node->getNamespaces(false);
                $nodeUri = reset($nodeNs);
                if ($nodeUri === $uri) {
                    return $prefix . ':' . $name;
                }
            }
        }
        // Fallback — try via node's own namespace map
        $own = $node->getNamespaces(false);
        if ($own) {
            $uri = reset($own);
            $prefix = array_search($uri, $namespaces, true);
            if ($prefix !== false && $prefix !== '') return $prefix . ':' . $name;
        }
        return $name; // last-resort, may be unprefixed
    }

    // -------- helpers -----------------------------------------------------------

    private function normaliseIri(string $token): string
    {
        if ($token === '' || $token === 'a') return self::RDF_TYPE;
        if ($token[0] === '<' && substr($token, -1) === '>') {
            return $this->iriToCurie(substr($token, 1, -1));
        }
        if (str_contains($token, ':') && !str_starts_with($token, 'http')) {
            return $token; // already a curie
        }
        if (str_starts_with($token, 'http')) {
            return $this->iriToCurie($token);
        }
        return $token;
    }

    private function normaliseCurie(string $token): string
    {
        return $this->normaliseIri($token);
    }

    private function iriToCurie(string $iri): string
    {
        foreach ($this->prefixes as $prefix => $base) {
            if ($prefix !== '' && str_starts_with($iri, $base)) {
                return $prefix . ':' . substr($iri, strlen($base));
            }
        }
        return $iri; // not prefixable
    }

    private function looksLikeIri(string $v): bool
    {
        return str_contains($v, '://') || preg_match('/^[A-Za-z][\w-]*:[A-Za-z][\w-]*$/', $v) === 1;
    }

    /** @param list<array<string,mixed>> $objects */
    private function localCurieList(array $objects): array
    {
        $out = [];
        foreach ($objects as $o) {
            if (($o['type'] ?? '') === 'iri' && !empty($o['value'])) {
                $out[] = $o['value'];
            }
        }
        return $out;
    }

    /** @param list<array<string,mixed>> $objects */
    private function collapseObjectValues(array $objects, array $rule, string $culture): ?string
    {
        // Prefer literal in target culture, then any literal, then a local-name IRI
        $byLang = null;
        $anyLit = null;
        $iri = null;
        foreach ($objects as $o) {
            if (($o['type'] ?? '') === 'literal') {
                if (!$anyLit) $anyLit = $o['value'];
                if (!empty($o['lang']) && stripos($o['lang'], $culture) === 0) {
                    $byLang = $o['value'];
                    break;
                }
            } elseif (($o['type'] ?? '') === 'iri' && !$iri) {
                $iri = $o['value'];
            }
        }
        $value = $byLang ?? $anyLit ?? $iri;
        if ($value === null) return null;
        if (($rule['transform'] ?? null) === 'iri_local' && is_string($value)) {
            $value = preg_replace('/^.*[#\/:]/', '', $value);
        }
        return is_string($value) ? trim($value) : null;
    }

    private function extractTitle(array $bag, string $entityType): ?string
    {
        $titleKeys = $entityType === 'actor'
            ? ['rico:authorizedFormOfName', 'rico:name', 'rdfs:label']
            : ['rico:name', 'dc:title', 'dcterms:title', 'rdfs:label'];
        foreach ($titleKeys as $k) {
            foreach ($bag['predicates'][$k] ?? [] as $obj) {
                if (($obj['type'] ?? '') === 'literal' && !empty($obj['value'])) return $obj['value'];
            }
        }
        return null;
    }
}
