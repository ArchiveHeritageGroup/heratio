<?php

declare(strict_types=1);

namespace AhgDiscovery\Services;

use AhgCore\Constants\TermId;
use Illuminate\Support\Facades\DB;

/**
 * PageIndex Service
 *
 * Builds hierarchical JSON trees ("table of contents") from source documents
 * and performs LLM-driven retrieval over them.
 *
 * Three document types supported:
 *   1. EAD finding aids — parsed from information_object + related tables
 *   2. Uploaded PDFs — text extracted via OCR service at 192.168.0.115:5006
 *   3. RiC-O metadata — queried from Fuseki via SPARQL
 *
 * @author The Archive and Heritage Group
 */
class PageIndexService
{
    private OllamaPageIndexClient $llmClient;

    /** OCR endpoint for PDF text extraction */
    private string $ocrEndpoint;

    /** Fuseki SPARQL endpoint for RiC-O queries */
    private string $fusekiEndpoint;
    private string $fusekiUsername;
    private string $fusekiPassword;

    /** Cached connection name resolved once per instance (issue #14). */
    private ?string $discoveryConn = null;

    public function __construct(?OllamaPageIndexClient $llmClient = null)
    {
        $this->llmClient = $llmClient ?? OllamaPageIndexClient::fromSettings();
        $this->loadConfig();
    }

    /**
     * Resolve the connection used for ANC content lookups. Mirrors
     * DiscoveryController::discoveryDb(); reads ahg_settings.discovery_db_connection
     * (default 'atom'); falls back to framework default if missing.
     */
    private function discoveryDb(): \Illuminate\Database\ConnectionInterface
    {
        if ($this->discoveryConn === null) {
            $name = (string) (DB::table('ahg_settings')
                ->where('setting_key', 'discovery_db_connection')
                ->value('setting_value') ?? 'atom');
            $this->discoveryConn = $name !== '' ? $name : 'atom';
        }
        try {
            return DB::connection($this->discoveryConn);
        } catch (\Throwable $e) {
            return DB::connection();
        }
    }

    private function loadConfig(): void
    {
        $this->ocrEndpoint = 'http://192.168.0.115:5006';

        // Load Fuseki config from ahg_settings (same as RicSyncService)
        $this->fusekiEndpoint = 'http://192.168.0.112:3030/ric';
        $this->fusekiUsername = 'admin';
        $this->fusekiPassword = '';

        try {
            $rows = DB::table('ahg_settings')
                ->where('setting_group', 'fuseki')
                ->get();

            foreach ($rows as $row) {
                match ($row->setting_key) {
                    'fuseki_endpoint' => $this->fusekiEndpoint = $row->setting_value,
                    'fuseki_username' => $this->fusekiUsername = $row->setting_value,
                    'fuseki_password' => $this->fusekiPassword = $row->setting_value,
                    default => null,
                };
            }

            // Also check for pageindex-specific OCR override
            $ocrRow = DB::table('ahg_settings')
                ->where('setting_group', 'pageindex')
                ->where('setting_key', 'ocr_endpoint')
                ->first();

            if ($ocrRow) {
                $this->ocrEndpoint = $ocrRow->setting_value;
            }
        } catch (\Exception $e) {
            // Use defaults
        }
    }

    // =========================================================================
    // PUBLIC API: BUILD TREE
    // =========================================================================

