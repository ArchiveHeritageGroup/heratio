<?php

/**
 * BibframeSerialisationService — BIBFRAME Turtle / JSON-LD / RDF/XML export.
 *
 * Converts catalogue records (library_item) to BIBFRAME 2.0 RDF in three
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

use Illuminate\Support\Facades\Log;

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
     * Fetches the fully-populated catalogue record (library_item) including agents,
     * instances, and items; builds BIBFRAME RDF graphs using EasyRdf; and
     * outputs the Turtle serialisation.
     *
     * @param int $workId  library_item.id of the work's representative item
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
     * @param int $workId  library_item.id of the work's representative item
     * @return string       JSON-LD string.
     */
    public function toJsonLd(int $workId): string
    {
        // EasyRdf delegates JSON-LD to ml/json-ld and throws a LogicException
        // when it is absent, which would surface as a 500. Degrade to the same
        // RDF/XML fallback used when EasyRdf itself is missing.
        if (! $this->easyrdfAvailable || ! class_exists(\ML\JsonLD\JsonLD::class)) {
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
     * @param int $workId  library_item.id of the work's representative item
     * @return string       RDF/XML string.
     */
    public function toRdfXml(int $workId): string
    {
        $service = new BibframeService();
        return $service->catalogToRdf($workId, 'xml');
    }

    /**
     * Whether a serialisation can actually be produced on this instance.
     *
     * Turtle needs easyrdf/easyrdf; JSON-LD additionally needs ml/json-ld,
     * which EasyRdf delegates to. Callers should use this to return a clear
     * error rather than handing back RDF/XML under a Turtle or JSON-LD
     * content type.
     *
     * @param string $format turtle | jsonld
     */
    public function supports(string $format): bool
    {
        if (! $this->easyrdfAvailable) {
            return false;
        }

        return $format === 'jsonld' ? class_exists(\ML\JsonLD\JsonLD::class) : true;
    }

    /**
     * Human-readable reason a serialisation is unavailable.
     */
    public function unsupportedReason(string $format): string
    {
        if (! $this->easyrdfAvailable) {
            return 'BIBFRAME RDF serialisation requires easyrdf/easyrdf. Install: composer require easyrdf/easyrdf';
        }

        return 'BIBFRAME JSON-LD requires ml/json-ld. Install: composer require ml/json-ld';
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    /**
     * Fetch fully-populated work data for a given work ID.
     *
     * @return array{work:object|null, instances:object, agents:object}
     */
    protected function fetchWorkData(int $workId): array
    {
        return (new BiblioWorkRepository())->find($workId);
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

        $required = $format === 'jsonld'
            ? 'easyrdf/easyrdf and ml/json-ld'
            : 'easyrdf/easyrdf';

        $note = <<<COMMENT
# BIBFRAME {$format} export requires "{$required}" in vendor/.
# Run: composer require {$required}
# Format requested: {$format}
# Falling back to RDF/XML below.

COMMENT;
        return $note . $rdfxml;
    }
}
