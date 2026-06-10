<?php

/**
 * CrmGraphSyncService - push CIDOC-CRM triples for a record into Fuseki
 *
 * Completes the "unified knowledge graph" loop for issue #1197 / #1214:
 * the museum CIDOC export (MuseumController::cidocExportDownload) already
 * produces RDF/Turtle per object; this service puts that Turtle into the
 * shared Fuseki triple store as a per-object **named graph**, so museum /
 * information-object records become queryable as CIDOC-CRM alongside the
 * RiC-native entities that RicEntityService already syncs.
 *
 * Named-graph URI scheme (mirrors RicEntityService::GRAPH_PREFIX
 * 'urn:ahg:ric:<type>:<id>'):
 *
 *     urn:ahg:crm:<class-or-record>:<objectId>
 *
 * where <class-or-record> is a slugified form of the CRM record class
 * (e.g. 'e22_human-made_object' for a museum object typed
 * crm:E22_Human-Made_Object, or 'record' for the archival E73 default).
 * One object per graph keeps deletes surgical: DROP GRAPH removes
 * everything we wrote for the object.
 *
 * Replace-graph (idempotent) write:
 *   DROP SILENT GRAPH <uri> ;
 *   INSERT DATA { GRAPH <uri> { ... } }
 * is issued in a single SparqlUpdateService::executeUpdate() call so a
 * re-sync of the same object replaces its CRM graph and never duplicates.
 *
 * @prefix vs PREFIX: CrmSerializer's Turtle output leads with Turtle
 * '@prefix crm: <…> .' directives, which are ILLEGAL inside SPARQL
 * 'INSERT DATA { … }'. As documented in
 * docs/reference/auth-res-provenance-fuseki.md (the "@prefix vs PREFIX"
 * wrinkle), we strip the @prefix lines from the body and re-emit them as
 * a SPARQL 'PREFIX …' prologue on the UPDATE - hence executeUpdate() is
 * used here rather than FusekiSyncService::insertRdfStar()/insertData(),
 * which only wrap a bare INSERT DATA and cannot carry a prologue.
 *
 * Degrades gracefully: if Fuseki is unreachable (or returns non-2xx) the
 * methods log a warning and return false; they never throw, so an on-save
 * hook can call syncObject() without risking the relational write.
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

use AhgRic\Crm\CrmSerializer;
use AhgRic\Crm\RicToCrmMapper;
use Illuminate\Support\Facades\Log;

class CrmGraphSyncService
{
    /**
     * Named-graph prefix for CRM graphs. Parallels RicEntityService's
     * 'urn:ahg:ric:' so a single Fuseki dataset cleanly partitions
     * RiC-native graphs from the CRM bridge graphs by URI prefix.
     */
    private const DEFAULT_GRAPH_PREFIX = 'urn:ahg:crm:';

    private SparqlUpdateService $sparql;
    private CrmSerializer $serializer;

    public function __construct(?SparqlUpdateService $sparql = null, ?CrmSerializer $serializer = null)
    {
        $this->sparql = $sparql ?? app(SparqlUpdateService::class);
        $this->serializer = $serializer ?? new CrmSerializer();
    }

    /**
     * Config-driven graph prefix. Defaults to 'urn:ahg:crm:'. Reuses the
     * same config-then-default resolution style as the Fuseki endpoint
     * keys; override with ahg-ric.crm_graph_prefix if a deployment wants a
     * different namespace.
     */
    private function graphPrefix(): string
    {
        $p = (string) config('ahg-ric.crm_graph_prefix', self::DEFAULT_GRAPH_PREFIX);
        return $p !== '' ? $p : self::DEFAULT_GRAPH_PREFIX;
    }

    /**
     * Resolve the CRM record class for an object. Mirrors MuseumController:
     * museum objects are physical artefacts typed crm:E22_Human-Made_Object
     * (rico:Item). Callers may pass an explicit CRM CURIE (e.g.
     * 'crm:E22_Human-Made_Object') to override; null falls back to the
     * archival rico:Record -> E73 default inside CrmSerializer.
     */
    public function graphUri(int $objectId, ?string $recordClass = null): string
    {
        $segment = $this->classSegment($recordClass);
        return $this->graphPrefix() . $segment . ':' . $objectId;
    }

    /**
     * Slugify the CRM record class CURIE into a stable, URI-safe graph
     * segment. 'crm:E22_Human-Made_Object' -> 'e22_human-made_object';
     * null (archival default) -> 'record'.
     */
    private function classSegment(?string $recordClass): string
    {
        if ($recordClass === null || trim($recordClass) === '') {
            return 'record';
        }
        // Drop the 'crm:'/'rico:' CURIE prefix, lowercase, keep only
        // URN-safe chars (letters, digits, dash, underscore).
        $local = $recordClass;
        if (($pos = strpos($local, ':')) !== false) {
            $local = substr($local, $pos + 1);
        }
        $local = strtolower($local);
        $local = preg_replace('/[^a-z0-9_-]+/', '-', $local) ?? '';
        $local = trim($local, '-');
        return $local !== '' ? $local : 'record';
    }

    /**
     * Build the CRM Turtle for an object via CrmSerializer. Returns the
     * serializer's text/turtle output (empty string when the object is
     * missing in the requested culture).
     *
     * NB the real CrmSerializer signature is
     *   serializeRecord(int $objectId, string $culture, string $format, ?string $recordClass)
     * - we pin format = FORMAT_TURTLE here.
     */
    public function buildTurtle(int $objectId, ?string $recordClass = null, string $culture = 'en'): string
    {
        return $this->serializer->serializeRecord(
            $objectId,
            $culture,
            CrmSerializer::FORMAT_TURTLE,
            $recordClass
        );
    }

    /**
     * Sync one object's CRM graph into Fuseki (replace-graph; idempotent).
     *
     * @return bool true on a 2xx Fuseki write; false on empty serialization
     *              or any Fuseki failure (logged, never thrown).
     */
    public function syncObject(int $objectId, ?string $recordClass = null, string $culture = 'en'): bool
    {
        $graphUri = $this->graphUri($objectId, $recordClass);

        try {
            $turtle = $this->buildTurtle($objectId, $recordClass, $culture);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ric] CRM graph sync: serialization failed', [
                'object_id' => $objectId,
                'graph_uri' => $graphUri,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }

        if (trim($turtle) === '') {
            Log::warning('[ahg-ric] CRM graph sync: empty serialization, nothing to write', [
                'object_id' => $objectId,
                'graph_uri' => $graphUri,
            ]);
            return false;
        }

        $update = $this->buildReplaceGraphUpdate($graphUri, $turtle);

        try {
            $result = $this->sparql->executeUpdate($update);
        } catch (\Throwable $e) {
            // SparqlUpdateService already swallows curl/HTTP errors into a
            // result array, but guard against any unexpected throw so an
            // on-save hook is always safe.
            Log::warning('[ahg-ric] CRM graph sync: update threw', [
                'object_id' => $objectId,
                'graph_uri' => $graphUri,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }

        if (empty($result['ok'])) {
            Log::warning('[ahg-ric] CRM graph sync: Fuseki write failed', [
                'object_id' => $objectId,
                'graph_uri' => $graphUri,
                'status'    => $result['status'] ?? 0,
                'error'     => $result['error'] ?? 'unknown',
            ]);
            return false;
        }

        return true;
    }

    /**
     * Drop an object's CRM named graph from Fuseki.
     *
     * @return bool true on a 2xx response; false on any Fuseki failure
     *              (logged, never thrown).
     */
    public function deleteObject(int $objectId, ?string $recordClass = null): bool
    {
        $graphUri = $this->graphUri($objectId, $recordClass);

        try {
            $result = $this->sparql->executeUpdate("DROP SILENT GRAPH <{$graphUri}>");
        } catch (\Throwable $e) {
            Log::warning('[ahg-ric] CRM graph delete: update threw', [
                'object_id' => $objectId,
                'graph_uri' => $graphUri,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }

        if (empty($result['ok'])) {
            Log::warning('[ahg-ric] CRM graph delete: Fuseki drop failed', [
                'object_id' => $objectId,
                'graph_uri' => $graphUri,
                'status'    => $result['status'] ?? 0,
                'error'     => $result['error'] ?? 'unknown',
            ]);
            return false;
        }

        return true;
    }

    /**
     * Build the replace-graph SPARQL UPDATE for a Turtle body.
     *
     * 1. Strip the Turtle '@prefix … .' directives from the serializer
     *    body (illegal inside INSERT DATA).
     * 2. Re-emit them as a SPARQL 'PREFIX …' prologue.
     * 3. DROP SILENT the existing graph then INSERT DATA the fresh body,
     *    in one update, so the graph is atomically replaced (idempotent).
     */
    public function buildReplaceGraphUpdate(string $graphUri, string $turtle): string
    {
        [$prologue, $body] = $this->splitPrefixes($turtle);

        $update = '';
        if ($prologue !== '') {
            $update .= $prologue . "\n";
        }
        $update .= "DROP SILENT GRAPH <{$graphUri}> ;\n";
        $update .= "INSERT DATA { GRAPH <{$graphUri}> {\n";
        $update .= $body . "\n";
        $update .= "} }";

        return $update;
    }

    /**
     * Split a Turtle document into a SPARQL PREFIX prologue + the body
     * with the '@prefix' directives removed.
     *
     * Turtle: '@prefix crm: <http://…> .'
     * SPARQL: 'PREFIX crm: <http://…>'   (no leading @, no trailing dot)
     *
     * @return array{0:string,1:string} [prologue, body]
     */
    private function splitPrefixes(string $turtle): array
    {
        $prefixLines = [];
        $bodyLines   = [];

        foreach (preg_split('/\R/', $turtle) as $line) {
            if (preg_match('/^\s*@prefix\s+([^:]*:)\s*(<[^>]*>)\s*\.\s*$/', $line, $m)) {
                $prefixLines[] = 'PREFIX ' . trim($m[1]) . ' ' . trim($m[2]);
                continue;
            }
            $bodyLines[] = $line;
        }

        $prologue = implode("\n", $prefixLines);
        // Trim leading/trailing blank lines left behind after pulling the
        // prefix block, but keep internal structure intact.
        $body = trim(implode("\n", $bodyLines), "\r\n");

        return [$prologue, $body];
    }

    /**
     * Convenience for the museum case: resolve the museum record class
     * (crm:E22_Human-Made_Object via rico:Item) the same way
     * MuseumController does, then sync.
     */
    public function syncMuseumObject(int $objectId, string $culture = 'en'): bool
    {
        return $this->syncObject($objectId, $this->museumRecordClass(), $culture);
    }

    /**
     * The CRM record class museum objects serialize as. Mirrors
     * MuseumController::cidocExportDownload (rico:Item ->
     * crm:E22_Human-Made_Object). Falls back to the literal CURIE if the
     * mapper returns null so callers always get a usable class.
     */
    public function museumRecordClass(): string
    {
        return RicToCrmMapper::classFor('rico:Item') ?? 'crm:E22_Human-Made_Object';
    }
}
