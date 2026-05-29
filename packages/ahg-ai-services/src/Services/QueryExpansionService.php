<?php

/**
 * QueryExpansionService - turn a natural-language search query into a set of
 * structured / synonym-expanded terms to widen recall.
 *
 * Strategy:
 *   1. Ask the local LLM (via LlmService) to extract concrete search terms,
 *      synonyms and related concepts from the user's phrasing.
 *   2. If the LLM is unavailable / disabled / returns nothing usable, fall
 *      back to the thesaurus expansion already shipped in
 *      AhgSemanticSearch\Services\SemanticSearchService::expandQuery().
 *   3. If neither is available, return the original query unchanged.
 *
 * This service is deliberately side-effect-free and config-gated by its
 * caller. It NEVER throws - every failure path degrades to the original
 * query so it can be wired into a hot search path without risk.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\Log;

class QueryExpansionService
{
    public function __construct(
        protected ?LlmService $llm = null,
    ) {}

    /**
     * Expand a natural-language query.
     *
     * @return array{
     *   original_query: string,
     *   expanded_query: string,
     *   terms: array<int,string>,
     *   source: string
     * }
     */
    public function expand(string $query, string $language = 'en'): array
    {
        $query = trim($query);

        $base = [
            'original_query' => $query,
            'expanded_query' => $query,
            'terms'          => [],
            'source'         => 'none',
        ];

        if ($query === '') {
            return $base;
        }

        // ── 1. LLM expansion ────────────────────────────────────────────
        $llmTerms = $this->expandWithLlm($query, $language);
        if (! empty($llmTerms)) {
            $merged = $this->mergeTerms($query, $llmTerms);

            return [
                'original_query' => $query,
                'expanded_query' => $merged,
                'terms'          => array_values($llmTerms),
                'source'         => 'llm',
            ];
        }

        // ── 2. Thesaurus fallback ───────────────────────────────────────
        $thesaurusTerms = $this->expandWithThesaurus($query, $language);
        if (! empty($thesaurusTerms)) {
            $merged = $this->mergeTerms($query, $thesaurusTerms);

            return [
                'original_query' => $query,
                'expanded_query' => $merged,
                'terms'          => array_values($thesaurusTerms),
                'source'         => 'thesaurus',
            ];
        }

        return $base;
    }

    /**
     * Ask the LLM for additional search terms. Returns a flat list of distinct
     * lowercase terms (excluding words already in the query) or [] on any
     * failure.
     *
     * @return array<int,string>
     */
    protected function expandWithLlm(string $query, string $language): array
    {
        try {
            $llm = $this->llm ?? app(LlmService::class);
        } catch (\Throwable $e) {
            return [];
        }

        $prompt = $this->buildPrompt($query, $language);

        try {
            $raw = $llm->complete($prompt, [
                'purpose'    => 'search_query_expansion',
                'data_scope' => 'internal',
            ]);
        } catch (\Throwable $e) {
            Log::debug('[query-expansion] LLM call failed: '.$e->getMessage());

            return [];
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return $this->parseTerms($raw, $query);
    }

    /**
     * Build the extraction prompt. Asks for a single comma-separated line of
     * terms so parsing stays robust across model families.
     */
    protected function buildPrompt(string $query, string $language): string
    {
        return "You are a search-query expansion assistant for an archival / "
            ."library catalogue. Given a natural-language query, output ONLY a "
            ."single comma-separated line of 3 to 8 concise search terms: "
            ."synonyms, broader/narrower concepts, and proper nouns that would "
            ."improve recall. No explanations, no numbering, no quotes. "
            ."Language: {$language}.\n\n"
            ."Query: {$query}\n\n"
            ."Terms:";
    }

    /**
     * Parse a comma/newline separated LLM response into distinct terms,
     * dropping anything already present in the original query and obvious
     * noise (numbering, bullets, very short tokens).
     *
     * @return array<int,string>
     */
    protected function parseTerms(string $raw, string $query): array
    {
        // Take the first non-empty line only - guards against chatty models
        // that prepend a sentence before the list.
        $line = trim($raw);
        foreach (preg_split('/\r?\n/', $raw) as $candidate) {
            if (str_contains($candidate, ',')) {
                $line = $candidate;
                break;
            }
            if (trim($candidate) !== '') {
                $line = $candidate;
            }
        }

        // Drop a leading "label:" preamble (e.g. "Here are terms: a, b, c").
        // Only when the colon comes before the first comma, so colons inside a
        // genuine term are left untouched.
        $colon = strpos($line, ':');
        $comma = strpos($line, ',');
        if ($colon !== false && ($comma === false || $colon < $comma)) {
            $line = trim(substr($line, $colon + 1));
        }

        $parts = preg_split('/[,;\n]/', $line) ?: [];
        $queryWords = array_map('mb_strtolower', preg_split('/\s+/', $query) ?: []);

        $terms = [];
        foreach ($parts as $part) {
            $term = trim($part);
            // strip leading list markers / numbering / quotes
            $term = preg_replace('/^[\s\-\*\d\.\)\("\']+/u', '', $term);
            $term = trim($term, " \t\"'.");
            if ($term === '' || mb_strlen($term) < 3) {
                continue;
            }
            $lower = mb_strtolower($term);
            if (in_array($lower, $queryWords, true)) {
                continue;
            }
            $terms[$lower] = $term;
            if (count($terms) >= 8) {
                break;
            }
        }

        return array_values($terms);
    }

    /**
     * Thesaurus-based fallback. Reuses the semantic-search synonym expansion
     * if that package is installed.
     *
     * @return array<int,string>
     */
    protected function expandWithThesaurus(string $query, string $language): array
    {
        $class = '\AhgSemanticSearch\Services\SemanticSearchService';
        if (! class_exists($class)) {
            return [];
        }

        try {
            $svc = app($class);
            $result = $svc->expandQuery($query, $language);
        } catch (\Throwable $e) {
            Log::debug('[query-expansion] thesaurus fallback failed: '.$e->getMessage());

            return [];
        }

        $terms = [];
        foreach ((array) ($result['expanded_terms'] ?? []) as $syns) {
            foreach ((array) $syns as $syn) {
                $syn = trim((string) $syn);
                if ($syn !== '' && mb_strlen($syn) >= 3) {
                    $terms[mb_strtolower($syn)] = $syn;
                }
            }
        }

        return array_values($terms);
    }

    /**
     * Append the expansion terms to the original query, OR-style, so ES
     * query_string broadens recall without dropping the original phrase.
     *
     * @param array<int,string> $terms
     */
    protected function mergeTerms(string $query, array $terms): string
    {
        if (empty($terms)) {
            return $query;
        }

        return trim($query.' '.implode(' ', $terms));
    }
}
