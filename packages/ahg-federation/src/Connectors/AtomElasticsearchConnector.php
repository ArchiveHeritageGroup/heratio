<?php

/**
 * AtomElasticsearchConnector — exposes the local Heratio/AtoM Elasticsearch
 * index as a federation peer.
 *
 * Lets the same federation dispatch path consume local + remote hits in one
 * ranked list. Without this, "federated search" would mean "search every
 * peer EXCEPT this archive's own records", which is rarely what users want.
 *
 * Wraps the existing `AhgSearch\Services\ElasticsearchService::globalSearch()`
 * and maps the ES hits into PeerSearchResult.
 *
 * @phase D
 */

namespace AhgFederation\Connectors;

use AhgSearch\Services\ElasticsearchService;

final class AtomElasticsearchConnector implements PeerConnector
{
    public const PEER_TYPE = 'atom_local';

    private object $peer;

    public function __construct(
        private readonly ElasticsearchService $es,
    ) {
    }

    public function bind(object $peerRow): void
    {
        $this->peer = $peerRow;
    }

    public function peerTypeKey(): string
    {
        return self::PEER_TYPE;
    }

    public function supportsCapability(string $capability): bool
    {
        return in_array($capability, ['full_text_search', 'metadata_filter', 'date_range', 'acl_user_scope'], true);
    }

    public function search(string $query, array $filters = [], int $limit = 50): array
    {
        $culture = (string) ($filters['culture'] ?? 'en');
        $response = $this->es->globalSearch($query, $culture, 0, $limit);

        $hits = $response['hits']['hits'] ?? [];
        if (!is_array($hits) || empty($hits)) {
            return [];
        }

        $appUrl = rtrim((string) (config('app.url') ?? ''), '/');
        $out = [];
        foreach ($hits as $hit) {
            $src = $hit['_source'] ?? [];
            if (!is_array($src)) { continue; }
            $type = $hit['_index'] ?? '';
            $slug = $src['slug'] ?? null;
            $sourceId = (string) ($src['id'] ?? $hit['_id'] ?? '');
            if ($sourceId === '') { continue; }

            $i18n = is_array($src['i18n'][$culture] ?? null) ? $src['i18n'][$culture] : [];
            $title = (string) (
                $i18n['title']
                ?? $i18n['authorizedFormOfName']
                ?? $src['title']
                ?? $src['authorizedFormOfName']
                ?? 'Untitled'
            );
            $snippet = $this->extractHighlight($hit) ?? ($i18n['scopeAndContent'] ?? $i18n['history'] ?? null);

            // Heratio uses /{slug} for IO and /actor/{slug} etc. for non-IO.
            $url = $slug
                ? $appUrl . '/' . $this->prefixFor($type) . ltrim($slug, '/')
                : $appUrl;

            $score = isset($hit['_score']) ? (float) $hit['_score'] : 1.0;

            $out[] = new PeerSearchResult(
                sourceId: $sourceId,
                title: $title,
                snippet: is_string($snippet) ? $snippet : null,
                url: $url,
                peerType: self::PEER_TYPE,
                sourceBadge: 'Archived in AtoM',
                score: $this->normaliseScore($score),
                dedupeKey: $src['sp_item_id'] ?? null,
                date: $src['date'] ?? null,
                extras: [
                    'index'       => $type,
                    'reference'   => $src['referenceCode'] ?? $src['identifier'] ?? null,
                    'level'       => $src['levelOfDescriptionId'] ?? null,
                    'thumbnail'   => $src['thumbnailUrl'] ?? null,
                ],
            );
        }
        return $out;
    }

    private function prefixFor(string $index): string
    {
        $lower = strtolower($index);
        if (str_contains($lower, 'actor'))      { return 'actor/'; }
        if (str_contains($lower, 'repository')) { return 'repository/'; }
        if (str_contains($lower, 'term'))       { return 'taxonomy/'; }
        return '';
    }

    private function extractHighlight(array $hit): ?string
    {
        $highlights = $hit['highlight'] ?? [];
        if (!is_array($highlights)) { return null; }
        foreach ($highlights as $frags) {
            if (is_array($frags) && !empty($frags[0]) && is_string($frags[0])) {
                return strip_tags($frags[0], '<mark>');
            }
        }
        return null;
    }

    /** ES scores are unbounded above ~1 — log-scale clamp into 0..1. */
    private function normaliseScore(float $score): float
    {
        if ($score <= 0) { return 0.0; }
        return min(1.0, log10(1 + $score) / 2.0);
    }
}