    /**
     * Build a PageIndex tree for a given object.
     *
     * Sets status to 'building', extracts document content, calls LLM to build
     * the tree, then stores it with status 'ready' or 'error'.
     *
     * @param int    $objectId   The information_object.id or external doc ID
     * @param string $objectType One of: ead, pdf, rico
     * @param string $culture    Language culture for i18n fields (default: en)
     *
     * @return array ['success' => bool, 'tree_id' => int|null, 'tree' => array|null,
     *                'node_count' => int, 'model' => string, 'error' => string|null]
     */
    public function buildTree(int $objectId, string $objectType, string $culture = 'en'): array
    {
        // Upsert the row with status=building
        $existingId = DB::table('ahg_pageindex_tree')
            ->where('object_id', $objectId)
            ->where('object_type', $objectType)
            ->value('id');

        if ($existingId) {
            DB::table('ahg_pageindex_tree')
                ->where('id', $existingId)
                ->update([
                    'status' => 'building',
                    'error_message' => null,
                ]);
            $treeId = (int) $existingId;
        } else {
            $treeId = (int) DB::table('ahg_pageindex_tree')->insertGetId([
                'object_id' => $objectId,
                'object_type' => $objectType,
                'tree_json' => '{}',
                'status' => 'building',
                'created_at' => now(),
            ]);
        }

        try {
            // Extract content based on document type
            $extraction = match ($objectType) {
                'ead' => $this->extractEadContent($objectId, $culture),
                'pdf' => $this->extractPdfContent($objectId),
                'rico' => $this->extractRicoContent($objectId),
                default => throw new \InvalidArgumentException("Unknown object type: {$objectType}"),
            };

            if (!$extraction['success']) {
                $this->markError($treeId, $extraction['error']);

                return [
                    'success' => false,
                    'tree_id' => $treeId,
                    'tree' => null,
                    'node_count' => 0,
                    'model' => '',
                    'error' => $extraction['error'],
                ];
            }

            $contentHash = hash('sha256', $extraction['text']);

            // Call LLM to build the tree
            $result = $this->llmClient->buildTree(
                $extraction['text'],
                $objectType,
                $extraction['metadata'] ?? []
            );

            if (!$result['success']) {
                $this->markError($treeId, $result['error']);

                return [
                    'success' => false,
                    'tree_id' => $treeId,
                    'tree' => null,
                    'node_count' => 0,
                    'model' => $result['model'] ?? '',
                    'error' => $result['error'],
                ];
            }

            // Store the tree
            DB::table('ahg_pageindex_tree')
                ->where('id', $treeId)
                ->update([
                    'tree_json' => json_encode($result['tree'], JSON_UNESCAPED_UNICODE),
                    'status' => 'ready',
                    'error_message' => null,
                    'indexed_at' => now(),
                    'model_used' => $result['model'],
                    'node_count' => $result['node_count'] ?? 0,
                    'source_hash' => $contentHash,
                ]);

            return [
                'success' => true,
                'tree_id' => $treeId,
                'tree' => $result['tree'],
                'node_count' => $result['node_count'] ?? 0,
                'model' => $result['model'],
                'error' => null,
            ];
        } catch (\Exception $e) {
            $this->markError($treeId, $e->getMessage());

            return [
                'success' => false,
                'tree_id' => $treeId,
                'tree' => null,
                'node_count' => 0,
                'model' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // PUBLIC API: QUERY
    // =========================================================================

    /**
     * Query a single PageIndex tree with a natural language query.
     *
     * @param int    $treeId The ahg_pageindex_tree.id
     * @param string $query  The user's search query
     * @param int|null $userId Optional user ID for logging
     *
     * @return array ['success' => bool, 'matches' => array, 'reasoning' => string,
     *                'tree_path' => array, 'model' => string, 'error' => string|null]
     */
    public function query(int $treeId, string $query, ?int $userId = null): array
    {
        $row = DB::table('ahg_pageindex_tree')
            ->where('id', $treeId)
            ->where('status', 'ready')
            ->first();

        if (!$row) {
            return [
                'success' => false,
                'matches' => [],
                'reasoning' => '',
                'tree_path' => [],
                'model' => '',
                'error' => 'Tree not found or not ready',
            ];
        }

        $tree = json_decode($row->tree_json, true);
        if (!$tree) {
            return [
                'success' => false,
                'matches' => [],
                'reasoning' => '',
                'tree_path' => [],
                'model' => '',
                'error' => 'Failed to decode stored tree JSON',
            ];
        }

        $result = $this->llmClient->retrieveNodes($tree, $query);

        // Build breadcrumb paths for matched nodes
        $enrichedMatches = [];
        foreach ($result['matches'] ?? [] as $match) {
            $path = $this->findNodePath($tree, $match['node_id']);
            $node = $this->findNode($tree, $match['node_id']);
            $enrichedMatches[] = array_merge($match, [
                'breadcrumb' => $path,
                'node_title' => $node['title'] ?? '',
                'node_summary' => $node['summary'] ?? '',
                'node_level' => $node['level'] ?? '',
                'node_keywords' => $node['keywords'] ?? [],
            ]);
        }

        // Log the query
        $this->logQuery($query, $treeId, $enrichedMatches, $result, $userId);

        return [
            'success' => $result['success'],
            'matches' => $enrichedMatches,
            'reasoning' => $result['reasoning'] ?? '',
            'tree_path' => [],
            'model' => $result['model'] ?? '',
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Search across all ready trees for a query.
     *
     * @param string $query       The user's search query
     * @param string|null $objectType Filter by object type (ead, pdf, rico) or null for all
     * @param int    $limit       Max trees to search
     * @param int|null $userId    Optional user ID for logging
     *
     * @return array ['success' => bool, 'results' => array, 'total_matches' => int]
     */
    public function searchAll(string $query, ?string $objectType = null, int $limit = 20, ?int $userId = null): array
    {
        $treeQuery = DB::table('ahg_pageindex_tree')
            ->where('status', 'ready');

        if ($objectType) {
            $treeQuery->where('object_type', $objectType);
        }

        $trees = $treeQuery->orderByDesc('indexed_at')
            ->limit($limit)
            ->get();

        $allResults = [];
        $totalMatches = 0;

        foreach ($trees as $treeRow) {
            $result = $this->query((int) $treeRow->id, $query, $userId);

            if ($result['success'] && !empty($result['matches'])) {
                $allResults[] = [
                    'tree_id' => (int) $treeRow->id,
                    'object_id' => (int) $treeRow->object_id,
                    'object_type' => $treeRow->object_type,
                    'matches' => $result['matches'],
                    'reasoning' => $result['reasoning'],
                    'model' => $result['model'],
                ];
                $totalMatches += count($result['matches']);
            }
        }

        // Sort results by best match relevance
        usort($allResults, function ($a, $b) {
            $aMax = max(array_column($a['matches'], 'relevance'));
            $bMax = max(array_column($b['matches'], 'relevance'));

            return $bMax <=> $aMax;
        });

        return [
            'success' => true,
            'results' => $allResults,
            'total_matches' => $totalMatches,
        ];
    }

    // =========================================================================
    // PUBLIC API: STATUS
    // =========================================================================

    /**
     * Get the indexing status for an object.
     */
    public function getStatus(int $objectId, string $objectType): ?array
    {
        $row = DB::table('ahg_pageindex_tree')
            ->where('object_id', $objectId)
            ->where('object_type', $objectType)
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'tree_id' => (int) $row->id,
            'status' => $row->status,
            'indexed_at' => $row->indexed_at,
            'model_used' => $row->model_used,
            'node_count' => (int) $row->node_count,
            'error_message' => $row->error_message,
            'source_hash' => $row->source_hash,
        ];
    }

    /**
     * Get the stored tree JSON for an object.
     */
    public function getTree(int $objectId, string $objectType): ?array
    {
        $row = DB::table('ahg_pageindex_tree')
            ->where('object_id', $objectId)
            ->where('object_type', $objectType)
            ->where('status', 'ready')
            ->first();

        if (!$row) {
            return null;
        }

        return json_decode($row->tree_json, true);
    }

    // =========================================================================
    // DOCUMENT TYPE 1: EAD (Finding Aids from MySQL)
    // =========================================================================

    /**
     * Extract EAD-style hierarchical content from information_object tables.
     *
     * Uses the same query patterns as InformationObjectRepository.
     */
    private function extractEadContent(int $objectId, string $culture): array
    {
        // Get the root information object
        $db = $this->discoveryDb();
        $root = $db->table('information_object as i')
            ->join('object as o', 'i.id', '=', 'o.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('i.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->where('i.id', $objectId)
            ->select(
                'i.id',
                'i.identifier',
                'i.level_of_description_id',
                'i.parent_id',
                'i.lft',
                'i.rgt',
                'i18n.title',
                'i18n.scope_and_content as scopeAndContent',
                'i18n.archival_history as archivalHistory',
                'i18n.acquisition',
                'i18n.arrangement',
                'i18n.access_conditions as accessConditions',
                'i18n.reproduction_conditions as reproductionConditions',
                'i18n.physical_characteristics as physicalCharacteristics',
                'i18n.finding_aids as findingAids',
                'i18n.extent_and_medium as extentAndMedium',
                'i18n.location_of_originals as locationOfOriginals',
                'i18n.location_of_copies as locationOfCopies',
                'i18n.related_units_of_description as relatedUnitsOfDescription',
                'i18n.rules',
                'i18n.sources',
                'i18n.revision_history as revisionHistory'
            )
            ->first();

        if (!$root) {
            return ['success' => false, 'text' => '', 'error' => "Information object {$objectId} not found"];
        }

        // Get the level of description label
        $levelLabel = $this->getLevelLabel($root->level_of_description_id, $culture);

        // Get children within the nested set range
        $children = $db->table('information_object as i')
            ->join('object as o', 'i.id', '=', 'o.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('i.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->where('i.lft', '>', $root->lft)
            ->where('i.rgt', '<', $root->rgt)
            ->select(
                'i.id',
                'i.identifier',
                'i.level_of_description_id',
                'i.parent_id',
                'i.lft',
                'i18n.title',
                'i18n.scope_and_content as scopeAndContent',
                'i18n.extent_and_medium as extentAndMedium',
                'i18n.arrangement'
            )
            ->orderBy('i.lft')
            ->limit(500)
            ->get();

        // Get events (dates)
        $events = $db->table('event as e')
            ->leftJoin('event_i18n as ei18n', function ($join) use ($culture) {
                $join->on('e.id', '=', 'ei18n.id')
                     ->where('ei18n.culture', $culture);
            })
            ->where('e.object_id', $objectId)
            ->select('ei18n.date', 'ei18n.name as eventType', 'e.start_date', 'e.end_date')
            ->get();

        // Get creators (via event)
        $creators = $db->table('event as e')
            ->join('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('e.actor_id', '=', 'ai.id')
                     ->where('ai.culture', $culture);
            })
            ->where('e.object_id', $objectId)
            ->where('e.type_id', TermId::EVENT_TYPE_CREATION)
            ->select('ai.authorized_form_of_name as name')
            ->get();

        // Get repository name
        $repository = $db->table('information_object as i')
            ->join('repository as r', 'i.repository_id', '=', 'r.id')
            ->join('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('r.id', '=', 'ai.id')
                     ->where('ai.culture', $culture);
            })
            ->where('i.id', $objectId)
            ->value('ai.authorized_form_of_name');

        // Build text representation
        $parts = [];
        $parts[] = "Title: " . ($root->title ?? 'Untitled');
        $parts[] = "Identifier: " . ($root->identifier ?? '');
        $parts[] = "Level: " . ($levelLabel ?? '');

        if ($repository) {
            $parts[] = "Repository: {$repository}";
        }

        foreach ($creators as $creator) {
            $parts[] = "Creator: " . $creator->name;
        }

        foreach ($events as $event) {
            $dateStr = $event->date ?: trim(($event->start_date ?? '') . ' - ' . ($event->end_date ?? ''), ' -');
            if ($dateStr) {
                $parts[] = "Date: {$dateStr}";
            }
        }

        $fields = [
            'scopeAndContent' => 'Scope and Content',
            'archivalHistory' => 'Archival History',
            'acquisition' => 'Immediate Source of Acquisition',
            'arrangement' => 'Arrangement',
            'accessConditions' => 'Conditions Governing Access',
            'reproductionConditions' => 'Conditions Governing Reproduction',
            'physicalCharacteristics' => 'Physical Characteristics',
            'findingAids' => 'Finding Aids',
            'extentAndMedium' => 'Extent and Medium',
            'locationOfOriginals' => 'Location of Originals',
            'locationOfCopies' => 'Location of Copies',
            'relatedUnitsOfDescription' => 'Related Units of Description',
            'rules' => 'Rules or Conventions',
            'sources' => 'Sources',
            'revisionHistory' => 'Revision History',
        ];

        foreach ($fields as $field => $label) {
            $value = $root->$field ?? '';
            if (!empty(trim(strip_tags($value)))) {
                $parts[] = "\n{$label}:\n" . strip_tags($value);
            }
        }

        // Append children as hierarchical content
        if ($children->isNotEmpty()) {
            $parts[] = "\n--- Child records ({$children->count()}) ---";
            foreach ($children as $child) {
                $childLevel = $this->getLevelLabel($child->level_of_description_id, $culture);
                $line = "[{$childLevel}] " . ($child->title ?? 'Untitled');
                if (!empty($child->identifier)) {
                    $line .= " ({$child->identifier})";
                }
                $parts[] = $line;
                $scope = strip_tags($child->scopeAndContent ?? '');
                if (!empty(trim($scope))) {
                    $parts[] = "  " . mb_substr($scope, 0, 300);
                }
            }
        }

        $text = implode("\n", $parts);

        return [
            'success' => true,
            'text' => $text,
            'metadata' => [
                'title' => $root->title ?? '',
                'identifier' => $root->identifier ?? '',
                'level' => $levelLabel ?? '',
                'repository' => $repository ?? '',
                'child_count' => $children->count(),
            ],
            'error' => null,
        ];
    }

    // =========================================================================
    // DOCUMENT TYPE 2: PDF (via OCR service)
    // =========================================================================

    /**
     * Extract text from a PDF digital object via the OCR service.
     */
    private function extractPdfContent(int $objectId): array
    {
        // Find the digital object's file path
        $db = $this->discoveryDb();
        $digitalObject = $db->table('digital_object as do')
            ->where('do.object_id', $objectId)
            ->select('do.id', 'do.path', 'do.name', 'do.mime_type')
            ->first();

        if (!$digitalObject) {
            return ['success' => false, 'text' => '', 'error' => "No digital object found for object {$objectId}"];
        }

        // Check if it's a PDF
        $isPdf = str_contains($digitalObject->mime_type ?? '', 'pdf')
            || str_ends_with(strtolower($digitalObject->name ?? ''), '.pdf');

        if (!$isPdf) {
            return ['success' => false, 'text' => '', 'error' => "Digital object is not a PDF (mime: {$digitalObject->mime_type})"];
        }

        // Build the full file path
        $uploadPath = config('heratio.uploads_path');
        $filePath = rtrim($uploadPath, '/') . '/' . ltrim($digitalObject->path, '/');

        if (!file_exists($filePath)) {
            return ['success' => false, 'text' => '', 'error' => "PDF file not found: {$filePath}"];
        }

        // Check if we already have OCR text in iiif_ocr_text table
        $existingOcr = $db->table('iiif_ocr_text')
            ->where('digital_object_id', $digitalObject->id)
            ->whereNotNull('full_text')
            ->where('full_text', '!=', '')
            ->value('full_text');

        if ($existingOcr) {
            $title = $db->table('information_object_i18n')
                ->where('id', $objectId)
                ->where('culture', 'en')
                ->value('title');

            return [
                'success' => true,
                'text' => $existingOcr,
                'metadata' => [
                    'title' => $title ?? $digitalObject->name,
                    'source' => 'cached_ocr',
                ],
                'error' => null,
            ];
        }

        // Call OCR service to extract text
        $text = $this->callOcrService($filePath);

        if ($text === null) {
            return ['success' => false, 'text' => '', 'error' => 'OCR service failed or unavailable'];
        }

        $title = $db->table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', 'en')
            ->value('title');

        return [
            'success' => true,
            'text' => $text,
            'metadata' => [
                'title' => $title ?? $digitalObject->name,
                'source' => 'ocr_extraction',
                'file' => $digitalObject->name,
            ],
            'error' => null,
        ];
    }

    /**
     * Call the OCR service to extract text from a PDF.
     */
    private function callOcrService(string $filePath): ?string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->ocrEndpoint, '/') . '/ocr/extract',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($filePath, 'application/pdf', basename($filePath)),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            error_log("[PageIndex] OCR service error: {$error}");

            return null;
        }

        if ($httpCode >= 400) {
            error_log("[PageIndex] OCR service HTTP {$httpCode}: {$response}");

            return null;
        }

        $data = json_decode($response, true);

        // Handle both direct text response and JSON response
        if (is_array($data)) {
            return $data['text'] ?? $data['full_text'] ?? $data['content'] ?? null;
        }

        // If response is plain text
        if (is_string($response) && !empty(trim($response))) {
            return $response;
        }

        return null;
    }

    // =========================================================================
    // DOCUMENT TYPE 3: RiC-O (via Fuseki SPARQL)
    // =========================================================================

    /**
     * Extract RiC-O metadata from Fuseki triplestore via SPARQL.
     */
    private function extractRicoContent(int $objectId): array
    {
        $baseUri = 'https://archives.theahg.co.za/ric/';
        $entityUri = $baseUri . 'informationobject/' . $objectId;

        // Query all triples for this entity and its related entities
        $sparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

SELECT ?p ?o ?oLabel WHERE {
  <{$entityUri}> ?p ?o .
  OPTIONAL {
    ?o rdfs:label ?oLabel .
  }
}
ORDER BY ?p
SPARQL;

        $bindings = $this->executeSparqlQuery($sparql);

        if (empty($bindings)) {
            return ['success' => false, 'text' => '', 'error' => "No RiC-O triples found for entity {$entityUri}"];
        }

        // Also get related entities (one level deep)
        $relatedSparql = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

SELECT ?relation ?related ?relatedLabel ?relatedType WHERE {
  {
    <{$entityUri}> ?relation ?related .
    FILTER(isIRI(?related))
    OPTIONAL { ?related rdfs:label ?relatedLabel . }
    OPTIONAL { ?related a ?relatedType . }
  }
  UNION
  {
    ?related ?relation <{$entityUri}> .
    FILTER(isIRI(?related))
    OPTIONAL { ?related rdfs:label ?relatedLabel . }
    OPTIONAL { ?related a ?relatedType . }
  }
}
LIMIT 100
SPARQL;

        $relatedBindings = $this->executeSparqlQuery($relatedSparql);

        // Build text representation
        $parts = [];
        $parts[] = "RiC-O Entity: {$entityUri}";
        $parts[] = "";

        // Group properties
        $properties = [];
        foreach ($bindings as $binding) {
            $predicate = $binding['p']['value'] ?? '';
            $object = $binding['o']['value'] ?? '';
            $label = $binding['oLabel']['value'] ?? '';

            // Clean up predicate to short form
            $shortPred = preg_replace('#^.*/([^/]+)$#', '$1', $predicate);
            $displayValue = $label ?: $object;

            $properties[$shortPred][] = $displayValue;
        }

        foreach ($properties as $pred => $values) {
            $parts[] = "{$pred}: " . implode('; ', array_unique($values));
        }

        // Add related entities
        if (!empty($relatedBindings)) {
            $parts[] = "\n--- Related Entities ---";
            $seen = [];
            foreach ($relatedBindings as $binding) {
                $related = $binding['related']['value'] ?? '';
                if (isset($seen[$related])) {
                    continue;
                }
                $seen[$related] = true;

                $relation = preg_replace('#^.*/([^/]+)$#', '$1', $binding['relation']['value'] ?? '');
                $label = $binding['relatedLabel']['value'] ?? '';
                $type = preg_replace('#^.*/([^/]+)$#', '$1', $binding['relatedType']['value'] ?? '');

                $line = "[{$relation}] " . ($label ?: $related);
                if ($type) {
                    $line .= " ({$type})";
                }
                $parts[] = $line;
            }
        }

        $text = implode("\n", $parts);

        // Extract a title from the properties
        $title = $properties['title'][0]
            ?? $properties['label'][0]
            ?? $properties['name'][0]
            ?? "RiC Entity #{$objectId}";

        return [
            'success' => true,
            'text' => $text,
            'metadata' => [
                'title' => $title,
                'uri' => $entityUri,
                'triple_count' => count($bindings),
                'related_count' => count($relatedBindings),
            ],
            'error' => null,
        ];
    }

    /**
     * Execute a SPARQL SELECT query against Fuseki.
     */
    private function executeSparqlQuery(string $sparql): array
    {
        $ch = curl_init($this->fusekiEndpoint . '/query');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $sparql,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/sparql-query',
                'Accept: application/json',
            ],
            CURLOPT_USERPWD => "{$this->fusekiUsername}:{$this->fusekiPassword}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error || $response === false || $response === '') {
            error_log("[PageIndex] SPARQL query error: " . ($error ?: "HTTP {$httpCode}"));

            return [];
        }

