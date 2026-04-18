<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Convert an OpenRiC JSON-LD graph (the shape returned by
 * RicSerializationService) into Turtle or RDF/XML. We avoid pulling in a
 * full RDF library (EasyRdf / hardf) and hand-roll a narrow converter
 * that handles the subset of JSON-LD the serializer actually produces:
 *
 *   - A top-level @graph array of typed nodes, OR
 *   - A single typed node
 *   - @context mapping prefixes to IRIs
 *   - @id as the subject URI
 *   - @type as rdf:type
 *   - rico:* / openric:* predicates with scalar or array values
 *   - Values that are strings, numbers, booleans, or {@id, @type?, @value?} objects
 *
 * Known limitations:
 *   - Language tags on literals are preserved only via @language objects.
 *   - Nested structures deeper than one level are emitted as blank nodes
 *     without unique skolemization — good enough for single-document export,
 *     not for triple-store ingest with deduplication.
 */

namespace AhgRic\Support;

class JsonLdConverter
{
    /** Convert a JSON-LD document to Turtle. */
    public static function toTurtle(array $doc): string
    {
        [$context, $nodes] = self::unpack($doc);

        $prefixes = [];
        foreach ($context as $k => $v) {
            // Skip malformed entries — a prefix must itself be a short name,
            // not a full IRI (some serializers put self-mapped IRIs here).
            if (!is_string($v)) continue;
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', (string) $k)) continue;
            $prefixes[$k] = $v;
        }
        // Always emit the standards we use.
        $prefixes += [
            'rdf'  => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'xsd'  => 'http://www.w3.org/2001/XMLSchema#',
            'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            'openric' => 'https://openric.org/ns/v1#',
        ];
        ksort($prefixes);

        $out = '';
        foreach ($prefixes as $p => $iri) {
            $out .= "@prefix {$p}: <{$iri}> .\n";
        }
        $out .= "\n";

        $bnCounter = 0;
        foreach ($nodes as $node) {
            $out .= self::nodeToTurtle($node, $prefixes, $bnCounter) . "\n";
        }
        return $out;
    }

    /** Convert a JSON-LD document to RDF/XML. */
    public static function toRdfXml(array $doc): string
    {
        [$context, $nodes] = self::unpack($doc);

        $nsMap = [];
        foreach ($context as $k => $v) {
            if (!is_string($v)) continue;
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', (string) $k)) continue;
            $nsMap[$k] = $v;
        }
        $nsMap += [
            'rdf'  => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            'openric' => 'https://openric.org/ns/v1#',
        ];

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rdf:RDF';
        foreach ($nsMap as $p => $iri) {
            $xml .= ' xmlns:' . $p . '="' . htmlspecialchars($iri, ENT_XML1) . '"';
        }
        $xml .= ">\n";

        foreach ($nodes as $node) {
            $xml .= self::nodeToRdfXml($node, $nsMap);
        }
        $xml .= '</rdf:RDF>' . "\n";
        return $xml;
    }

    // ---- shared --------------------------------------------------

    private static function unpack(array $doc): array
    {
        $context = $doc['@context'] ?? [];
        if (is_string($context)) $context = [];
        if (isset($doc['@graph']) && is_array($doc['@graph'])) {
            return [$context, $doc['@graph']];
        }
        // Single-node document — wrap in array.
        return [$context, [$doc]];
    }

    private static function nodeToTurtle(array $node, array $prefixes, int &$bnCounter): string
    {
        $subject = $node['@id'] ?? ('_:b' . (++$bnCounter));
        $subjectTerm = self::iriOrBlank($subject, $prefixes);
        $lines = [];

        if (isset($node['@type'])) {
            $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
            $lines[] = '    a ' . implode(', ', array_map(fn($t) => self::iriOrBlank($t, $prefixes), $types));
        }

        foreach ($node as $key => $value) {
            if (str_starts_with($key, '@')) continue;
            $predicate = self::iriOrBlank($key, $prefixes);
            foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $v) {
                $lines[] = '    ' . $predicate . ' ' . self::valueToTurtle($v, $prefixes, $bnCounter);
            }
        }

