<?php

declare(strict_types=1);

namespace AhgDiscovery\Services;

use Illuminate\Support\Facades\DB;

/**
 * Ollama PageIndex Client
 *
 * Handles two LLM calls per query for the PageIndex retrieval system:
 *   1. Tree construction — analyse document structure, return JSON tree
 *   2. Retrieval reasoning — given tree + query, return ranked node_ids
 *
 * Uses Ollama chat endpoint at http://192.168.0.112:11434/api/chat
 * Model: llama3.1:8b (configurable)
 *
 * @author The Archive and Heritage Group
 */
class OllamaPageIndexClient
{
    private string $endpoint;
    private string $model;
    private int $timeout;
    private float $temperature;
    private int $maxRetries;

    public function __construct(array $config = [])
    {
        $this->endpoint = rtrim($config['endpoint'] ?? 'http://192.168.0.112:11434', '/');
        $this->model = $config['model'] ?? 'llama3.1:8b';
        $this->timeout = (int) ($config['timeout'] ?? 300);
        $this->temperature = (float) ($config['temperature'] ?? 0.3);
        $this->maxRetries = (int) ($config['max_retries'] ?? 2);
    }

    /**
     * Load config from ahg_settings table if available.
     */
    public static function fromSettings(): self
    {
        $config = [];

        try {
            $rows = DB::table('ahg_settings')
                ->where('setting_group', 'pageindex')
                ->get();

            foreach ($rows as $row) {
                $config[$row->setting_key] = $row->setting_value;
            }
        } catch (\Exception $e) {
            // Table may not exist; use defaults
        }

        return new self($config);
    }

    // =========================================================================
    // CALL 1: TREE CONSTRUCTION
    // =========================================================================

    /**
     * Build a hierarchical JSON tree from document content.
     *
     * The LLM analyses the document structure and returns a table-of-contents
     * style JSON tree with node IDs, titles, summaries, and child nodes.
     *
     * @param string $documentText The full text content of the document
     * @param string $documentType One of: ead, pdf, rico
     * @param array  $metadata     Optional metadata (title, identifier, level, dates)
     *
     * @return array ['success' => bool, 'tree' => array|null, 'model' => string,
     *                'tokens_used' => int, 'generation_time_ms' => int, 'error' => string|null]
     */
    public function buildTree(string $documentText, string $documentType, array $metadata = []): array
    {
        $systemPrompt = $this->getTreeConstructionSystemPrompt($documentType);
        $userPrompt = $this->getTreeConstructionUserPrompt($documentText, $documentType, $metadata);

        $result = $this->chat($systemPrompt, $userPrompt, [
            'temperature' => 0.2,
            'num_predict' => 4000,
        ]);

        if (!$result['success']) {
            return $result + ['tree' => null];
        }

        // Parse the JSON tree from the LLM response
        $tree = $this->extractJsonFromResponse($result['text']);

        if ($tree === null) {
            return [
                'success' => false,
                'tree' => null,
                'model' => $result['model'],
                'tokens_used' => $result['tokens_used'],
                'generation_time_ms' => $result['generation_time_ms'],
                'error' => 'Failed to parse JSON tree from LLM response',
                'raw_response' => $result['text'],
            ];
        }

        // Assign IDs to nodes if missing
        $tree = $this->ensureNodeIds($tree);

        return [
            'success' => true,
            'tree' => $tree,
            'model' => $result['model'],
            'tokens_used' => $result['tokens_used'],
            'generation_time_ms' => $result['generation_time_ms'],
            'node_count' => $this->countNodes($tree),
            'error' => null,
        ];
    }

    // =========================================================================
    // CALL 2: RETRIEVAL REASONING
    // =========================================================================

    /**
     * Given a tree and a query, reason about which nodes are relevant.
     *
     * Returns ranked node IDs with reasoning explanations.
     *
     * @param array  $tree  The hierarchical tree JSON (from buildTree)
     * @param string $query The user's search query
     *
     * @return array ['success' => bool, 'matches' => array, 'reasoning' => string,
     *                'model' => string, 'tokens_used' => int, 'generation_time_ms' => int,
     *                'error' => string|null]
     */
    public function retrieveNodes(array $tree, string $query): array
    {
        $systemPrompt = $this->getRetrievalSystemPrompt();
        $userPrompt = $this->getRetrievalUserPrompt($tree, $query);

        $result = $this->chat($systemPrompt, $userPrompt, [
            'temperature' => 0.1,
            'num_predict' => 2000,
        ]);

        if (!$result['success']) {
            return $result + ['matches' => [], 'reasoning' => ''];
        }

        // Parse the matches from the LLM response
        $parsed = $this->parseRetrievalResponse($result['text']);

        return [
            'success' => true,
            'matches' => $parsed['matches'],
            'reasoning' => $parsed['reasoning'],
            'model' => $result['model'],
            'tokens_used' => $result['tokens_used'],
            'generation_time_ms' => $result['generation_time_ms'],
            'error' => null,
        ];
    }

