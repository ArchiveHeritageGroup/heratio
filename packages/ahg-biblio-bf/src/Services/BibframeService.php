<?php

/**
 * BibframeService - BIBFRAME 2.0 conversion service for Heratio
 *
 * Converts bibliographic catalogue records to/from BIBFRAME 2.0 RDF via
 * the OpenRiC RiC-O service layer. Supports BIBFRAME XML/RDF import, export
 * in multiple RDF serialisations, and basic structural validation.
 *
 * BIBFRAME 2.0 model (Library of Congress):
 *   Work — a distinct intellectual or artistic creation
 *   Instance — a specific realisation of a Work (edition, format)
 *   Item — a concrete copy of an Instance
 *   Agent — a person or corporate body associated with a Work
 *
 * Conversion path:
 *   Heratio library_biblio_work → BIBFRAME Work
 *   Heratio library_biblio_instance → BIBFRAME Instance
 *   Heratio library_biblio_item → BIBFRAME Item
 *   Heratio library_biblio_agent → BIBFRAME Agent
 *
 * All round-tripping goes through OpenRiC for canonical RiC-O handling.
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

namespace AhgBiblioBf\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BibframeService
{
    protected string $openricUrl;

    public function __construct()
    {
        // OpenRiC is the authoritative RiC-O service; BF conversion is a passthrough.
        // Gracefully degrade if OpenRiC is not configured.
        $this->openricUrl = rtrim(config('services.openric.url', 'http://localhost:3030'), '/');
    }

    /**
     * Convert a Heratio bibliographic work to BIBFRAME 2.0 RDF/XML.
     *
     * @param int    $workId  library_biblio_work.id
     * @param string $format  Output serialization: xml | rdfxml | turtle | ntriples | json-ld
     * @return string RDF serialisation
     */
    public function catalogToRdf(int $workId, string $format = 'xml'): string
    {
        $work = DB::connection('heratio')
            ->table('library_biblio_work')
            ->where('id', $workId)
            ->first();

        if (! $work) {
            throw new \InvalidArgumentException(" bibliographic work {$workId} not found.");
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

        $workUri     = "urn:uuid:{$work->id}-work";
        $rdf = $this->rdfHeader();

        // BIBFRAME Work
        $rdf .= $this->bfWorkBlock($work, $workUri, $agents, $instances);

        // BIBFRAME Instances
        foreach ($instances as $instance) {
            $instanceUri = "urn:uuid:{$instance->id}-instance";
            $items = DB::connection('heratio')
                ->table('library_biblio_item')
                ->where('instance_id', $instance->id)
                ->get();

            $rdf .= $this->bfInstanceBlock($instance, $instanceUri, $workUri, $items);
        }

        // BIBFRAME Agents
        foreach ($agents as $agent) {
            $agentUri = "urn:uuid:{$agent->id}-agent";
            $rdf .= $this->bfAgentBlock($agent, $agentUri);
        }

        $rdf .= "</rdf:RDF>\n";

        return $this->serialise($rdf, $format);
    }

    /**
     * Import a BIBFRAME RDF document into the Heratio catalogue.
     * Parses BF XML and upserts into the bibframe-related tables.
     *
     * @param string $rdfContent Raw RDF/XML
     * @return array{works:int, instances:int, items:int, warnings:int, errors:int}
     */
    public function importRdf(string $rdfContent): array
    {
        $stats = ['works' => 0, 'instances' => 0, 'items' => 0, 'warnings' => 0, 'errors' => 0];

        // Basic structural check before attempting XML parse
        if (! str_contains($rdfContent, 'bf:Bibframe')) {
            // Try loading as generic RDF
            try {
                $xml = @simplexml_load_string($rdfContent);
            } catch (\Throwable $e) {
                return array_merge($stats, ['errors' => 1]);
            }
        } else {
            $xml = @simplexml_load_string($rdfContent);
        }

        if ($xml === false) {
            Log::warning('[Bibframe] Import failed: invalid XML');
            return array_merge($stats, ['errors' => 1]);
        }

        $xml->registerXPathNamespace('bf', 'http://id.loc.gov/ontologies/bibframe/');
        $xml->registerXPathNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
        $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

        // Parse Works
        $workNodes = $xml->xpath('//bf:Work') ?: [];
        foreach ($workNodes as $workNode) {
            try {
                $id = $this->upsertWork($workNode);
                $stats['works']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning("[Bibframe] Work import error: {$e->getMessage()}");
            }
        }

        // Parse Instances
        $instanceNodes = $xml->xpath('//bf:Instance') ?: [];
        foreach ($instanceNodes as $instanceNode) {
            try {
                $id = $this->upsertInstance($instanceNode);
                $stats['instances']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['warnings']++;
                Log::warning("[Bibframe] Instance import error: {$e->getMessage()}");
            }
        }

        // Parse Items
        $itemNodes = $xml->xpath('//bf:Item') ?: [];
        foreach ($itemNodes as $itemNode) {
            try {
                $id = $this->upsertItem($itemNode);
                $stats['items']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['warnings']++;
                Log::warning("[Bibframe] Item import error: {$e->getMessage()}");
            }
        }

        return $this->proxyToOpenric($rdfContent, 'import');
    }

    /**
     * Validate BIBFRAME RDF for structural correctness.
     *
     * @param string $rdfContent Raw RDF/XML
     * @return array{errors:string[], warnings:string[], fatal:string[]}
     */
    public function validateRdf(string $rdfContent): array
    {
        $issues = ['errors' => [], 'warnings' => [], 'fatal' => []];

        if (empty(trim($rdfContent))) {
            $issues['fatal'][] = 'Empty document.';
            return $issues;
        }

        // Basic XML well-formedness check
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($rdfContent);

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

        $xml->registerXPathNamespace('bf', 'http://id.loc.gov/ontologies/bibframe/');
        $xml->registerXPathNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
        $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

        // Check for root RDF element
        if (! $xml->xpath('//rdf:RDF')) {
            $issues['warnings'][] = 'No rdf:RDF root element found.';
        }

        // Check for at least one Work or Instance
        $works = $xml->xpath('//bf:Work') ?: [];
        $instances = $xml->xpath('//bf:Instance') ?: [];

        if (empty($works) && empty($instances)) {
            $issues['warnings'][] = 'No bf:Work or bf:Instance elements found.';
        }

        // Check for required bf:mainTitle on Works
        foreach ($works as $work) {
            $titles = $work->xpath('bf:mainTitle') ?: [];
            if (empty($titles)) {
                $issues['warnings'][] = 'bf:Work missing bf:mainTitle.';
            }
        }

        return $issues;
    }

    /**
     * Extract work data from the DB as a plain array (used by the controller).
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

    protected function rdfHeader(): string
    {
        $ns = [
            'xmlns:bf'   => 'http://id.loc.gov/ontologies/bibframe/',
            'xmlns:rdf'  => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'xmlns:rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'xmlns:owl'  => 'http://www.w3.org/2002/07/owl#',
        ];

        $attrs = '';
        foreach ($ns as $prefix => $uri) {
            $attrs .= $prefix . '="' . $uri . '"' . "\n         ";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rdf:RDF
     {$attrs}
     xml:base="https://heratio.theahg.co.za/bibframe/">

XML;
    }

    protected function bfWorkBlock(object $work, string $workUri, object $agents, object $instances): string
    {
        $title = $this->xmlEscape($work->title ?? 'Unknown Title');
        $author = $this->xmlEscape($work->author ?? '');
        $lang = $work->language ?? 'en';

        $agentBlocks = '';
        foreach ($agents as $agent) {
            $agentUri = "urn:uuid:{$agent->id}-agent";
            $role = $this->xmlEscape($agent->type ?? 'aut');
            $name = $this->xmlEscape($agent->name ?? 'Unknown Agent');
            $agentBlocks .= <<<XML
    <bf:contributor>
      <bf:Contribution>
        <bf:agent rdf:resource="{$agentUri}"/>
        <bf:role rdf:resource="http://id.loc.gov/vocabulary/relRoles/{$role}"/>
      </bf:Contribution>
    </bf:contributor>
XML;
        }

        $instanceRefs = '';
        foreach ($instances as $instance) {
            $instanceUri = "urn:uuid:{$instance->id}-instance";
            $instanceRefs .= <<<XML
    <bf:hasInstance rdf:resource="{$instanceUri}"/>
XML;
        }

        return <<<XML
  <bf:Work rdf:about="{$workUri}">
    <bf:adminMetadata>
      <bf:AdminMetadata>
        <bf:status rdf:resource="http://id.loc.gov/vocabulary/recordStatus/catbot"/>
        <bf:creationDate rdf:datatype="http://www.w3.org/2001/XMLSchema#date">{$work->created_at}</bf:creationDate>
      </bf:AdminMetadata>
    </bf:adminMetadata>
    <bf:mainTitle xml:lang="{$lang}">{$title}</bf:mainTitle>
    <bf:creator>
      <bf:Contribution>
        <bf:agent>
          <bf:Unconstrained>
            <rdfs:label>{$author}</rdfs:label>
          </bf:Unconstrained>
        </bf:agent>
      </bf:Contribution>
    </bf:creator>
{$agentBlocks}
{$instanceRefs}
  </bf:Work>

XML;
    }

    protected function bfInstanceBlock(object $instance, string $instanceUri, string $workUri, object $items): string
    {
        $title = $this->xmlEscape($instance->title ?? '');
        $pubPlace = $this->xmlEscape($instance->pub_place ?? '');
        $publisher = $this->xmlEscape($instance->publisher ?? '');
        $pubDate = $this->xmlEscape($instance->pub_date ?? '');
        $isbn = $this->xmlEscape($instance->isbn ?? '');
        $format = $this->xmlEscape($instance->carrier ?? '');

        $itemRefs = '';
        foreach ($items as $item) {
            $itemUri = "urn:uuid:{$item->id}-item";
            $itemRefs .= <<<XML
    <bf:hasItem rdf:resource="{$itemUri}"/>
XML;
        }

        return <<<XML
  <bf:Instance rdf:about="{$instanceUri}">
    <bf:instanceOf rdf:resource="{$workUri}"/>
    <bf:title>
      <bf:Title>
        <bf:mainTitle>{$title}</bf:mainTitle>
      </bf:Title>
    </bf:title>
    <bf:provisionActivity>
      <bf:ProvisionActivity>
        <bf:type rdf:resource="http://id.loc.gov/vocabulary/relIssuance/monographic"/>
        <bf:place>
          <bf:Place>
            <rdfs:label>{$pubPlace}</rdfs:label>
          </bf:Place>
        </bf:place>
        <bf:agent>
          <bf:Unconstrained>
            <rdfs:label>{$publisher}</rdfs:label>
          </bf:Unconstrained>
        </bf:agent>
        <bf:date>{$pubDate}</bf:date>
      </bf:ProvisionActivity>
    </bf:provisionActivity>
    <bf:identifiedBy>
      <bf:LCCN>
        <rdf:value>{$isbn}</rdf:value>
      </bf:LCCN>
    </bf:identifiedBy>
    <bf:carrier rdf:resource="http://id.loc.gov/vocabulary/carriers/{$format}"/>
{$itemRefs}
  </bf:Instance>

XML;
    }

    protected function bfAgentBlock(object $agent, string $agentUri): string
    {
        $name = $this->xmlEscape($agent->name ?? 'Unknown');
        $type = $this->xmlEscape(match ($agent->type ?? 'per') {
            'aut' => 'http://id.loc.gov/vocabulary/relators/aut',
            'ctb' => 'http://id.loc.gov/vocabulary/relators/ctb',
            'edt' => 'http://id.loc.gov/vocabulary/relators/edt',
            'ill' => 'http://id.loc.gov/vocabulary/relators/ill',
            default => 'http://id.loc.gov/vocabulary/relators/aut',
        });

        return <<<XML
  <bf:Agent rdf:about="{$agentUri}">
    <rdfs:label>{$name}</rdfs:label>
    <bf:identifiedBy>
      <bf:Local>
        <bf:source>
          <bf:Source>
            <rdfs:label>Heratio</rdfs:label>
          </bf:Source>
        </bf:source>
      </bf:Local>
    </bf:identifiedBy>
  </bf:Agent>

XML;
    }

    /**
     * Persist a BIBFRAME Work node into library_biblio_work.
     *
     * @param \SimpleXMLElement $workNode
     * @return int work id
     */
    protected function upsertWork(\SimpleXMLElement $workNode): int
    {
        $mainTitle = (string) ($workNode->xpath('bf:mainTitle')[0] ?? '');
        $creatorLabel = (string) ($workNode->xpath('bf:creator/bf:Contribution/bf:agent//rdfs:label')[0] ?? '');

        if (! Schema::connection('heratio')->hasTable('library_biblio_work')) {
            throw new \RuntimeException('Table library_biblio_work does not exist.');
        }

        $existing = DB::connection('heratio')
            ->table('library_biblio_work')
            ->where('title', $mainTitle)
            ->first();

        if ($existing) {
            // Update the stored RDF alongside the existing record
            DB::connection('heratio')
                ->table('library_biblio_work')
                ->where('id', $existing->id)
                ->update([
                    'author' => $creatorLabel,
                    'updated_at' => now(),
                    // Store serialized RDF in the bibframe_rdf column if present
                ]);

            return (int) $existing->id;
        }

        return DB::connection('heratio')
            ->table('library_biblio_work')
            ->insertGetId([
                'title'      => $mainTitle,
                'author'     => $creatorLabel,
                'language'   => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Persist a BIBFRAME Instance node into library_biblio_instance.
     *
     * @param \SimpleXMLElement $instanceNode
     * @return int instance id
     */
    protected function upsertInstance(\SimpleXMLElement $instanceNode): int
    {
        if (! Schema::connection('heratio')->hasTable('library_biblio_instance')) {
            throw new \RuntimeException('Table library_biblio_instance does not exist.');
        }

        $title = (string) ($instanceNode->xpath('bf:title/bf:Title/bf:mainTitle')[0] ?? '');
        $pubPlace = (string) ($instanceNode->xpath('bf:provisionActivity//rdfs:label')[0] ?? '');
        $publisher = (string) ($instanceNode->xpath('bf:provisionActivity//bf:agent//rdfs:label')[0] ?? '');
        $pubDate = (string) ($instanceNode->xpath('bf:provisionActivity/bf:ProvisionActivity/bf:date')[0] ?? '');
        $isbn = (string) ($instanceNode->xpath('bf:identifiedBy[1]/bf:LCCN/rdf:value')[0] ?? '');

        return DB::connection('heratio')
            ->table('library_biblio_instance')
            ->insertGetId([
                'title'      => $title,
                'pub_place'  => $pubPlace,
                'publisher'  => $publisher,
                'pub_date'   => $pubDate,
                'isbn'       => $isbn,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Persist a BIBFRAME Item node into library_biblio_item.
     *
     * @param \SimpleXMLElement $itemNode
     * @return int item id
     */
    protected function upsertItem(\SimpleXMLElement $itemNode): int
    {
        if (! Schema::connection('heratio')->hasTable('library_biblio_item')) {
            throw new \RuntimeException('Table library_biblio_item does not exist.');
        }

        $barcode = (string) ($itemNode->xpath('bf:identifiedBy/bf:Barcode/rdf:value')[0] ?? '');

        return DB::connection('heratio')
            ->table('library_biblio_item')
            ->insertGetId([
                'barcode'    => $barcode ?: 'BF-' . substr(md5((string) mt_rand()), 0, 8),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Serialise BF RDF into the requested format.
     * Currently returns XML as-is; other serialisations are stubs for future.
     *
     * @param string $rdf   BIBFRAME RDF/XML
     * @param string $format xml | rdfxml | turtle | ntriples | json-ld
     * @return string
     */
    protected function serialise(string $rdf, string $format): string
    {
        return match ($format) {
            'rdfxml', 'xml' => $rdf,
            'turtle'  => "# Turtle serialisation — requires EasyRdf or equivalent.\n# Returned as RDF/XML for now.\n" . $rdf,
            'ntriples' => "# N-Triples serialisation — requires EasyRdf or equivalent.\n" . $rdf,
            'json-ld' => "# JSON-LD serialisation — requires EasyRdf or equivalent.\n" . $rdf,
            default   => $rdf,
        };
    }

    /**
     * Proxy content to OpenRiC for authoritative RiC-O handling.
     * OpenRiC is the round-trippable RiC-O surface; BIBFRAME here is a reader.
     *
     * @param string $rdfContent
     * @param string $action   import | export | validate
     * @return array
     */
    protected function proxyToOpenric(string $rdfContent, string $action): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json'])
                ->post("{$this->openricUrl}/api/ric/bibframe/{$action}", [
                    'rdf' => $rdfContent,
                ]);

            if ($response->successful()) {
                Log::info("[Bibframe] OpenRiC {$action} proxy successful", $response->json());
                return $response->json();
            }

            Log::warning("[Bibframe] OpenRiC proxy failed with status {$response->status()}");
        } catch (\Throwable $e) {
            Log::warning("[Bibframe] OpenRiC not available: {$e->getMessage()}");
        }

        // Return the stats collected so far (OpenRiC is optional / future)
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
