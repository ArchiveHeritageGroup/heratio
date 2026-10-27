<?php

/**
 * XmpJsonLd - XMP packets (RDF/XML) to JSON-LD in the shape heratio#1557 sets
 * out for ISO 16684-3 (XMP Part 3), and back.
 *
 * The flat key/value XMP Heratio kept until now lost the namespaces, the
 * kind of each array, language alternatives, structures and qualifiers, so
 * it could not be turned back into XMP. This keeps all of them:
 *
 *   simple value          "dc:format": "image/jpeg"
 *   language-tagged       {"@value": "...", "@language": "en"}
 *   URI (rdf:resource)    {"@id": "https://..."}
 *   rdf:Bag (unordered)   {"@set": [...]}
 *   rdf:Seq (ordered)     {"@list": [...]}
 *   rdf:Alt               {"@type": "rdf:Alt", "rdf:_1": ..., "rdf:_2": ...}
 *                         (the RDF container form, so the order and the
 *                         x-default item survive)
 *   structure             nested object, properties keyed prefix:name
 *   qualified value       nested object with "rdf:value" and the qualifiers
 *
 * The document carries an @context for the namespaces present and an
 * explicit @id: inside an inventory or an API response "" would resolve
 * against the container, so the caller names the described file.
 *
 * ponytail: the forms above follow the issue's reading of Part 3 and the
 * XMP data model of ISO 16684-1; the Part 3 text (licensed per user) was not
 * available to check clause by clause. Change the shapes here, in one place,
 * if a conformance check disagrees.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMetadataExtraction\Xmp;

use DOMDocument;
use DOMElement;

final class XmpJsonLd
{
    public const RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    /** The JSON-LD media type for a standalone document. */
    public const MEDIA_TYPE = 'application/ld+json';

    /**
     * The XMP packet embedded in a file, as RDF/XML, or null when it has none
     * or exiftool is not available.
     */
    public static function packetFromFile(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $out = @shell_exec('exiftool -q -q -xmp -b '.escapeshellarg($path).' 2>/dev/null');

        return is_string($out) && str_contains($out, 'rdf:RDF') ? $out : null;
    }

    /** A packet as a JSON-LD document about $id, or null when it holds no properties. */
    public static function fromRdfXml(string $packet, string $id): ?array
    {
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($packet, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $ok) {
            return null;
        }
        $rdf = $dom->getElementsByTagNameNS(self::RDF, 'RDF')->item(0);
        if (! $rdf instanceof DOMElement) {
            return null;
        }

        $ctx = ['rdf' => self::RDF];
        $props = [];
        foreach (self::elements($rdf) as $desc) {
            if ($desc->namespaceURI === self::RDF && $desc->localName === 'Description') {
                $props = array_merge($props, self::nodeProperties($desc, $ctx));
            }
        }
        if ($props === []) {
            return null;
        }
        ksort($props, SORT_STRING);
        ksort($ctx, SORT_STRING);

        return ['@context' => $ctx, '@id' => $id] + $props;
    }

    /**
     * A JSON-LD document from fromRdfXml() back as an XMP packet. Several
     * documents can be combined with an @graph; this writes one description.
     */
    public static function toRdfXml(array $doc, string $about = ''): string
    {
        $ctx = $doc['@context'] ?? [];
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $meta = $dom->createElementNS('adobe:ns:meta/', 'x:xmpmeta');
        $dom->appendChild($meta);
        $rdf = $dom->createElementNS(self::RDF, 'rdf:RDF');
        $meta->appendChild($rdf);
        $desc = $dom->createElementNS(self::RDF, 'rdf:Description');
        $desc->setAttributeNS(self::RDF, 'rdf:about', $about);
        $rdf->appendChild($desc);
        foreach ($doc as $key => $value) {
            if (! str_starts_with((string) $key, '@')) {
                self::appendProperty($dom, $desc, (string) $key, $value, $ctx);
            }
        }

        return (string) $dom->saveXML($meta);
    }

    /**
     * Combine per-file documents into one: a single document as is, several
     * as an @graph under a merged @context.
     *
     * @param  array<int, array>  $docs
     */
    public static function combine(array $docs): ?array
    {
        $docs = array_values(array_filter($docs));
        if (count($docs) <= 1) {
            return $docs[0] ?? null;
        }
        $ctx = [];
        $graph = [];
        foreach ($docs as $doc) {
            $ctx += $doc['@context'] ?? [];
            unset($doc['@context']);
            $graph[] = $doc;
        }
        ksort($ctx, SORT_STRING);

        return ['@context' => $ctx, '@graph' => $graph];
    }

    /**
     * Remove every property whose local name starts with GPS (exif:GPSLatitude
     * and the rest), at any depth, for the privacy gate. Returns null when
     * nothing but the frame is left.
     */
    public static function stripGps(?array $doc): ?array
    {
        if ($doc === null) {
            return null;
        }
        $strip = function (array $node) use (&$strip): array {
            foreach ($node as $key => $value) {
                $local = str_contains((string) $key, ':') ? substr((string) $key, strpos((string) $key, ':') + 1) : (string) $key;
                if (! str_starts_with((string) $key, '@') && stripos($local, 'GPS') === 0) {
                    unset($node[$key]);
                } elseif (is_array($value) && $key !== '@context') {
                    $node[$key] = $strip($value);
                }
            }

            return $node;
        };
        $doc = $strip($doc);
        $hasProps = fn (array $n) => (bool) array_filter(array_keys($n), fn ($k) => ! str_starts_with((string) $k, '@'));
        if (isset($doc['@graph'])) {
            $doc['@graph'] = array_values(array_filter($doc['@graph'], $hasProps));

            return $doc['@graph'] === [] ? null : $doc;
        }

        return $hasProps($doc) ? $doc : null;
    }

    // ── RDF/XML in ─────────────────────────────────────────────────────

    /** Properties of a node: its property attributes and property elements. */
    private static function nodeProperties(DOMElement $el, array &$ctx): array
    {
        $out = [];
        foreach ($el->attributes ?? [] as $attr) {
            if ($attr->namespaceURI === self::RDF || $attr->namespaceURI === self::XML || $attr->namespaceURI === null) {
                continue; // rdf:about / parseType / resource, xml:lang, unqualified
            }
            $out[self::key($attr->prefix, $attr->localName, $attr->namespaceURI, $ctx)] = $attr->value;
        }
        foreach (self::elements($el) as $child) {
            $out[self::key($child->prefix, $child->localName, (string) $child->namespaceURI, $ctx)] = self::value($child, $ctx);
        }
        ksort($out, SORT_STRING);

        return $out;
    }

    /** The value of a property element (or an rdf:li). */
    private static function value(DOMElement $el, array &$ctx): mixed
    {
        $lang = $el->hasAttributeNS(self::XML, 'lang') ? $el->getAttributeNS(self::XML, 'lang') : null;
        if ($el->hasAttributeNS(self::RDF, 'resource')) {
            return ['@id' => $el->getAttributeNS(self::RDF, 'resource')];
        }
        if ($el->getAttributeNS(self::RDF, 'parseType') === 'Resource') {
            return self::qualified(self::nodeProperties($el, $ctx), $lang);
        }

        $children = self::elements($el);
        if (count($children) === 1 && $children[0]->namespaceURI === self::RDF) {
            $c = $children[0];
            // By reference: namespaces first seen inside an item belong in the context too.
            $items = function () use ($c, &$ctx) {
                $out = [];
                foreach (self::elements($c) as $li) {
                    if ($li->namespaceURI === self::RDF && $li->localName === 'li') {
                        $out[] = self::value($li, $ctx);
                    }
                }

                return $out;
            };
            switch ($c->localName) {
                case 'Bag':
                    return ['@set' => $items()];
                case 'Seq':
                    return ['@list' => $items()];
                case 'Alt':
                    $alt = ['@type' => 'rdf:Alt'];
                    foreach ($items() as $i => $item) {
                        $alt['rdf:_'.($i + 1)] = $item;
                    }

                    return $alt;
                case 'Description':
                    return self::qualified(self::nodeProperties($c, $ctx), $lang);
            }
        }
        if ($children !== []) {
            return self::qualified(self::nodeProperties($el, $ctx), $lang);
        }
        // An empty element whose attributes are the fields of a structure.
        $fields = self::nodeProperties($el, $ctx);
        if ($fields !== []) {
            return self::qualified($fields, $lang);
        }
        $text = $el->textContent;

        return $lang !== null ? ['@value' => $text, '@language' => $lang] : $text;
    }

    /** A structure, or a qualified value when it has rdf:value. */
    private static function qualified(array $node, ?string $lang): array
    {
        if ($lang !== null && isset($node['rdf:value']) && is_string($node['rdf:value'])) {
            $node['rdf:value'] = ['@value' => $node['rdf:value'], '@language' => $lang];
        }

        return $node;
    }

    /** prefix:name for a node, recording the namespace in the context. */
    private static function key(?string $prefix, string $local, string $ns, array &$ctx): string
    {
        $prefix = $prefix ?: 'ns';
        $base = $prefix;
        for ($i = 2; isset($ctx[$prefix]) && $ctx[$prefix] !== $ns; $i++) {
            $prefix = $base.$i; // the same prefix bound to another namespace
        }
        $ctx[$prefix] = $ns;

        return $prefix.':'.$local;
    }

    /** @return array<int, DOMElement> */
    private static function elements(DOMElement $el): array
    {
        $out = [];
        foreach ($el->childNodes as $n) {
            if ($n instanceof DOMElement) {
                $out[] = $n;
            }
        }

        return $out;
    }

    // ── RDF/XML out ────────────────────────────────────────────────────

    private static function appendProperty(DOMDocument $dom, DOMElement $parent, string $key, mixed $value, array $ctx): void
    {
        [$prefix] = explode(':', $key, 2);
        $el = $dom->createElementNS($ctx[$prefix] ?? self::RDF, $key);
        self::fill($dom, $el, $value, $ctx);
        $parent->appendChild($el);
    }

    private static function fill(DOMDocument $dom, DOMElement $el, mixed $value, array $ctx): void
    {
        if (! is_array($value)) {
            $el->appendChild($dom->createTextNode((string) $value));

            return;
        }
        if (array_keys($value) === ['@id']) {
            $el->setAttributeNS(self::RDF, 'rdf:resource', (string) $value['@id']);

            return;
        }
        if (array_key_exists('@value', $value)) {
            $el->appendChild($dom->createTextNode((string) $value['@value']));
            if (isset($value['@language'])) {
                $el->setAttributeNS(self::XML, 'xml:lang', (string) $value['@language']);
            }

            return;
        }
        $container = match (true) {
            isset($value['@set']) => ['Bag', $value['@set']],
            isset($value['@list']) => ['Seq', $value['@list']],
            ($value['@type'] ?? null) === 'rdf:Alt' => ['Alt', self::altItems($value)],
            default => null,
        };
        if ($container !== null) {
            $box = $dom->createElementNS(self::RDF, 'rdf:'.$container[0]);
            foreach ($container[1] as $item) {
                $li = $dom->createElementNS(self::RDF, 'rdf:li');
                self::fill($dom, $li, $item, $ctx);
                $box->appendChild($li);
            }
            $el->appendChild($box);

            return;
        }
        // Structure or qualified value.
        $el->setAttributeNS(self::RDF, 'rdf:parseType', 'Resource');
        foreach ($value as $k => $v) {
            self::appendProperty($dom, $el, (string) $k, $v, $ctx);
        }
    }

    /** rdf:_1, rdf:_2 ... in numeric order. */
    private static function altItems(array $alt): array
    {
        $items = [];
        foreach ($alt as $k => $v) {
            if (preg_match('/^rdf:_(\d+)$/', (string) $k, $m)) {
                $items[(int) $m[1]] = $v;
            }
        }
        ksort($items);

        return array_values($items);
    }
}
