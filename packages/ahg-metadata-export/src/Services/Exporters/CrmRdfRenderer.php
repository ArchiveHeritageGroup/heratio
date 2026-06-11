<?php

/**
 * CrmRdfRenderer - shared CIDOC-CRM node-bag rendering for the metadata-export
 * CRM serializers (records / actors / terms-and-places).
 *
 * The records exporter (CidocCrmSerializer) shipped first with its own private
 * renderTurtle()/renderRdfXml() pair and a fixed node-bag shape:
 *
 *     each node = [uri, type-curie, [ [predicate-curie, value, kind] ... ] ]
 *
 * where kind is one of:
 *   'iri'   - object is an absolute IRI
 *   'lang'  - language-tagged literal ("..."@culture)
 *   'plain' - plain literal ("...")
 *   'date'  - xsd:date literal
 *
 * To let the actor and term/place serializers emit byte-identical Turtle and
 * RDF/XML for the same node bag - so the three CRM surfaces cannot drift in
 * their serialisation - that exact rendering is lifted here verbatim and shared
 * via a trait. The original records serializer is intentionally left untouched
 * (it keeps its own private copies); this trait is consumed only by the new
 * actor + term/place serializers, which therefore share one rendering code path
 * with each other and produce output structurally identical to the records
 * exporter.
 *
 * The namespace constants are duplicated from CidocCrmSerializer on purpose: the
 * records exporter must not be edited, and a trait cannot pull constants from an
 * unrelated class. The metadata-export test harness asserts them equal to
 * CidocCrmSerializer::NS_* so any future divergence is caught.
 *
 * Phase of issue #1197 (Unified G/L/A/M knowledge graph - RiC + CIDOC-CRM + KM).
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

namespace AhgMetadataExport\Services\Exporters;

trait CrmRdfRenderer
{
    /** Namespaces - identical to CidocCrmSerializer::NS_* so the actor / term
     *  documents share prefixes with the records export. */
    public const NS_RDF  = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    public const NS_RDFS = 'http://www.w3.org/2000/01/rdf-schema#';
    public const NS_XSD  = 'http://www.w3.org/2001/XMLSchema#';
    public const NS_CRM  = 'http://www.cidoc-crm.org/cidoc-crm/';
    public const NS_ECRM = 'http://erlangen-crm.org/current/';

    /** Output formats accepted by the serialize* entry points. */
    public const FORMAT_TURTLE = 'turtle';
    public const FORMAT_RDFXML = 'rdfxml';

    /**
     * Render a format-neutral node bag (['nodes' => [...], 'culture' => 'en'])
     * in the requested format. Mirrors CidocCrmSerializer::serializeRecord's
     * branch so all three CRM surfaces dispatch identically.
     */
    protected function render(array $bag, string $format): string
    {
        if ($format === self::FORMAT_RDFXML) {
            return $this->renderRdfXml($bag);
        }

        return $this->renderTurtle($bag);
    }

    // -----------------------------------------------------------------
    // Turtle output (verbatim from CidocCrmSerializer).
    // -----------------------------------------------------------------

    protected function renderTurtle(array $bag): string
    {
        $culture = $bag['culture'];
        $ttl  = '@prefix rdf: <' . self::NS_RDF . "> .\n";
        $ttl .= '@prefix rdfs: <' . self::NS_RDFS . "> .\n";
        $ttl .= '@prefix xsd: <' . self::NS_XSD . "> .\n";
        $ttl .= '@prefix crm: <' . self::NS_CRM . "> .\n";
        $ttl .= '@prefix ecrm: <' . self::NS_ECRM . "> .\n\n";

        foreach ($bag['nodes'] as [$uri, $typeCurie, $props]) {
            $ttl .= '<' . $uri . '> a ' . $typeCurie;
            foreach ($props as [$pred, $value, $kind]) {
                $ttl .= ' ;' . "\n" . '  ' . $pred . ' ' . $this->ttlValue($value, $kind, $culture);
            }
            $ttl .= " .\n\n";
        }

        return $ttl;
    }

    protected function ttlValue(string $value, string $kind, string $culture): string
    {
        switch ($kind) {
            case 'iri':
                return '<' . $value . '>';
            case 'date':
                return '"' . addcslashes($value, "\\\"\n\r") . '"^^xsd:date';
            case 'lang':
                return '"' . addcslashes($value, "\\\"\n\r") . '"@' . $culture;
            case 'plain':
            default:
                return '"' . addcslashes($value, "\\\"\n\r") . '"';
        }
    }

    // -----------------------------------------------------------------
    // RDF/XML output (verbatim from CidocCrmSerializer).
    // -----------------------------------------------------------------

    protected function renderRdfXml(array $bag): string
    {
        $culture = $bag['culture'];
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rdf:RDF';
        $xml .= ' xmlns:rdf="' . self::NS_RDF . '"';
        $xml .= ' xmlns:rdfs="' . self::NS_RDFS . '"';
        $xml .= ' xmlns:xsd="' . self::NS_XSD . '"';
        $xml .= ' xmlns:crm="' . self::NS_CRM . '"';
        $xml .= ' xmlns:ecrm="' . self::NS_ECRM . '"';
        $xml .= '>' . "\n";

        foreach ($bag['nodes'] as [$uri, $typeCurie, $props]) {
            $xml .= '  <rdf:Description rdf:about="' . $this->escAttr($uri) . '">' . "\n";
            $xml .= '    <rdf:type rdf:resource="' . $this->escAttr($this->expand($typeCurie)) . '"/>' . "\n";
            foreach ($props as [$pred, $value, $kind]) {
                $tag = $this->localTag($pred);
                if ($kind === 'iri') {
                    $xml .= '    <' . $tag . ' rdf:resource="' . $this->escAttr($value) . '"/>' . "\n";
                } elseif ($kind === 'date') {
                    $xml .= '    <' . $tag . ' rdf:datatype="' . $this->escAttr(self::NS_XSD . 'date') . '">'
                        . $this->escXmlValue($value) . '</' . $tag . '>' . "\n";
                } elseif ($kind === 'lang') {
                    $xml .= '    <' . $tag . ' xml:lang="' . $this->escAttr($culture) . '">'
                        . $this->escXmlValue($value) . '</' . $tag . '>' . "\n";
                } else {
                    $xml .= '    <' . $tag . '>' . $this->escXmlValue($value) . '</' . $tag . '>' . "\n";
                }
            }
            $xml .= '  </rdf:Description>' . "\n";
        }

        $xml .= '</rdf:RDF>' . "\n";

        return $xml;
    }

    // -----------------------------------------------------------------
    // Helpers (verbatim from CidocCrmSerializer).
    // -----------------------------------------------------------------

    /** Map a 'prefix:Local' CURIE used for predicates to a QName tag. The
     *  CIDOC P-numbers contain no characters illegal in an XML local name. */
    protected function localTag(string $curie): string
    {
        return $curie;
    }

    /** Expand a CURIE into an absolute IRI for rdf:type objects. */
    protected function expand(string $curie): string
    {
        if (str_starts_with($curie, 'crm:')) {
            return self::NS_CRM . substr($curie, 4);
        }
        if (str_starts_with($curie, 'ecrm:')) {
            return self::NS_ECRM . substr($curie, 5);
        }
        if (str_starts_with($curie, 'rdfs:')) {
            return self::NS_RDFS . substr($curie, 5);
        }
        return $curie;
    }

    protected function escAttr(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function escXmlValue(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
