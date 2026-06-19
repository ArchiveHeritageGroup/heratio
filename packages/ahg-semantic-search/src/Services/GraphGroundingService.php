<?php

/**
 * GraphGroundingService - Heratio
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

namespace AhgSemanticSearch\Services;

/**
 * GraphRAG disambiguation layer (#1320).
 *
 * Turns a resolved RiC entity IRI into an authoritative "grounding pack" -
 * type, label, dates, key properties, relations and provenance, read straight
 * from the Fuseki graph (the source-of-truth projection) via SparqlQueryService.
 * KM / an agent injects this into its grounding prompt so it resolves entities
 * to authority facts instead of guessing (vectors for retrieval + ontology
 * graph for disambiguation).
 *
 * Read-only. Never invents: a fact appears only if it is in the graph.
 */
class GraphGroundingService
{
    private const RDF_TYPE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

    /** Resolve the SPARQL primitive lazily so this package doesn't hard-fail when ahg-ric is absent. */
    private function sparql()
    {
        $cls = '\\AhgRic\\Services\\SparqlQueryService';
        return class_exists($cls) ? app($cls) : null;
    }

    /**
     * Build the disambiguation grounding pack for one entity IRI.
     *
     * @return array{iri:string,types:array,label:?string,dates:array,properties:array,relations:array,provenance:array}|null
     */
    public function groundEntity(string $uri): ?array
    {
        $sparql = $this->sparql();
        if ($sparql === null) {
            return null;
        }

        $entity = $sparql->getEntity($uri);
        if (! $entity || empty($entity['properties'])) {
            return null;
        }

        $types = [];
        $label = null;
        $dates = [];
        $props = [];
        $provenance = [];

        foreach ($entity['properties'] as $row) {
            $prop = $this->bindingValue($row['property'] ?? null);
            $val = $this->bindingValue($row['value'] ?? null);
            if ($prop === '' || $val === '') {
                continue;
            }

            if ($prop === self::RDF_TYPE) {
                $types[] = $this->localName($val);
            } elseif ($this->propMatches($prop, ['name', 'title', 'label'])) {
                $label = $label ?? $val;
            } elseif ($this->propMatches($prop, ['date', 'beginning', 'end', 'created'])) {
                $dates[] = $this->localName($prop) . ': ' . $val;
            } elseif ($this->propMatches($prop, ['prov', 'inference', 'receipt', 'wasgeneratedby', 'wasattributedto'])) {
                $provenance[] = $this->localName($prop) . ': ' . $val;
            } else {
                $props[$this->localName($prop)] = $val;
            }
        }

        return [
            'iri' => $uri,
            'types' => array_values(array_unique($types)),
            'label' => $label,
            'dates' => array_values(array_unique($dates)),
            'properties' => $props,
            'relations' => $this->summariseRelations($sparql->getRelationships($uri)),
            'provenance' => $provenance,
        ];
    }

    /**
     * Render one or more grounding packs into a compact, prompt-ready block.
     * The framing tells the model these are authoritative facts to ground on.
     */
    public function groundingText(array $packs): string
    {
        $packs = array_values(array_filter($packs));
        if (empty($packs)) {
            return '';
        }

        $out = ["Authoritative facts from the archive's knowledge graph "
            . '(use these to disambiguate; do not contradict or invent beyond them):'];
        foreach ($packs as $p) {
            $line = '- ' . ($p['label'] ?? $p['iri']);
            if (! empty($p['types'])) {
                $line .= ' (' . implode('/', $p['types']) . ')';
            }
            $line .= ' [' . $p['iri'] . ']';
            if (! empty($p['dates'])) {
                $line .= '; ' . implode(', ', $p['dates']);
            }
            if (! empty($p['relations'])) {
                $line .= '; relations: ' . implode(', ', array_slice($p['relations'], 0, 8));
            }
            if (! empty($p['provenance'])) {
                $line .= '; provenance: ' . implode(', ', $p['provenance']);
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /** Flatten outgoing/incoming relationships into short "predicate -> target" strings. */
    private function summariseRelations(array $rels): array
    {
        $summary = [];
        foreach (($rels['outgoing'] ?? []) as $r) {
            $p = $this->localName($this->bindingValue($r['property'] ?? null));
            $t = $this->bindingValue($r['target'] ?? null);
            if ($p !== '' && $t !== '' && $p !== 'type') {
                $summary[] = $p . ' -> ' . $this->localName($t);
            }
        }
        foreach (($rels['incoming'] ?? []) as $r) {
            $p = $this->localName($this->bindingValue($r['property'] ?? null));
            $s = $this->bindingValue($r['source'] ?? null);
            if ($p !== '' && $s !== '') {
                $summary[] = $this->localName($s) . ' -> ' . $p . ' -> (this)';
            }
        }
        return array_values(array_unique($summary));
    }

    private function propMatches(string $prop, array $needles): bool
    {
        $p = strtolower($prop);
        foreach ($needles as $n) {
            if (str_contains($p, $n)) {
                return true;
            }
        }
        return false;
    }

    /** Extract a SPARQL binding's value (results are {type,value} objects, not flat strings). */
    private function bindingValue($cell): string
    {
        if (is_array($cell)) {
            return (string) ($cell['value'] ?? '');
        }
        return $cell === null ? '' : (string) $cell;
    }

    /** Local name of an IRI (after the last # or /), else the value itself. */
    private function localName(string $iri): string
    {
        if (! preg_match('~^https?://|^urn:~', $iri)) {
            return $iri;
        }
        // Delimiter is ~ (NOT #) - the char class contains '#', which would
        // otherwise close a #-delimited pattern and silently break the regex.
        $tail = preg_replace('~^.*[#/:]~', '', $iri);
        return ($tail !== null && $tail !== '') ? $tail : $iri;
    }
}
