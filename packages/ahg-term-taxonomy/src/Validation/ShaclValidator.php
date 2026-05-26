<?php

/**
 * Heratio - Minimal SHACL validator for SKOS concept schemes.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems / The Archive and
 * Heritage Group (Pty) Ltd. Released under the AGPL-3.0-or-later licence.
 *
 * Implements four SKOS integrity rules from the SKOS Reference / SKOS-RC
 * profile, in pure PHP, against an in-memory concept graph produced by
 * the taxonomy exporter. Full pyshacl/easyrdf integration is queued for
 * #661 Phase 4. The vendored shapes file at resources/shacl/skos-shapes.ttl
 * is the canonical spec for what we will eventually evaluate via the full
 * SHACL engine; today it documents the rules + acts as a forward hook.
 *
 * Rules:
 *   S1. Every concept must have at least one skos:prefLabel.
 *   S2. No concept may have more than one prefLabel in the same language.
 *   S3. prefLabel + altLabel must not share the same literal+lang.
 *   S4. skos:broader must not produce a transitive cycle.
 */

namespace AhgTermTaxonomy\Validation;

class ShaclValidator
{
    /**
     * Validate a set of concepts in the same shape produced by the SKOS
     * exporter (see TermController::exportSkos). Each concept is an array:
     *   [
     *     'id' => int,
     *     'uri' => string,
     *     'prefLabel' => string,            // primary language only
     *     'broader' => ?string,
     *     'altLabels' => [['lang','name'],...],
     *     'hiddenLabels' => [['lang','name'],...],
     *     ...
     *   ]
     *
     * The exporter currently emits a single prefLabel per concept in the
     * requested locale. To honour S2 across multiple cultures the validator
     * accepts an optional `prefLabels` array (lang => literal) - the
     * artisan command builds this from term_i18n directly.
     *
     * @param  array<int, array<string, mixed>>  $concepts
     * @param  string  $primaryCulture  default culture for the bare prefLabel field
     * @return array<int, array{shape:string, concept:string, message:string, severity:string}>
     */
    public function validate(array $concepts, string $primaryCulture = 'en'): array
    {
        $reports = [];

        // S1, S2, S3 are local to a concept.
        foreach ($concepts as $c) {
            $uri = (string) ($c['uri'] ?? '#unknown');
            $prefLabel = trim((string) ($c['prefLabel'] ?? ''));

            // Build the multi-language prefLabel map.
            $prefByLang = [];
            if ($prefLabel !== '') {
                $prefByLang[$primaryCulture] = [$prefLabel];
            }
            if (! empty($c['prefLabels']) && is_array($c['prefLabels'])) {
                foreach ($c['prefLabels'] as $lang => $val) {
                    if (is_array($val)) {
                        foreach ($val as $v) {
                            $prefByLang[(string) $lang][] = (string) $v;
                        }
                    } else {
                        $prefByLang[(string) $lang][] = (string) $val;
                    }
                }
            }

            // S1 - at least one prefLabel.
            if (empty($prefByLang)) {
                $reports[] = [
                    'shape' => 'S1-MinPrefLabel',
                    'concept' => $uri,
                    'message' => 'Concept has no skos:prefLabel.',
                    'severity' => 'Violation',
                ];
            }

            // S2 - at most one prefLabel per language.
            foreach ($prefByLang as $lang => $values) {
                if (count($values) > 1) {
                    $reports[] = [
                        'shape' => 'S2-UniqueLangPrefLabel',
                        'concept' => $uri,
                        'message' => sprintf(
                            'Concept has %d skos:prefLabel literals in language "%s"; SKOS allows at most one.',
                            count($values),
                            $lang
                        ),
                        'severity' => 'Violation',
                    ];
                }
            }

            // S3 - prefLabel and altLabel must not share literal+lang.
            $altSet = [];
            foreach (($c['altLabels'] ?? []) as $alt) {
                $altSet[(string) $alt['lang']][trim((string) $alt['name'])] = true;
            }
            foreach ($prefByLang as $lang => $values) {
                foreach ($values as $val) {
                    if (isset($altSet[$lang][$val])) {
                        $reports[] = [
                            'shape' => 'S3-PrefAltDisjoint',
                            'concept' => $uri,
                            'message' => sprintf(
                                'Concept has identical skos:prefLabel and skos:altLabel "%s"@%s.',
                                $val,
                                $lang
                            ),
                            'severity' => 'Violation',
                        ];
                    }
                }
            }
        }

        // S4 - cycle detection across the concept set (transitive broader).
        $broaderEdges = [];
        $uriToConcept = [];
        foreach ($concepts as $c) {
            $uri = (string) ($c['uri'] ?? '');
            if ($uri === '') {
                continue;
            }
            $uriToConcept[$uri] = $c;
            if (! empty($c['broader'])) {
                $broaderEdges[$uri][] = (string) $c['broader'];
            }
        }
        foreach (array_keys($uriToConcept) as $startUri) {
            if ($this->hasBroaderCycle($startUri, $broaderEdges)) {
                $reports[] = [
                    'shape' => 'S4-NoBroaderCycles',
                    'concept' => $startUri,
                    'message' => 'Concept participates in a skos:broader transitive cycle.',
                    'severity' => 'Violation',
                ];
            }
        }

        return $reports;
    }

    /**
     * DFS-based cycle detection from a single starting URI.
     * Returns true if we can reach $start again by following skos:broader edges.
     *
     * @param  array<string, array<int, string>>  $edges
     */
    private function hasBroaderCycle(string $start, array $edges): bool
    {
        $stack = [$start];
        $visited = [];
        $first = true;
        while ($stack) {
            $cur = array_pop($stack);
            // The very first pop is the starting URI; we don't want to count
            // *entering* the start as a cycle - only re-entering it.
            if (! $first && $cur === $start) {
                return true;
            }
            $first = false;
            if (isset($visited[$cur])) {
                continue;
            }
            $visited[$cur] = true;
            foreach ($edges[$cur] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }
}