        if ($httpCode >= 400) {
            error_log("[PageIndex] SPARQL query HTTP {$httpCode}: {$response}");

            return [];
        }

        $data = json_decode($response, true);

        return $data['results']['bindings'] ?? [];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get the level of description label from term_i18n.
     */
    private function getLevelLabel(?int $termId, string $culture): string
    {
        if (!$termId) {
            return '';
        }

        $db = $this->discoveryDb();
        $label = $db->table('term_i18n')
            ->where('id', $termId)
            ->where('culture', $culture)
            ->value('name');

        return $label ?? '';
    }

    /**
     * Mark a tree record as errored.
     */
    private function markError(int $treeId, string $error): void
    {
        DB::table('ahg_pageindex_tree')
            ->where('id', $treeId)
            ->update([
                'status' => 'error',
                'error_message' => mb_substr($error, 0, 65535),
            ]);
    }

    /**
     * Log a query to ahg_pageindex_query_log.
     */
    private function logQuery(string $query, int $treeId, array $matches, array $result, ?int $userId): void
    {
        try {
            DB::table('ahg_pageindex_query_log')->insert([
                'query_text' => mb_substr($query, 0, 65535),
                'tree_id' => $treeId,
                'matched_node_ids' => json_encode(array_column($matches, 'node_id')),
                'result_count' => count($matches),
                'reasoning_text' => mb_substr($result['reasoning'] ?? '', 0, 65535),
                'model_used' => $result['model'] ?? null,
                'response_ms' => $result['generation_time_ms'] ?? null,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            error_log("[PageIndex] Failed to log query: " . $e->getMessage());
        }
    }

    /**
     * Find the breadcrumb path to a node in the tree.
     *
     * @return array Array of ['id' => ..., 'title' => ...] from root to target node
     */
    private function findNodePath(array $node, string $targetId, array $currentPath = []): array
    {
        $currentPath[] = [
            'id' => $node['id'] ?? '',
            'title' => $node['title'] ?? '',
            'level' => $node['level'] ?? '',
        ];

        if (($node['id'] ?? '') === $targetId) {
            return $currentPath;
        }

        foreach ($node['children'] ?? [] as $child) {
            $result = $this->findNodePath($child, $targetId, $currentPath);
            if (!empty($result)) {
                return $result;
            }
        }

        return [];
    }

    /**
     * Find a specific node in the tree by ID.
     */
    private function findNode(array $node, string $targetId): ?array
    {
        if (($node['id'] ?? '') === $targetId) {
            return $node;
        }

        foreach ($node['children'] ?? [] as $child) {
            $found = $this->findNode($child, $targetId);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
