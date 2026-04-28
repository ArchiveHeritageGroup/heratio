<?php

/**
 * SearchStrategyInterface — contract every Discovery search strategy implements.
 *
 * The Discovery pipeline runs N strategies in parallel, captures per-strategy
 * results + timing into rm_discovery_log.strategy_breakdown, then merges + enriches.
 *
 * Each strategy returns a normalised array of hits whose minimum shape is
 *   {object_id:int, score:float, source:string}
 * with optional metadata keys (slug, title, highlights, mime_type, etc.) that the
 * merger and enricher can use.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgDiscovery\Services\Search;

interface SearchStrategyInterface
{
    /**
     * Identifier used as the JSON key in ahg_discovery_log.strategy_breakdown
     * (e.g. 'vector', 'image', 'keyword'). Lowercase, snake_case.
     */
    public function name(): string;

    /**
     * Whether the strategy is currently usable (settings on, dependencies up).
     * Cheap pre-flight; called once per query.
     */
    public function isEnabled(): bool;

    /**
     * Execute the strategy.
     *
     * @param string $query    User query (already trimmed)
     * @param array  $context  Per-request context: ['culture'=>'en', 'limit'=>100, 'expanded'=>['original'=>..,'all'=>[..]], ...]
     * @return array<int, array{object_id:int, score:float, source:string}>
     */
    public function search(string $query, array $context): array;
}
