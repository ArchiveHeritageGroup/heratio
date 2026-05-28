<?php

/**
 * BibframeSerialisationService — BIBFRAME Turtle / JSON-LD / RDF/XML export.
 *
 * Converts fully-populated library_item records to BIBFRAME 2.0 RDF in three
 * serialisation formats:
 *   - Turtle   (text/turtle)
 *   - JSON-LD  (application/ld+json, BIBFRAME @context)
 *   - RDF/XML  (alias for existing BibframeService serialise())
 *
 * Uses EasyRdf to perform the conversion. If EasyRdf is not available
 * (not installed in vendor/), graceful stubs are returned with the existing
 * RDF/XML content and a note.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgBiblioBf\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BibframeSerialisationService
{
    protected string $openricUrl;
    protected bool $easyrdfAvailable;

    public function __construct()
    {
        $this->openricUrl = rtrim(config('services.openric.url', 'http://localhost:3030'), '/');
        $this->easyrdfAvailable = class_exists(\EasyRdf\Graph::class);
    }

    /**
     * Serialise a BIBFRAME work to Turtle (text/turtle).
     *
     * Fetches the fully-populated library_biblio_work record including agents,
     * instances, and items; builds BIBFRAME RDF graphs using EasyRdf; and
     * outputs the Turtle serialisation.
     *
     * @param int $workId  library_biblio_work.id
     * @return string       Turtle string, or RDF/XML with a comment fallback.
     */
    public function toTurtle(int $workId): string
    {
        if (! $this->easyrdfAvailable) {
            return $this->easyrdfFallback($workId, 'turtle');
        }

        $workData = $this->fetchWorkData($workId);
        if (! $workData['work']) {
            throw new \InvalidArgumentException("Work {$workId} not found.");
        }

        $graph = new \EasyRdf\Graph();
        $baseUri = "https://heratio.theahg.co.za/bibframe/work/{$workId}";

        // Build Work node
        $workNode = $graph->resource($baseUri, 'bf:Work');
        $workNode->set('bf:mainTitle', $workData['work']->title ?? 'Unknown Title', 'xsd:string');
        $workNode->set('rdf:type', 'bf:Work');

        // Authors / agents
        foreach ($workData['agents'] as $agent) {
            $agentUri = "https://heratio.theahg.co.za/bibframe/agent/{$agent->id}";
            $agentNode = $graph->resource($agentUri, 'bf:Agent');
            $agentNode->set('rdfs:label', $agent->name ?? 'Unknown Agent');
            $workNode->addResource('bf:contributor', $agentNode);
        }

        // Instances
        foreach ($workData['instances'] as $instance) {
            $instanceUri = "https://heratio.theahg.co.za/bibframe/instance/{$instance->id}";
            $instanceNode = $graph->resource($instanceUri, 'bf:Instance');
            $instanceNode->addResource('bf:instanceOf', $workNode);
            $instanceNode->set('bf:title', $instance->title ?? '');
            if ($instance->publisher || $instance->pub_place || $instance->pub_date) {
                $provNode = $graph->resource($instanceUri . '/provision', 'bf:ProvisionActivity');
                if ($instance->pub_place) {
                    $placeNode = $graph->resource($instanceUri . '/place', 'bf:Place');
                    $placeNode->set('rdfs:label', $instance->pub_place);
                    $provNode->addResource('bf:place', $placeNode);
                }
                if ($instance->publisher) {
                    $agentNode = $graph->resource("{$instanceUri}/publisher", 'bf:Unconstrained');
                    $agentNode->set('rdfs:label', $instance->publisher);
                    $provNode->addResource('bf:agent', $agentNode);
                }
                if ($instance->pub_date) {
                    $provNode->set('bf:date', $instance->pub_date);
                }
                $instanceNode->addResource('bf:provisionActivity', $provNode);
            }
            if ($instance->isbn) {
                $idNode = $graph->resource($instanceUri . '/isbn', 'bf:Isbn');
                $idNode->set('rdf:value', $instance->isbn);
                $instanceNode->addResource('bf:identifiedBy', $idNode);
            }
            $workNode->addResource('bf:hasInstance', $instanceNode);
        }

        return $graph->serialise('turtle');
    }

    /**
     * Serialise a BIBFRAME work to JSON-LD with BIBFRAME @context.
     *
     * @param int $workId  library_biblio_work.id
     * @return string       JSON-LD string.
     */
    public function toJsonLd(int $workId): string
    {
        if (! $this->easyrdfAvailable) {
            return $this->easyrdfFallback($workId, 'jsonld');
        }

        $workData = $this->fetchWorkData($workId);
        if (! $workData['work']) {
            throw new \InvalidArgumentException("Work {$workId} not found.");
        }

        $graph = new \EasyRdf\Graph();
        $baseUri = "https://heratio.theahg.co.za/bibframe/work/{$workId}";

        $workNode = $graph->resource($baseUri, 'bf:Work');
        $workNode->set('bf:mainTitle', $workData['work']->title ?? 'Unknown Title');
        $workNode->set('rdf:type', 'bf:Work');

        foreach ($workData['agents'] as $agent) {
            $agentUri = "https://heratio.theahg.co.za/bibframe/agent/{$agent->id}";
            $agentNode = $graph->resource($agentUri, 'bf:Agent');
            $agentNode->set('rdfs:label', $agent->name ?? '');
            $workNode->addResource('bf:contributor', $agentNode);
        }

        foreach ($workData['instances'] as $instance) {
            $instanceUri = "https://heratio.theahg.co.za/bibframe/instance/{$instance->id}";
            $instanceNode = $graph->resource($instanceUri, 'bf:Instance');
            $instanceNode->addResource('bf:instanceOf', $workNode);
            $instanceNode->set('bf:title', $instance->title ?? '');
            if ($instance->isbn) {
                $idNode = $graph->resource($instanceUri . '/isbn', 'bf:Isbn');
                $idNode->set('rdf:value', $instance->isbn);
                $instanceNode->addResource('bf:identifiedBy', $idNode);
            }
            $workNode->addResource('bf:hasInstance', $instanceNode);
        }

        $jsonld = $graph->serialise('jsonld');

        // Inject BIBFRAME @context after parse-encode cycle
        $decoded = json_decode($jsonld, true);
        if (is_array($decoded)) {
            if (! isset($decoded['@context'])) {
                $decoded = array_merge(
                    ['@context' => 'https://id.loc.gov/ontologies/bibframe.jsonld'],
                    is_int(key($decoded)) ? [] : ['@graph' => $decoded]
                );
            }
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $jsonld;
    }

    /**
     * Serialise to RDF/XML — delegates to existing BibframeService for canonical
     * RDF/XML output (this method is an alias for that path).
     *
     * @param int $workId  library_biblio_work.id
     * @return string       RDF/XML string.
     */
    public function toRdfXml(int $workId): string
    {
        $service = new BibframeService();
        return $service->catalogToRdf($workId, 'xml');
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    /**
     * Fetch fully-populated work data for a given work ID.
     *
     * @return array{work:object|null, instances:object, agents:object}
     */
    protected function fetchWorkData(int $workId): array
    {
        $work = DB::connection('heratio')
            ->table('library_biblio_work')
            ->where('id', $workId)
            ->first();

        if (! $work) {
            return ['work' => null, 'instances' => collect(), 'agents' => collect()];
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

    /**
     * Graceful fallback when EasyRdf is not installed.
     * Returns the RDF/XML from BibframeService with a note indicating
     * which format the Turtle/JSON-LD would have used.
     */
    protected function easyrdfFallback(int $workId, string $format): string
    {
        try {
            $service = new BibframeService();
            $rdfxml = $service->catalogToRdf($workId, 'xml');
        } catch (\Throwable $e) {
            $rdfxml = '<!-- EasyRdf not available. --><rdf:RDF/>';
            Log::warning("BibframeSerialisation fallback for work {$workId}: {$e->getMessage()}");
        }

        $note = <<<COMMENT
# BIBFRAME Turtle / JSON-LD export requires "easyrdf/easyrdf" in vendor/.
# Run: composer require easyrdf/easyrdf
# Format requested: {$format}
# Falling back to RDF/XML below.

COMMENT;
        return $note . $rdfxml;
    }
}
