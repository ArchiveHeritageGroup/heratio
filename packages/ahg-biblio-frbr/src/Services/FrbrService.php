<?php

/**
 * FrbrService — IFLA FRBR conceptual model conversion for Heratio
 *
 * Converts bibliographic catalogue records to/from the FRBR entity model:
 *   Work → Expression → Manifestation → Item
 *   (Person | Corporate Body) — responsible Agent entities
 *
 * Heratio catalogue mapping (see AhgBiblioBf\Services\BiblioWorkRepository):
 *   library_item work_key cluster → FRBR Work
 *   library_item                  → FRBR Expression / Manifestation
 *   library_copy                  → FRBR Item
 *   library_item_creator          → FRBR Agent (Person/Corporate Body)
 *
 * The work_key clustering that defines a Work is computed by this package's own
 * WorkKeyService, so FRBR is the native consumer of that column.
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

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Catalogue reader shared with the BIBFRAME package.
     *
     * FRBR and BIBFRAME need the same Work / Expression / Item / Agent view of
     * `library_item`, so this reuses `BiblioWorkRepository` rather than carrying
     * a second projection (#1417). Resolved at call time with a guard so this
     * package declares no composer dependency on ahg-biblio-bf; if that package
     * is absent the FRBR surfaces degrade to empty rather than fatal, matching
     * the hasTable() guards this package already used.
     *
     * A later refactor could move the repository into ahg-library, which both
     * consumers already depend on implicitly, and drop the guard.
     */
    protected function works(): ?object
    {
        if (! class_exists(\AhgBiblioBf\Services\BiblioWorkRepository::class)) {
            Log::warning('[FRBR] ahg-biblio-bf is not installed; catalogue reads unavailable.');

            return null;
        }

        return app(\AhgBiblioBf\Services\BiblioWorkRepository::class);
    }

    /**
     * Convert a Heratio bibliographic work to FRBR JSON.
     *
     * @param int $workId  library_item.id of the work's representative item
     * @return array FRBR entity graph
     */
    public function catalogToFrbr(int $workId): array
    {
        ['work' => $work, 'instances' => $instances, 'agents' => $agents] = $this->fetchWork($workId);

        return $this->buildFrbrGraph($work, $instances, $agents);
    }

    /**
     * Load a work from the catalogue, or fail loudly.
     *
     * @return array{work:object, instances:\Illuminate\Support\Collection, agents:\Illuminate\Support\Collection}
     */
    protected function fetchWork(int $workId): array
    {
        $data = $this->works()?->find($workId);

        if (! $data || ! $data['work']) {
            throw new \InvalidArgumentException("Bibliographic work {$workId} not found.");
        }

        return $data;
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
        ['work' => $work, 'instances' => $instances, 'agents' => $agents] = $this->fetchWork($workId);

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

        if (! $this->works()?->canWrite()) {
            Log::error('[FRBR] Import unavailable: the catalogue writer is not installed on this instance.');
            $stats['errors'] = 1;

            return $stats;
        }

        // Walk the hierarchy rather than three flat xpath sweeps. catalogToXml()
        // emits Work > frbr:realization > Expression > frbr:exemplar > Item, so
        // descending mirrors the export exactly and an Item keeps its Expression
        // and Work. The old flat version created every entity unlinked.
        foreach ($xml->xpath('//frbr:Work') ?: [] as $workNode) {
            try {
                $libraryItemId = $this->importWorkNode($workNode);
                if ($libraryItemId === null) {
                    $stats['warnings']++;
                    continue;
                }
                $stats['works']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning("[FRBR] Work import error: {$e->getMessage()}");
                continue;
            }

            foreach ($workNode->xpath('.//frbr:Expression') ?: [] as $exprNode) {
                try {
                    $this->importExpressionNode($exprNode, $libraryItemId);
                    $stats['instances']++;
                } catch (\Throwable $e) {
                    $stats['warnings']++;
                    Log::warning("[FRBR] Expression import error: {$e->getMessage()}");
                }

                foreach ($exprNode->xpath('.//frbr:Item') ?: [] as $itemNode) {
                    try {
                        if ($this->importItemNode($itemNode, $libraryItemId) === null) {
                            $stats['warnings']++;
                            continue;
                        }
                        $stats['items']++;
                    } catch (\Throwable $e) {
                        $stats['warnings']++;
                        Log::warning("[FRBR] Item import error: {$e->getMessage()}");
                    }
                }
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
        $data = $this->works()?->find($workId);

        return ($data && $data['work']) ? $data : null;
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
            $items = $this->works()?->itemsForInstance((int) $instance->id) ?? collect();

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
            $items = $this->works()?->itemsForInstance((int) $instance->id) ?? collect();

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
            // collect() rather than (array): casting a Collection to array
            // yields its protected properties (items, escapeWhenCastingToString),
            // not its elements, so the old array_map ran over the wrong data and
            // reported two nonsense agents for every work.
            'agents'   => collect($agents)->map(fn($a) => [
                'id'   => $a->id,
                'name' => $a->name ?? 'Unknown',
                'type' => $a->type ?? 'per',
            ])->values()->all(),
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
            // The catalogue projection has no work_id column - an Expression's
            // Work is the cluster it was fetched under, which is in scope here.
            'workId'   => $work->id,
        ];
    }

    protected function frbrItem(object $item, object $instance): array
    {
        return [
            'id'         => $item->id,
            'barcode'    => $item->barcode ?? null,
            'instanceId' => $instance->id,
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
     * Create or match a catalogue record from a FRBR Work node.
     *
     * Matching on title keeps a re-import from duplicating the catalogue.
     *
     * @return int|null library_item.id, or null when the node carries no label
     */
    protected function importWorkNode(\SimpleXMLElement $workNode): ?int
    {
        $title = trim((string) ($workNode->xpath('rdfs:label')[0] ?? ''));
        if ($title === '') {
            Log::warning('[FRBR] Skipped a frbr:Work with no rdfs:label.');

            return null;
        }

        $creator = trim((string) ($workNode->xpath('frbr:creator//rdfs:label')[0] ?? ''));

        $works = $this->works();
        $existing = $works?->findIdByTitle($title);
        if ($existing !== null) {
            return $existing;
        }

        return $works?->createFromImport([
            'title'   => $title,
            'creator' => $creator,
        ]);
    }

    /**
     * Apply a FRBR Expression's fields to its Work's catalogue record.
     *
     * In the library_item projection an Expression is not a separate row - the
     * Work cluster and its manifestations are the same records - so this fills
     * blank publication fields rather than inserting a duplicate.
     */
    protected function importExpressionNode(\SimpleXMLElement $exprNode, int $libraryItemId): void
    {
        $this->works()?->applyInstanceFields($libraryItemId, [
            'pub_date'  => trim((string) ($exprNode->xpath('frbr:date')[0] ?? '')),
            'publisher' => trim((string) ($exprNode->xpath('frbr:publisher')[0] ?? '')),
        ]);
    }

    /**
     * Attach a FRBR Item to its Work's catalogue record as a physical copy.
     *
     * @return int|null library_copy.id
     */
    protected function importItemNode(\SimpleXMLElement $itemNode, int $libraryItemId): ?int
    {
        $barcode = trim((string) ($itemNode->xpath('frbr:shelfMark')[0] ?? ''));

        return $this->works()?->addCopy($libraryItemId, $barcode ?: null);
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
