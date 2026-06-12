<?php

/**
 * PeerSearchResult — common result shape returned by every PeerConnector.
 *
 * The federation dispatcher collects PeerSearchResult arrays from every
 * connector, applies dedupe (by dedupe_key when present), tags each row with
 * the source-attribution badge, and hands the merged list to the result
 * renderer.
 *
 * Field semantics are documented per-field; connectors that have no value
 * for an optional field pass null and the renderer falls back to a sensible
 * default.
 *
 * @phase B
 */

namespace AhgFederation\Connectors;

final class PeerSearchResult
{
    public function __construct(
        /** @var string Stable identifier within the source (e.g. SP item id, AtoM slug). */
        public readonly string $sourceId,

        /** @var string Human-readable title. Required. */
        public readonly string $title,

        /** @var ?string One-paragraph summary or first 280 chars of body/scope. */
        public readonly ?string $snippet,

        /** @var string Click-through URL. SHOULD be absolute. */
        public readonly string $url,

        /** @var string peerTypeKey of the producing connector — used for badge dispatch. */
        public readonly string $peerType,

        /** @var string Human-readable source label (e.g. "Archived in AtoM"). */
        public readonly string $sourceBadge,

        /** @var float Relevance score (0.0–1.0). Connector responsible for normalisation. */
        public readonly float $score,

        /** @var ?string Optional dedupe key — when two results share this, dedupe wins by score. */
        public readonly ?string $dedupeKey = null,

        /** @var ?string ISO-8601 date string for the result's primary date (modified/created/event). */
        public readonly ?string $date = null,

        /** @var array<string,mixed> Arbitrary extra fields the renderer may surface. */
        public readonly array $extras = [],
    ) {
    }

    /**
     * Convenience: serialize to a flat array for the federation_search_cache
     * JSON column and the JSON API response.
     */
    public function toArray(): array
    {
        return [
            'source_id'    => $this->sourceId,
            'title'        => $this->title,
            'snippet'      => $this->snippet,
            'url'          => $this->url,
            'peer_type'    => $this->peerType,
            'source_badge' => $this->sourceBadge,
            'score'        => $this->score,
            'dedupe_key'   => $this->dedupeKey,
            'date'         => $this->date,
            'extras'       => $this->extras,
        ];
    }
}
