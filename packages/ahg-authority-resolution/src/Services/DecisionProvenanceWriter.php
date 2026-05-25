<?php

/**
 * DecisionProvenanceWriter - Service for Heratio
 *
 * Writes RDF-Star provenance for every authority-resolution decision to the
 * Heratio Fuseki dataset (named graph configurable, default
 * urn:heratio:auth-res:graph:decisions). The reified assertion captures the
 * outcome (mention -> linkedTo -> actor/term, or mention -> rejected, etc.),
 * with PROV-O triples annotating who decided, when, and our auth_res:*
 * predicates carrying the system's original confidence + the candidates
 * visible at decision time.
 *
 * Per Task 8 of the AHG Authority Resolution build: do NOT write a new
 * Fuseki client. We delegate every HTTP call to ahg-ric's SparqlUpdateService.
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

namespace AhgAuthorityResolution\Services;

use AhgRic\Services\SparqlUpdateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DecisionProvenanceWriter
{
    public const DEFAULT_GRAPH_URI = 'urn:heratio:auth-res:graph:decisions';

    public const NS_PROV = 'http://www.w3.org/ns/prov#';

    public const NS_AUTH_RES = 'https://heratio.theahg.co.za/ontology/auth-res#';

    private const PLACE_TYPES = ['GPE', 'LOC', 'PLACE', 'ISAD_PLACE'];

    public function __construct(
        private SparqlUpdateService $sparql,
    ) {}

    /**
     * Write RDF-Star provenance for a decision. Updates ahg_mention_decision.fuseki_graph_uri on success.
     *
     * @return array{ok:bool, graph?:string, turtle?:string, status?:int, error?:string}
     */
    public function write(int $decisionId, ?string $graphUri = null): array
    {
        $decision = $this->loadDecision($decisionId);
        if (! $decision) {
            return ['ok' => false, 'error' => "decision #{$decisionId} not found"];
        }

        $graphUri = $graphUri ?: $this->loadGraphUri();
        $turtle = $this->buildTurtle($decision);
        // SPARQL UPDATE expects PREFIX declarations outside the INSERT DATA wrapper,
        // not Turtle's @prefix syntax. executeUpdate takes the full statement;
        // insertRdfStar only wraps in INSERT DATA which would put @prefix in the wrong place.
        $sparqlUpdate = $this->buildPrefixes()."\nINSERT DATA {\n  GRAPH <{$graphUri}> {\n{$turtle}\n  }\n}";

        $result = $this->sparql->executeUpdate($sparqlUpdate);

        if ($result['ok'] ?? false) {
            DB::table('ahg_mention_decision')
                ->where('id', $decisionId)
                ->update(['fuseki_graph_uri' => $graphUri]);

            return [
                'ok' => true,
                'graph' => $graphUri,
                'turtle' => $turtle,
                'status' => $result['status'] ?? 200,
            ];
        }

        Log::warning('DecisionProvenanceWriter::write failed', [
            'decision_id' => $decisionId,
            'status' => $result['status'] ?? null,
            'error' => $result['error'] ?? null,
        ]);

        return [
            'ok' => false,
            'graph' => $graphUri,
            'turtle' => $turtle,
            'status' => $result['status'] ?? 0,
            'error' => $result['error'] ?? 'unknown',
        ];
    }

    /**
     * Build the turtle-star body (without INSERT DATA / GRAPH wrappers, which
     * SparqlUpdateService handles).
     */
    public function buildTurtle(object $decision): string
    {
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');

        $mentionUri = "<{$base}/auth-res/mention/{$decision->mention_id}>";
        $userUri = "<{$base}/user/{$decision->archivist_user_id}>";
        $assertion = $this->buildAssertion($decision, $mentionUri, $base);
        $timestamp = $this->formatTimestamp((string) $decision->decided_at);

        $reified = "<< {$assertion} >>";

        $triples = [];
        $triples[] = "{$reified}";
        $triples[] = "    prov:wasAttributedTo {$userUri} ;";
        $triples[] = "    prov:generatedAtTime \"{$timestamp}\"^^xsd:dateTime ;";
        $triples[] = '    auth_res:decisionType '.$this->literal($decision->decision_type).' ;';
        $triples[] = '    auth_res:mentionValue '.$this->literal($decision->entity_value ?? '').' ;';
        $triples[] = '    auth_res:mentionEntityType '.$this->literal($decision->entity_type ?? '').($decision->original_system_top_score !== null ? ' ;' : ' .');

        if ($decision->original_system_top_score !== null) {
            $triples[] = "    auth_res:originalSystemConfidence \"{$decision->original_system_top_score}\"^^xsd:decimal".($decision->fuseki_graph_uri || true ? ' .' : ' .');
        }

        // Candidate annotations
        $candidates = $this->decodeJson($decision->candidates_visible_snapshot ?? null);
        $candidatesTurtle = '';
        if (is_array($candidates) && ! empty($candidates)) {
            $candidateUris = [];
            foreach ($candidates as $c) {
                $cid = $c['candidate_id'] ?? $c['id'] ?? null;
                if (! $cid) {
                    continue;
                }
                $candidateUris[] = "<{$base}/auth-res/candidate/{$cid}>";
            }
            if (! empty($candidateUris)) {
                $triples[count($triples) - 1] = rtrim($triples[count($triples) - 1], '.').';';
                $triples[] = '    auth_res:hadCandidate '.implode(', ', $candidateUris).' .';
            }

            $candidatesTurtle = "\n".$this->buildCandidateTriples($candidates, $base);
        }

        // Evidence snapshot (frozen JSON literal, if present)
        $evidence = $this->decodeJson($decision->evidence_snapshot ?? null);
        $evidenceTurtle = '';
        if (is_array($evidence) && ! empty($evidence)) {
            $evidenceTurtle = "\n{$reified}\n    auth_res:evidenceSnapshot ".$this->literal(json_encode($evidence, JSON_UNESCAPED_UNICODE)).' .';
        }

        $body = implode("\n", $triples).$candidatesTurtle.$evidenceTurtle;

        // No @prefix here — buildSparqlUpdate emits PREFIX (SPARQL syntax) outside
        // the INSERT DATA wrapper. Returning just the triples body.
        return $body;
    }

    private function buildAssertion(object $decision, string $mentionUri, string $base): string
    {
        switch ($decision->decision_type) {
            case 'link':
            case 'link_different':
                $authorityUri = $this->authorityUri($decision, $base);
                $predicate = $decision->decision_type === 'link_different'
                    ? 'auth_res:linkedToDifferent'
                    : 'auth_res:linkedTo';

                return "{$mentionUri} {$predicate} {$authorityUri}";
            case 'create_new':
                $authorityUri = $this->authorityUri($decision, $base);

                return "{$mentionUri} auth_res:linkedToNew {$authorityUri}";
            case 'park':
                return "{$mentionUri} auth_res:parked \"true\"^^xsd:boolean";
            case 'reject':
                return "{$mentionUri} auth_res:rejected \"true\"^^xsd:boolean";
            default:
                return "{$mentionUri} auth_res:decision ".$this->literal($decision->decision_type);
        }
    }

    private function authorityUri(object $decision, string $base): string
    {
        $id = $decision->chosen_authority_id;
        if ($id === null) {
            return "<{$base}/auth-res/null-authority>";
        }
        if (in_array($decision->entity_type, self::PLACE_TYPES, true)) {
            return "<{$base}/place/{$id}>";
        }

        return "<{$base}/actor/{$id}>";
    }

    private function buildCandidateTriples(array $candidates, string $base): string
    {
        $lines = [];
        foreach ($candidates as $c) {
            $cid = $c['candidate_id'] ?? $c['id'] ?? null;
            if (! $cid) {
                continue;
            }
            $uri = "<{$base}/auth-res/candidate/{$cid}>";
            $parts = [];
            if (isset($c['rank']) || isset($c['rank_position'])) {
                $rank = (int) ($c['rank'] ?? $c['rank_position']);
                $parts[] = "auth_res:rank \"{$rank}\"^^xsd:integer";
            }
            if (! empty($c['display_name'])) {
                $parts[] = 'auth_res:displayName '.$this->literal((string) $c['display_name']);
            }
            if (! empty($c['source']) || ! empty($c['candidate_source'])) {
                $src = (string) ($c['source'] ?? $c['candidate_source']);
                $parts[] = 'auth_res:source '.$this->literal($src);
            }
            if (isset($c['name_similarity_score']) || isset($c['nameSimilarity'])) {
                $score = (float) ($c['name_similarity_score'] ?? $c['nameSimilarity']);
                $parts[] = "auth_res:nameSimilarity \"{$score}\"^^xsd:decimal";
            }
            if (empty($parts)) {
                continue;
            }
            $lines[] = $uri."\n    ".implode(" ;\n    ", $parts).' .';
        }

        return implode("\n\n", $lines);
    }

    private function buildPrefixes(): string
    {
        // SPARQL PREFIX syntax (no @, no trailing dot). Different from Turtle's
        // @prefix. Used outside the INSERT DATA wrapper in buildSparqlUpdate.
        return implode("\n", [
            'PREFIX prov: <'.self::NS_PROV.'>',
            'PREFIX auth_res: <'.self::NS_AUTH_RES.'>',
            'PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>',
        ]);
    }

    private function loadDecision(int $decisionId): ?object
    {
        return DB::table('ahg_mention_decision as d')
            ->join('ahg_mention as m', 'm.id', '=', 'd.mention_id')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('d.id', $decisionId)
            ->first([
                'd.id',
                'd.mention_id',
                'd.decision_type',
                'd.chosen_candidate_id',
                'd.chosen_authority_id',
                'd.original_system_top_score',
                'd.archivist_user_id',
                'd.decided_at',
                'd.fuseki_graph_uri',
                'd.evidence_snapshot',
                'd.candidates_visible_snapshot',
                'm.entity_type',
                'n.entity_value',
            ]);
    }

    private function loadGraphUri(): string
    {
        try {
            $row = DB::table('ahg_settings')
                ->where('setting_key', 'authority_resolution.decisions_graph_uri')
                ->value('setting_value');
            if (is_string($row) && trim($row) !== '') {
                return $row;
            }
        } catch (\Throwable $e) {
            // fall through to default
        }

        return self::DEFAULT_GRAPH_URI;
    }

    private function decodeJson(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function formatTimestamp(string $mysqlDateTime): string
    {
        try {
            $dt = new \DateTimeImmutable($mysqlDateTime, new \DateTimeZone('UTC'));

            return $dt->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable $e) {
            return gmdate('Y-m-d\TH:i:s\Z');
        }
    }

    /**
     * Escape a string as a turtle-safe literal. Handles UTF-8 directly (turtle
     * literals support multi-byte natively).
     */
    private function literal(?string $s): string
    {
        if ($s === null) {
            return '""';
        }
        $escaped = str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $s
        );

        return '"'.$escaped.'"';
    }
}