    // =========================================================================
    // HEALTH CHECK
    // =========================================================================

    /**
     * Check if Ollama is available and the model is loaded.
     */
    public function isAvailable(): bool
    {
        $response = $this->request('GET', '/api/tags', null, 5);

        return $response !== null && isset($response['models']);
    }

    /**
     * Get health info including available models.
     */
    public function getHealth(): array
    {
        $response = $this->request('GET', '/api/tags', null, 5);

        if (!$response) {
            return [
                'status' => 'error',
                'endpoint' => $this->endpoint,
                'model' => $this->model,
                'error' => 'Cannot connect to Ollama at ' . $this->endpoint,
            ];
        }

        $models = [];
        foreach ($response['models'] ?? [] as $m) {
            $models[] = $m['name'] ?? 'unknown';
        }

        $hasModel = in_array($this->model, $models, true);

        return [
            'status' => $hasModel ? 'ok' : 'model_missing',
            'endpoint' => $this->endpoint,
            'model' => $this->model,
            'available_models' => $models,
            'model_loaded' => $hasModel,
            'error' => $hasModel ? null : "Model {$this->model} not found. Available: " . implode(', ', $models),
        ];
    }

    // =========================================================================
    // PROMPTS
    // =========================================================================

    private function getTreeConstructionSystemPrompt(string $documentType): string
    {
        $typeDescriptions = [
            'ead' => 'an EAD (Encoded Archival Description) finding aid with hierarchical levels of description (fonds, series, sub-series, files, items)',
            'pdf' => 'a PDF document with sections, chapters, headings, and paragraphs',
            'rico' => 'RiC-O (Records in Contexts Ontology) metadata describing archival entities and their relationships',
        ];

        $typeDesc = $typeDescriptions[$documentType] ?? 'an archival document';

        return <<<PROMPT
You are a document structure analyser for an archival management system.

Your task is to build a hierarchical JSON tree (table of contents) from {$typeDesc}.

Each node in the tree MUST have:
- "id": a unique string identifier (e.g., "n1", "n2", "n1.1")
- "title": a short descriptive title for this section/level
- "summary": a 1-2 sentence summary of what this section contains
- "level": the hierarchical level (e.g., "fonds", "series", "sub-series", "file", "item", "section", "chapter", "paragraph")
- "children": an array of child nodes (empty array if leaf node)
- "keywords": an array of 3-5 key terms for this section

Rules:
1. Preserve the original document hierarchy faithfully
2. Every piece of content must belong to at least one node
3. Summaries must be factual — do not invent information not in the source
4. The root node represents the entire document
5. Return ONLY valid JSON — no markdown, no explanation, no preamble

Output format: a single JSON object representing the root node.
PROMPT;
    }

    private function getTreeConstructionUserPrompt(string $documentText, string $documentType, array $metadata): string
    {
        $metaBlock = '';
        if (!empty($metadata)) {
            $metaParts = [];
            foreach ($metadata as $key => $value) {
                if (!empty($value)) {
                    $metaParts[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
                }
            }
            if ($metaParts) {
                $metaBlock = "Document Metadata:\n" . implode("\n", $metaParts) . "\n\n";
            }
        }

        // Truncate text if too long for context window (keep ~6000 chars for 8b model)
        $maxTextLen = 12000;
        $truncated = '';
        if (mb_strlen($documentText) > $maxTextLen) {
            $documentText = mb_substr($documentText, 0, $maxTextLen);
            $truncated = "\n\n[Document truncated at {$maxTextLen} characters]";
        }

        return <<<PROMPT
{$metaBlock}Document Type: {$documentType}

Document Content:
---
{$documentText}{$truncated}
---

Build the hierarchical JSON tree for this document. Return ONLY the JSON.
PROMPT;
    }

    private function getRetrievalSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a retrieval reasoning engine for an archival management system.

Given a hierarchical tree index of a document and a user query, determine which nodes in the tree are relevant to answering the query.

For each relevant node, explain WHY it matches the query.

Output format (JSON only, no markdown):
{
  "matches": [
    {
      "node_id": "n1.2",
      "relevance": 0.95,
      "reason": "This series contains correspondence about the queried topic"
    }
  ],
  "reasoning": "Brief overall explanation of the search strategy and findings"
}

Rules:
1. Return ALL relevant nodes, ranked by relevance (1.0 = perfect match, 0.0 = irrelevant)
2. Only include nodes with relevance >= 0.3
3. Consider parent nodes as context — a child node inherits relevance from its parent's topic
4. The "reason" field must reference specific content from the node, not generic statements
5. Return ONLY valid JSON — no markdown fences, no preamble
PROMPT;
    }

