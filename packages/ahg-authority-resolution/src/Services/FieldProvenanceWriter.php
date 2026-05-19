<?php

/**
 * FieldProvenanceWriter - Heratio
 *
 * Task 6 sibling of DecisionProvenanceWriter. Emits per-field provenance
 * to Fuseki when a new authority record is created via the "Create new
 * authority" sub-workflow. Each pre-filled field becomes one reified
 * RDF-Star assertion carrying source URL, retrieval date, and licence
 * info - that's the chain a future FOIA / audit query walks.
 *
 * Example output (one field):
 *
 *   << <https://heratio.theahg.co.za/actor/123>
 *        auth_res:hasField "authorized_form_of_name" >>
 *       auth_res:fieldValue "Nelson Mandela" ;
 *       prov:wasDerivedFrom <https://viaf.org/viaf/12345/> ;
 *       prov:generatedAtTime "2026-05-19T12:00:00Z"^^xsd:dateTime ;
 *       auth_res:source "viaf" ;
 *       auth_res:licence "CC0-1.0" ;
 *       auth_res:licenceUrl <https://creativecommons.org/publicdomain/zero/1.0/> .
 *
 * Distinct named graph from the decisions graph so SPARQL queries can
 * target one or the other (urn:heratio:auth-res:graph:field-provenance
 * vs. urn:heratio:auth-res:graph:decisions).
 *
 * Best-effort: failures are logged but do not throw - the SQL insert of
 * the new authority record is durable; provenance can be replayed.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services;

use AhgRic\Services\SparqlUpdateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FieldProvenanceWriter
{
    public const DEFAULT_GRAPH_URI = 'urn:heratio:auth-res:graph:field-provenance';
    public const NS_PROV = 'http://www.w3.org/ns/prov#';
    public const NS_AUTH_RES = 'https://heratio.theahg.co.za/ontology/auth-res#';

    public function __construct(private SparqlUpdateService $sparql) {}

    /**
     * Write provenance for every pre-filled field on a newly created
     * authority record. `$prefillProvenance` is the `_provenance` map
     * produced by PrefillEngine::merge_fields() and `$mergedFields` is
     * the corresponding value map (without the `_provenance` key).
     *
     * @param array<string,string|null|int|float> $mergedFields
     * @param array<string,array{source:string,uri:?string,licence:?string,licence_url:?string,retrieved_at:?string}> $prefillProvenance
     * @return array{ok:bool, triple_count:int, graph?:string, turtle?:string, status?:int, error?:string}
     */
    public function writeForCreation(
        int $authorityId,
        string $authorityType,
        array $mergedFields,
        array $prefillProvenance,
        ?string $graphUri = null
    ): array {
        if (!in_array($authorityType, ['actor', 'term'], true)) {
            return ['ok' => false, 'triple_count' => 0, 'error' => "authorityType must be 'actor' or 'term'"];
        }

        $graphUri = $graphUri ?: $this->loadGraphUri();
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');
        $subjectUri = $authorityType === 'actor'
            ? "<{$base}/actor/{$authorityId}>"
            : "<{$base}/place/{$authorityId}>";

        // Drop the internal _provenance map before iterating real fields.
        unset($mergedFields['_provenance']);

        $tripleCount = 0;
        $turtleChunks = [];

        foreach ($mergedFields as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $prov = $prefillProvenance[$field] ?? null;
            if (!is_array($prov)) {
                continue;
            }
            $turtleChunks[] = $this->buildOneFieldTurtle(
                $subjectUri,
                (string) $field,
                $this->stringify($value),
                $prov
            );
            $tripleCount++;
        }

        if ($tripleCount === 0) {
            return ['ok' => true, 'triple_count' => 0, 'graph' => $graphUri];
        }

        $turtleBody = implode("\n\n", $turtleChunks);
        $sparqlUpdate = $this->buildPrefixes()
            . "\nINSERT DATA {\n  GRAPH <{$graphUri}> {\n{$turtleBody}\n  }\n}";

        try {
            $result = $this->sparql->executeUpdate($sparqlUpdate);
        } catch (\Throwable $e) {
            Log::warning('FieldProvenanceWriter: sparql threw', [
                'authority_id' => $authorityId,
                'error' => $e->getMessage(),
            ]);
            return [
                'ok' => false,
                'triple_count' => $tripleCount,
                'graph' => $graphUri,
                'turtle' => $turtleBody,
                'error' => $e->getMessage(),
            ];
        }

        if ($result['ok'] ?? false) {
            return [
                'ok' => true,
                'triple_count' => $tripleCount,
                'graph' => $graphUri,
                'turtle' => $turtleBody,
                'status' => $result['status'] ?? 200,
            ];
        }

        Log::warning('FieldProvenanceWriter::writeForCreation failed', [
            'authority_id' => $authorityId,
            'status' => $result['status'] ?? null,
            'error' => $result['error'] ?? null,
        ]);

        return [
            'ok' => false,
            'triple_count' => $tripleCount,
            'graph' => $graphUri,
            'turtle' => $turtleBody,
            'status' => $result['status'] ?? 0,
            'error' => $result['error'] ?? 'unknown',
        ];
    }

    private function buildOneFieldTurtle(string $subjectUri, string $field, string $value, array $prov): string
    {
        $assertion = "{$subjectUri} auth_res:hasField " . $this->literal($field);
        $reified = "<< {$assertion} >>";

        $sourceUri = isset($prov['uri']) && is_string($prov['uri']) && trim($prov['uri']) !== ''
            ? '<' . $prov['uri'] . '>'
            : null;
        $sourceName = (string) ($prov['source'] ?? 'unknown');
        $licence = isset($prov['licence']) ? (string) $prov['licence'] : null;
        $licenceUrl = isset($prov['licence_url']) && is_string($prov['licence_url']) && trim($prov['licence_url']) !== ''
            ? '<' . $prov['licence_url'] . '>'
            : null;
        $retrievedAt = isset($prov['retrieved_at']) ? (string) $prov['retrieved_at'] : gmdate('Y-m-d\TH:i:s\Z');

        $parts = [];
        $parts[] = 'auth_res:fieldValue ' . $this->literal($value);
        if ($sourceUri !== null) {
            $parts[] = 'prov:wasDerivedFrom ' . $sourceUri;
        }
        $parts[] = "prov:generatedAtTime \"{$retrievedAt}\"^^xsd:dateTime";
        $parts[] = 'auth_res:source ' . $this->literal($sourceName);
        if ($licence !== null && $licence !== '') {
            $parts[] = 'auth_res:licence ' . $this->literal($licence);
        }
        if ($licenceUrl !== null) {
            $parts[] = 'auth_res:licenceUrl ' . $licenceUrl;
        }

        return $reified . "\n    " . implode(" ;\n    ", $parts) . " .";
    }

    private function buildPrefixes(): string
    {
        return implode("\n", [
            'PREFIX prov: <' . self::NS_PROV . '>',
            'PREFIX auth_res: <' . self::NS_AUTH_RES . '>',
            'PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>',
        ]);
    }

    private function loadGraphUri(): string
    {
        try {
            $row = DB::table('ahg_settings')
                ->where('setting_key', 'lookup.field_provenance_graph_uri')
                ->value('setting_value');
            if (is_string($row) && trim($row) !== '') {
                return $row;
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return self::DEFAULT_GRAPH_URI;
    }

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
        return '"' . $escaped . '"';
    }

    private function stringify($value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return '';
    }
}
