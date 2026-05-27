<?php

/**
 * FrbrService — IFLA FRBR conceptual model conversion for Heratio
 *
 * Converts bibliographic catalogue records to/from the FRBR entity model:
 *   Work → Expression → Manifestation → Item
 *   (Person | Corporate Body) — responsible Agent entities
 *
 * Heratio tables:
 *   library_biblio_work     → FRBR Work
 *   library_biblio_instance → FRBR Expression / Manifestation
 *   library_biblio_item     → FRBR Item
 *   library_biblio_agent    → FRBR Agent (Person/Corporate Body)
 *
 * All round-tripping proxies through OpenRiC for canonical RiC-O handling.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 * Email: johan@theahg.co.za
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

namespace AhgBiblioFrbr\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FrbrService
{
    protected string $openricUrl;

    public function __construct()
    {
        // OpenRiC is the authoritative RiC-O service; FRBR conversion is a passthrough.
        // Gracefully degrade if OpenRiC is not configured.
        $this->openricUrl = rtrim(config('services.openric.url', 'http://localhost:3030'), '/');
    }

    /**
     * Convert a Heratio bibliographic work to FRBR JSON.
     *
     * @param int $workId  library_biblio_work.id
     * @return array FRBR entity graph
     */
    public function catalogToFrbr(int $workId): array
    {
        $work = DB::connection('heratio')
            ->table('library_biblio_work')
            ->where('id', $workId)
            ->first();

        if (! $work) {
            throw new \InvalidArgumentException("Bibliographic work {$workId} not found.");
        }

        $instances = DB::connection('heratio')
            ->table('library_biblio_instance')
            ->where('work_id', $workId)
            ->get();

        $agentIds = DB::connection('heratio')
            ->table('library_biblio_work_agent')
            ->where('work_id', $workId)
            ->pluck('agent_id')
            ->unique();

        $agents = DB::connection('heratio')
            ->table('library_biblio_agent')
            ->whereIn('id', $agentIds)
            ->get();

        return $this->buildFrbrGraph($work, $instances, $agents);
    }

    /**
     * Export a Heratio bibliographic work to FRBR XML.
     *
     * @param int $workId
     * @param string $format xml | json | rdf
     * @return string
     */
    public function catalogToXml(int $workId, string $format = 'xml'): string
    {
        $work = DB::connection('heratio')
            ->table('library_biblio_work')
            ->where('id', $workId)
            ->first();

        if (! $work) {
            throw new \InvalidArgumentException("Bibliographic work {$workId} not found.");
        }

        $instances = DB::connection('heratio')
            ->table('library_biblio_instance')
            ->where('work_id', $workId)
            ->get();

        $agentIds = DB::connection('heratio')
            ->table('library_biblio_work_agent')
            ->where('work_id', $workId)
            ->pluck('agent_id')
            ->unique();

        $agents = DB::connection('heratio')
            ->table('library_biblio_agent')
            ->whereIn('id', $agentIds)
            ->get();

        return $this->buildFrbrXml($work, $instances, $agents, $format);
    }

    /**
     * Import an external FRBR XML document into the Heratio catalogue.
     *
     * @param string $xmlContent Raw FRBR XML
     * @return array{works:int, instances:int, items:int, warnings:int, errors:int}
     */
    public function importXml(string $xmlContent): array
    {
        $stats = ['works' => 0, 'instances' => 0, 'items' => 0, 'warnings' => 0, 'errors' => 0];

        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent);

        if ($xml === false) {
            $libxmlErrors = libxml_get_errors();
            libxml_clear_errors();
            $stats['errors'] = count($libxmlErrors) ?: 1;
            return $stats;
        }

        // Register namespaces common in FRBR XML
        $xml->registerXPathNamespace('frbr', 'http://iflastandards.info/ns/fr/frbr/frbrer/');
        $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $xml->registerXPathNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');

        // Parse Work nodes (standard FRBRer namespace)
        $workNodes = $xml->xpath('//frbr:Work') ?: [];
        foreach ($workNodes as $workNode) {
            try {
                $this->upsertWork($workNode);
                $stats['works']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning("[FRBR] Work import error: {$e->getMessage()}");
            }
        }

        // Parse Expression nodes
        $exprNodes = $xml->xpath('//frbr:Expression') ?: [];
        foreach ($exprNodes as $exprNode) {
            try {
                $this->upsertExpression($exprNode);
                $stats['instances']++;
            } catch (\Throwable $e) {
                $stats['warnings']++;
                Log::warning("[FRBR] Expression import error: {$e->getMessage()}");
            }
        }

        // Parse Item nodes
        $itemNodes = $xml->xpath('//frbr:Item') ?: [];
        foreach ($itemNodes as $itemNode) {
            try {
                $this->upsertItem($itemNode);
                $stats['items']++;
            } catch (\Throwable $e) {
                $stats['warnings']++;
                Log::warning("[FRBR] Item import error: {$e->getMessage()}");
            }
        }

        // Notify OpenRiC for authoritative RiC-O handling, then return import stats.
        $this->proxyToOpenric($xmlContent, 'import');
        return $stats;
    }

    /**
     * Validate an FRBR XML document for structural correctness.
     *
     * @param string $xmlContent
     * @return array{errors:string[], warnings:string[], fatal:string[]}
     */
    public function validateXml(string $xmlContent): array
    {
        $issues = ['errors' => [], 'warnings' => [], 'fatal' => []];

        if (empty(trim($xmlContent))) {
            $issues['fatal'][] = 'Empty document.';
            return $issues;
        }

        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent);

        if ($xml === false) {
            $libxmlErrors = libxml_get_errors();
            libxml_clear_errors();
            foreach ($libxmlErrors as $error) {
                $issues['errors'][] = trim($error->message);
            }
            if (empty($issues['errors'])) {
                $issues['fatal'][] = 'Document is not well-formed XML.';
            }
            return $issues;
        }

        $xml->registerXPathNamespace('frbr', 'http://iflastandards.info/ns/fr/frbr/frbrer/');
        $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

        // Check for at least one Work or Expression
        $works = $xml->xpath('//frbr:Work') ?: [];
        $expressions = $xml->xpath('//frbr:Expression') ?: [];

        if (empty($works) && empty($expressions)) {
            $issues['warnings'][] = 'No frbr:Work or frbr:Expression elements found — document may not be FRBR.';
        }

        // Warn if no rdf:RDF root
        if (! $xml->xpath('//rdf:RDF') && ! $xml->getNamespaces()) {
            $issues['warnings'][] = 'No rdf:RDF root element — namespace-independent check applied.';
        }

        // Warn about orphan Items (Items should belong to an Expression)
        $items = $xml->xpath('//frbr:Item') ?: [];
        if (count($items) > 0 && empty($expressions)) {
            $issues['warnings'][] = 'frbr:Item elements found with no parent Expression — verify hierarchy.';
        }

        return $issues;
    }

    /**
     * Extract work data from the DB as a plain array.
     *
     * @param int $workId
     * @return array|null
     */
    public function workToArray(int $workId): ?array
    {
        $work = DB::connection('heratio')
            ->table('library_biblio_work')
            ->where('id', $workId)
            ->first();

        if (! $work) {
            return null;
        }

        $instances = DB::connection('heratio')
            ->table('library_biblio_instance')
            ->where('work_id', $workId)
            ->get();

        $agentIds = DB::connection('heratio')
            ->table('library_biblio_work_agent')
            ->where('work_id', $workId)
            ->pluck('agent_id')
            ->unique();

        $agents = DB::connection('heratio')
            ->table('library_biblio_agent')
            ->whereIn('id', $agentIds)
            ->get();

        return [
            'work'      => $work,
            'instances' => $instances,
            'agents'    => $agents,
        ];
    }

    // ─── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build the in-memory FRBR entity graph.
     */
    protected function buildFrbrGraph(object $work, object $instances, object $agents): array
    {
        $graph = [
            'entityType' => 'frbr',
            'work' => $this->frbrWork($work, $agents),
            'expressions' => [],
            'items' => [],
            'agents' => [],
        ];

        foreach ($instances as $instance) {
            $expr = $this->frbrExpression($instance, $work);
            $items = DB::connection('heratio')
                ->table('library_biblio_item')
                ->where('instance_id', $instance->id)
                ->get();

            foreach ($items as $item) {
                $graph['items'][] = $this->frbrItem($item, $instance);
            }

            $graph['expressions'][] = $expr;
        }

        foreach ($agents as $agent) {
            $graph['agents'][] = $this->frbrAgent($agent);
        }

        return $graph;
    }

    /**
     * Build FRBR XML serialisation.
     */
    protected function buildFrbrXml(object $work, object $instances, object $agents, string $format): string
    {
        $workId = "urn:uuid:{$work->id}-work";
        $title  = $this->xmlEscape($work->title ?? 'Unknown Title');
        $author = $this->xmlEscape($work->author ?? '');
        $lang   = $work->language ?? 'en';
        $subjectField = $this->xmlEscape($work->subject ?? '');

        $agentBlocks = '';
        foreach ($agents as $agent) {
            $agentId = "urn:uuid:{$agent->id}-agent";
            $agentName = $this->xmlEscape($agent->name ?? 'Unknown');
            $role = $this->xmlEscape($agent->type ?? 'aut');
            $roleUri = match ($agent->type ?? 'aut') {
                'aut' => 'http://id.loc.gov/vocabulary/relators/aut',
                'ctb' => 'http://id.loc.gov/vocabulary/relators/ctb',
                'edt' => 'http://id.loc.gov/vocabulary/relators/edt',
                default => 'http://id.loc.gov/vocabulary/relators/aut',
            };
            $agentBlocks .= <<<XML
    <frbr:responsibleAgent>
      <frbr:Agent rdf:about="{$agentId}">
        <rdfs:label>{$agentName}</rdfs:label>
        <frbr:role rdf:resource="{$roleUri}"/>
      </frbr:Agent>
    </frbr:responsibleAgent>
XML;
        }

        $exprBlocks = '';
        foreach ($instances as $instance) {
            $exprId = "urn:uuid:{$instance->id}-expr";
            $exprTitle = $this->xmlEscape($instance->title ?? $title);

            $carrier = $instance->carrier ?? 'nc';
            $items = DB::connection('heratio')
                ->table('library_biblio_item')
                ->where('instance_id', $instance->id)
                ->get();

            foreach ($items as $item) {
                $itemId = "urn:uuid:{$item->id}-item";
                $barcode = $this->xmlEscape($item->barcode ?? 'unknown');
                $exprBlocks .= <<<XML
    <frbr:exemplar>
      <frbr:Item rdf:about="{$itemId}">
        <frbr:shelfMark>{$barcode}</frbr:shelfMark>
      </frbr:Item>
    </frbr:exemplar>
XML;
            }

            $carrierEsc = $this->xmlEscape($carrier);
            $exprBlocks .= <<<XML
    <frbr:realization>
      <frbr:Expression rdf:about="{$exprId}">
        <rdfs:label>{$exprTitle}</rdfs:label>
        <frbr:carrier rdf:resource="http://iflastandards.info/ns/fr/frbr/frbrer/carrier/{$carrierEsc}"/>
      </frbr:Expression>
    </frbr:realization>
XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rdf:RDF
     xmlns:frbr="http://iflastandards.info/ns/fr/frbr/frbrer/"
     xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
     xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
     xmlns:owl="http://www.w3.org/2002/07/owl#"
     xml:base="https://heratio.theahg.co.za/frbr/">

  <frbr:Work rdf:about="{$workId}">
    <rdfs:label>{$title}</rdfs:label>
    <frbr:creator>
      <frbr:Agent>
        <rdfs:label>{$author}</rdfs:label>
      </frbr:Agent>
    </frbr:creator>
    <frbr:subject xml:lang="{$lang}">{$subjectField}</frbr:subject>
{$agentBlocks}
{$exprBlocks}
  </frbr:Work>

</rdf:RDF>
XML;
    }

    protected function frbrWork(object $work, object $agents): array
    {
        return [
            'id'       => $work->id,
            'title'    => $work->title ?? 'Unknown Title',
            'author'   => $work->author ?? '',
            'language' => $work->language ?? 'en',
            'subject'  => $work->subject ?? null,
            'created_at' => $work->created_at,
            'agents'   => array_map(fn($a) => [
                'id'   => $a->id,
                'name' => $a->name ?? 'Unknown',
                'type' => $a->type ?? 'per',
            ], (array) $agents),
        ];
    }

    protected function frbrExpression(object $instance, object $work): array
    {
        return [
            'id'       => $instance->id,
            'title'    => $instance->title ?? $work->title ?? 'Unknown',
            'publisher' => $instance->publisher ?? null,
            'pub_date' => $instance->pub_date ?? null,
            'isbn'     => $instance->isbn ?? null,
            'carrier'  => $instance->carrier ?? null,
            'workId'   => $instance->work_id,
        ];
    }

    protected function frbrItem(object $item, object $instance): array
    {
        return [
            'id'         => $item->id,
            'barcode'    => $item->barcode ?? null,
            'instanceId' => $item->instance_id,
            'instance'   => [
                'title'    => $instance->title ?? null,
                'publisher' => $instance->publisher ?? null,
            ],
        ];
    }

    protected function frbrAgent(object $agent): array
    {
        return [
            'id'   => $agent->id,
            'name' => $agent->name ?? 'Unknown',
            'type' => $agent->type ?? 'per',
        ];
    }

    /**
     * Upsert a FRBR Work into library_biblio_work.
     *
     * @param \SimpleXMLElement $workNode
     * @return int work id
     */
    protected function upsertWork(\SimpleXMLElement $workNode): int
    {
        $title = (string) ($workNode->xpath('rdfs:label')[0] ?? '');
        $creatorLabel = (string) ($workNode->xpath('frbr:creator//rdfs:label')[0] ?? '');

        if (! Schema::connection('heratio')->hasTable('library_biblio_work')) {
            throw new \RuntimeException('Table library_biblio_work does not exist.');
        }

        $existing = DB::connection('heratio')
            ->table('library_biblio_work')
            ->where('title', $title)
            ->first();

        if ($existing) {
            DB::connection('heratio')
                ->table('library_biblio_work')
                ->where('id', $existing->id)
                ->update([
                    'author'     => $creatorLabel,
                    'updated_at' => now(),
                ]);

            return (int) $existing->id;
        }

        return DB::connection('heratio')
            ->table('library_biblio_work')
            ->insertGetId([
                'title'      => $title,
                'author'     => $creatorLabel,
                'language'   => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Upsert a FRBR Expression into library_biblio_instance.
     *
     * @param \SimpleXMLElement $exprNode
     * @return int instance id
     */
    protected function upsertExpression(\SimpleXMLElement $exprNode): int
    {
        if (! Schema::connection('heratio')->hasTable('library_biblio_instance')) {
            throw new \RuntimeException('Table library_biblio_instance does not exist.');
        }

        $title = (string) ($exprNode->xpath('rdfs:label')[0] ?? '');
        $pubDate = (string) ($exprNode->xpath('frbr:date')[0] ?? '');

        return DB::connection('heratio')
            ->table('library_biblio_instance')
            ->insertGetId([
                'title'     => $title,
                'pub_date' => $pubDate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Upsert a FRBR Item into library_biblio_item.
     *
     * @param \SimpleXMLElement $exprNode
     * @return int item id
     */
    protected function upsertItem(\SimpleXMLElement $exprNode): int
    {
        if (! Schema::connection('heratio')->hasTable('library_biblio_item')) {
            throw new \RuntimeException('Table library_biblio_item does not exist.');
        }

        $barcode = (string) ($exprNode->xpath('frbr:shelfMark')[0] ?? '');

        return DB::connection('heratio')
            ->table('library_biblio_item')
            ->insertGetId([
                'barcode'    => $barcode ?: 'FRBR-' . substr(md5((string) mt_rand()), 0, 8),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Proxy content to OpenRiC for authoritative RiC-O handling.
     *
     * @param string $xmlContent
     * @param string $action   import | export | validate
     * @return array
     */
    protected function proxyToOpenric(string $xmlContent, string $action): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json'])
                ->post("{$this->openricUrl}/api/ric/frbr/frbr", [
                    'xml' => $xmlContent,
                ]);

            if ($response->successful()) {
                Log::info("[FRBR] OpenRiC {$action} proxy successful", $response->json());
                return $response->json();
            }

            Log::warning("[FRBR] OpenRiC proxy failed with status {$response->status()}");
        } catch (\Throwable $e) {
            Log::warning("[FRBR] OpenRiC not available: {$e->getMessage()}");
        }

        return [
            'proxied' => false,
            'via'     => 'openric',
            'url'     => $this->openricUrl,
        ];
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