    private function getRetrievalUserPrompt(array $tree, string $query): string
    {
        $treeJson = json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Truncate tree if too large
        $maxLen = 10000;
        if (strlen($treeJson) > $maxLen) {
            // Strip summaries to reduce size
            $compactTree = $this->compactTree($tree);
            $treeJson = json_encode($compactTree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return <<<PROMPT
User Query: {$query}

Document Tree Index:
{$treeJson}

Which nodes are relevant to this query? Return JSON only.
PROMPT;
    }

    // =========================================================================
    // HTTP / CHAT
    // =========================================================================

    /**
     * Send a chat completion to Ollama /api/chat endpoint.
     */
    private function chat(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $startTime = microtime(true);

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'stream' => false,
            'options' => [
                'num_predict' => $options['num_predict'] ?? 2000,
                'temperature' => $options['temperature'] ?? $this->temperature,
            ],
        ];

        $response = null;
        $lastError = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            $response = $this->request('POST', '/api/chat', $payload);

            if ($response !== null && !isset($response['error'])) {
                break;
            }

            $lastError = $response['error'] ?? 'No response from Ollama';

            if ($attempt < $this->maxRetries) {
                usleep(500000 * ($attempt + 1)); // 0.5s, 1s backoff
            }
        }

        $generationTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'text' => null,
                'model' => $this->model,
                'tokens_used' => 0,
                'generation_time_ms' => $generationTimeMs,
                'error' => $lastError ?? 'Failed to get response from Ollama',
            ];
        }

        $text = $response['message']['content'] ?? '';
        $tokensUsed = ($response['prompt_eval_count'] ?? 0) + ($response['eval_count'] ?? 0);

        return [
            'success' => true,
            'text' => trim($text),
            'model' => $response['model'] ?? $this->model,
            'tokens_used' => $tokensUsed,
            'generation_time_ms' => $generationTimeMs,
            'error' => null,
        ];
    }

    /**
     * Make HTTP request to Ollama API.
     */
    private function request(string $method, string $endpoint, ?array $data = null, ?int $timeout = null): ?array
    {
        $ch = curl_init();
        $url = $this->endpoint . $endpoint;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout ?? $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ];

        if ($method === 'POST' && $data !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            error_log("[PageIndex] Ollama API error ({$endpoint}): {$error}");

            return null;
        }

        if ($httpCode >= 400) {
            error_log("[PageIndex] Ollama API HTTP {$httpCode} ({$endpoint}): {$response}");

            return ['error' => "HTTP {$httpCode}: " . ($response ?: 'Unknown error')];
        }

        return json_decode($response, true);
    }

    // =========================================================================
    // PARSING HELPERS
    // =========================================================================

    /**
     * Extract JSON from an LLM response that may contain markdown fences or preamble.
     */
    private function extractJsonFromResponse(string $text): ?array
    {
        // Try direct parse first
        $decoded = json_decode($text, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        // Strip markdown code fences
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }
        }

        // Try to find JSON object in the text
        if (preg_match('/\{[\s\S]*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Parse the retrieval reasoning response into matches array.
     */
    private function parseRetrievalResponse(string $text): array
    {
        $json = $this->extractJsonFromResponse($text);

        if ($json === null) {
            return ['matches' => [], 'reasoning' => 'Failed to parse LLM response'];
        }

        $matches = [];
        foreach ($json['matches'] ?? [] as $match) {
            if (empty($match['node_id'])) {
                continue;
            }
            $matches[] = [
                'node_id' => (string) $match['node_id'],
                'relevance' => (float) ($match['relevance'] ?? 0.5),
                'reason' => (string) ($match['reason'] ?? ''),
            ];
        }

        // Sort by relevance descending
        usort($matches, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

        return [
            'matches' => $matches,
            'reasoning' => (string) ($json['reasoning'] ?? ''),
        ];
    }

    /**
     * Ensure every node in the tree has an "id" field.
     */
    private function ensureNodeIds(array $node, string $prefix = 'n', int &$counter = 0): array
    {
        if (empty($node['id'])) {
            $counter++;
            $node['id'] = $prefix . $counter;
        }

        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $i => &$child) {
                $child = $this->ensureNodeIds($child, $node['id'] . '.', $counter);
            }
        }

        return $node;
    }

    /**
     * Count total nodes in a tree.
     */
    private function countNodes(array $node): int
    {
        $count = 1;
        foreach ($node['children'] ?? [] as $child) {
            $count += $this->countNodes($child);
        }

        return $count;
    }

    /**
     * Create a compact version of the tree (strip summaries) to reduce token count.
     */
    private function compactTree(array $node): array
    {
        $compact = [
            'id' => $node['id'] ?? '',
            'title' => $node['title'] ?? '',
            'level' => $node['level'] ?? '',
            'keywords' => $node['keywords'] ?? [],
        ];

        if (!empty($node['children'])) {
            $compact['children'] = array_map([$this, 'compactTree'], $node['children']);
        } else {
            $compact['children'] = [];
        }

        return $compact;
    }
}