        if (empty($lines)) return '';
        return $subjectTerm . "\n" . implode(" ;\n", $lines) . " .\n";
    }

    private static function valueToTurtle($v, array $prefixes, int &$bnCounter): string
    {
        if (is_array($v)) {
            if (isset($v['@id'])) {
                return self::iriOrBlank($v['@id'], $prefixes);
            }
            if (isset($v['@value'])) {
                $lit = '"' . self::escapeTurtleLit((string) $v['@value']) . '"';
                if (isset($v['@language'])) $lit .= '@' . preg_replace('/[^a-zA-Z0-9-]/', '', $v['@language']);
                if (isset($v['@type']))     $lit .= '^^' . self::iriOrBlank($v['@type'], $prefixes);
                return $lit;
            }
            // Blank-node with nested object structure.
            $bn = '_:b' . (++$bnCounter);
            return $bn;  // nested values are skolemised away — good enough for single-doc export
        }
        if (is_bool($v))   return $v ? 'true' : 'false';
        if (is_numeric($v)) return (string) $v;
        return '"' . self::escapeTurtleLit((string) $v) . '"';
    }

    private static function iriOrBlank(string $v, array $prefixes): string
    {
        if (str_starts_with($v, '_:')) return $v;
        // Is this already a prefixed name (ns:local)?
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9]*):[a-zA-Z][A-Za-z0-9._-]*$/', $v, $m) && isset($prefixes[$m[1]])) {
            return $v;
        }
        // Full IRI — try to compact against a known prefix.
        foreach ($prefixes as $p => $iri) {
            if (str_starts_with($v, $iri) && substr($v, strlen($iri)) !== '') {
                $local = substr($v, strlen($iri));
                if (preg_match('/^[a-zA-Z][A-Za-z0-9._-]*$/', $local)) {
                    return $p . ':' . $local;
                }
            }
        }
        return '<' . $v . '>';
    }

    private static function escapeTurtleLit(string $s): string
    {
        return str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\"', '\n', '\r', '\t'], $s);
    }

    // ---- RDF/XML -----------------------------------------------------

    private static function nodeToRdfXml(array $node, array $nsMap): string
    {
        $about = $node['@id'] ?? null;
        $typeTag = 'rdf:Description';
        if (isset($node['@type'])) {
            $t = is_array($node['@type']) ? $node['@type'][0] : $node['@type'];
            $compact = self::xmlCompactName($t, $nsMap);
            if ($compact) $typeTag = $compact;
        }
        $attrs = $about ? ' rdf:about="' . htmlspecialchars($about, ENT_XML1 | ENT_QUOTES) . '"' : '';
        $xml = "  <{$typeTag}{$attrs}>\n";

        if (isset($node['@type']) && is_array($node['@type']) && count($node['@type']) > 1) {
            foreach (array_slice($node['@type'], 1) as $t) {
                $xml .= '    <rdf:type rdf:resource="' . htmlspecialchars(self::expand($t, $nsMap), ENT_XML1 | ENT_QUOTES) . "\"/>\n";
            }
        }

        foreach ($node as $k => $v) {
            if (str_starts_with($k, '@')) continue;
            $tag = self::xmlCompactName($k, $nsMap) ?: str_replace([':'], ['_'], $k);
            foreach (is_array($v) && array_is_list($v) ? $v : [$v] as $val) {
                if (is_array($val)) {
                    if (isset($val['@id'])) {
                        $xml .= "    <{$tag} rdf:resource=\"" . htmlspecialchars($val['@id'], ENT_XML1 | ENT_QUOTES) . "\"/>\n";
                    } elseif (isset($val['@value'])) {
                        $lang = isset($val['@language']) ? ' xml:lang="' . htmlspecialchars($val['@language'], ENT_XML1 | ENT_QUOTES) . '"' : '';
                        $dt   = isset($val['@type']) ? ' rdf:datatype="' . htmlspecialchars(self::expand($val['@type'], $nsMap), ENT_XML1 | ENT_QUOTES) . '"' : '';
                        $xml .= "    <{$tag}{$lang}{$dt}>" . htmlspecialchars((string) $val['@value'], ENT_XML1) . "</{$tag}>\n";
                    }
                    // Nested objects without @id/@value are skipped — see class docblock.
                    continue;
                }
                $xml .= "    <{$tag}>" . htmlspecialchars((string) $val, ENT_XML1) . "</{$tag}>\n";
            }
        }
        $xml .= "  </{$typeTag}>\n";
        return $xml;
    }

    private static function xmlCompactName(string $v, array $nsMap): ?string
    {
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9]*):([a-zA-Z][A-Za-z0-9._-]*)$/', $v, $m)) {
            if (isset($nsMap[$m[1]])) return $m[1] . ':' . $m[2];
        }
        foreach ($nsMap as $p => $iri) {
            if (str_starts_with($v, $iri)) {
                $local = substr($v, strlen($iri));
                if (preg_match('/^[a-zA-Z][A-Za-z0-9._-]*$/', $local)) return $p . ':' . $local;
            }
        }
        return null;
    }

    private static function expand(string $v, array $nsMap): string
    {
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9]*):(.+)$/', $v, $m) && isset($nsMap[$m[1]])) {
            return $nsMap[$m[1]] . $m[2];
        }
        return $v;
    }
}
